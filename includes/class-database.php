<?php
namespace Alenseo;

/**
 * Datenbank-Klasse für Alenseo SEO
 *
 * Diese Klasse ist verantwortlich für die Datenbank-Operationen
 * 
 * @link       https://www.imponi.ch
 * @since      2.0.3
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Die Datenbank-Klasse
 */
class Alenseo_Database {
    
    /**
     * Die Versions-Nummer der Datenbank
     * 
     * @var string
     */
    private $db_version = '1.0.0';
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Aktivierungshook für Datenbankinstallation
        register_activation_hook(ALENSEO_PLUGIN_FILE, array($this, 'install'));
        
        // Upgrade-Check bei Admin-Initialisierung
        add_action('admin_init', array($this, 'check_version'));
    }
    
    /**
     * Plugin-Datenbank installieren
     */
    public function install() {
        $this->create_tables();
        $this->update_db_version();
    }
    
    /**
     * Datenbanktabellen erstellen
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabellennamen mit Präfix definieren
        $keywords_table = $wpdb->prefix . 'alenseo_keywords';
        $seo_scores_table = $wpdb->prefix . 'alenseo_seo_scores';
        $api_history_table = $wpdb->prefix . 'alenseo_api_history';
        
        // SQL für die Keywords-Tabelle
        $sql_keywords = "CREATE TABLE $keywords_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            keyword varchar(255) NOT NULL,
            score int(11) DEFAULT 0,
            status varchar(50) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY keyword (keyword(191))
        ) $charset_collate;";
        
        // SQL für die SEO-Scores-Tabelle
        $sql_scores = "CREATE TABLE $seo_scores_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            score int(11) DEFAULT 0,
            meta_title_score int(11) DEFAULT 0,
            meta_description_score int(11) DEFAULT 0,
            content_score int(11) DEFAULT 0,
            headings_score int(11) DEFAULT 0,
            status varchar(50) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY post_id (post_id)
        ) $charset_collate;";
        
        // SQL für die API-History-Tabelle
        $sql_api = "CREATE TABLE $api_history_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_key varchar(64) NOT NULL,
            request_type varchar(50) NOT NULL,
            tokens_used int(11) DEFAULT 0,
            success tinyint(1) DEFAULT 1,
            error_message text DEFAULT NULL,
            request_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY request_date (request_date)
        ) $charset_collate;";
        
        // Datenbank-Upgrade-Routine ausführen
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_keywords);
        dbDelta($sql_scores);
        dbDelta($sql_api);
    }
    
    /**
     * DB-Version aktualisieren
     */
    private function update_db_version() {
        update_option('alenseo_db_version', $this->db_version);
    }
    
    /**
     * Prüfen, ob ein DB-Upgrade nötig ist
     */
    public function check_version() {
        if (get_option('alenseo_db_version') != $this->db_version) {
            $this->install();
        }
    }
    
    /**
     * Keyword speichern oder aktualisieren
     * 
     * @param int    $post_id  Die Post-ID
     * @param string $keyword  Das zu speichernde Keyword
     * @param int    $score    Der Score für das Keyword (optional)
     * @param string $status   Der Status des Keywords (optional)
     * @return bool Erfolg oder Misserfolg
     */
    public function save_keyword($post_id, $keyword, $score = 0, $status = 'pending') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_keywords';
        
        // Erst prüfen, ob das Keyword bereits existiert
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE post_id = %d AND keyword = %s",
                $post_id,
                $keyword
            )
        );
        
        if ($existing) {
            // Aktualisieren des bestehenden Keywords
            return $wpdb->update(
                $table_name,
                array(
                    'score' => $score,
                    'status' => $status,
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'id' => $existing->id
                )
            );
        } else {
            // Neues Keyword einfügen
            return $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'keyword' => $keyword,
                    'score' => $score,
                    'status' => $status,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        }
    }
    
    /**
     * Keywords für einen Post abrufen
     * 
     * @param int $post_id Die Post-ID
     * @return array Array mit Keywords und Scores
     */
    public function get_keywords($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_keywords';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT keyword, score, status FROM $table_name WHERE post_id = %d ORDER BY score DESC",
                $post_id
            ),
            ARRAY_A
        );
        
        return $results ? $results : array();
    }
    
    /**
     * SEO-Score für einen Post speichern oder aktualisieren
     * 
     * @param int    $post_id             Die Post-ID
     * @param int    $score               Der Gesamt-SEO-Score
     * @param array  $detailed_scores     Array mit Detail-Scores (optional)
     * @param string $status              Der SEO-Status (optional)
     * @return bool Erfolg oder Misserfolg
     */
    public function save_seo_score($post_id, $score, $detailed_scores = array(), $status = 'pending') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_seo_scores';
        
        // Default-Werte für Detail-Scores
        $meta_title_score = isset($detailed_scores['meta_title']) ? $detailed_scores['meta_title'] : 0;
        $meta_description_score = isset($detailed_scores['meta_description']) ? $detailed_scores['meta_description'] : 0;
        $content_score = isset($detailed_scores['content']) ? $detailed_scores['content'] : 0;
        $headings_score = isset($detailed_scores['headings']) ? $detailed_scores['headings'] : 0;
        
        // Bestehenden Eintrag suchen
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE post_id = %d",
                $post_id
            )
        );
        
        if ($existing) {
            // Aktualisieren des bestehenden Scores
            return $wpdb->update(
                $table_name,
                array(
                    'score' => $score,
                    'meta_title_score' => $meta_title_score,
                    'meta_description_score' => $meta_description_score,
                    'content_score' => $content_score,
                    'headings_score' => $headings_score,
                    'status' => $status,
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'id' => $existing->id
                )
            );
        } else {
            // Neuen Score einfügen
            return $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'score' => $score,
                    'meta_title_score' => $meta_title_score,
                    'meta_description_score' => $meta_description_score,
                    'content_score' => $content_score,
                    'headings_score' => $headings_score,
                    'status' => $status,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        }
    }
    
    /**
     * SEO-Score für einen Post abrufen
     * 
     * @param int $post_id Die Post-ID
     * @return array|false Array mit Score-Daten oder false wenn nicht gefunden
     */
    public function get_seo_score($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_seo_scores';
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT score, meta_title_score, meta_description_score, content_score, headings_score, status 
                FROM $table_name 
                WHERE post_id = %d",
                $post_id
            ),
            ARRAY_A
        );
        
        return $result ? $result : false;
    }
    
    /**
     * API-Nutzungsstatistik speichern
     * 
     * @param string $api_key       Der API-Key (gekürzt für Sicherheit)
     * @param string $request_type  Der API-Anfrage-Typ
     * @param int    $tokens_used   Die Anzahl verwendeter Tokens
     * @param bool   $success       Erfolg oder Misserfolg
     * @param string $error_message Fehlermeldung bei Misserfolg (optional)
     * @return bool Erfolg oder Misserfolg
     */
    public function log_api_usage($api_key, $request_type, $tokens_used, $success = true, $error_message = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_api_history';
        
        // API-Key kürzen (nur letzte 8 Zeichen speichern)
        $api_key_short = substr($api_key, -8);
        
        return $wpdb->insert(
            $table_name,
            array(
                'api_key' => $api_key_short,
                'request_type' => $request_type,
                'tokens_used' => $tokens_used,
                'success' => $success ? 1 : 0,
                'error_message' => $error_message,
                'request_date' => current_time('mysql')
            )
        );
    }
    
    /**
     * API-Nutzungsstatistik abrufen
     * 
     * @param string $period Zeitraum ('today', 'week', 'month', 'all')
     * @return array Statistik-Daten
     */
    public function get_api_usage($period = 'today') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_api_history';
        
        $where = '';
        switch ($period) {
            case 'today':
                $where = "WHERE DATE(request_date) = CURDATE()";
                break;
            case 'week':
                $where = "WHERE request_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $where = "WHERE request_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }
        
        // Abfragen für die Statistiken
        $total_requests = $wpdb->get_var("
            SELECT COUNT(*) FROM $table_name $where
        ");
        
        $successful_requests = $wpdb->get_var("
            SELECT COUNT(*) FROM $table_name $where AND success = 1
        ");
        
        $total_tokens = $wpdb->get_var("
            SELECT SUM(tokens_used) FROM $table_name $where
        ");
        
        // Statistikdaten zurückgeben
        return array(
            'total_requests' => intval($total_requests) ?: 0,
            'successful_requests' => intval($successful_requests) ?: 0,
            'error_rate' => $total_requests ? round(100 - ($successful_requests / $total_requests * 100), 2) : 0,
            'total_tokens' => intval($total_tokens) ?: 0
        );
    }
    
    /**
     * Top-Keywords basierend auf Score abrufen
     * 
     * @param int $limit  Maximale Anzahl der zurückgegebenen Keywords
     * @return array Array mit Top-Keywords und ihren Scores
     */
    public function get_top_keywords($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_keywords';
        
        $results = $wpdb->get_results("
            SELECT keyword, COUNT(*) as count, AVG(score) as avg_score
            FROM $table_name 
            GROUP BY keyword
            ORDER BY avg_score DESC, count DESC
            LIMIT $limit
        ", ARRAY_A);
        
        return $results ? $results : array();
    }
    
    /**
     * Durchschnittliche SEO-Scores aller Posts abrufen
     * 
     * @return array Array mit durchschnittlichen Scores
     */
    public function get_average_scores() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_seo_scores';
        
        $results = $wpdb->get_row("
            SELECT 
                AVG(score) as avg_score,
                AVG(meta_title_score) as avg_meta_title_score,
                AVG(meta_description_score) as avg_meta_description_score,
                AVG(content_score) as avg_content_score,
                AVG(headings_score) as avg_headings_score
            FROM $table_name
        ", ARRAY_A);
        
        return $results ? $results : array(
            'avg_score' => 0,
            'avg_meta_title_score' => 0,
            'avg_meta_description_score' => 0,
            'avg_content_score' => 0,
            'avg_headings_score' => 0
        );
    }
    
    /**
     * SEO-Score-Verlauf für das Dashboard abrufen
     * 
     * @param int $days Anzahl der Tage für den Verlauf
     * @return array Array mit Datum => Score-Einträgen
     */
    public function get_score_history($days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_seo_scores';
        
        $results = $wpdb->get_results("
            SELECT 
                DATE(updated_at) as score_date,
                AVG(score) as avg_score
            FROM $table_name
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
            GROUP BY DATE(updated_at)
            ORDER BY score_date
        ", ARRAY_A);
        
        $history = array();
        foreach ($results as $result) {
            $history[$result['score_date']] = round($result['avg_score']);
        }
        
        return $history;
    }
    
    /**
     * Posts mit fehlendem SEO-Score finden
     * 
     * @param int $limit Maximale Anzahl der zurückgegebenen Posts
     * @return array Array mit Post-IDs
     */
    public function get_posts_without_scores($limit = 50) {
        global $wpdb;
        $posts_table = $wpdb->posts;
        $scores_table = $wpdb->prefix . 'alenseo_seo_scores';
        
        $query = "
            SELECT p.ID
            FROM $posts_table p
            LEFT JOIN $scores_table s ON p.ID = s.post_id
            WHERE p.post_status = 'publish' 
            AND (p.post_type = 'post' OR p.post_type = 'page')
            AND s.id IS NULL
            LIMIT $limit
        ";
        
        return $wpdb->get_col($query);
    }

    /**
     * Get the total number of posts based on selected post types.
     *
     * @return int Total post count.
     */
    public function get_total_posts() {
        global $wpdb;
        $settings = get_option('alenseo_settings', array());
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        $post_types_placeholder = implode(",", array_fill(0, count($post_types), '%s'));

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($post_types_placeholder);",
            $post_types
        );

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get the number of analyzed posts.
     *
     * @return int Analyzed post count.
     */
    public function get_analyzed_posts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_analysis';
        $query = "SELECT COUNT(DISTINCT post_id) FROM $table_name WHERE analyzed = 1;";
        return (int) $wpdb->get_var($query);
    }

    /**
     * Get the number of posts needing improvement.
     *
     * @return int Posts needing improvement count.
     */
    public function get_needs_improvement_posts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_analysis';
        $query = "SELECT COUNT(DISTINCT post_id) FROM $table_name WHERE score < 50;";
        return (int) $wpdb->get_var($query);
    }

    /**
     * Get the number of posts without keywords.
     *
     * @return int Posts without keywords count.
     */
    public function get_no_keyword_posts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_keywords';
        $query = "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->posts} p LEFT JOIN $table_name k ON p.ID = k.post_id WHERE k.post_id IS NULL AND p.post_status = 'publish';";
        return (int) $wpdb->get_var($query);
    }

    /**
     * Get the average SEO score.
     *
     * @return float Average score.
     */
    public function get_average_score() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_analysis';
        $query = "SELECT AVG(score) FROM $table_name WHERE analyzed = 1;";
        return (float) $wpdb->get_var($query);
    }

    /**
     * Get the total number of keywords.
     *
     * @return int Total keyword count.
     */
    public function get_total_keywords() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'alenseo_keywords';
        $query = "SELECT COUNT(*) FROM $table_name;";
        return (int) $wpdb->get_var($query);
    }

    /**
     * Führt ein Upgrade der Datenbank durch
     */
    public static function upgrade() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'alenseo_data';

        // Neue Spalten hinzufügen, falls nicht vorhanden
        if (!self::column_exists($table_name, 'new_column')) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN new_column VARCHAR(255) DEFAULT NULL");
        }

        // Weitere Upgrade-Logik hier hinzufügen
    }

    /**
     * Prüft, ob eine Spalte in der Tabelle existiert
     */
    private static function column_exists($table_name, $column_name) {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
            $table_name,
            $column_name
        );
        return $wpdb->get_var($query) > 0;
    }

    public function get_optimized_results($table, $columns = '*', $where = '', $order_by = '', $limit = '') {
        global $wpdb;
        $query = "SELECT $columns FROM {$wpdb->prefix}$table";

        if ($where) {
            $query .= " WHERE $where";
        }

        if ($order_by) {
            $query .= " ORDER BY $order_by";
        }

        if ($limit) {
            $query .= " LIMIT $limit";
        }

        return $wpdb->get_results($query);
    }

    // Example usage
    public function get_recent_posts($limit = 10) {
        return $this->get_optimized_results('posts', '*', "post_status = 'publish'", 'post_date DESC', $limit);
    }
}
