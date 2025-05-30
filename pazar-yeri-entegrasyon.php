<?php
/**
 * Plugin Name: Pazar Yeri Entegrasyon ve Kar/Zarar Hesaplama
 * Description: WooCommerce için çoklu tedarikçi ve pazar yeri entegrasyonu
 * Version: 2.0.0
 * Author: Your Name
 * Text Domain: pazar-yeri-entegrasyon
 */

defined('ABSPATH') || exit;

// Define constants
define('PYE_VERSION', '2.0.0');
define('PYE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PYE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'pye_woocommerce_missing_notice');
    return;
}

function pye_woocommerce_missing_notice() {
    echo '<div class="error"><p>' . esc_html__('WooCommerce required for this plugin', 'pazar-yeri-entegrasyon') . '</p></div>';
}

// Load textdomain
add_action('plugins_loaded', function() {
    load_plugin_textdomain('pazar-yeri-entegrasyon', false, dirname(plugin_basename(__FILE__)) . '/languages/';
});

// Include class files
$files = [
    'class-supplier-manager',
    'class-importer',
    'class-exporter',
    'class-marketplace',
    'class-order-manager',
    'class-profit-calculator',
    'class-settings'
];

foreach ($files as $file) {
    require_once PYE_PLUGIN_DIR . "includes/{$file}.php";
}

// Activation/deactivation
register_activation_hook(__FILE__, 'pye_activate');
register_deactivation_hook(__FILE__, 'pye_deactivate');

function pye_activate() {
    PYE_Supplier_Manager::create_tables();
    PYE_Order_Manager::create_tables();
    
    if (!wp_next_scheduled('pye_hourly_import')) {
        wp_schedule_event(time(), 'six_hours', 'pye_hourly_import');
    }
}

function pye_deactivate() {
    wp_clear_scheduled_hook('pye_hourly_import');
}

// Add custom cron interval
add_filter('cron_schedules', function($schedules) {
    $schedules['six_hours'] = [
        'interval' => 6 * HOUR_IN_SECONDS,
        'display' => __('Every 6 Hours', 'pazar-yeri-entegrasyon')
    ];
    return $schedules;
});

// Initialize plugin
add_action('plugins_loaded', function() {
    PYE_Settings::instance();
    PYE_Supplier_Manager::instance();
    PYE_Importer::instance();
    PYE_Exporter::instance();
    PYE_Marketplace::instance();
    PYE_Order_Manager::instance();
    PYE_Profit_Calculator::instance();
});
<?php
/**
 * Plugin Name: Pazar Yeri Entegrasyon ve Kar/Zarar Hesaplama
 * Plugin URI: https://example.com/pazar-yeri-entegrasyon
 * Description: WooCommerce için çoklu dil desteği ile tedarikçi ve pazar yeri entegrasyonu, otomatik fiyatlandırma ve kar/zarar analizi.
 * Version: 2.0.0
 * Author: Sizin Adınız
 * Author URI: https://example.com
 * Text Domain: pazar-yeri-entegrasyon
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 6.8.1
 * WC requires at least: 3.4.8
 * WC tested up to: 3.4.8
 */

defined('ABSPATH') || exit;

// Eklenti sabitleri
define('PYE_VERSION', '2.0.0');
define('PYE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PYE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PYE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// WooCommerce kontrolü
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'pye_woocommerce_missing_notice');
    return;
}

function pye_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>' . esc_html__('Pazar Yeri Entegrasyon', 'pazar-yeri-entegrasyon') . '</strong> ' . 
         esc_html__('eklentisi çalışmak için WooCommerce eklentisine ihtiyaç duyar.', 'pazar-yeri-entegrasyon') . '</p></div>';
}

