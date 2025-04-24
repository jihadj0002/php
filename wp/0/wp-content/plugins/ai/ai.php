<?php
/**
 * Plugin Name: AI Content Suite
 * Description: Comprehensive AI-powered content generation toolkit for WordPress
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ai-content-suite
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_CONTENT_SUITE_VERSION', '1.0.0');
define('AI_CONTENT_SUITE_PATH', plugin_dir_path(__FILE__));
define('AI_CONTENT_SUITE_URL', plugin_dir_url(__FILE__));
define('AI_CONTENT_SUITE_BASENAME', plugin_basename(__FILE__));

/**
 * Main AI Content Suite Class
 */
final class AI_Content_Suite {

    /**
     * The single instance of the class
     */
    private static $instance = null;

    /**
     * Plugin modules
     */
    public $api_manager;
    public $text_generator;
    public $image_generator;
    public $video_generator;
    public $voice_generator;
    public $seo_optimizer;
    public $workflow_engine;

    /**
     * Main AI_Content_Suite Instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Load plugin files
        $this->includes();
        
        // Initialize plugin
        $this->init_hooks();
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core files
        require_once AI_CONTENT_SUITE_PATH . 'includes/class-database.php';
        require_once AI_CONTENT_SUITE_PATH . 'includes/class-helper.php';
        require_once AI_CONTENT_SUITE_PATH . 'includes/class-ajax-handler.php';
        require_once AI_CONTENT_SUITE_PATH . 'includes/class-api-manager.php';
        require_once AI_CONTENT_SUITE_PATH . 'includes/class-content-post-type.php';

        // Admin files
        if (is_admin()) {
            require_once AI_CONTENT_SUITE_PATH . 'admin/admin-ui.php';
            require_once AI_CONTENT_SUITE_PATH . 'admin/settings-api.php';
            require_once AI_CONTENT_SUITE_PATH . 'admin/content-workflows.php';
        }

        // Module files
        require_once AI_CONTENT_SUITE_PATH . 'modules/text-generator/class-text-generator.php';
        require_once AI_CONTENT_SUITE_PATH . 'modules/image-generator/class-image-generator.php';
        require_once AI_CONTENT_SUITE_PATH . 'modules/video-generator/class-video-generator.php';
        require_once AI_CONTENT_SUITE_PATH . 'modules/voice-generator/class-voice-generator.php';
        require_once AI_CONTENT_SUITE_PATH . 'modules/seo-optimizer/class-seo-optimizer.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize modules
        add_action('init', array($this, 'init_modules'), 0);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Initialize plugin modules
     */
    public function init_modules() {
        $this->api_manager = new AI_Content_Suite_API_Manager();
        $this->text_generator = new AI_Content_Suite_Text_Generator();
        $this->image_generator = new AI_Content_Suite_Image_Generator();
        $this->video_generator = new AI_Content_Suite_Video_Generator();
        $this->voice_generator = new AI_Content_Suite_Voice_Generator();
        $this->seo_optimizer = new AI_Content_Suite_SEO_Optimizer();
        
        // Only load workflow engine in admin
        if (is_admin()) {
            $this->workflow_engine = new AI_Content_Suite_Workflow_Engine();
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-content-suite',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'ai-content-suite') === false) {
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
            array('jquery', 'wp-util'),
            AI_CONTENT_SUITE_VERSION,
            true
        );

        // Localize script
        wp_localize_script('ai-content-suite-admin', 'aiContentSuite', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_content_suite_nonce'),
            'i18n' => array(
                'generating' => __('Generating content...', 'ai-content-suite'),
                'error' => __('An error occurred', 'ai-content-suite'),
            )
        ));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        AI_Content_Suite_Database::create_tables();
        
        // Set default options
        update_option('ai_content_suite_version', AI_CONTENT_SUITE_VERSION);
        
        // Register custom post type
        AI_Content_Suite_Content_Post_Type::register();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('ai_content_suite_daily_maintenance');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Main instance of AI Content Suite
 */
function AI_Content_Suite() {
    return AI_Content_Suite::instance();
}

// Initialize the plugin
AI_Content_Suite();