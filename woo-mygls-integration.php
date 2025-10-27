<?php
/**
 * Plugin Name: WooCommerce MyGLS Pro (HU) — ParcelShop + Labels + Status
 * Description: Teljes MyGLS integráció WooCommerce-hez: Csomagpont/Automata térképes választó, automata és bulk címkegenerálás (PDF/ZPL), csomagstátusz szinkron, rendelés műveletek.
 * Version: 1.1.6
 * Author: Forme.hu x ChatGPT
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * WC requires at least: 7.0
 * WC tested up to: 9.2
 * Text Domain: woo-mygls
 */

if (!defined('ABSPATH')) { exit; }

define('WOO_MYGLS_VERSION', '1.1.6');
define('WOO_MYGLS_DIR', plugin_dir_path(__FILE__));
define('WOO_MYGLS_URL', plugin_dir_url(__FILE__));

// Autoload
spl_autoload_register(function($class){
    if (strpos($class, 'WOO_MYGLS\\') === 0) {
        $path = WOO_MYGLS_DIR . 'includes/' . str_replace(['WOO_MYGLS\\','\\'], ['', '/'], $class) . '.php';
        if (file_exists($path)) require $path;
    }
});

// Bootstrap
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) { return; }
    WOO_MYGLS\Plugin::init();
});

// HPOS (High-Performance Order Storage) compatibility
add_action('before_woocommerce_init', function(){
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
