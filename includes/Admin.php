<?php
namespace WOO_MYGLS;

class Admin {
    public static function init(){
        add_action('admin_menu', function(){
            add_menu_page('MyGLS','MyGLS','manage_woocommerce','woo_mygls',[Settings::class,'page'],'dashicons-location-alt',56);
        });
        add_action('admin_enqueue_scripts', [__CLASS__,'assets']);
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
            ]);
            wp_enqueue_style('woo-mygls-admin', WOO_MYGLS_URL.'assets/admin.css', [], WOO_MYGLS_VERSION);
        }
    }
}
