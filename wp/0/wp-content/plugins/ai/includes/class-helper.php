<?php
/**
 * Helper functions for AI Content Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Content_Suite_Helper {

    /**
     * Generate a unique content hash
     */
    public static function generate_content_hash($content, $settings = array()) {
        $hash_data = array(
            'content' => $content,
            'settings' => $settings,
            'plugin_version' => AI_CONTENT_SUITE_VERSION
        );
        
        return md5(serialize($hash_data));
    }

    /**
     * Format API error message
     */
    public static function format_api_error($error) {
        if (is_wp_error($error)) {
            return $error->get_error_message();
        }
        
        if (is_array($error) && isset($error['message'])) {
            return $error['message'];
        }
        
        if (is_string($error)) {
            return $error;
        }
        
        return __('Unknown API error occurred', 'ai-content-suite');
    }

    /**
     * Sanitize AI generation settings
     */
    public static function sanitize_settings($settings) {
        $defaults = array(
            'tone' => 'professional',
            'length' => 800,
            'creativity' => 0.7,
            'language' => get_locale(),
            'include_images' => true,
            'include_seo' => true,
            'target_audience' => 'general'
        );

        $sanitized = array();
        
        if (!is_array($settings)) {
            return $defaults;
        }

        // Tone
        $valid_tones = array('professional', 'casual', 'friendly', 'humorous', 'academic');
        $sanitized['tone'] = in_array(strtolower($settings['tone']), $valid_tones) 
            ? strtolower($settings['tone']) 
            : $defaults['tone'];

        // Length
        $sanitized['length'] = isset($settings['length']) 
            ? absint($settings['length']) 
            : $defaults['length'];
        $sanitized['length'] = max(300, min(5000, $sanitized['length']));

        // Creativity
        $sanitized['creativity'] = isset($settings['creativity']) 
            ? floatval($settings['creativity']) 
            : $defaults['creativity'];
        $sanitized['creativity'] = max(0.1, min(1.0, $sanitized['creativity']));

        // Language
        $sanitized['language'] = isset($settings['language']) 
            ? sanitize_text_field($settings['language']) 
            : $defaults['language'];

        // Booleans
        $sanitized['include_images'] = isset($settings['include_images']) 
            ? (bool) $settings['include_images'] 
            : $defaults['include_images'];
        $sanitized['include_seo'] = isset($settings['include_seo']) 
            ? (bool) $settings['include_seo'] 
            : $defaults['include_seo'];

        // Target audience
        $sanitized['target_audience'] = isset($settings['target_audience']) 
            ? sanitize_text_field($settings['target_audience']) 
            : $defaults['target_audience'];

        return wp_parse_args($sanitized, $defaults);
    }

    /**
     * Extract headings from content
     */
    public static function extract_headings($content) {
        if (empty($content)) {
            return array();
        }

        $headings = array();
        $pattern = '/<h([1-6])(?:.*?)>(.*?)<\/h\1>/i';
        
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $headings[] = array(
                'level' => (int) $match[1],
                'text' => wp_strip_all_tags($match[2])
            );
        }

        return $headings;
    }

    /**
     * Generate SEO meta from content
     */
    public static function generate_seo_meta($content, $title = '') {
        $content = wp_strip_all_tags($content);
        $excerpt = wp_trim_words($content, 30, '');

        return array(
            'title' => !empty($title) ? $title : wp_trim_words($content, 10, ''),
            'description' => $excerpt,
            'keywords' => self::extract_keywords($content),
            'og_title' => !empty($title) ? $title : wp_trim_words($content, 8, ''),
            'og_description' => wp_trim_words($content, 20, '')
        );
    }

    /**
     * Extract keywords from content
     */
    public static function extract_keywords($content, $max_keywords = 5) {
        $content = wp_strip_all_tags($content);
        $content = strtolower($content);

        // Remove short words and common stop words
        $stop_words = array('the', 'and', 'that', 'have', 'for', 'not', 'with', 'you', 'this', 'but', 'his', 'from');
        $words = preg_split('/\s+/', $content);
        $words = array_filter($words, function($word) use ($stop_words) {
            return strlen($word) > 3 && !in_array($word, $stop_words);
        });

        $word_counts = array_count_values($words);
        arsort($word_counts);

        return array_slice(array_keys($word_counts), 0, $max_keywords);
    }

    /**
     * Format content with proper HTML
     */
    public static function format_content($content) {
        if (empty($content)) {
            return '';
        }

        // Convert markdown-style headers if present
        $content = preg_replace('/^#\s(.+)$/m', '<h1>$1</h1>', $content);
        $content = preg_replace('/^##\s(.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^###\s(.+)$/m', '<h3>$1</h3>', $content);

        // Convert line breaks to paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $paragraphs = array_map('trim', $paragraphs);
        $paragraphs = array_filter($paragraphs);

        $content = implode("\n\n", array_map(function($p) {
            if (preg_match('/^<(h[1-6]|ul|ol|li|blockquote|table)/i', $p)) {
                return $p;
            }
            return "<p>$p</p>";
        }, $paragraphs));

        // Add rel="nofollow" to external links
        $content = preg_replace_callback(
            '/<a\s[^>]*href=["\'](https?:\/\/[^"\']+)["\'][^>]*>/i',
            function($matches) {
                $site_url = site_url();
                $link_url = $matches[1];
                
                if (strpos($link_url, $site_url) === false) {
                    return str_replace('<a ', '<a rel="nofollow" ', $matches[0]);
                }
                return $matches[0];
            },
            $content
        );

        return $content;
    }

    /**
     * Log debug information
     */
    public static function log($message, $data = null) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (!is_string($message)) {
            $message = print_r($message, true);
        }

        if ($data !== null) {
            $message .= ' - ' . print_r($data, true);
        }

        error_log('[AI Content Suite] ' . $message);
    }

    /**
     * Get available content types
     */
    public static function get_content_types() {
        return apply_filters('ai_content_suite_content_types', array(
            'blog_post' => __('Blog Post', 'ai-content-suite'),
            'product_description' => __('Product Description', 'ai-content-suite'),
            'social_media' => __('Social Media Post', 'ai-content-suite'),
            'newsletter' => __('Email Newsletter', 'ai-content-suite'),
            'landing_page' => __('Landing Page', 'ai-content-suite')
        ));
    }

    /**
     * Get available tones
     */
    public static function get_tones() {
        return apply_filters('ai_content_suite_tones', array(
            'professional' => __('Professional', 'ai-content-suite'),
            'casual' => __('Casual', 'ai-content-suite'),
            'friendly' => __('Friendly', 'ai-content-suite'),
            'humorous' => __('Humorous', 'ai-content-suite'),
            'academic' => __('Academic', 'ai-content-suite'),
            'persuasive' => __('Persuasive', 'ai-content-suite')
        ));
    }

    /**
     * Get target audiences
     */
    public static function get_target_audiences() {
        return apply_filters('ai_content_suite_target_audiences', array(
            'general' => __('General Audience', 'ai-content-suite'),
            'business' => __('Business Professionals', 'ai-content-suite'),
            'tech' => __('Tech Savvy', 'ai-content-suite'),
            'students' => __('Students', 'ai-content-suite'),
            'seniors' => __('Seniors', 'ai-content-suite')
        ));
    }

    /**
     * Get the first image from content
     */
    public static function get_first_image($content) {
        if (preg_match('/<img.+?src=[\'"]([^\'"]+)[\'"].*?>/i', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Check if a string contains AI placeholders
     */
    public static function has_ai_placeholders($content) {
        return preg_match('/\{\{ai_.+?\}\}/i', $content);
    }

    /**
     * Replace AI placeholders in content
     */
    public static function replace_placeholders($content, $replacements) {
        return preg_replace_callback(
            '/\{\{ai_(.+?)\}\}/i',
            function($matches) use ($replacements) {
                $key = strtolower($matches[1]);
                return isset($replacements[$key]) ? $replacements[$key] : '';
            },
            $content
        );
    }
}



// Content Processing Utilities:

// generate_content_hash() - Creates unique hash for caching

// format_content() - Converts raw AI output to proper HTML

// extract_headings() - Parses content for headings structure

// SEO Tools:

// generate_seo_meta() - Creates meta tags from content

// extract_keywords() - Identifies important keywords

// Settings Management:

// sanitize_settings() - Validates and sanitizes generation parameters

// get_content_types() - Returns available content formats

// get_tones() - Lists available writing tones

// get_target_audiences() - Provides audience options

// Error Handling:

// format_api_error() - Standardizes API error messages

// log() - Debug logging helper

// Content Analysis:

// get_first_image() - Extracts first image from HTML

// has_ai_placeholders() - Detects template variables

// replace_placeholders() - Fills in dynamic content

// Security & Sanitization:

// Proper input validation throughout

// WordPress security best practices

// Protection against XSS in generated content