<?php

class Alenseo_Ajax {
    public function init_ajax_handlers() {
        add_action('wp_ajax_alenseo_filter_content', array($this, 'handle_filter_content'));
        add_action('wp_ajax_alenseo_check_updates', array($this, 'handle_check_updates'));
    }

    public function handle_filter_content() {
        check_ajax_referer('alenseo_nonce', 'nonce');
        
        $filters = $_POST['filters'];
        $query_args = array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'meta_query' => array()
        );
        
        // Status-Filter
        if (!empty($filters['status'])) {
            $query_args['meta_query'][] = array(
                'key' => '_alenseo_status',
                'value' => $filters['status']
            );
        }
        
        // Score-Filter
        if (!empty($filters['score'])) {
            $score_range = explode('-', $filters['score']);
            $query_args['meta_query'][] = array(
                'key' => '_alenseo_score',
                'value' => $score_range,
                'type' => 'NUMERIC',
                'compare' => 'BETWEEN'
            );
        }
        
        // Datum-Filter
        if (!empty($filters['date'])) {
            $date_range = $this->get_date_range($filters['date']);
            $query_args['date_query'] = array(
                array(
                    'after' => $date_range['start'],
                    'before' => $date_range['end'],
                    'inclusive' => true
                )
            );
        }
        
        $query = new WP_Query($query_args);
        $posts = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $posts[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'status' => get_post_meta(get_the_ID(), '_alenseo_status', true),
                    'score' => get_post_meta(get_the_ID(), '_alenseo_score', true),
                    'date' => get_the_date('Y-m-d')
                );
            }
        }
        
        wp_reset_postdata();
        
        wp_send_json_success(array(
            'posts' => $posts
        ));
    }

    public function handle_check_updates() {
        check_ajax_referer('alenseo_nonce', 'nonce');
        
        $last_check = get_option('alenseo_last_update_check', 0);
        $current_time = time();
        
        // Nur Updates der letzten 30 Sekunden abrufen
        $query_args = array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_alenseo_last_update',
                    'value' => $last_check,
                    'compare' => '>',
                    'type' => 'NUMERIC'
                )
            )
        );
        
        $query = new WP_Query($query_args);
        $updates = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $updates[] = array(
                    'type' => 'score_update',
                    'postId' => $post_id,
                    'score' => get_post_meta($post_id, '_alenseo_score', true)
                );
                
                $updates[] = array(
                    'type' => 'keyword_update',
                    'postId' => $post_id,
                    'keywords' => get_post_meta($post_id, '_alenseo_keywords', true)
                );
                
                $updates[] = array(
                    'type' => 'status_update',
                    'postId' => $post_id,
                    'status' => get_post_meta($post_id, '_alenseo_status', true)
                );
            }
        }
        
        wp_reset_postdata();
        
        // Update-Zeitstempel aktualisieren
        update_option('alenseo_last_update_check', $current_time);
        
        wp_send_json_success(array(
            'updates' => $updates
        ));
    }

    private function get_date_range($range) {
        $end = current_time('Y-m-d');
        
 