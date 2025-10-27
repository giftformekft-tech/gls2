<?php
namespace WOO_MYGLS;

class API {

    protected $base;

    public function __construct(){
        $env = Settings::get('env','test');
        $country = 'hu';
        $this->base = $env === 'prod' ? "https://api.mygls.$country/ParcelService.svc/json" : "https://api.test.mygls.$country/ParcelService.svc/json";
    }

    protected function creds(){
        $s = Settings::get();
        $username = $s['username'] ?? '';
        $password = $s['password'] ?? '';
        // SHA512 -> raw bytes -> base64 (transport-friendly)
        $hash = base64_encode(hash('sha512', $password, true));
        return [
            'Username' => $username,
            'Password' => $this->base64_to_byte_array($hash),
            'WebshopEngine' => $s['webshop_engine'] ?? 'WooCommerce',
        ];
    }

    protected function base64_to_byte_array($b64){
        $raw = base64_decode($b64);
        $out = [];
        for ($i=0;$i<strlen($raw);$i++){ $out[] = ord($raw[$i]); }
        return $out;
    }

    protected function pickup_address(){
        $p = Settings::get('pickup',[]);
        return [
            'Name' => $p['name'] ?? '',
            'Street' => $p['street'] ?? '',
            'HouseNumber' => (string)($p['housenumber'] ?? ''),
            'HouseNumberInfo' => '',
            'City' => $p['city'] ?? '',
            'ZipCode' => $p['zip'] ?? '',
            'CountryIsoCode' => $p['country'] ?? 'HU',
            'ContactName' => $p['contact'] ?? '',
            'ContactPhone' => $p['phone'] ?? '',
            'ContactEmail' => $p['email'] ?? '',
        ];
    }

    public function build_parcel_from_order($order, $psd_id, $psd_label){
        $client = Settings::get('client_number','');
        if (!$client) return new \WP_Error('gls','Hiányzó ClientNumber a beállításokban.');
        $shipping = $order->get_address('shipping');
        if (empty($shipping['address_1'])) $shipping = $order->get_address('billing');

        if (empty($shipping['postcode']) || empty($shipping['city']) || empty($shipping['address_1'])){
            return new \WP_Error('gls','Hiányos szállítási cím.');
        }

        $delivery = [
            'Name' => trim(($shipping['company'] ?: $shipping['first_name'].' '.$shipping['last_name'])),
            'Street' => preg_replace('/\s+\d.*$/u','', $shipping['address_1']),
            'HouseNumber' => (preg_match('/(\d+[A-Za-z\/]*)/u', $shipping['address_1'], $m) ? $m[1] : '1'),
            'HouseNumberInfo' => '',
            'City' => $shipping['city'],
            'ZipCode' => $shipping['postcode'],
            'CountryIsoCode' => strtoupper($shipping['country'] ?: 'HU'),
            'ContactName' => $shipping['first_name'].' '.$shipping['last_name'],
            'ContactPhone' => $order->get_billing_phone(),
            'ContactEmail' => $order->get_billing_email(),
        ];

        $services = [];
        // PSD (ParcelShop Delivery) if selected
        if ($psd_id){
            $services[] = [
                'Code' => 'PSD',
                'PSDParameter' => ['StringValue' => (string)$psd_id]
            ];
            // PSD kötelező kontakt mezők már kitöltve, OK
        }

        // COD (utánvét) detektálás — WooCommerce COD fizetési mód slug: cod
        if ($order->get_payment_method() === 'cod'){
            $services[] = [
                'Code' => 'COD',
                'CODAmount' => (float)$order->get_total(),
                'CODCurrency' => $order->get_currency() ?: 'HUF'
            ];
        }

        $content = sprintf('Woo rendelés #%s', $order->get_order_number());

        $parcel = [
            'ClientNumber' => (int)$client,
            'ClientReference' => (string)$order->get_order_number(),
            'Count' => 1,
            'Content' => $content,
            'PickupDate' => date('c'),
            'PickupAddress' => $this->pickup_address(),
            'DeliveryAddress' => $delivery,
            'ServiceList' => $services
        ];

        return $parcel;
    }

