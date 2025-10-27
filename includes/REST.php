<?php
namespace WOO_MYGLS;

class REST {
    public static function init(){
        add_action('rest_api_init', function(){
            register_rest_route('woo-mygls/v1','/label/(?P<order>\d+)', [
                'methods' => 'POST',
                'callback' => [__CLASS__,'create_label'],
                'permission_callback' => function(){ return current_user_can('manage_woocommerce'); }
            ]);
        });
    }
    public static function create_label($req){
        $order_id = (int)$req['order'];
        $res = Orders::create_or_print($order_id, true);
        if (is_wp_error($res)) return $res;
        return ['ok'=>true];
    }
}
