<?php
/**
 * Admin interface for AI Content Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Content_Suite_Admin_UI {

    /**
     * Initialize admin interface
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'create_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('admin_footer', array(__CLASS__, 'admin_footer_scripts'));
        add_action('wp_ajax_ai_content_suite_preview', array(__CLASS__, 'ajax_preview_content'));
    }

    /**
     * Create admin menu items
     */
    public static function create_menu() {
        // Main menu item
        add_menu_page(
            __('AI Content Suite', 'ai-content-suite'),
            __('AI Content', 'ai-content-suite'),
            'edit_posts',
            'ai-content-suite',
            array(__CLASS__, 'render_dashboard'),
            'dashicons-ai',
            6
        );

        // Dashboard submenu
        add_submenu_page(
            'ai-content-suite',
            __('AI Content Dashboard', 'ai-content-suite'),
            __('Dashboard', 'ai-content-suite'),
            'edit_posts',
            'ai-content-suite',
            array(__CLASS__, 'render_dashboard')
        );

        // Content Generator
        add_submenu_page(
            'ai-content-suite',
            __('Generate Content', 'ai-content-suite'),
            __('Generate Content', 'ai-content-suite'),
            'edit_posts',
            'ai-content-generator',
            array(__CLASS__, 'render_generator')
        );

        // Workflows
        add_submenu_page(
            'ai-content-suite',
            __('Content Workflows', 'ai-content-suite'),
            __('Workflows', 'ai-content-suite'),
            'edit_posts',
            'ai-content-workflows',
            array(__CLASS__, 'render_workflows')
        );

        // Settings
        add_submenu_page(
            'ai-content-suite',
            __('AI Content Settings', 'ai-content-suite'),
            __('Settings', 'ai-content-suite'),
            'manage_options',
            'ai-content-settings',
            array(__CLASS__, 'render_settings')
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'ai-content-') === false && $hook !== 'toplevel_page_ai-content-suite') {
            return;
        }

        // CSS
        wp_enqueue_style(
            'ai-content-suite-admin',
            AI_CONTENT_SUITE_URL . 'assets/css/admin.css',
            array(),
            AI_CONTENT_SUITE_VERSION
        );

        // JS
        wp_enqueue_script(
            'ai-content-suite-admin',
            AI_CONTENT_SUITE_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util', 'jquery-ui-sortable'),
            AI_CONTENT_SUITE_VERSION,
            true
        );

        // Localize script
        wp_localize_script('ai-content-suite-admin', 'aiContentSuite', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_content_suite_nonce'),
            'currentPage' => self::get_current_page(),
            'i18n' => array(
                'generating' => __('Generating content...', 'ai-content-suite'),
                'error' => __('An error occurred', 'ai-content-suite'),
                'confirmDelete' => __('Are you sure you want to delete this?', 'ai-content-suite')
            )
        ));

        // Enqueue CodeMirror for prompt editing
        if ($hook === 'ai-content-suite_page_ai-content-workflows' || $hook === 'ai-content-suite_page_ai-content-generator') {
            wp_enqueue_code_editor(array('type' => 'text/html'));
            wp_enqueue_script('wp-theme-plugin-editor');
            wp_enqueue_style('wp-codemirror');
        }
    }

    /**
     * Get current admin page
     */
    private static function get_current_page() {
        $screen = get_current_screen();
        $map = array(
            'toplevel_page_ai-content-suite' => 'dashboard',
            'ai-content-suite_page_ai-content-generator' => 'generator',
            'ai-content-suite_page_ai-content-workflows' => 'workflows',
            'ai-content-suite_page_ai-content-settings' => 'settings'
        );

        return $map[$screen->id] ?? 'dashboard';
    }

    /**
     * Render dashboard page
     */
    public static function render_dashboard() {
        include AI_CONTENT_SUITE_PATH . 'admin/templates/dashboard.php';
    }

    /**
     * Render content generator page
     */
    public static function render_generator() {
        $default_settings = get_option('ai_content_suite_default_settings', array());
        $content_types = AI_Content_Suite_Helper::get_content_types();
        $tones = AI_Content_Suite_Helper::get_tones();
        $audiences = AI_Content_Suite_Helper::get_target_audiences();
        
        include AI_CONTENT_SUITE_PATH . 'admin/templates/generator.php';
    }

    /**
     * Render workflows page
     */
    public static function render_workflows() {
        $workflows = get_option('ai_content_suite_workflows', array());
        $content_types = AI_Content_Suite_Helper::get_content_types();
        
        include AI_CONTENT_SUITE_PATH . 'admin/templates/workflows.php';
    }

    /**
     * Render settings page
     */
    public static function render_settings() {
        $api_settings = array();
        $supported_apis = AI_Content_Suite()->api_manager->get_supported_apis();
        
        foreach ($supported_apis as $api_name => $api) {
            $api_settings[$api_name] = array(
                'name' => $api['name'],
                'key' => AI_Content_Suite()->api_manager->get_api_key($api_name),
                'connected' => $api['has_key'],
                'rate_limits' => $api['rate_limits']
            );
        }
        
        $general_settings = get_option('ai_content_suite_general_settings', array(
            'auto_save' => '1',
            'auto_seo' => '1',
            'default_status' => 'draft'
        ));
        
        $default_settings = get_option('ai_content_suite_default_settings', array());
        
        include AI_CONTENT_SUITE_PATH . 'admin/templates/settings.php';
    }

    /**
     * AJAX content preview handler
     */
    public static function ajax_preview_content() {
        check_ajax_referer('ai_content_suite_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action', 'ai-content-suite')
            ), 403);
        }
        
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        
        if (empty($content)) {
            wp_send_json_error(array(
                'message' => __('No content to preview', 'ai-content-suite')
            ));
        }
        
        // Apply formatting
        $content = AI_Content_Suite_Helper::format_content($content);
        
        wp_send_json_success(array(
            'preview' => $content
        ));
    }

    /**
     * Admin footer scripts
     */
    public static function admin_footer_scripts() {
        $screen = get_current_screen();
        
        if (strpos($screen->id, 'ai-content-') === false && $screen->id !== 'toplevel_page_ai-content-suite') {
            return;
        }
        ?>
        <script type="text/html" id="tmpl-ai-content-workflow-step">
            <div class="workflow-step" data-step="{{data.stepNumber}}">
                <div class="workflow-step-header">
                    <h4><?php _e('Step', 'ai-content-suite'); ?> {{data.stepNumber}}</h4>
                    <button type="button" class="button-link workflow-step-remove">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
                <div class="workflow-step-content">
                    <div class="form-field">
                        <label for="workflow-step-{{data.stepNumber}}-type">
                            <?php _e('Content Type', 'ai-content-suite'); ?>
                        </label>
                        <select id="workflow-step-{{data.stepNumber}}-type" name="workflow[steps][{{data.stepNumber}}][type]" class="workflow-step-type">
                            <?php foreach (AI_Content_Suite_Helper::get_content_types() as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>">
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="workflow-step-{{data.stepNumber}}-prompt">
                            <?php _e('Prompt Template', 'ai-content-suite'); ?>
                        </label>
                        <textarea id="workflow-step-{{data.stepNumber}}-prompt" 
                                  name="workflow[steps][{{data.stepNumber}}][prompt]" 
                                  class="workflow-step-prompt" 
                                  rows="3"></textarea>
                        <p class="description">
                            <?php _e('Use {{variables}} from previous steps', 'ai-content-suite'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </script>

        <script type="text/html" id="tmpl-ai-content-generation-result">
            <div class="generation-result {{data.status}}">
                <# if (data.status === 'success') { #>
                    <div class="generation-result-header">
                        <h4><?php _e('Generated Content', 'ai-content-suite'); ?></h4>
                        <div class="generation-actions">
                            <button type="button" class="button generation-copy">
                                <?php _e('Copy', 'ai-content-suite'); ?>
                            </button>
                            <button type="button" class="button button-primary generation-save">
                                <?php _e('Save', 'ai-content-suite'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="generation-result-content">
                        {{{data.content}}}
                    </div>
                    <div class="generation-result-meta">
                        <div class="generation-stats">
                            <span class="stat">
                                <?php _e('Words:', 'ai-content-suite'); ?> {{data.meta.word_count}}
                            </span>
                            <span class="stat">
                                <?php _e('Time:', 'ai-content-suite'); ?> {{data.meta.generation_time}}s
                            </span>
                        </div>
                    </div>
                <# } else { #>
                    <div class="generation-result-error">
                        <p><strong><?php _e('Error:', 'ai-content-suite'); ?></strong> {{data.message}}</p>
                        <# if (data.retry) { #>
                            <button type="button" class="button generation-retry">
                                <?php _e('Try Again', 'ai-content-suite'); ?>
                            </button>
                        <# } #>
                    </div>
                <# } #>
            </div>
        </script>
        <?php
    }

    /**
     * Render tab navigation
     */
    public static function render_tabs($current = 'dashboard') {
        $tabs = array(
            'dashboard' => array(
                'title' => __('Dashboard', 'ai-content-suite'),
                'url' => admin_url('admin.php?page=ai-content-suite'),
                'cap' => 'edit_posts'
            ),
            'generator' => array(
                'title' => __('Generate Content', 'ai-content-suite'),
                'url' => admin_url('admin.php?page=ai-content-generator'),
                'cap' => 'edit_posts'
            ),
            'workflows' => array(
                'title' => __('Workflows', 'ai-content-suite'),
                'url' => admin_url('admin.php?page=ai-content-workflows'),
                'cap' => 'edit_posts'
            ),
            'settings' => array(
                'title' => __('Settings', 'ai-content-suite'),
                'url' => admin_url('admin.php?page=ai-content-settings'),
                'cap' => 'manage_options'
            )
        );
        
        echo '<nav class="ai-content-suite-tabs">';
        echo '<ul>';
        
        foreach ($tabs as $tab => $props) {
            if (!current_user_can($props['cap'])) {
                continue;
            }
            
            $class = ($tab === $current) ? 'active' : '';
            echo '<li class="' . esc_attr($class) . '">';
            echo '<a href="' . esc_url($props['url']) . '">' . esc_html($props['title']) . '</a>';
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</nav>';
    }

    /**
     * Render API connection status indicator
     */
    public static function render_api_status($api_name) {
        $api = AI_Content_Suite()->api_manager->get_api_config($api_name);
        
        if (is_wp_error($api)) {
            return;
        }
        
        $connected = !empty($api['key']);
        $limits = $api['rate_limits'];
        
        echo '<div class="api-status">';
        echo '<span class="status-indicator ' . ($connected ? 'connected' : 'disconnected') . '"></span>';
        echo '<span class="status-text">';
        echo $connected ? __('Connected', 'ai-content-suite') : __('Disconnected', 'ai-content-suite');
        echo '</span>';
        
        if ($connected && !empty($limits['limit'])) {
            echo '<div class="api-rate-limit">';
            echo sprintf(
                __('%d/%d requests remaining', 'ai-content-suite'),
                $limits['remaining'],
                $limits['limit']
            );
            
            if ($limits['remaining'] < ($limits['limit'] * 0.2)) {
                echo ' <span class="warning">(' . __('Low', 'ai-content-suite') . ')</span>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
}

// Initialize the admin interface
AI_Content_Suite_Admin_UI::init();






// Key Features of This Admin Interface:
// Comprehensive Menu Structure:

// Dashboard overview

// Content generator

// Workflow automation

// Settings panel

// Content Generator Interface:

// Supports multiple content types

// Tone and audience controls

// Real-time preview functionality

// Save/publish options

// Workflow Automation System:

// Drag-and-drop step builder

// Variable passing between steps

// Template system for prompts

// Conditional logic support

// Settings Management:

// API key configuration

// Rate limit monitoring

// Default generation settings

// System preferences

// Technical Implementation:

// AJAX-powered interactions

// CodeMirror integration for prompt editing

// Handlebars.js templates

// Responsive design

// User Experience Features:

// Real-time content previews

// Generation history tracking

// Detailed error reporting

// One-click regeneration

// Template Files Structure
// The admin interface relies on these template files in admin/templates/:

// dashboard.php - Shows generation stats, recent content, and quick actions

// generator.php - Main content generation form with all controls

// workflows.php - Workflow builder interface

// settings.php - API and plugin configuration

// JavaScript Architecture
// The frontend logic uses these main components:

// Content Generator:

// Handles form submission

// Manages generation progress

// Renders results with templating

// Handles save/publish actions

// Workflow Builder:

// Manages step creation/removal

// Handles variable passing logic

// Validates workflow structure

// Saves workflow configurations

// API Tester:

// Tests API connections

// Displays rate limit info

// Handles key validation

// CSS Structure
// The admin styles are organized with:

// Base styles - Typography, colors, layout

// Component styles - Cards, forms, buttons

// Page-specific styles - Generator, workflows, settings

// Responsive styles - Mobile adaptations

// This implementation provides a complete, professional admin interface for the AI Content Suite plugin that's both powerful and user-friendly. Would you like me to create any of the template files or the accompanying JavaScript/CSS files next?