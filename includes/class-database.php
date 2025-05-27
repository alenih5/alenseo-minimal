<?php
namespace SEOAI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verwaltung der Plugin-Datenbanktabellen
 */
class Database {
    /**
     * Instanz
     */
    private static $instance = null;
    /**
     * WPDB-Objekt
     */
    private $wpdb;
    
    /**
     * Konstruktor
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Instanz holen
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Erstelle alle notwendigen Tabellen
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $table_data = $this->wpdb->prefix . 'seo_ai_data';
        $table_usage = $this->wpdb->prefix . 'seo_ai_usage';
        $table_keywords = $this->wpdb->prefix . 'seo_ai_keywords';
        
        $sql = "
        CREATE TABLE {$table_data} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            target_keyword varchar(255) DEFAULT NULL,
            seo_score int(3) DEFAULT NULL,
            meta_title_score int(3) DEFAULT NULL,
            meta_desc_score int(3) DEFAULT NULL,
            content_score int(3) DEFAULT NULL,
            last_analyzed datetime DEFAULT NULL,
            ai_suggestions longtext,
            optimization_history longtext,
            PRIMARY KEY  (id),
            KEY post_id (post_id)
        ) {$charset_collate};
        
        CREATE TABLE {$table_usage} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            api_provider varchar(50) DEFAULT NULL,
            action_type varchar(100) DEFAULT NULL,
            tokens_used int(10) DEFAULT 0,
            cost_estimate decimal(10,4) DEFAULT 0.0000,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY timestamp (timestamp)
        ) {$charset_collate};
        
        CREATE TABLE {$table_keywords} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            post_id bigint(20) DEFAULT NULL,
            difficulty_score int(3) DEFAULT NULL,
            search_volume int(10) DEFAULT NULL,
            last_updated datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY keyword (keyword),
            KEY post_id (post_id)
        ) {$charset_collate};
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * SEO-Daten speichern
     * @param int $post_id
     * @param array $data
     */
    public function save_seo_data($post_id, $data) {
        $table = $this->wpdb->prefix . 'seo_ai_data';
        $json = wp_json_encode($data);
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d",
                $post_id
        ));
        if ($existing) {
            $this->wpdb->update(
                $table,
                ['ai_suggestions' => $json, 'last_analyzed' => current_time('mysql')],
                ['id' => $existing],
                ['%s','%s'],
                ['%d']
            );
        } else {
            $this->wpdb->insert(
                $table,
                ['post_id' => $post_id, 'ai_suggestions' => $json, 'last_analyzed' => current_time('mysql')],
                ['%d','%s','%s']
            );
        }
    }
    
    /**
     * SEO-Daten abrufen
     * @param int $post_id
     * @return array
     */
    public function get_seo_data($post_id) {
        $table = $this->wpdb->prefix . 'seo_ai_data';
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT ai_suggestions FROM {$table} WHERE post_id = %d",
                $post_id
        ));
        if ($row && !empty($row->ai_suggestions)) {
            return json_decode($row->ai_suggestions, true);
        }
        return [];
    }
} 