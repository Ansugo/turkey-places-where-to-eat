<?php
/**
 * Plugin Name: Turkey Places - Where to Eat
 * Plugin URI: https://farukenes.com.tr
 * Description: Fetches and displays restaurants from Google Places API for Turkish cities
 * Version: 1.0.0
 * Author: Haci Faruk Enes
 * License: GPL v2 or later
 */

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Plugin sabitleri
define('TPWE_VERSION', '1.0.0');
define('TPWE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPWE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPWE_CRON_INTERVAL', 604800); // 7 gün

// Otomatik yükleme sınıfları
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'TPWE_') === 0) {
        $file = TPWE_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Tüm gerekli dosyaları include et
$required_files = [
    'class-database.php',
    'class-api-handler.php',
    'class-cron-handler.php',
    'class-admin-page.php',
    'class-ajax-handler.php',
    'class-shortcode.php',
    'class-cache-handler.php'
];

foreach ($required_files as $file) {
    $file_path = TPWE_PLUGIN_DIR . 'includes/' . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}

// Eklentiyi başlat
function TPWE_init() {
    // Veritabanı tablolarını oluştur
    TPWE_Database::create_tables();
    
    // Cron job'ları ayarla
    TPWE_Cron_Handler::schedule_events();
    
    // Admin sayfasını yükle
    if (is_admin()) {
        new TPWE_Admin_Page();
    }
    
    // Widget'ı kaydet
// Widget'ı kaydet
//add_action('widgets_init', 'TPWE_register_widgets');
/*
function TPWE_register_widgets() {
    if (class_exists('TPWE_Widget')) {
        register_widget('TPWE_Widget');
    } else {
        // Debug için
        error_log('TPWE_Widget sınıfı bulunamadı');
    }
}
*/
    
    // AJAX handler'ı başlat
    new TPWE_Ajax_Handler();
    
    // Shortcode'u başlat
    new TPWE_Shortcode();
    
    // Cache handler'ı başlat
    new TPWE_Cache_Handler();
}
add_action('plugins_loaded', 'TPWE_init');

// Eklenti aktivasyonu
register_activation_hook(__FILE__, 'TPWE_activate');
function TPWE_activate() {
    TPWE_Database::create_tables();
    TPWE_Cron_Handler::schedule_events();
    
    // Default seçenekleri ekle
    add_option('TPWE_google_api_key', '');
    add_option('TPWE_cache_duration', 604800);
    add_option('TPWE_max_places_per_city', 20);
    add_option('TPWE_min_rating', 4.0);
}

// Eklenti deaktivasyonu
register_deactivation_hook(__FILE__, 'TPWE_deactivate');
function TPWE_deactivate() {
    TPWE_Cron_Handler::clear_scheduled_events();
    wp_clear_scheduled_hook('TPWE_fetch_places_daily');
}

// Eklenti silinmesi
register_uninstall_hook(__FILE__, 'TPWE_uninstall');
function TPWE_uninstall() {
    global $wpdb;
    
    // Tabloları sil
    $tables = [
        $wpdb->prefix . 'TPWE_places',
        $wpdb->prefix . 'TPWE_cities'
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // Seçenekleri sil
    delete_option('TPWE_google_api_key');
    delete_option('TPWE_cache_duration');
    delete_option('TPWE_max_places_per_city');
    delete_option('TPWE_min_rating');
    
    // Transient'leri temizle
    $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_TPWE_%'");
    $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_timeout_TPWE_%'");
    
    // Cache'leri temizle
    wp_cache_flush();
}

// Dil dosyaları yükleme
add_action('init', 'TPWE_load_textdomain');
function TPWE_load_textdomain() {
    load_plugin_textdomain(
        'tpwe',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

// Admin bildirimleri
add_action('admin_notices', 'TPWE_admin_notices');
function TPWE_admin_notices() {
    if (!get_option('TPWE_google_api_key')) {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>Turkey Places:</strong> 
                Google Places API key'iniz yapılandırılmamış. 
                <a href="<?php echo admin_url('admin.php?page=turkey-places-eat'); ?>">
                    Ayarlar sayfasından API key ekleyin
                </a>.
            </p>
        </div>
        <?php
    }
}

// Widget cache temizleme
/*
add_action('save_post', 'TPWE_clear_widget_cache');
function TPWE_clear_widget_cache() {
    wp_cache_delete('widget_TPWE_widget', 'widget');
}*/