<?php
class TPWE_API_Handler {
    
    private $api_key;
    private $base_url = 'https://maps.googleapis.com/maps/api/place';
    
    public function __construct() {
        $this->api_key = get_option('tpwe_google_api_key', '');
    }
    
    public function fetch_places_for_city($city_id) {
        global $wpdb;
        
        if (empty($this->api_key)) {
            error_log('TPWE: API key bulunamadı');
            return false;
        }
        
        // Şehir bilgilerini al
        $city = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tpwe_cities WHERE id = %d",
            $city_id
        ));
        
        if (!$city) {
            return false;
        }
        
        // Google'dan veri çek
        $places = $this->search_nearby_restaurants($city->latitude, $city->longitude);
        
        if ($places && is_array($places)) {
            $this->save_places_to_database($places, $city_id);
            return true;
        }
        
        return false;
    }
    
private function search_nearby_restaurants($lat, $lng, $radius = 20000) {
    $url = $this->base_url . '/nearbysearch/json';
    
    $params = [
        'key' => $this->api_key,
        'location' => "$lat,$lng",
        'radius' => $radius,
        'type' => 'restaurant',
        'rankby' => 'prominence'
    ];
    
    $all_places = [];
    $next_page_token = null;
    $max_pages = 3; // Max 3 sayfa (60 mekan) çek
    
    for ($page = 0; $page < $max_pages; $page++) {
        // Eğer next_page_token varsa ekle
        if ($next_page_token) {
            $params['pagetoken'] = $next_page_token;
            // Google API, next_page_token için 2 saniye bekleme gerektirir
            sleep(2);
        }
        
        $response = wp_remote_get(add_query_arg($params, $url));
        
        if (is_wp_error($response)) {
            error_log('TPWE API Error: ' . $response->get_error_message());
            break;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($body['status'] !== 'OK') {
            error_log('TPWE API Status: ' . $body['status']);
            break;
        }
        
        // Tüm 4+ rating'li yerleri topla
        foreach ($body['results'] as $place) {
            if (isset($place['rating']) && $place['rating'] >= 4.0) {
                $all_places[] = $place;
            }
        }
        
        // Next page token kontrolü
        if (isset($body['next_page_token'])) {
            $next_page_token = $body['next_page_token'];
        } else {
            break; // Daha fazla sayfa yok
        }
    }
    
    // 1. Yorum sayısına göre sırala (azalan)
    usort($all_places, function($a, $b) {
        $a_reviews = isset($a['user_ratings_total']) ? $a['user_ratings_total'] : 0;
        $b_reviews = isset($b['user_ratings_total']) ? $b['user_ratings_total'] : 0;
        return $b_reviews - $a_reviews;
    });
    
    // 2. İlk 20'yi al (en çok yoruma sahip olanlar)
    $filtered_places = array_slice($all_places, 0, 20);
    
    return $filtered_places;
}

	private function save_places_to_database($places, $city_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'tpwe_places';
    
		// Sadece ilk 20 kaydı işle (zaten API'den 20 geliyor ama yine de)
		$places = array_slice($places, 0, 20);
    
		foreach ($places as $place) {
			$data = [
				'city_id' => $city_id,
				'google_place_id' => $place['place_id'],
				'name' => $place['name'],
				'address' => isset($place['vicinity']) ? $place['vicinity'] : '',
				'rating' => isset($place['rating']) ? $place['rating'] : null,
				'total_ratings' => isset($place['user_ratings_total']) ? $place['user_ratings_total'] : 0,
				'price_level' => isset($place['price_level']) ? $place['price_level'] : null,
				'photo_reference' => isset($place['photos'][0]['photo_reference']) ? $place['photos'][0]['photo_reference'] : '',
				'place_url' => 'https://www.google.com/maps/place/?q=place_id:' . $place['place_id'],
				'last_updated' => current_time('mysql')
			];
        
			// Var olan kaydı güncelle veya yeni ekle
			$existing = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM $table_name WHERE google_place_id = %s",
				$place['place_id']
			));
        
			if ($existing) {
				$wpdb->update($table_name, $data, ['id' => $existing]);
			} else {
				$wpdb->insert($table_name, $data);
			}
		}
    
		// Eski kayıtları sil (opsiyonel - aktif olmayanları temizler)
		$this->clean_old_places($city_id, array_column($places, 'place_id'));
    
		// Şehirin güncelleme tarihini güncelle
		$wpdb->update(
			$wpdb->prefix . 'tpwe_cities',
			['last_updated' => current_time('mysql')],
			['id' => $city_id]
		);
	}

	// Eski/aktif olmayan kayıtları temizle
	private function clean_old_places($city_id, $current_place_ids) {
		global $wpdb;
    
		if (empty($current_place_ids)) {
			return;
		}
    
		$place_ids_placeholder = implode(',', array_fill(0, count($current_place_ids), '%s'));
    
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}tpwe_places 
			WHERE city_id = %d 
			AND google_place_id NOT IN ($place_ids_placeholder)
			AND last_updated < DATE_SUB(NOW(), INTERVAL 1 DAY)",
			array_merge([$city_id], $current_place_ids)
		));
	}

   public function get_place_photo_url($photo_reference, $max_width = 400) {
    if (empty($photo_reference)) {
        // Varsayılan resim için SVG oluştur
        return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="' . $max_width . '" height="' . ($max_width * 0.75) . '" viewBox="0 0 400 300">
                    <rect width="400" height="300" fill="#f0f0f0"/>
                    <rect x="50" y="50" width="300" height="200" fill="#ffffff" stroke="#cccccc" stroke-width="2"/>
                    <rect x="100" y="80" width="200" height="20" fill="#e0e0e0"/>
                    <rect x="100" y="110" width="150" height="15" fill="#e0e0e0"/>
                    <rect x="100" y="130" width="100" height="15" fill="#e0e0e0"/>
                    <circle cx="300" cy="150" r="40" fill="#4a90e2" opacity="0.3"/>
                    <text x="200" y="220" text-anchor="middle" font-family="Arial" font-size="14" fill="#666666">Konaklama Yeri</text>
                </svg>');
    }
    
    $url = $this->base_url . '/photo';
    $params = [
        'key' => $this->api_key,
        'photoreference' => $photo_reference,
        'maxwidth' => $max_width
    ];
    
    return add_query_arg($params, $url);
	}
}