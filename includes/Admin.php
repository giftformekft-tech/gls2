<?php
namespace WOO_MYGLS;

class Admin {
    public static function init(){
        add_action('admin_menu', function(){
            add_menu_page('MyGLS','MyGLS','manage_woocommerce','woo_mygls',[Settings::class,'page'],'dashicons-location-alt',56);
        });
        add_action('admin_enqueue_scripts', [__CLASS__,'assets']);
        add_action('wp_ajax_woo_mygls_test_connection', [__CLASS__,'ajax_test_connection']);
    }

    public static function assets($hook){
        if (strpos($hook, 'woo_mygls') !== false || $hook === 'post.php' || $hook === 'post-new.php'){
            wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
            wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
            wp_enqueue_script('woo-mygls-admin', WOO_MYGLS_URL.'assets/admin.js', ['jquery','leaflet'], WOO_MYGLS_VERSION, true);
            wp_localize_script('woo-mygls-admin','WOO_MYGLS', [
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('woo_mygls'),
                'pointsUrl' => 'https://map.gls-hungary.com/data/deliveryPoints/hu.json',
                'i18n' => [
                    'testing' => __('Kapcsolat tesztelése...','woo-mygls'),
                    'success' => __('Kapcsolat sikeres.','woo-mygls'),
                    'warning' => __('Kapcsolat sikeres, de a GLS hibát jelzett:','woo-mygls'),
                    'error' => __('Sikertelen kapcsolat:','woo-mygls'),
                ],
            ]);
            wp_enqueue_style('woo-mygls-admin', WOO_MYGLS_URL.'assets/admin.css', [], WOO_MYGLS_VERSION);
        }
    }

    public static function ajax_test_connection(){
        if (!current_user_can('manage_woocommerce')){
            wp_send_json_error(['message' => __('Nincs jogosultság a művelethez.','woo-mygls')], 403);
        }
        $nonce = $_POST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'woo_mygls')){
            wp_send_json_error(['message' => __('Érvénytelen biztonsági token.','woo-mygls')], 403);
        }
        $api = new API();
        $result = $api->test_connection();
        if (is_wp_error($result)){
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        $status = 'success';
        $message = __('Kapcsolat sikeres.','woo-mygls');
        if (is_array($result)){
            if (!empty($result['message'])){
                $message = $result['message'];
            }
            if (!empty($result['warning'])){
                $status = 'warning';
            }
        }
        wp_send_json_success(['message' => $message, 'status' => $status]);
    }
}
