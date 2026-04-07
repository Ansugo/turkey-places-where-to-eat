<?
<?php
// includes/class-widget.php

// Doğrudan yüklendiğini kontrol et
if (!defined('ABSPATH')) {
    exit;
}

class TPWE_Widget extends WP_Widget {
    
    public function __construct() {
        $widget_ops = array(
            'classname' => 'tpwe_widget',
            'description' => 'Şehirdeki 3.5-5 yıldızlı restoranları gösterir',
        );
        
        parent::__construct(
            'tpwe_widget', // Base ID
            'Turkey Places - Where to Eat', // Name
            $widget_ops
        );
        
        // Script ve stil dosyalarını sadece widget aktifken yükle
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_if_active'));
    }
    
    public function enqueue_scripts_if_active() {
        if (is_active_widget(false, false, $this->id_base, true)) {
            wp_enqueue_style(
                'tpwe-public-style',
                TPWE_PLUGIN_URL . 'public/css/public-style.css',
                array(),
                TPWE_VERSION
            );
            
            wp_enqueue_script(
                'tpwe-public-script',
                TPWE_PLUGIN_URL . 'public/js/public-script.js',
                array('jquery'),
                TPWE_VERSION,
                true
            );
            
            // AJAX için nonce
            wp_localize_script('tpwe-public-script', 'tpwe_public', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tpwe_public_nonce')
            ));
        }
    }
    
    // Diğer fonksiyonlar aynı kalacak...
    // ...
	
    public function widget($args, $instance) {
        global $wpdb;
        
        $title = apply_filters('widget_title', $instance['title']);
        $city_id = $instance['city_id'];
        $max_items = min($instance['max_items'], 20);
        
        if (empty($city_id)) {
            return;
        }
        
        // Şehir adını al
        $city = $wpdb->get_row($wpdb->prepare(
            "SELECT city_name FROM {$wpdb->prefix}tpwe_cities WHERE id = %d",
            $city_id
        ));
        
        if (!$city) {
            return;
        }
        
        // Mekanları veritabanından al
        $places = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tpwe_places 
             WHERE city_id = %d AND is_active = 1 AND rating >= 4.0
             ORDER BY rating DESC, total_ratings DESC 
             LIMIT %d",
            $city_id,
            $max_items
        ));
        
        if (empty($places)) {
            return;
        }
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        
        // Widget içeriği
        include TPWE_PLUGIN_DIR . 'public/templates/widget-template.php';
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        global $wpdb;
        
        $title = $instance['title'] ?? 'Where to Eat in [City]';
        $city_id = $instance['city_id'] ?? '';
        $max_items = $instance['max_items'] ?? 10;
        
        // Şehirleri getir
        $cities = $wpdb->get_results("SELECT id, city_name FROM {$wpdb->prefix}tpwe_cities WHERE is_active = 1 ORDER BY city_name");
        ?>
        
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Başlık:</label>
            <input type="text" 
                   id="<?php echo $this->get_field_id('title'); ?>" 
                   name="<?php echo $this->get_field_name('title'); ?>" 
                   value="<?php echo esc_attr($title); ?>" 
                   class="widefat" />
        </p>
        
        <p>
            <label for="<?php echo $this->get_field_id('city_id'); ?>">Şehir:</label>
            <select id="<?php echo $this->get_field_id('city_id'); ?>" 
                    name="<?php echo $this->get_field_name('city_id'); ?>" 
                    class="widefat">
                <option value="">Şehir Seçin</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?php echo $city->id; ?>" 
                            <?php selected($city_id, $city->id); ?>>
                        <?php echo esc_html($city->city_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <p>
            <label for="<?php echo $this->get_field_id('max_items'); ?>">Maksimum Gösterilecek Mekan:</label>
            <input type="number" 
                   id="<?php echo $this->get_field_id('max_items'); ?>" 
                   name="<?php echo $this->get_field_name('max_items'); ?>" 
                   value="<?php echo esc_attr($max_items); ?>" 
                   min="1" max="20" 
                   class="widefat" />
        </p>
        
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title']);
        $instance['city_id'] = intval($new_instance['city_id']);
        $instance['max_items'] = min(intval($new_instance['max_items']), 20);
        
        return $instance;
    }
}