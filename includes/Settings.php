<?php
namespace WOO_MYGLS;

class Settings {
    const OPTION_KEY = 'woo_mygls_settings';

    public static function init(){
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function get($key = null, $default = null){
        $opt = get_option(self::OPTION_KEY, [
            'env' => 'test',
            'username' => '',
            'password' => '',
            'client_number' => '',
            'type_of_printer' => 'A4_2x2',
            'webshop_engine' => 'WooCommerce',
            'pickup' => ['name'=>'','street'=>'','housenumber'=>'','city'=>'','zip'=>'','country'=>'HU','contact'=>'','phone'=>'','email'=>''],
            'enable_status_sync' => 1,
            'status_sync_interval' => 'hourly',
            'shipping_psd_methods' => [],
        ]);
        if ($key === null) return $opt;
        return $opt[$key] ?? $default;
    }

    public static function register(){
        register_setting('woo_mygls', self::OPTION_KEY, ['sanitize_callback' => [__CLASS__,'sanitize']]);
        add_settings_section('main','MyGLS beállítások', function(){
            echo '<p>Állítsd be a MyGLS API adataidat. A jelszót a plugin SHA512-re hash-eli és bájttömbként küldi.</p>';
        }, 'woo_mygls');

        add_settings_field('env','Környezet', [__CLASS__,'field_env'], 'woo_mygls','main');
        add_settings_field('username','Felhasználónév (e-mail)', [__CLASS__,'field_text'],'woo_mygls','main',['key'=>'username']);
        add_settings_field('password','Jelszó (nem kerül tárolásra plain-textben)', [__CLASS__,'field_password'],'woo_mygls','main',['key'=>'password']);
        add_settings_field('client_number','Ügyfélszám (ClientNumber)', [__CLASS__,'field_text'],'woo_mygls','main',['key'=>'client_number']);
        add_settings_field('printer','Nyomtató típus (TypeOfPrinter)', [__CLASS__,'field_printer'],'woo_mygls','main');
        add_settings_field('pickup','Feladó adatai (PickupAddress)', [__CLASS__,'field_pickup'],'woo_mygls','main');
        add_settings_field('shipping_psd_methods','Csomagpont/Automata szállítási módok', [__CLASS__,'field_shipping_psd_methods'],'woo_mygls','main');
        add_settings_field('status','Csomagstátusz szinkron', [__CLASS__,'field_status'],'woo_mygls','main');
    }

    public static function sanitize($input){
        if (!is_array($input)) return [];

        if (empty($input['enable_status_sync'])){
            $input['enable_status_sync'] = 0;
        }

        foreach (['env','username','password','client_number','type_of_printer','webshop_engine','status_sync_interval'] as $field){
            if (isset($input[$field])){
                $input[$field] = sanitize_text_field($input[$field]);
            }
        }

        if (!empty($input['pickup']) && is_array($input['pickup'])){
            foreach ($input['pickup'] as $key => $value){
                $input['pickup'][$key] = sanitize_text_field($value);
            }
        }

        if (!empty($input['shipping_psd_methods']) && is_array($input['shipping_psd_methods'])){
            $input['shipping_psd_methods'] = array_values(array_unique(array_map('sanitize_text_field', $input['shipping_psd_methods'])));
        } else {
            $input['shipping_psd_methods'] = [];
        }

        return $input;
    }

    public static function get_shipping_method_catalog(){
        if (!class_exists('\\WC_Shipping_Zones')){
            return [];
        }

        $options = [];
        $zones = \WC_Shipping_Zones::get_zones();

        $default_zone = new \WC_Shipping_Zone(0);
        $zones[] = [
            'zone_name' => __('Alapértelmezett zóna','woo-mygls'),
            'shipping_methods' => $default_zone->get_shipping_methods(),
        ];

        foreach ($zones as $zone){
            if (empty($zone['shipping_methods'])){
                continue;
            }
            $zone_name = $zone['zone_name'] ?? __('Ismeretlen zóna','woo-mygls');
            foreach ($zone['shipping_methods'] as $method){
                if (!is_object($method)){
                    continue;
                }
                $method_id = '';
                if (method_exists($method, 'get_method_id')) {
                    $method_id = (string) $method->get_method_id();
                } elseif (isset($method->method_id)) {
                    $method_id = (string) $method->method_id;
                } elseif (isset($method->id)) {
                    $method_id = (string) $method->id;
                }

                if ($method_id === '') {
                    continue;
                }

                $instance_id = 0;
                if (method_exists($method, 'get_instance_id')) {
                    $instance_id = (int) $method->get_instance_id();
                } elseif (isset($method->instance_id)) {
                    $instance_id = (int) $method->instance_id;
                }

                $rate_id = $instance_id > 0 ? sprintf('%s:%d', $method_id, $instance_id) : $method_id;
                $title = method_exists($method, 'get_title') ? $method->get_title() : ($method->title ?? '');
                $method_title = method_exists($method, 'get_method_title') ? $method->get_method_title() : ($method->method_title ?? '');
                $label = sprintf('%s — %s (%s)', $zone_name, $title ?: $method_title, $method_title ?: ($method->id ?? $rate_id));
                $options[$rate_id] = [
                    'label' => $label,
                    'zone' => $zone_name,
                    'title' => $title ?: $method_title,
                    'method_title' => $method_title ?: ($method->id ?? $rate_id),
                    'method_id' => $method_id,
                    'instance_id' => $instance_id,
                ];
            }
        }

        return $options;
    }

    public static function page(){
        echo '<div class="wrap"><h1>MyGLS integráció</h1><form method="post" action="options.php">';
        settings_fields('woo_mygls');
        do_settings_sections('woo_mygls');
        submit_button();
        echo '</form></div>';
    }

    public static function field_env(){
        $v = esc_attr(self::get('env','test'));
        echo '<select name="'.self::OPTION_KEY.'[env]">
            <option value="test" '.selected($v,'test',false).'>Teszt</option>
            <option value="prod" '.selected($v,'prod',false).'>Éles</option>
        </select>';
    }

    public static function field_text($args){
        $k = $args['key'];
        $v = esc_attr(self::get($k,''));
        echo '<input type="text" style="width:420px" name="'.self::OPTION_KEY.'['.$k.']" value="'.$v.'">';
    }

    public static function field_password($args){
        $k = $args['key'];
        $v = esc_attr(self::get($k,''));
        echo '<input type="password" style="width:420px" name="'.self::OPTION_KEY.'['.$k.']" value="'.$v.'" autocomplete="new-password">';
        echo '<p class="description">A GLS API SHA512 jelszó bátjtömböt vár. A plugin küldés előtt konvertálja.</p>';
    }

    public static function field_printer(){
        $v = esc_attr(self::get('type_of_printer','A4_2x2'));
        $opts = ['A4_2x2','A4_4x1','Connect','Thermo','ThermoZPL','ShipItThermoPdf','ThermoZPL_300DPI'];
        echo '<select name="'.self::OPTION_KEY.'[type_of_printer]">';
        foreach($opts as $o){
            echo '<option value="'.$o.'" '.selected($v,$o,false).'>'.$o.'</option>';
        }
        echo '</select>';
    }

    public static function field_pickup(){
        $p = self::get('pickup',[]);
        $f = function($name,$label) use ($p){
            $v = esc_attr($p[$name] ?? '');
            echo '<p><label>'.$label.'<br><input type="text" name="'.self::OPTION_KEY.'[pickup]['.$name.']" value="'.$v.'" style="width:420px"></label></p>';
        };
        $f('name','Név/Cégnév');
        $f('street','Utca');
        $f('housenumber','Házszám (csak szám)');
        $f('city','Város');
        $f('zip','Irányítószám');
        $f('country','Ország (ISO2, pl. HU)');
        $f('contact','Kapcsolattartó');
        $f('phone','Telefon (+36...)');
        $f('email','E-mail');
    }

    public static function field_shipping_psd_methods(){
        if (!class_exists('\\WC_Shipping_Zones')){
            echo '<p>'.esc_html__('WooCommerce szállítás nem érhető el.','woo-mygls').'</p>';
            return;
        }

        $selected = self::get('shipping_psd_methods', []);
        if (!is_array($selected)){
            $selected = [];
        }
        $selected = array_values(array_map('strval', $selected));
        $selected_base = array_map(function($id){
            $parts = explode(':', (string) $id, 2);
            return $parts[0];
        }, $selected);

        $options = self::get_shipping_method_catalog();

        if (!$options){
            echo '<p>'.esc_html__('Még nem állítottál be szállítási módokat a WooCommerce-ben.','woo-mygls').'</p>';
            return;
        }

        echo '<fieldset class="woo-mygls-shipping-psd">';
        foreach ($options as $value => $data){
            $value_attr = esc_attr($value);
            $label_html = esc_html($data['label']);
            $is_checked = in_array($value, $selected, true) || in_array($data['method_id'], $selected_base, true);
            echo '<label style="display:block;margin:6px 0 10px">';
            echo '<input type="checkbox" name="'.self::OPTION_KEY.'[shipping_psd_methods][]" value="'.$value_attr.'" '.checked($is_checked, true, false).'>'; 
            echo ' '.$label_html;
            echo '</label>';
        }
        echo '</fieldset>';
        echo '<p class="description">'.esc_html__('Pipáld ki azokat a szállítási módokat, amelyekhez kötelező a GLS Csomagpont vagy automata kiválasztása a vásárlónak.','woo-mygls').'</p>';

        $legacy = array_diff($selected, array_keys($options));
        if ($legacy){
            echo '<p class="description">'.sprintf(esc_html__('A következő korábban kijelölt szállítási módok már nem érhetők el: %s','woo-mygls'), esc_html(implode(', ', $legacy))).'</p>';
        }
    }

    public static function field_status(){
        $en = (int)self::get('enable_status_sync',1);
        $int = esc_attr(self::get('status_sync_interval','hourly'));
        echo '<label><input type="checkbox" name="'.self::OPTION_KEY.'[enable_status_sync]" value="1" '.checked($en,1,false).'> Engedélyezve</label>';
        echo '<p><label>Gyakoriság: <select name="'.self::OPTION_KEY.'[status_sync_interval]">
            <option value="hourly" '.selected($int,'hourly',false).'>óránként</option>
            <option value="twicedaily" '.selected($int,'twicedaily',false).'>napi 2x</option>
            <option value="daily" '.selected($int,'daily',false).'>naponta</option>
        </select></label></p>';
    }
}
