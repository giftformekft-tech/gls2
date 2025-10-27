<?php
namespace WOO_MYGLS;

class Frontend {
    protected static $rendered = false;
    public static function init(){
        add_action('woocommerce_after_order_notes', [__CLASS__,'checkout_field']);
        add_action('woocommerce_review_order_before_submit', [__CLASS__,'checkout_field']);
        add_action('woocommerce_checkout_process', [__CLASS__,'validate_psd']);
        add_action('wp_enqueue_scripts', [__CLASS__,'assets']);
        add_action('woocommerce_checkout_create_order',[__CLASS__,'create_order_meta'],10,2);
        add_action('woocommerce_review_order_before_submit',[__CLASS__,'picker_button']);
    }

    public static function assets(){
        if (is_checkout()) {
            $configured_methods = Settings::get('shipping_psd_methods', []);
            if (!is_array($configured_methods)) {
                $configured_methods = [];
            }
            wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
            wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
            wp_enqueue_script('woo-mygls-front', WOO_MYGLS_URL.'assets/front.js', ['jquery','leaflet'], WOO_MYGLS_VERSION, true);
            wp_localize_script('woo-mygls-front','WOO_MYGLS', [
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('woo_mygls'),
                'pointsUrl' => 'https://map.gls-hungary.com/data/deliveryPoints/hu.json',
                'shippingMethods' => array_values(array_unique(array_map('strval', $configured_methods))),
            ]);
            wp_enqueue_style('woo-mygls-front', WOO_MYGLS_URL.'assets/front.css', [], WOO_MYGLS_VERSION);
        }
    }

    public static function checkout_field($checkout = null){
        if (self::$rendered) return; self::$rendered = true;
        $value = '';
        $value_id = '';
        if ($checkout instanceof \WC_Checkout) {
            $value = $checkout->get_value('gls_psd');
            $value_id = $checkout->get_value('gls_psd_id');
        }
        echo '<div id="gls-psd-field" style="display:none" data-gls-psd="1"><h3>'.esc_html__('GLS Csomagpont választó','woo-mygls').'</h3>';
        woocommerce_form_field('gls_psd', [
            'type'=>'text',
            'label'=>__('Választott pont','woo-mygls'),
            'required'=>false,
            'custom_attributes'=>['readonly'=>'readonly'],
            'placeholder'=>__('Nincs kiválasztva – kattints a "Csomagpont választás" gombra','woo-mygls')
        ], $value);
        echo '<button type="button" class="button" id="gls-open-map">'.__('Csomagpont választás','woo-mygls').'</button>';
        echo '<input type="hidden" name="gls_psd_id" id="gls_psd_id" value="'.esc_attr($value_id).'">';
        echo '</div>';
        // Modal container
        echo '<div id="gls-map-modal" style="display:none"><div id="gls-map"></div><div id="gls-list"></div></div>';
    }

    public static function picker_button(){
        echo '<div id="gls-modal-root"></div>';
    }

    public static function create_order_meta($order, $data){
        if (!empty($_POST['gls_psd_id'])){
            $order->update_meta_data('_gls_psd_id', sanitize_text_field($_POST['gls_psd_id']));
            $order->update_meta_data('_gls_psd_label', sanitize_text_field($_POST['gls_psd'] ?? ''));
        }
    }
    public static function save($order_id){ // legacy no-op for HPOS

        if (!empty($_POST['gls_psd_id'])){
            update_post_meta($order_id,'_gls_psd_id', sanitize_text_field($_POST['gls_psd_id']));
            update_post_meta($order_id,'_gls_psd_label', sanitize_text_field($_POST['gls_psd'] ?? ''));
        }

    }

    public static function validate_psd(){
        // Determine selected shipping method (supports multiple packages; check all)
        $selected_methods = [];
        if (!empty($_POST['shipping_method'])){
            if (is_array($_POST['shipping_method'])){
                foreach ($_POST['shipping_method'] as $sm){
                    $selected_methods[] = sanitize_text_field((string)$sm);
                }
            } else {
                $selected_methods[] = sanitize_text_field((string)$_POST['shipping_method']);
            }
        }

        $need_psd = false;
        $configured = Settings::get('shipping_psd_methods', []);
        if (!is_array($configured)){
            $configured = [];
        }

        if ($selected_methods && $configured){
            foreach ($selected_methods as $method){
                if (in_array($method, $configured, true)){
                    $need_psd = true;
                    break;
                }
            }
        }

        if (!$need_psd && $selected_methods){
            foreach ($selected_methods as $method){
                $sm_l = strtolower($method);
                if (strpos($sm_l,'csomagpont')!==false || strpos($sm_l,'automata')!==false || strpos($sm_l,'parcel')!==false){
                    $need_psd = true;
                    break;
                }
            }
        }

        if ($need_psd){
            $psd_id = sanitize_text_field($_POST['gls_psd_id'] ?? '');
            if (!$psd_id){
                wc_add_notice(__('Kérjük, válassz GLS Csomagpontot a térképen a folytatáshoz.','woo-mygls'), 'error');
            }
        }
    }
    
}
