<?php
namespace WOO_MYGLS;

class StatusSync {

    public static function init(){
        add_action('init',[__CLASS__,'schedule']);
        add_action('woo_mygls_status_sync',[__CLASS__,'run']);
    }

    public static function schedule(){
        $opt = Settings::get();
        if (!empty($opt['enable_status_sync'])){
            if (!wp_next_scheduled('woo_mygls_status_sync')){
                wp_schedule_event(time()+300, $opt['status_sync_interval'] ?? 'hourly', 'woo_mygls_status_sync');
            }
        } else {
            $ts = wp_next_scheduled('woo_mygls_status_sync');
            if ($ts) wp_unschedule_event($ts, 'woo_mygls_status_sync');
        }
    }

    public static function run(){
        $orders = wc_get_orders([
            'limit' => 50,
            'status' => ['processing','on-hold'],
            'meta_key' => '_gls_parcelnumber',
            'meta_compare' => 'EXISTS',
        ]);
        $api = new API();
        foreach($orders as $order){
            $num = $order->get_meta('_gls_parcelnumber');
            if (!$num) continue;
            $res = $api->get_status((int)$num, 'HU', false);
            if (is_wp_error($res) || empty($res['ParcelStatusList'])) continue;
            $last = end($res['ParcelStatusList']);
            $order->update_meta_data('_gls_last_status', $last); $order->save();
            $order->add_order_note(sprintf('GLS státusz: %s (%s) %s',
                $last['StatusCode'] ?? '',
                $last['StatusDescription'] ?? '',
                $last['StatusDate'] ?? ''
            ));
            if (($last['StatusCode'] ?? '') == '5'){ // delivered
                $order->update_status('completed','GLS kézbesítve.');
            }
        }
    }
}
