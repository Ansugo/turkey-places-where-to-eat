<?php
class TPWE_Cache_Handler {
    
    private $cache_time = 604800; // 7 gün saniye cinsinden
    
    public function __construct() {
        add_action('tpwe_place_updated', [$this, 'clear_caches'], 10, 2);
        add_action('tpwe_city_updated', [$this, 'clear_city_caches'], 10, 1);
    }
    
    public function get_cached_places($city_id, $limit = 10) {
        $cache_key = $this->get_cache_key($city_id, $limit);
        $cached = wp_cache_get($cache_key, 'tpwe');
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        
        $places = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tpwe_places 
             WHERE city_id = %d AND is_active = 1 AND rating >= 4.0
             ORDER BY rating DESC, total_ratings DESC 
             LIMIT %d",
            $city_id,
            $limit
        ));
        
        // Fotoğraf URL'lerini cache'e ekle
        $api_handler = new TPWE_API_Handler();
        foreach ($places as &$place) {
            if (!empty($place->photo_reference)) {
                $place->photo_url = $api_handler->get_place_photo_url($place->photo_reference, 400);
            } else {
                $place->photo_url = TPWE_PLUGIN_URL . 'assets/images/default-hotel.jpg';
            }
        }
        
        wp_cache_set($cache_key, $places, 'tpwe', $this->cache_time);
        
        return $places;
    }
    
    public function get_cached_city_stats($city_id) {
        $cache_key = 'tpwe_stats_' . $city_id;
        $cached = wp_cache_get($cache_key, 'tpwe');
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_places,
                AVG(rating) as avg_rating,
                MAX(rating) as max_rating,
                MIN(rating) as min_rating,
                SUM(total_ratings) as total_reviews
             FROM {$wpdb->prefix}tpwe_places 
             WHERE city_id = %d AND is_active = 1",
            $city_id
        ));
        
        wp_cache_set($cache_key, $stats, 'tpwe', 3600); // 1 saat cache
        
        return $stats;
    }
    
    public function clear_caches($city_id, $place_ids) {
        $patterns = [
            'tpwe_places_' . $city_id . '_*',
            'tpwe_stats_' . $city_id
        ];
        
        foreach ($patterns as $pattern) {
            wp_cache_delete($pattern, 'tpwe');
        }
        
        // Transient'leri temizle
        $this->clear_transients($city_id);
    }
    
    public function clear_city_caches($city_id) {
        $this->clear_caches($city_id, []);
        
        // Widget cache'leri temizle
        $widget_instances = get_option('widget_tpwe_widget');
        
        if (is_array($widget_instances)) {
            foreach ($widget_instances as $key => $instance) {
                if (is_array($instance) && isset($instance['city_id']) && $instance['city_id'] == $city_id) {
                    wp_cache_delete('tpwe_widget_' . $key, 'tpwe');
                }
            }
        }
    }
    
    private function clear_transients($city_id) {
        $transients = [
            'tpwe_places_city_' . $city_id,
            'tpwe_city_data_' . $city_id
        ];
        
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
    }
    
    private function get_cache_key($city_id, $limit) {
        return 'tpwe_places_' . $city_id . '_' . $limit . '_' . get_locale();
    }
    
    public function preload_city_data($city_id) {
        // Önceden yükleme işlemi
        $this->get_cached_places($city_id, 20);
        $this->get_cached_city_stats($city_id);
    }
    
    public function get_all_active_cities() {
        $cache_key = 'tpwe_active_cities';
        $cached = wp_cache_get($cache_key, 'tpwe');
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        
        $cities = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}tpwe_cities 
             WHERE is_active = 1 
             ORDER BY city_name"
        );
        
        wp_cache_set($cache_key, $cities, 'tpwe', 86400); // 24 saat
        
        return $cities;
    }
}

// Cache handler'ı başlat
new TPWE_Cache_Handler();