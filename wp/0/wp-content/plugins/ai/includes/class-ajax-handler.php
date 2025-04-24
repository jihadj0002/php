<?php
/**
 * Handles all AJAX requests for AI Content Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Content_Suite_Ajax_Handler {

    /**
     * Initialize AJAX hooks
     */
    public function __construct() {
        // Content generation
        add_action('wp_ajax_ai_content_suite_generate_content', array($this, 'generate_content'));
        add_action('wp_ajax_ai_content_suite_generate_ideas', array($this, 'generate_ideas'));
        
        // Media generation
        add_action('wp_ajax_ai_content_suite_generate_image', array($this, 'generate_image'));
        add_action('wp_ajax_ai_content_suite_generate_video', array($this, 'generate_video'));
        add_action('wp_ajax_ai_content_suite_generate_voice', array($this, 'generate_voice'));
        
        // Content management
        add_action('wp_ajax_ai_content_suite_save_content', array($this, 'save_content'));
        add_action('wp_ajax_ai_content_suite_publish_content', array($this, 'publish_content'));
        
        // Settings
        add_action('wp_ajax_ai_content_suite_test_api', array($this, 'test_api_connection'));
        add_action('wp_ajax_ai_content_suite_save_settings', array($this, 'save_settings'));
        
        // Workflows
        add_action('wp_ajax_ai_content_suite_run_workflow', array($this, 'run_workflow'));
        add_action('wp_ajax_ai_content_suite_save_workflow', array($this, 'save_workflow'));
    }

    /**
     * Verify AJAX request
     */
    private function verify_request($action = 'ai_content_suite_nonce') {
        check_ajax_referer($action, 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action', 'ai-content-suite')
            ), 403);
        }
    }

    /**
     * Generate content from prompt
     */
    public function generate_content() {
        $this->verify_request();
        
        $prompt = isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '';
        $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : 'blog_post';
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        if (empty($prompt)) {
            wp_send_json_error(array(
                'message' => __('Please enter a prompt', 'ai-content-suite')
            ));
        }
        
        // Sanitize settings
        $settings = AI_Content_Suite_Helper::sanitize_settings($settings);
        
        // Create job record
        $job_id = AI_Content_Suite_Database::create_job(array(
            'content_type' => $content_type,
            'prompt' => $prompt,
            'settings' => $settings
        ));
        
        // Process via appropriate generator
        $result = $this->process_generation($job_id, $content_type, $prompt, $settings);
        
        if (is_wp_error($result)) {
            AI_Content_Suite_Database::update_job($job_id, array(
                'status' => 'failed',
                'error_message' => $result->get_error_message()
            ));
            
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'job_id' => $job_id
            ));
        }
        
        // Update job with results
        AI_Content_Suite_Database::update_job($job_id, array(
            'status' => 'completed',
            'generated_content' => $result['content'],
            'generated_content_id' => $result['content_id'] ?? 0
        ));
        
        wp_send_json_success(array(
            'message' => __('Content generated successfully', 'ai-content-suite'),
            'content' => $result['content'],
            'job_id' => $job_id,
            'meta' => $result['meta'] ?? array()
        ));
    }

    /**
     * Process generation based on content type
     */
    private function process_generation($job_id, $content_type, $prompt, $settings) {
        $ai_content_suite = AI_Content_Suite();
        
        switch ($content_type) {
            case 'blog_post':
                return $ai_content_suite->text_generator->generate_blog_post($prompt, $settings);
                
            case 'product_description':
                return $ai_content_suite->text_generator->generate_product_description($prompt, $settings);
                
            case 'social_media':
                return $ai_content_suite->text_generator->generate_social_post($prompt, $settings);
                
            default:
                // Allow extensions to handle custom content types
                $result = apply_filters('ai_content_suite_generate_' . $content_type, null, $prompt, $settings);
                
                if ($result !== null) {
                    return $result;
                }
                
                return new WP_Error('invalid_type', __('Invalid content type', 'ai-content-suite'));
        }
    }

    /**
     * Generate content ideas from topic
     */
    public function generate_ideas() {
        $this->verify_request();
        
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 5;
        
        if (empty($topic)) {
            wp_send_json_error(array(
                'message' => __('Please enter a topic', 'ai-content-suite')
            ));
        }
        
        $ideas = AI_Content_Suite()->text_generator->generate_ideas($topic, $count);
        
        if (is_wp_error($ideas)) {
            wp_send_json_error(array(
                'message' => $ideas->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'ideas' => $ideas,
            'message' => __('Ideas generated successfully', 'ai-content-suite')
        ));
    }

    /**
     * Generate AI image
     */
    public function generate_image() {
        $this->verify_request();
        
        $prompt = isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '';
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        if (empty($prompt)) {
            wp_send_json_error(array(
                'message' => __('Please enter an image description', 'ai-content-suite')
            ));
        }
        
        $result = AI_Content_Suite()->image_generator->generate_image($prompt, $settings);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'image_url' => $result['url'],
            'image_id' => $result['attachment_id'],
            'message' => __('Image generated successfully', 'ai-content-suite')
        ));
    }

    /**
     * Generate AI video
     */
    public function generate_video() {
        $this->verify_request();
        
        $script = isset($_POST['script']) ? wp_kses_post($_POST['script']) : '';
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        if (empty($script)) {
            wp_send_json_error(array(
                'message' => __('Please enter a video script', 'ai-content-suite')
            ));
        }
        
        $result = AI_Content_Suite()->video_generator->generate_video($script, $settings);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'video_url' => $result['url'],
            'video_id' => $result['attachment_id'],
            'message' => __('Video generated successfully', 'ai-content-suite')
        ));
    }

    /**
     * Generate AI voiceover
     */
    public function generate_voice() {
        $this->verify_request();
        
        $text = isset($_POST['text']) ? wp_kses_post($_POST['text']) : '';
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        if (empty($text)) {
            wp_send_json_error(array(
                'message' => __('Please enter text to convert to speech', 'ai-content-suite')
            ));
        }
        
        $result = AI_Content_Suite()->voice_generator->generate_voice($text, $settings);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'audio_url' => $result['url'],
            'audio_id' => $result['attachment_id'],
            'message' => __('Voiceover generated successfully', 'ai-content-suite')
        ));
    }

    /**
     * Save generated content as draft
     */
    public function save_content() {
        $this->verify_request();
        
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $meta = isset($_POST['meta']) ? $_POST['meta'] : array();
        
        if (empty($content)) {
            wp_send_json_error(array(
                'message' => __('No content to save', 'ai-content-suite')
            ));
        }
        
        $post_data = array(
            'post_title' => $title ?: __('AI Generated Content', 'ai-content-suite'),
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'post'
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error(array(
                'message' => $post_id->get_error_message()
            ));
        }
        
        // Save SEO meta if available
        if (!empty($meta['seo'])) {
            update_post_meta($post_id, '_ai_content_suite_seo', $meta['seo']);
            
            // Update WordPress SEO fields if plugins exist
            if (function_exists('YoastSEO')) {
                update_post_meta($post_id, '_yoast_wpseo_title', $meta['seo']['title']);
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta['seo']['description']);
                update_post_meta($post_id, '_yoast_wpseo_focuskw', implode(',', $meta['seo']['keywords']));
            }
        }
        
        // Save media attachments if available
        if (!empty($meta['media'])) {
            update_post_meta($post_id, '_ai_content_suite_media', $meta['media']);
            
            // Set featured image if available
            if (!empty($meta['media']['featured_image'])) {
                set_post_thumbnail($post_id, $meta['media']['featured_image']);
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Content saved as draft', 'ai-content-suite'),
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, '')
        ));
    }

    /**
     * Publish generated content
     */
    public function publish_content() {
        $this->verify_request();
        
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $meta = isset($_POST['meta']) ? $_POST['meta'] : array();
        
        if (empty($content)) {
            wp_send_json_error(array(
                'message' => __('No content to publish', 'ai-content-suite')
            ));
        }
        
        $post_data = array(
            'post_title' => $title ?: __('AI Generated Content', 'ai-content-suite'),
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'post'
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error(array(
                'message' => $post_id->get_error_message()
            ));
        }
        
        // Save SEO meta if available
        if (!empty($meta['seo'])) {
            update_post_meta($post_id, '_ai_content_suite_seo', $meta['seo']);
            
            // Update WordPress SEO fields if plugins exist
            if (function_exists('YoastSEO')) {
                update_post_meta($post_id, '_yoast_wpseo_title', $meta['seo']['title']);
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta['seo']['description']);
                update_post_meta($post_id, '_yoast_wpseo_focuskw', implode(',', $meta['seo']['keywords']));
            }
        }
        
        // Save media attachments if available
        if (!empty($meta['media'])) {
            update_post_meta($post_id, '_ai_content_suite_media', $meta['media']);
            
            // Set featured image if available
            if (!empty($meta['media']['featured_image'])) {
                set_post_thumbnail($post_id, $meta['media']['featured_image']);
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Content published successfully', 'ai-content-suite'),
            'post_id' => $post_id,
            'view_url' => get_permalink($post_id)
        ));
    }

    /**
     * Test API connection
     */
    public function test_api_connection() {
        $this->verify_request('ai_content_suite_settings_nonce');
        
        $api_name = isset($_POST['api_name']) ? sanitize_text_field($_POST['api_name']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_name) || empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('API name and key are required', 'ai-content-suite')
            ));
        }
        
        $result = AI_Content_Suite()->api_manager->test_connection($api_name, $api_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('API connection successful', 'ai-content-suite'),
            'details' => $result
        ));
    }

    /**
     * Save plugin settings
     */
    public function save_settings() {
        $this->verify_request('ai_content_suite_settings_nonce');
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        if (empty($settings)) {
            wp_send_json_error(array(
                'message' => __('No settings to save', 'ai-content-suite')
            ));
        }
        
        // Sanitize and save API keys
        if (!empty($settings['api_keys'])) {
            foreach ($settings['api_keys'] as $api_name => $api_key) {
                update_option('ai_content_suite_api_' . sanitize_key($api_name), sanitize_text_field($api_key));
            }
        }
        
        // Save general settings
        if (!empty($settings['general'])) {
            update_option('ai_content_suite_general_settings', array_map('sanitize_text_field', $settings['general']));
        }
        
        // Save content defaults
        if (!empty($settings['defaults'])) {
            update_option('ai_content_suite_default_settings', AI_Content_Suite_Helper::sanitize_settings($settings['defaults']));
        }
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully', 'ai-content-suite')
        ));
    }

    /**
     * Run content workflow
     */
    public function run_workflow() {
        $this->verify_request();
        
        $workflow_id = isset($_POST['workflow_id']) ? absint($_POST['workflow_id']) : 0;
        $input_data = isset($_POST['input_data']) ? $_POST['input_data'] : array();
        
        if (!$workflow_id) {
            wp_send_json_error(array(
                'message' => __('Invalid workflow', 'ai-content-suite')
            ));
        }
        
        $result = AI_Content_Suite()->workflow_engine->run_workflow($workflow_id, $input_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Workflow completed successfully', 'ai-content-suite'),
            'results' => $result
        ));
    }

    /**
     * Save content workflow
     */
    public function save_workflow() {
        $this->verify_request();
        
        $workflow_data = isset($_POST['workflow_data']) ? $_POST['workflow_data'] : array();
        
        if (empty($workflow_data)) {
            wp_send_json_error(array(
                'message' => __('No workflow data to save', 'ai-content-suite')
            ));
        }
        
        $result = AI_Content_Suite()->workflow_engine->save_workflow($workflow_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Workflow saved successfully', 'ai-content-suite'),
            'workflow_id' => $result
        ));
    }
}

new AI_Content_Suite_Ajax_Handler();




// Key Features of This AJAX Handler:
// Comprehensive Content Generation:

// Handles text, images, videos, and voice generation

// Supports multiple content types (blog posts, product descriptions, etc.)

// Manages generation jobs with status tracking

// Content Management:

// Save as draft or publish directly

// Automatic SEO meta generation

// Media attachment handling

// API & Settings Management:

// API connection testing

// Secure settings storage

// Configuration for default generation parameters

// Workflow Automation:

// Run predefined content workflows

// Save custom workflow configurations

// Security & Validation:

// Nonce verification for all requests

// Capability checks (edit_posts required)

// Input sanitization and validation

// Proper error handling

// Integration Ready:

// Works with popular SEO plugins (Yoast)

// Standard WordPress post type handling

// Media library integration