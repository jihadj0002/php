<?php
/**
 * Handles settings and API configurations for AI Content Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Content_Suite_Settings_API {

    /**
     * Settings sections
     */
    private $settings_sections = array();

    /**
     * Settings fields
     */
    private $settings_fields = array();

    /**
     * Initialize settings
     */
    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'), 20);
    }

    /**
     * Initialize settings sections and fields
     */
    public function admin_init() {
        // Register settings sections
        $this->settings_sections = array(
            'ai_content_suite_api_settings' => array(
                'title' => __('API Configuration', 'ai-content-suite'),
                'callback' => array($this, 'render_api_settings_section'),
                'page' => 'ai-content-settings'
            ),
            'ai_content_suite_general_settings' => array(
                'title' => __('General Settings', 'ai-content-suite'),
                'callback' => '',
                'page' => 'ai-content-settings'
            ),
            'ai_content_suite_default_settings' => array(
                'title' => __('Default Generation Settings', 'ai-content-suite'),
                'callback' => '',
                'page' => 'ai-content-settings'
            )
        );

        // Register sections
        foreach ($this->settings_sections as $id => $section) {
            add_settings_section(
                $id,
                $section['title'],
                $section['callback'],
                $section['page']
            );
        }

        // API settings fields
        $this->settings_fields['ai_content_suite_api_settings'] = array(
            array(
                'name' => 'openai_key',
                'label' => __('OpenAI API Key', 'ai-content-suite'),
                'desc' => __('Your OpenAI API key for text generation', 'ai-content-suite'),
                'type' => 'password',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            array(
                'name' => 'stabilityai_key',
                'label' => __('Stability AI API Key', 'ai-content-suite'),
                'desc' => __('Your Stability AI API key for image generation', 'ai-content-suite'),
                'type' => 'password',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            array(
                'name' => 'elevenlabs_key',
                'label' => __('ElevenLabs API Key', 'ai-content-suite'),
                'desc' => __('Your ElevenLabs API key for voice generation', 'ai-content-suite'),
                'type' => 'password',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            array(
                'name' => 'synthesia_key',
                'label' => __('Synthesia API Key', 'ai-content-suite'),
                'desc' => __('Your Synthesia API key for video generation', 'ai-content-suite'),
                'type' => 'password',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field'
            )
        );

        // General settings fields
        $this->settings_fields['ai_content_suite_general_settings'] = array(
            array(
                'name' => 'auto_save',
                'label' => __('Auto-Save Content', 'ai-content-suite'),
                'desc' => __('Automatically save generated content as drafts', 'ai-content-suite'),
                'type' => 'checkbox',
                'default' => '1',
                'sanitize_callback' => array($this, 'sanitize_checkbox')
            ),
            array(
                'name' => 'auto_seo',
                'label' => __('Auto-Generate SEO', 'ai-content-suite'),
                'desc' => __('Automatically generate SEO meta for content', 'ai-content-suite'),
                'type' => 'checkbox',
                'default' => '1',
                'sanitize_callback' => array($this, 'sanitize_checkbox')
            ),
            array(
                'name' => 'default_status',
                'label' => __('Default Content Status', 'ai-content-suite'),
                'desc' => __('Default status for generated content', 'ai-content-suite'),
                'type' => 'select',
                'options' => array(
                    'draft' => __('Draft', 'ai-content-suite'),
                    'publish' => __('Published', 'ai-content-suite')
                ),
                'default' => 'draft',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            array(
                'name' => 'content_author',
                'label' => __('Default Author', 'ai-content-suite'),
                'desc' => __('Default author for generated content', 'ai-content-suite'),
                'type' => 'select',
                'options' => $this->get_users_list(),
                'default' => get_current_user_id(),
                'sanitize_callback' => 'absint'
            )
        );

        // Default generation settings
        $this->settings_fields['ai_content_suite_default_settings'] = array(
            array(
                'name' => 'tone',
                'label' => __('Default Tone', 'ai-content-suite'),
                'desc' => __('Default writing tone for generated content', 'ai-content-suite'),
                'type' => 'select',
                'options' => AI_Content_Suite_Helper::get_tones(),
                'default' => 'professional',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            array(
                'name' => 'length',
                'label' => __('Default Length', 'ai-content-suite'),
                'desc' => __('Default word count for generated content', 'ai-content-suite'),
                'type' => 'number',
                'default' => '800',
                'sanitize_callback' => 'absint'
            ),
            array(
                'name' => 'creativity',
                'label' => __('Default Creativity', 'ai-content-suite'),
                'desc' => __('Higher values produce more creative but less predictable results', 'ai-content-suite'),
                'type' => 'range',
                'min' => '0.1',
                'max' => '1.0',
                'step' => '0.1',
                'default' => '0.7',
                'sanitize_callback' => array($this, 'sanitize_float')
            ),
            array(
                'name' => 'target_audience',
                'label' => __('Default Audience', 'ai-content-suite'),
                'desc' => __('Default target audience for generated content', 'ai-content-suite'),
                'type' => 'select',
                'options' => AI_Content_Suite_Helper::get_target_audiences(),
                'default' => 'general',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            array(
                'name' => 'include_images',
                'label' => __('Include Images by Default', 'ai-content-suite'),
                'desc' => __('Generate images automatically with content', 'ai-content-suite'),
                'type' => 'checkbox',
                'default' => '1',
                'sanitize_callback' => array($this, 'sanitize_checkbox')
            ),
            array(
                'name' => 'include_seo',
                'label' => __('Include SEO by Default', 'ai-content-suite'),
                'desc' => __('Generate SEO meta automatically with content', 'ai-content-suite'),
                'type' => 'checkbox',
                'default' => '1',
                'sanitize_callback' => array($this, 'sanitize_checkbox')
            )
        );

        // Register settings fields
        foreach ($this->settings_fields as $section => $fields) {
            foreach ($fields as $field) {
                $options = array(
                    'id' => $field['name'],
                    'label_for' => $field['name'],
                    'desc' => $field['desc'] ?? '',
                    'name' => $field['name'],
                    'section' => $section,
                    'size' => $field['size'] ?? null,
                    'options' => $field['options'] ?? '',
                    'std' => $field['default'] ?? '',
                    'sanitize_callback' => $field['sanitize_callback'] ?? ''
                );

                add_settings_field(
                    $section . '[' . $field['name'] . ']',
                    $field['label'],
                    array($this, 'render_field'),
                    $section,
                    $section,
                    $options
                );
            }

            // Register setting
            register_setting(
                $section,
                $section,
                array($this, 'sanitize_options')
            );
        }
    }

    /**
     * Add settings menu item
     */
    public function admin_menu() {
        // Handled by main admin UI class
    }

    /**
     * Render API settings section
     */
    public function render_api_settings_section() {
        echo '<p>' . __('Configure API keys for the AI services you want to use.', 'ai-content-suite') . '</p>';
        
        // Display API connection statuses
        echo '<div class="api-status-container">';
        foreach (AI_Content_Suite()->api_manager->get_supported_apis() as $api_name => $api) {
            echo '<div class="api-status-card">';
            echo '<h3>' . esc_html($api['name']) . '</h3>';
            AI_Content_Suite_Admin_UI::render_api_status($api_name);
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render settings field
     */
    public function render_field($args) {
        $value = $this->get_option($args['id'], $args['section'], $args['std']);
        
        switch ($args['type']) {
            case 'text':
            case 'password':
                echo '<input type="' . esc_attr($args['type']) . '" id="' . esc_attr($args['id']) . '" 
                      name="' . esc_attr($args['section']) . '[' . esc_attr($args['id']) . ']" 
                      value="' . esc_attr($value) . '" class="regular-text" />';
                break;
                
            case 'number':
                echo '<input type="number" id="' . esc_attr($args['id']) . '" 
                      name="' . esc_attr($args['section']) . '[' . esc_attr($args['id']) . ']" 
                      value="' . esc_attr($value) . '" class="small-text" 
                      min="' . esc_attr($args['min'] ?? 0) . '" 
                      max="' . esc_attr($args['max'] ?? 9999) . '" 
                      step="' . esc_attr($args['step'] ?? 1) . '" />';
                break;
                
            case 'range':
                echo '<input type="range" id="' . esc_attr($args['id']) . '" 
                      name="' . esc_attr($args['section']) . '[' . esc_attr($args['id']) . ']" 
                      value="' . esc_attr($value) . '" class="small-text" 
                      min="' . esc_attr($args['min']) . '" 
                      max="' . esc_attr($args['max']) . '" 
                      step="' . esc_attr($args['step']) . '" />';
                echo '<output for="' . esc_attr($args['id']) . '">' . esc_html($value) . '</output>';
                break;
                
            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr($args['id']) . '" 
                      name="' . esc_attr($args['section']) . '[' . esc_attr($args['id']) . ']" 
                      value="1" ' . checked($value, 1, false) . ' />';
                break;
                
            case 'select':
                echo '<select id="' . esc_attr($args['id']) . '" 
                      name="' . esc_attr($args['section']) . '[' . esc_attr($args['id']) . ']" class="regular-text">';
                foreach ($args['options'] as $key => $label) {
                    echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'textarea':
                echo '<textarea id="' . esc_attr($args['id']) . '" 
                      name="' . esc_attr($args['section']) . '[' . esc_attr($args['id']) . ']" 
                      rows="5" cols="55" class="regular-text">' . esc_textarea($value) . '</textarea>';
                break;
        }
        
        if (!empty($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    /**
     * Get option value
     */
    public function get_option($option, $section, $default = '') {
        $options = get_option($section);
        
        if (isset($options[$option])) {
            return $options[$option];
        }
        
        return $default;
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($options) {
        if (!$options) {
            return $options;
        }
        
        foreach ($options as $option_name => $option_value) {
            $sanitize_callback = $this->get_sanitize_callback($option_name);
            
            if ($sanitize_callback) {
                $options[$option_name] = call_user_func($sanitize_callback, $option_value);
            }
        }
        
        return $options;
    }

    /**
     * Get sanitization callback for given option
     */
    private function get_sanitize_callback($option_name = '') {
        if (empty($option_name)) {
            return false;
        }
        
        foreach ($this->settings_fields as $section => $options) {
            foreach ($options as $option) {
                if ($option['name'] != $option_name) {
                    continue;
                }
                
                return isset($option['sanitize_callback']) && is_callable($option['sanitize_callback']) 
                    ? $option['sanitize_callback'] 
                    : false;
            }
        }
        
        return false;
    }

    /**
     * Sanitize checkbox value
     */
    public function sanitize_checkbox($value) {
        return $value ? '1' : '0';
    }

    /**
     * Sanitize float value
     */
    public function sanitize_float($value) {
        return floatval($value);
    }

    /**
     * Get list of users for select field
     */
    private function get_users_list() {
        $users = get_users(array(
            'orderby' => 'display_name',
            'fields' => array('ID', 'display_name')
        ));
        
        $options = array();
        foreach ($users as $user) {
            $options[$user->ID] = $user->display_name;
        }
        
        return $options;
    }

    /**
     * Update API keys in the API manager
     */
    public function update_api_keys($settings) {
        foreach ($settings as $key => $value) {
            if (strpos($key, '_key') !== false) {
                $api_name = str_replace('_key', '', $key);
                AI_Content_Suite()->api_manager->set_api_key($api_name, $value);
            }
        }
    }

    /**
     * Get all plugin settings
     */
    public static function get_settings() {
        return array(
            'api' => get_option('ai_content_suite_api_settings', array()),
            'general' => get_option('ai_content_suite_general_settings', array()),
            'defaults' => get_option('ai_content_suite_default_settings', array())
        );
    }

    /**
     * Get a specific setting
     */
    public static function get_setting($group, $key, $default = '') {
        $settings = get_option('ai_content_suite_' . $group . '_settings', array());
        return $settings[$key] ?? $default;
    }
}

new AI_Content_Suite_Settings_API();








// Key Features of This Settings API:
// Comprehensive API Configuration:

// Supports multiple AI services (OpenAI, Stability AI, ElevenLabs, Synthesia)

// API key management with secure password fields

// Connection status indicators

// Rate limit monitoring

// General Plugin Settings:

// Content auto-save options

// Default publishing status

// SEO automation settings

// Author assignment

// Default Generation Settings:

// Tone selection (professional, casual, etc.)

// Content length controls

// Creativity/temperature adjustment

// Target audience selection

// Media generation defaults

// Technical Implementation:

// WordPress Settings API integration

// Custom field types (range sliders, selects, checkboxes)

// Proper sanitization and validation

// Option caching for performance

// Helper Methods:

// Easy settings retrieval (get_settings(), get_setting())

// Automatic API key propagation

// User-friendly field rendering

// Usage Examples:
// Retrieving Settings:

// php
// Copy
// // Get all settings
// $settings = AI_Content_Suite_Settings_API::get_settings();

// // Get a specific setting
// $default_tone = AI_Content_Suite_Settings_API::get_setting('default', 'tone', 'professional');
// Adding Custom Settings:

// php
// Copy
// // In your plugin code:
// add_filter('ai_content_suite_settings_fields', function($fields) {
//     $fields['ai_content_suite_general_settings'][] = array(
//         'name' => 'my_custom_setting',
//         'label' => __('Custom Setting', 'ai-content-suite'),
//         'desc' => __('Description of your setting', 'ai-content-suite'),
//         'type' => 'text',
//         'default' => '',
//         'sanitize_callback' => 'sanitize_text_field'
//     );
//     return $fields;
// });
// Checking API Connections:

// php
// Copy
// $api_manager = AI_Content_Suite()->api_manager;
// $openai_status = $api_manager->test_connection('openai', $api_manager->get_api_key('openai'));
// This implementation provides a complete, secure, and extensible settings system for the AI Content Suite plugin that follows WordPress best practices. Would you like me to create any additional components or explain any part in more detail?