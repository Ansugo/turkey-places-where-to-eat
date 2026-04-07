<?php
class TPWE_Admin_Page {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_post_tpwe_save_city', [$this, 'save_city']);
        add_action('admin_post_tpwe_delete_city', [$this, 'delete_city']);
        add_action('admin_post_tpwe_manual_fetch', [$this, 'manual_fetch']);
    }
    
	public function add_admin_menu() {
		add_menu_page(
			'Turkey Places - Eat', // 'Turkey Places' → 'Turkey Places - Eat'
			'Turkey Places - Eat', // Menü adı
			'manage_options',
			'turkey-places-eat', // 'turkey-places' → 'turkey-places-eat'
			[$this, 'render_admin_page'],
			'dashicons-food', // 'dashicons-admin-multisite' → 'dashicons-food'
			30
		);
	}
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_turkey-places') {
            return;
        }
        
        wp_enqueue_style(
            'tpwe-admin-style',
            TPWE_PLUGIN_URL . 'admin/css/admin-style.css',
            [],
            TPWE_VERSION
        );
        
        wp_enqueue_script(
            'tpwe-admin-script',
            TPWE_PLUGIN_URL . 'admin/js/admin-script.js',
            ['jquery'],
            TPWE_VERSION,
            true
        );
    }
    
    public function render_admin_page() {
        global $wpdb;
        
        // API key ayarı
        if (isset($_POST['save_api_key'])) {
            update_option('tpwe_google_api_key', sanitize_text_field($_POST['api_key']));
            echo '<div class="notice notice-success"><p>API key kaydedildi.</p></div>';
        }
        
        $api_key = get_option('tpwe_google_api_key', '');
        $cities = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tpwe_cities ORDER BY city_name");
        ?>
        
        <div class="wrap tpwe-admin">
            <h1>Turkey Places - Where to Eat</h1>
            
            <div class="tpwe-settings-section">
                <h2>Google API Ayarları</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('tpwe_api_key_nonce', '_wpnonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="api_key">Google Places API Key</label></th>
                            <td>
                                <input type="text" id="api_key" name="api_key" 
                                       value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <a href="https://console.cloud.google.com/" target="_blank">
                                        Google Cloud Console'dan alın
                                    </a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <input type="submit" name="save_api_key" class="button button-primary" value="Kaydet">
                </form>
            </div>
            
            <div class="tpwe-cities-section">
                <h2>Şehir Yönetimi</h2>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="tpwe-add-city">
                    <input type="hidden" name="action" value="tpwe_save_city">
                    <?php wp_nonce_field('tpwe_add_city', 'tpwe_nonce'); ?>
                    
                    <h3>Yeni Şehir Ekle</h3>
                    <div class="form-row">
                        <input type="text" name="city_name" placeholder="Şehir Adı (örn: Manavgat)" required>
                        <input type="text" name="latitude" placeholder="Enlem (örn: 36.7867)" required>
                        <input type="text" name="longitude" placeholder="Boylam (örn: 31.4430)" required>
                        <input type="submit" class="button button-primary" value="Şehir Ekle">
                    </div>
                </form>
                
                <h3>Mevcut Şehirler</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Şehir Adı</th>
                            <th>Koordinatlar</th>
                            <th>Son Güncelleme</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cities): ?>
                            <?php foreach ($cities as $city): ?>
                                <tr>
                                    <td><?php echo $city->id; ?></td>
                                    <td><?php echo esc_html($city->city_name); ?></td>
                                    <td><?php echo $city->latitude . ', ' . $city->longitude; ?></td>
                                    <td><?php echo $city->last_updated ?: 'Henüz çekilmedi'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $city->is_active ? 'active' : 'inactive'; ?>">
                                            <?php echo $city->is_active ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                            <input type="hidden" name="action" value="tpwe_manual_fetch">
                                            <input type="hidden" name="city_id" value="<?php echo $city->id; ?>">
                                            <?php wp_nonce_field('tpwe_manual_fetch_' . $city->id, '_wpnonce'); ?>
                                            <button type="submit" class="button button-small">Şimdi Çek</button>
                                        </form>
                                        
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                            <input type="hidden" name="action" value="tpwe_delete_city">
                                            <input type="hidden" name="city_id" value="<?php echo $city->id; ?>">
                                            <?php wp_nonce_field('tpwe_delete_city_' . $city->id, '_wpnonce'); ?>
                                            <button type="submit" class="button button-small button-danger" 
                                                    onclick="return confirm('Bu şehri silmek istediğinize emin misiniz?')">
                                                Sil
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Henüz şehir eklenmemiş.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="tpwe-actions">
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="tpwe_manual_fetch">
                        <input type="hidden" name="city_id" value="all">
                        <?php wp_nonce_field('tpwe_manual_fetch_all', '_wpnonce'); ?>
                        <button type="submit" class="button button-large button-primary">
                            Tüm Şehirleri Şimdi Çek
                        </button>
                        <p class="description">Not: Bu işlem biraz zaman alabilir ve API limitinizi kullanır.</p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function save_city() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['tpwe_nonce'], 'tpwe_add_city')) {
            wp_die('Yetkiniz yok.');
        }
        
        global $wpdb;
        
        $data = [
            'city_name' => sanitize_text_field($_POST['city_name']),
            'latitude' => floatval($_POST['latitude']),
            'longitude' => floatval($_POST['longitude']),
            'is_active' => 1
        ];
        
        $wpdb->insert($wpdb->prefix . 'tpwe_cities', $data);
        
        wp_redirect(admin_url('admin.php?page=turkey-places-eat&message=saved'));
        exit;
    }
    
    public function delete_city() {
        if (!current_user_can('manage_options')) {
            wp_die('Yetkiniz yok.');
        }
        
        global $wpdb;
        
        $city_id = intval($_POST['city_id']);
        $wpdb->delete($wpdb->prefix . 'tpwe_cities', ['id' => $city_id]);
        
        wp_redirect(admin_url('admin.php?page=turkey-places-eat&message=deleted'));
        exit;
    }
    
    public function manual_fetch() {
        if (!current_user_can('manage_options')) {
            wp_die('Yetkiniz yok.');
        }
        
        $city_id = $_POST['city_id'];
        $api_handler = new TPWE_API_Handler();
        
        if ($city_id === 'all') {
            TPWE_Cron_Handler::fetch_all_cities_places();
        } else {
            $api_handler->fetch_places_for_city(intval($city_id));
        }
        
        wp_redirect(admin_url('admin.php?page=turkey-places-eat&message=fetched'));
        exit;
    }
}