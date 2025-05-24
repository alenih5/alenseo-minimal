<?php
/**
 * Klasse für die Dashboard-Funktionalität
 */
class Alenseo_Dashboard {
    
    /**
     * Initialisiert die Dashboard-Funktionalität
     */
    public function init() {
        add_action('wp_ajax_alenseo_get_dashboard_data', array($this, 'get_dashboard_data'));
    }
    
    /**
     * Bereitet die Daten für die Dashboard-Visualisierungen vor
     */
    public function get_dashboard_data() {
        check_ajax_referer('alenseo_nonce', 'nonce');
        
        $data = array(
            'status' => $this->get_status_data(),
            'performance' => $this->get_performance_data(),
            'keywords' => $this->get_keyword_data(),
            'heatmap' => $this->get_heatmap_data(),
            'trend' => $this->get_trend_data()
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * Bereitet die Status-Daten vor
     */
    private function get_status_data() {
        $optimized = $this->count_posts_by_status('optimized');
        $to_improve = $this->count_posts_by_status('to-improve');
        $no_keywords = $this->count_posts_by_status('no-keywords');
        
        return array(
            'optimized' => $optimized,
            'to_improve' => $to_improve,
            'no_keywords' => $no_keywords
        );
    }
    
    /**
     * Bereitet die Performance-Daten vor
     */
    private function get_performance_data() {
        $posts = $this->get_recent_posts(30); // Letzte 30 Tage
        $labels = array();
        $load_times = array();
        $seo_scores = array();
        
        foreach ($posts as $post) {
            $labels[] = get_the_date('d.m.', $post->ID);
            $load_times[] = get_post_meta($post->ID, '_alenseo_load_time', true);
            $seo_scores[] = get_post_meta($post->ID, '_alenseo_score', true);
        }
        
        return array(
            'labels' => $labels,
            'loadTimes' => $load_times,
            'seoScores' => $seo_scores
        );
    }
    
    /**
     * Bereitet die Keyword-Daten vor
     */
    private function get_keyword_data() {
        $keywords = $this->get_all_keywords();
        $distribution = array();
        
        foreach ($keywords as $keyword) {
            $count = $this->count_posts_with_keyword($keyword);
            $distribution[$keyword] = $count;
        }
        
        arsort($distribution);
        $distribution = array_slice($distribution, 0, 10); // Top 10 Keywords
        
        return array(
            'labels' => array_keys($distribution),
            'values' => array_values($distribution)
        );
    }
    
    /**
     * Bereitet die Heatmap-Daten vor
     */
    private function get_heatmap_data() {
        $posts = $this->get_recent_posts(7); // Letzte 7 Tage
        $points = array();
        
        foreach ($posts as $post) {
            $score = get_post_meta($post->ID, '_alenseo_score', true);
            $points[] = array(
                'x' => rand(0, 100),
                'y' => rand(0, 100),
                'value' => $score
            );
        }
        
        return array(
            'points' => $points
        );
    }
    
    /**
     * Bereitet die Trend-Daten vor
     */
    private function get_trend_data() {
        $posts = $this->get_recent_posts(30); // Letzte 30 Tage
        $dates = array();
        $scores = array();
        
        foreach ($posts as $post) {
            $dates[] = get_the_date('Y-m-d', $post->ID);
            $scores[] = get_post_meta($post->ID, '_alenseo_score', true);
        }
        
        return array(
            'dates' => $dates,
            'scores' => $scores
        );
    }
    
    /**
     * Zählt Beiträge nach Status
     */
    private function count_posts_by_status($status) {
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_alenseo_status',
                    'value' => $status
                )
            )
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Holt die neuesten Beiträge
     */
    private function get_recent_posts($days) {
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'after' => $days . ' days ago'
                )
            )
        );
        
        return get_posts($args);
    }
    
    /**
     * Holt alle verwendeten Keywords
     */
    private function get_all_keywords() {
        global $wpdb;
        
        $keywords = $wpdb->get_col(
            "SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_alenseo_keywords'"
        );
        
        $all_keywords = array();
        foreach ($keywords as $keyword_string) {
            $keyword_array = explode(',', $keyword_string);
            $all_keywords = array_merge($all_keywords, $keyword_array);
        }
        
        return array_unique($all_keywords);
    }
    
    /**
     * Zählt Beiträge mit einem bestimmten Keyword
     */
    private function count_posts_with_keyword($keyword) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_alenseo_keywords' 
            AND meta_value LIKE %s",
            '%' . $wpdb->esc_like($keyword) . '%'
        ));
    }
} 