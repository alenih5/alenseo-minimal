<?php
namespace Alenseo;

/**
 * Schema.org Generator für Alenseo SEO
 *
 * Diese Klasse generiert strukturierte Daten nach Schema.org
 * 
 * @link       https://www.imponi.ch
 * @since      2.0.4
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Alenseo_Schema_Generator {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Schema.org JSON-LD zum Header hinzufügen
        add_action('wp_head', array($this, 'output_schema'));
        
        // Schema.org-Daten beim Speichern aktualisieren
        add_action('save_post', array($this, 'update_schema'), 10, 2);
    }
    
    /**
     * Schema.org JSON-LD ausgeben
     */
    public function output_schema() {
        if (!is_singular()) {
            return;
        }
        
        $post_id = get_the_ID();
        $schema = get_post_meta($post_id, '_alenseo_schema', true);
        
        if (!empty($schema)) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
        }
    }
    
    /**
     * Schema.org-Daten aktualisieren
     * 
     * @param int $post_id Die Post-ID
     * @param WP_Post $post Das Post-Objekt
     */
    public function update_schema($post_id, $post) {
        // AutoSave überspringen
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Schema.org-Daten generieren
        $schema = $this->generate_schema($post);
        
        // Schema.org-Daten speichern
        update_post_meta($post_id, '_alenseo_schema', $schema);
    }
    
    /**
     * Schema.org-Daten generieren
     * 
     * @param WP_Post $post Das Post-Objekt
     * @return array Die Schema.org-Daten
     */
    private function generate_schema($post) {
        // Basis-Schema für Artikel
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author)
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => $this->get_site_logo()
                )
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink($post)
            )
        );
        
        // Featured Image hinzufügen
        if (has_post_thumbnail($post)) {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id($post), 'full');
            if ($image) {
                $schema['image'] = array(
                    '@type' => 'ImageObject',
                    'url' => $image[0],
                    'width' => $image[1],
                    'height' => $image[2]
                );
            }
        }
        
        // Meta Description hinzufügen
        $description = get_post_meta($post->ID, '_alenseo_meta_description', true);
        if (!empty($description)) {
            $schema['description'] = $description;
        }
        
        // Schema.org-Typ basierend auf Post-Typ anpassen
        $schema = $this->adjust_schema_type($schema, $post);
        
        // Zusätzliche Schema.org-Daten basierend auf Inhalt
        $schema = $this->add_content_specific_schema($schema, $post);
        
        return $schema;
    }
    
    /**
     * Schema.org-Typ anpassen
     * 
     * @param array $schema Die Schema.org-Daten
     * @param WP_Post $post Das Post-Objekt
     * @return array Die angepassten Schema.org-Daten
     */
    private function adjust_schema_type($schema, $post) {
        switch ($post->post_type) {
            case 'product':
                $schema['@type'] = 'Product';
                // Produkt-spezifische Daten hinzufügen
                $price = get_post_meta($post->ID, '_price', true);
                if ($price) {
                    $schema['offers'] = array(
                        '@type' => 'Offer',
                        'price' => $price,
                        'priceCurrency' => get_woocommerce_currency(),
                        'availability' => 'https://schema.org/InStock'
                    );
                }
                break;
                
            case 'recipe':
                $schema['@type'] = 'Recipe';
                // Rezept-spezifische Daten hinzufügen
                $cook_time = get_post_meta($post->ID, '_recipe_cook_time', true);
                if ($cook_time) {
                    $schema['cookTime'] = $cook_time;
                }
                break;
                
            case 'event':
                $schema['@type'] = 'Event';
                // Event-spezifische Daten hinzufügen
                $start_date = get_post_meta($post->ID, '_event_start_date', true);
                if ($start_date) {
                    $schema['startDate'] = $start_date;
                }
                break;
        }
        
        return $schema;
    }
    
    /**
     * Inhaltsspezifische Schema.org-Daten hinzufügen
     * 
     * @param array $schema Die Schema.org-Daten
     * @param WP_Post $post Das Post-Objekt
     * @return array Die erweiterten Schema.org-Daten
     */
    private function add_content_specific_schema($schema, $post) {
        $content = $post->post_content;
        
        // FAQ-Schema prüfen
        if (preg_match_all('/<h[2-6][^>]*>(.*?)<\/h[2-6]>/i', $content, $headings)) {
            $faq_items = array();
            foreach ($headings[1] as $heading) {
                // Versuchen, die Antwort nach der Überschrift zu finden
                $answer = $this->extract_answer_after_heading($content, $heading);
                if ($answer) {
                    $faq_items[] = array(
                        '@type' => 'Question',
                        'name' => strip_tags($heading),
                        'acceptedAnswer' => array(
                            '@type' => 'Answer',
                            'text' => strip_tags($answer)
                        )
                    );
                }
            }
            
            if (!empty($faq_items)) {
                $schema['mainEntity'] = array(
                    '@type' => 'FAQPage',
                    'mainEntity' => $faq_items
                );
            }
        }
        
        // HowTo-Schema prüfen
        if (preg_match('/<ol[^>]*>(.*?)<\/ol>/is', $content, $matches)) {
            $steps = array();
            preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $matches[1], $list_items);
            
            foreach ($list_items[1] as $index => $step) {
                $steps[] = array(
                    '@type' => 'HowToStep',
                    'position' => $index + 1,
                    'name' => strip_tags($step),
                    'text' => strip_tags($step)
                );
            }
            
            if (!empty($steps)) {
                $schema['mainEntity'] = array(
                    '@type' => 'HowTo',
                    'name' => get_the_title($post),
                    'step' => $steps
                );
            }
        }
        
        return $schema;
    }
    
    /**
     * Antwort nach einer Überschrift extrahieren
     * 
     * @param string $content Der Inhalt
     * @param string $heading Die Überschrift
     * @return string|false Die Antwort oder false
     */
    private function extract_answer_after_heading($content, $heading) {
        $pattern = '/<h[2-6][^>]*>' . preg_quote($heading, '/') . '<\/h[2-6]>(.*?)(?=<h[2-6]|$)/is';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }
        return false;
    }
    
    /**
     * Site-Logo URL abrufen
     * 
     * @return string Die Logo-URL
     */
    private function get_site_logo() {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
            if ($logo) {
                return $logo[0];
            }
        }
        
        // Fallback auf Site-Icon
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            $icon = wp_get_attachment_image_src($site_icon_id, 'full');
            if ($icon) {
                return $icon[0];
            }
        }
        
        // Fallback auf Site-Name
        return get_site_url();
    }
} 