// Çoklu dil desteği
function pye_load_textdomain() {
    load_plugin_textdomain('pazar-yeri-entegrasyon', false, dirname(plugin_basename(__FILE__)) . '/languages/';
}
add_action('plugins_loaded', 'pye_load_textdomain');

// Sınıfları yükle
require_once PYE_PLUGIN_DIR . 'includes/class-supplier-manager.php';
require_once PYE_PLUGIN_DIR . 'includes/class-importer.php';
require_once PYE_PLUGIN_DIR . 'includes/class-exporter.php';
require_once PYE_PLUGIN_DIR . 'includes/class-marketplace.php';
require_once PYE_PLUGIN_DIR . 'includes/class-order-manager.php';
require_once PYE_PLUGIN_DIR . 'includes/class-profit-calculator.php';
require_once PYE_PLUGIN_DIR . 'includes/class-settings.php';

// Eklenti etkinleştirme/deaktivasyon
register_activation_hook(__FILE__, 'pye_activate');
register_deactivation_hook(__FILE__, 'pye_deactivate');

function pye_activate() {
    PYE_Supplier_Manager::create_tables();
    PYE_Order_Manager::create_tables();
    
    // Zamanlanmış görevler
    if (!wp_next_scheduled('pye_hourly_import')) {
        wp_schedule_event(time(), 'six_hours', 'pye_hourly_import');
    }
    
    if (!wp_next_scheduled('pye_daily_order_sync')) {
        wp_schedule_event(time(), 'daily', 'pye_daily_order_sync');
    }
}

function pye_deactivate() {
    wp_clear_scheduled_hook('pye_hourly_import');
    wp_clear_scheduled_hook('pye_daily_order_sync');
}

// Özel cron aralığı
add_filter('cron_schedules', 'pye_add_cron_interval');
function pye_add_cron_interval($schedules) {
    $schedules['six_hours'] = array(
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => esc_html__('Her 6 Saatte Bir', 'pazar-yeri-entegrasyon'),
    );
    return $schedules;
}

// Eklentiyi başlat
function pye_init() {
    PYE_Settings::instance();
    PYE_Supplier_Manager::instance();
    PYE_Importer::instance();
    PYE_Exporter::instance();
    PYE_Marketplace::instance();
    PYE_Order_Manager::instance();
    PYE_Profit_Calculator::instance();
}
add_action('plugins_loaded', 'pye_init');
<?php
/**
 * Plugin Name: Pazar Yeri Kar Zarar Hesaplama ve Entegrasyon
 * Plugin URI: https://orneksite.com/pazar-yeri-eklentisi
 * Description: WooCommerce için çoklu tedarikçi ve pazar yeri entegrasyonu, kar/zarar hesaplama ve otomatik ürün yönetimi eklentisi.
 * Version: 1.0.0
 * Author: Sizin Adınız
 * Author URI: https://orneksite.com
 * Text Domain: pazar-yeri-kar-zarar
 * Domain Path: /languages
 * WC requires at least: 3.4.8
 * WC tested up to: 3.4.8
 * Requires at least: 5.6
 * Tested up to: 6.8.1
 */

defined('ABSPATH') || exit;

// Eklenti sabitleri
define('PY_KZ_VERSION', '1.0.0');
define('PY_KZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PY_KZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PY_KZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

// WooCommerce aktif mi kontrolü
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'py_kz_woocommerce_missing_notice');
    return;
}

function py_kz_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>Pazar Yeri Kar Zarar Hesaplama</strong> eklentisi çalışmak için WooCommerce eklentisine ihtiyaç duyar.</p></div>';
}

// Sınıfları yükle
require_once PY_KZ_PLUGIN_DIR . 'includes/class-supplier-manager.php';
require_once PY_KZ_PLUGIN_DIR . 'includes/class-importer.php';
require_once PY_KZ_PLUGIN_DIR . 'includes/class-exporter.php';
require_once PY_KZ_PLUGIN_DIR . 'includes/class-marketplace-integration.php';
require_once PY_KZ_PLUGIN_DIR . 'includes/class-order-tracker.php';
require_once PY_KZ_PLUGIN_DIR . 'includes/class-profit-calculator.php';
require_once PY_KZ_PLUGIN_DIR . 'includes/class-settings.php';

// Eklenti etkinleştirme ve devre dışı bırakma işlemleri
register_activation_hook(__FILE__, 'py_kz_activate');
register_deactivation_hook(__FILE__, 'py_kz_deactivate');

function py_kz_activate() {
    // Gerekli tabloları oluştur
    PY_KZ_Supplier_Manager::create_tables();
    PY_KZ_Order_Tracker::create_tables();
    
    // Zamanlanmış görevleri ayarla
    if (!wp_next_scheduled('py_kz_hourly_import')) {
        wp_schedule_event(time(), 'six_hours', 'py_kz_hourly_import');
    }
    
    if (!wp_next_scheduled('py_kz_daily_order_sync')) {
        wp_schedule_event(time(), 'daily', 'py_kz_daily_order_sync');
    }
}

function py_kz_deactivate() {
    // Zamanlanmış görevleri temizle
    wp_clear_scheduled_hook('py_kz_hourly_import');
    wp_clear_scheduled_hook('py_kz_daily_order_sync');
}

// Özel zaman aralığı ekle
add_filter('cron_schedules', 'py_kz_add_cron_interval');
function py_kz_add_cron_interval($schedules) {
    $schedules['six_hours'] = array(
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => esc_html__('Her 6 Saatte Bir'),
    );
    return $schedules;
}

// Eklenti sınıfını başlat
function py_kz_init() {
    PY_KZ_Settings::instance();
    PY_KZ_Supplier_Manager::instance();
    PY_KZ_Importer::instance();
    PY_KZ_Exporter::instance();
    PY_KZ_Marketplace_Integration::instance();
    PY_KZ_Order_Tracker::instance();
    PY_KZ_Profit_Calculator::instance();
}
add_action('plugins_loaded', 'py_kz_init');

// Çeviri yükle
function py_kz_load_textdomain() {
    load_plugin_textdomain('pazar-yeri-kar-zarar', false, dirname(plugin_basename(__FILE__)) . '/languages/';
}
add_action('plugins_loaded', 'py_kz_load_textdomain');
