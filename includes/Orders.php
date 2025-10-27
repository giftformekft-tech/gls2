<?php
namespace WOO_MYGLS;

class Orders {

    public static function init(){
        add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__,'panel']);
        add_filter('bulk_actions-edit-shop_order', [__CLASS__,'bulk_actions']);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [__CLASS__,'bulk_actions']);
        add_filter('handle_bulk_actions-edit-shop_order', [__CLASS__,'handle_bulk'], 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [__CLASS__,'handle_bulk'], 10, 3);
        add_filter('woocommerce_admin_order_actions', [__CLASS__,'row_action'], 10, 2);
        add_action('wp_ajax_woo_mygls_create_label', [__CLASS__,'ajax_create_label']);
        add_action('wp_ajax_woo_mygls_delete_label', [__CLASS__,'ajax_delete_label']);
    }

    public static function panel($order){
        if (!$order) return;
        $parcel = $order->get_meta('_gls_parcel');
        $psd = $order->get_meta('_gls_psd_label');
        echo '<div class="order_data_column"><h3>MyGLS</h3>';
        echo '<p><strong>Csomagpont:</strong><br>'.esc_html($psd ?: '—').'</p>';
        if ($parcel && !empty($parcel['number'])){
            echo '<p><strong>Parcel number:</strong><br>'.esc_html($parcel['number']).'</p>';
            echo '<p><a class="button" href="'.esc_url(admin_url('admin-ajax.php?action=woo_mygls_create_label&order_id='.$order->get_id().'&download=1&_wpnonce='.wp_create_nonce('woo_mygls'))).'">Címke újranyomtatása (PDF)</a></p>';
            echo '<p><a class="button button-secondary" href="'.esc_url(admin_url('admin-ajax.php?action=woo_mygls_delete_label&order_id='.$order->get_id().'&_wpnonce='.wp_create_nonce('woo_mygls'))).'">Címke törlése (API)</a></p>';
        } else {
            echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin-ajax.php?action=woo_mygls_create_label&order_id='.$order->get_id().'&_wpnonce='.wp_create_nonce('woo_mygls'))).'">Címke létrehozása</a></p>';
        }
        echo '</div>';
    }

    public static function bulk_actions($actions){
        $actions['woo_mygls_bulk_labels'] = 'GLS: Címkék generálása és letöltése (PDF)';
        return $actions;
    }

    public static function handle_bulk($redirect, $doaction, $ids){
        if ($doaction !== 'woo_mygls_bulk_labels') return $redirect;
        check_admin_referer('bulk-posts');
        $created = [];
        foreach($ids as $id){
            $res = self::create_or_print($id, false);
            if (!is_wp_error($res)) $created[] = $id;
        }
        if ($created){
            // összevont PDF visszaadás helyett egyszerű redirect + notice
            $redirect = add_query_arg(['gls_bulk'=>count($created)], $redirect);
        }
        return $redirect;
    }

    public static function row_action($actions,$order){
        $url = admin_url('admin-ajax.php?action=woo_mygls_create_label&order_id='.$order->get_id().'&_wpnonce='.wp_create_nonce('woo_mygls'));
        $actions['woo_mygls'] = '<a href="'.esc_url($url).'" class="button tips">GLS címke</a>';
        return $actions;
    }

    public static function ajax_create_label(){
        if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'woo_mygls')) wp_die('forbidden');
        $order_id = (int)($_GET['order_id'] ?? 0);
        $download = !empty($_GET['download']);
        $res = self::create_or_print($order_id, true);
        if (is_wp_error($res)) wp_die($res->get_error_message());
        if ($download && !empty($res['pdf'])){
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="gls-label-'.$order_id.'.pdf"');
            echo base64_decode($res['pdf']);
            exit;
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post='.$order_id.'&action=edit'));
        exit;
    }

    public static function ajax_delete_label(){
        if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'woo_mygls')) wp_die('forbidden');
        $order_id = (int)($_GET['order_id'] ?? 0);
        $order = wc_get_order($order_id);
        if (!$order) wp_die('Rendelés nem található.');
        $parcel = $order->get_meta('_gls_parcel');
        if (!$parcel || empty($parcel['id'])) wp_die('Nincs GLS parcel ehhez a rendeléshez.');
        $api = new API();
        $r = $api->delete_labels([$parcel['id']]);
        if (is_wp_error($r)) wp_die($r->get_error_message());
        $order->delete_meta_data('_gls_parcel');
        $order->delete_meta_data('_gls_parcelnumber');
        $order->update_meta_data('_gls_deleted', current_time('mysql'));
        $order->add_order_note('GLS címke törölve az API-n keresztül.');
        $order->save();
        wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post='.$order_id.'&action=edit'));
        exit;
    }

    protected static function create_or_print($order_id, $return_pdf = false){
        $order = wc_get_order($order_id);
        if (!$order) return new \WP_Error('gls','Rendelés nem található.');
        $api = new API();

        $psd_id = wc_get_order($order_id)->get_meta('_gls_psd_id');
        $psd_label = wc_get_order($order_id)->get_meta('_gls_psd_label');

        $payload = $api->build_parcel_from_order($order, $psd_id, $psd_label);
        if (is_wp_error($payload)) return $payload;

        // PrintLabels (one step)
        $res = $api->print_labels([$payload]);
        if (is_wp_error($res)) return $res;

        // Save parcel meta
        if (!empty($res['info'][0])){
            $order->update_meta_data('_gls_parcel', $res['info'][0]);
            $order->update_meta_data('_gls_parcelnumber', $res['info'][0]['number'] ?? '');
            $order->add_order_note('GLS címke létrehozva. ParcelNumber: '.($res['info'][0]['number'] ?? ''));
        }
        $order->save();
        if ($return_pdf){
            return ['pdf' => $res['pdf'] ?? null];
        }
        return true;
    }
}
