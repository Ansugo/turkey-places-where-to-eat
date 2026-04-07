<?php
class TPWE_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'tpwe_places';
        $cities_table = $wpdb->prefix . 'tpwe_cities';
        
        // Şehirler tablosu
        $cities_sql = "CREATE TABLE IF NOT EXISTS $cities_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            city_name varchar(100) NOT NULL,
            google_place_id varchar(255),
            latitude decimal(10,8),
            longitude decimal(11,8),
            is_active tinyint(1) DEFAULT 1,
            last_updated datetime DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Mekanlar tablosu
        $places_sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            city_id mediumint(9) NOT NULL,
            google_place_id varchar(255) NOT NULL UNIQUE,
            name varchar(200) NOT NULL,
            address text,
            phone varchar(50),
            website varchar(255),
            rating decimal(2,1),
            total_ratings int(11),
            price_level int(1),
            photo_reference varchar(500),
            place_url varchar(500),
            is_active tinyint(1) DEFAULT 1,
            last_updated datetime DEFAULT NULL,
            PRIMARY KEY (id),
            FOREIGN KEY (city_id) REFERENCES $cities_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($cities_sql);
        dbDelta($places_sql);
    }
}