    protected function post($method, $body){
        $url = trailingslashit($this->base) . $method;
        $args = [
            'headers' => ['Content-Type'=>'application/json'],
            'timeout' => 45,
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE)
        ];
        $resp = wp_remote_post($url, $args);
        if (is_wp_error($resp)) return $resp;
        $code = wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400){
            return new \WP_Error('gls_http',"HTTP $code hiba: ".wp_remote_retrieve_body($resp));
        }
        return $json;
    }

    public function print_labels(array $parcel_list){
        $creds = $this->creds();
        $type = Settings::get('type_of_printer','A4_2x2');
        $req = array_merge($creds, [
            'ParcelList' => $parcel_list,
            'PrintPosition' => 1,
            'ShowPrintDialog' => false,
            'TypeOfPrinter' => $type
        ]);

        $res = $this->post('PrintLabels', $req);
        if (is_wp_error($res)) return $res;

        if (!empty($res['PrintLabelsErrorList'])){
            $first = $res['PrintLabelsErrorList'][0];
            return new \WP_Error('gls_api','GLS API hiba: '.$first['ErrorCode'].' - '.$first['ErrorDescription']);
        }
        $info = [];
        if (!empty($res['PrintLabelsInfoList'])){
            foreach($res['PrintLabelsInfoList'] as $i){
                $info[] = [
                    'client_reference' => $i['ClientReference'] ?? '',
                    'id' => $i['ParcelId'] ?? null,
                    'number' => $i['ParcelNumber'] ?? null,
                    'pin' => $i['PIN'] ?? null,
                ];
            }
        }
        return ['pdf' => $res['Labels'] ?? null, 'info' => $info];
    }

    public function get_printed_labels(array $ids){
        $creds = $this->creds();
        $type = Settings::get('type_of_printer','A4_2x2');
        $req = array_merge($creds, [
            'ParcelIdList' => array_map('intval',$ids),
            'PrintPosition' => 1,
            'ShowPrintDialog' => false,
            'TypeOfPrinter' => $type
        ]);
        $res = $this->post('GetPrintedLabels', $req);
        if (is_wp_error($res)) return $res;
        if (!empty($res['GetPrintedLabelsErrorList'])){
            $first = $res['GetPrintedLabelsErrorList'][0];
            return new \WP_Error('gls_api','GLS API hiba: '.$first['ErrorCode'].' - '.$first['ErrorDescription']);
        }
        return ['pdf' => $res['Labels'] ?? null, 'meta' => $res['PrintDataInfoList'] ?? []];
    }

    public function delete_labels(array $ids){
        $creds = $this->creds();
        $req = array_merge($creds, ['ParcelIdList' => array_map('intval',$ids)]);
        $res = $this->post('DeleteLabels', $req);
        if (is_wp_error($res)) return $res;
        if (!empty($res['DeleteLabelsErrorList'])){
            $first = $res['DeleteLabelsErrorList'][0];
            return new \WP_Error('gls_api','GLS API hiba: '.$first['ErrorCode'].' - '.$first['ErrorDescription']);
        }
        return $res['SuccessfullyDeletedList'] ?? [];
    }

    public function get_status($parcelNumber, $lang='HU', $pod=false){
        $creds = $this->creds();
        $req = array_merge($creds, [
            'ParcelNumber' => (int)$parcelNumber,
            'ReturnPOD' => (bool)$pod,
            'LanguageIsoCode' => $lang
        ]);
        return $this->post('GetParcelStatuses', $req);
    }

    public function test_connection(){
        $settings = Settings::get();
        if (empty($settings['username']) || empty($settings['password']) || empty($settings['client_number'])){
            return new \WP_Error('gls_missing', __('Előbb add meg a GLS API felhasználónevet, jelszót és ügyfélszámot a mentéshez, majd próbáld újra.','woo-mygls'));
        }
        $req = array_merge($this->creds(), [
            'ParcelNumber' => 0,
            'ReturnPOD' => false,
            'LanguageIsoCode' => 'HU'
        ]);
        $response = $this->post('GetParcelStatuses', $req);
        if (is_wp_error($response)){
            return $response;
        }
        $errors = [];
        if (!empty($response['GetParcelStatusesErrorList']) && is_array($response['GetParcelStatusesErrorList'])){
            $errors = $response['GetParcelStatusesErrorList'];
        } elseif (!empty($response['ErrorList']) && is_array($response['ErrorList'])){
            $errors = $response['ErrorList'];
        }
        if ($errors){
            $first = $errors[0];
            $code = is_array($first) ? ($first['ErrorCode'] ?? '') : '';
            $desc = is_array($first) ? ($first['ErrorDescription'] ?? '') : '';
            $message = trim(($code ? $code.' - ' : '').$desc);
            $lowerDesc = strtolower($desc);
            if (strpos($lowerDesc, 'auth') !== false || strpos($lowerDesc, 'login') !== false){
                return new \WP_Error('gls_auth', $message ?: __('Hitelesítési hiba a GLS API-val.','woo-mygls'));
            }
            if (strpos($lowerDesc, 'not found') !== false || strpos($lowerDesc, 'nincs') !== false || strpos($lowerDesc, 'nem található') !== false){
                return [
                    'success' => true,
                    'warning' => false,
                    'message' => __('Kapcsolat sikeres. A teszt küldemény nem található, ez várható viselkedés.','woo-mygls'),
                ];
            }
            return [
                'success' => true,
                'warning' => true,
                'message' => sprintf(__('Kapcsolat sikeres, de a GLS ezt válaszolta: %s','woo-mygls'), $message ?: __('Ismeretlen hiba','woo-mygls')),
            ];
        }
        return [
            'success' => true,
            'warning' => false,
            'message' => __('Kapcsolat sikeres.','woo-mygls'),
        ];
    }
}
