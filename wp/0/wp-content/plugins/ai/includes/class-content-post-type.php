<?php
/**
 * Registers custom post type for AI-generated content
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Content_Suite_Content_Post_Type {

    /**
     * Post type key
     */
    const POST_TYPE = 'ai_content';

    /**
     * Initialize post type
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('init', array(__CLASS__, 'register_taxonomies'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_meta_boxes'), 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array(__CLASS__, 'add_custom_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array(__CLASS__, 'render_custom_columns'), 10, 2);
        add_filter('post_row_actions', array(__CLASS__, 'modify_row_actions'), 10, 2);
    }

    /**
     * Register custom post type
     */
    public static function register_post_type() {
        $labels = array(
            'name'                  => __('AI Content', 'ai-content-suite'),
            'singular_name'         => __('AI Content', 'ai-content-suite'),
            'menu_name'             => __('AI Content', 'ai-content-suite'),
            'name_admin_bar'        => __('AI Content', 'ai-content-suite'),
            'add_new'               => __('Add New', 'ai-content-suite'),
            'add_new_item'          => __('Add New AI Content', 'ai-content-suite'),
            'new_item'              => __('New AI Content', 'ai-content-suite'),
            'edit_item'             => __('Edit AI Content', 'ai-content-suite'),
            'view_item'             => __('View AI Content', 'ai-content-suite'),
            'all_items'             => __('All AI Content', 'ai-content-suite'),
            'search_items'          => __('Search AI Content', 'ai-content-suite'),
            'parent_item_colon'     => __('Parent AI Content:', 'ai-content-suite'),
            'not_found'             => __('No AI content found.', 'ai-content-suite'),
            'not_found_in_trash'    => __('No AI content found in Trash.', 'ai-content-suite'),
            'featured_image'        => __('Featured Image', 'ai-content-suite'),
            'set_featured_image'    => __('Set featured image', 'ai-content-suite'),
            'remove_featured_image' => __('Remove featured image', 'ai-content-suite'),
            'use_featured_image'    => __('Use as featured image', 'ai-content-suite'),
            'archives'              => __('AI content archives', 'ai-content-suite'),
            'insert_into_item'      => __('Insert into content', 'ai-content-suite'),
            'uploaded_to_this_item' => __('Uploaded to this content', 'ai-content-suite'),
            'filter_items_list'     => __('Filter AI content list', 'ai-content-suite'),
            'items_list_navigation' => __('AI content list navigation', 'ai-content-suite'),
            'items_list'           => __('AI content list', 'ai-content-suite'),
        );

        $args = array(
            'labels'             => $labels,
            'public'            => true,
            'publicly_queryable' => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'ai-content'),
            'capability_type'    => 'post',
            'has_archive'       => true,
            'hierarchical'      => false,
            'menu_position'     => 5,
            'menu_icon'         => 'dashicons-ai',
            'supports'          => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields',
                'revisions'
            ),
            'show_in_rest'      => true,
            'rest_base'         => 'ai-content'
        );

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register custom taxonomies
     */
    public static function register_taxonomies() {
        // Content Type taxonomy
        register_taxonomy(
            'ai_content_type',
            self::POST_TYPE,
            array(
                'label' => __('Content Types', 'ai-content-suite'),
                'rewrite' => array('slug' => 'ai-content-type'),
                'hierarchical' => true,
                'show_admin_column' => true,
                'show_in_rest' => true,
                'default_term' => array(
                    'name' => __('General', 'ai-content-suite'),
                    'slug' => 'general'
                )
            )
        );

        // AI Model taxonomy
        register_taxonomy(
            'ai_model',
            self::POST_TYPE,
            array(
                'label' => __('AI Models', 'ai-content-suite'),
                'rewrite' => array('slug' => 'ai-model'),
                'hierarchical' => false,
                'show_admin_column' => true,
                'show_in_rest' => true
            )
        );
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'ai_content_meta',
            __('AI Content Details', 'ai-content-suite'),
            array(__CLASS__, 'render_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'ai_content_stats',
            __('Generation Statistics', 'ai-content-suite'),
            array(__CLASS__, 'render_stats_box'),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render main meta box
     */
    public static function render_meta_box($post) {
        wp_nonce_field('ai_content_meta_box', 'ai_content_meta_box_nonce');

        $generation_prompt = get_post_meta($post->ID, '_ai_generation_prompt', true);
        $content_settings = get_post_meta($post->ID, '_ai_content_settings', true);
        $api_used = get_post_meta($post->ID, '_ai_api_used', true);
        $model_used = get_post_meta($post->ID, '_ai_model_used', true);

        // Default settings
        if (empty($content_settings)) {
            $content_settings = array(
                'tone' => 'professional',
                'length' => 800,
                'creativity' => 0.7
            );
        }

        ?>
        <div class="ai-content-meta-fields">
            <div class="form-field">
                <label for="ai_generation_prompt">
                    <strong><?php _e('Original Prompt', 'ai-content-suite'); ?></strong>
                </label>
                <textarea id="ai_generation_prompt" name="ai_generation_prompt" rows="3" style="width:100%"><?php echo esc_textarea($generation_prompt); ?></textarea>
                <p class="description"><?php _e('The original prompt used to generate this content', 'ai-content-suite'); ?></p>
            </div>

            <div class="form-field">
                <label for="ai_content_settings_tone">
                    <strong><?php _e('Content Tone', 'ai-content-suite'); ?></strong>
                </label>
                <select id="ai_content_settings_tone" name="ai_content_settings[tone]" style="width:100%">
                    <?php foreach (AI_Content_Suite_Helper::get_tones() as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($content_settings['tone'], $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="ai_content_settings_length">
                    <strong><?php _e('Target Length (words)', 'ai-content-suite'); ?></strong>
                </label>
                <input type="number" id="ai_content_settings_length" name="ai_content_settings[length]" 
                       value="<?php echo esc_attr($content_settings['length']); ?>" style="width:100%">
            </div>

            <div class="form-field">
                <label for="ai_content_settings_creativity">
                    <strong><?php _e('Creativity Level', 'ai-content-suite'); ?></strong>
                </label>
                <input type="range" id="ai_content_settings_creativity" name="ai_content_settings[creativity]" 
                       min="0.1" max="1.0" step="0.1" 
                       value="<?php echo esc_attr($content_settings['creativity']); ?>" style="width:100%">
                <output for="ai_content_settings_creativity"><?php echo esc_html($content_settings['creativity']); ?></output>
            </div>

            <div class="form-field">
                <label for="ai_api_used">
                    <strong><?php _e('AI Service Used', 'ai-content-suite'); ?></strong>
                </label>
                <input type="text" id="ai_api_used" name="ai_api_used" 
                       value="<?php echo esc_attr($api_used); ?>" style="width:100%">
            </div>

            <div class="form-field">
                <label for="ai_model_used">
                    <strong><?php _e('AI Model Used', 'ai-content-suite'); ?></strong>
                </label>
                <input type="text" id="ai_model_used" name="ai_model_used" 
                       value="<?php echo esc_attr($model_used); ?>" style="width:100%">
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#ai_content_settings_creativity').on('input', function() {
                    $(this).next('output').text($(this).val());
                });
            });
        </script>
        <?php
    }

    /**
     * Render stats meta box
     */
    public static function render_stats_box($post) {
        $generation_time = get_post_meta($post->ID, '_ai_generation_time', true);
        $word_count = get_post_meta($post->ID, '_ai_word_count', true);
        $cost = get_post_meta($post->ID, '_ai_generation_cost', true);
        $api_response = get_post_meta($post->ID, '_ai_api_response', true);

        ?>
        <div class="ai-content-stats">
            <?php if ($generation_time): ?>
                <p>
                    <strong><?php _e('Generation Time:', 'ai-content-suite'); ?></strong><br>
                    <?php echo esc_html($generation_time); ?>s
                </p>
            <?php endif; ?>

            <?php if ($word_count): ?>
                <p>
                    <strong><?php _e('Word Count:', 'ai-content-suite'); ?></strong><br>
                    <?php echo esc_html($word_count); ?>
                </p>
            <?php endif; ?>

            <?php if ($cost): ?>
                <p>
                    <strong><?php _e('Estimated Cost:', 'ai-content-suite'); ?></strong><br>
                    $<?php echo esc_html(number_format($cost, 5)); ?>
                </p>
            <?php endif; ?>

            <?php if ($api_response): ?>
                <p>
                    <strong><?php _e('API Usage:', 'ai-content-suite'); ?></strong><br>
                    <?php echo esc_html($api_response['usage']['total_tokens'] ?? 0); ?> tokens
                </p>
            <?php endif; ?>

            <p>
                <strong><?php _e('Shortcode:', 'ai-content-suite'); ?></strong><br>
                <code>[ai_content id="<?php echo esc_attr($post->ID); ?>"]</code>
            </p>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public static function save_meta_boxes($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['ai_content_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['ai_content_meta_box_nonce'], 'ai_content_meta_box')) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check post type
        if ($post->post_type != self::POST_TYPE) {
            return;
        }

        // Save meta fields
        $fields = array(
            'ai_generation_prompt' => 'sanitize_text_field',
            'ai_api_used' => 'sanitize_text_field',
            'ai_model_used' => 'sanitize_text_field',
            'ai_content_settings' => array(__CLASS__, 'sanitize_settings')
        );

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }

    /**
     * Sanitize content settings
     */
    public static function sanitize_settings($settings) {
        return AI_Content_Suite_Helper::sanitize_settings($settings);
    }

    /**
     * Add custom columns to post list
     */
    public static function add_custom_columns($columns) {
        $new_columns = array(
            'cb' => $columns['cb'],
            'title' => $columns['title'],
            'ai_content_type' => __('Content Type', 'ai-content-suite'),
            'ai_model' => __('AI Model', 'ai-content-suite'),
            'word_count' => __('Words', 'ai-content-suite'),
            'generation_time' => __('Gen Time', 'ai-content-suite'),
            'date' => $columns['date']
        );

        return $new_columns;
    }

    /**
     * Render custom columns
     */
    public static function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'ai_content_type':
                echo get_the_term_list($post_id, 'ai_content_type', '', ', ');
                break;

            case 'ai_model':
                echo get_the_term_list($post_id, 'ai_model', '', ', ');
                break;

            case 'word_count':
                $count = get_post_meta($post_id, '_ai_word_count', true);
                echo $count ? esc_html($count) : '—';
                break;

            case 'generation_time':
                $time = get_post_meta($post_id, '_ai_generation_time', true);
                echo $time ? esc_html($time) . 's' : '—';
                break;
        }
    }

    /**
     * Modify row actions
     */
    public static function modify_row_actions($actions, $post) {
        if ($post->post_type === self::POST_TYPE) {
            // Add "Regenerate" action
            $actions['regenerate'] = sprintf(
                '<a href="%s" class="regenerate-content" data-post-id="%d">%s</a>',
                '#',
                $post->ID,
                __('Regenerate', 'ai-content-suite')
            );

            // Add "Quick Edit" action
            $actions['quick_edit'] = sprintf(
                '<a href="%s" class="quick-edit-content" data-post-id="%d">%s</a>',
                '#',
                $post->ID,
                __('Quick Edit', 'ai-content-suite')
            );
        }

        return $actions;
    }

    /**
     * Create AI content post
     */
    public static function create_post($content_data) {
        $defaults = array(
            'title' => __('AI Generated Content', 'ai-content-suite'),
            'content' => '',
            'excerpt' => '',
            'status' => 'draft',
            'meta' => array()
        );

        $content_data = wp_parse_args($content_data, $defaults);

        $post_data = array(
            'post_title' => $content_data['title'],
            'post_content' => $content_data['content'],
            'post_excerpt' => $content_data['excerpt'],
            'post_status' => $content_data['status'],
            'post_type' => self::POST_TYPE
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Save meta data
        if (!empty($content_data['meta'])) {
            foreach ($content_data['meta'] as $key => $value) {
                update_post_meta($post_id, '_' . $key, $value);
            }
        }

        // Set default terms if none exist
        if (!get_the_terms($post_id, 'ai_content_type')) {
            wp_set_object_terms($post_id, 'general', 'ai_content_type');
        }

        return $post_id;
    }

    /**
     * Get AI content by prompt hash
     */
    public static function get_by_prompt_hash($hash) {
        global $wpdb;

        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta 
                WHERE meta_key = '_ai_prompt_hash' AND meta_value = %s 
                ORDER BY post_id DESC LIMIT 1",
                $hash
            )
        );

        return $post_id ? get_post($post_id) : null;
    }

    /**
     * Shortcode handler for displaying AI content
     */
    public static function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_meta' => false,
            'show_prompt' => false
        ), $atts, 'ai_content');

        $post = get_post($atts['id']);

        if (!$post || $post->post_type !== self::POST_TYPE) {
            return '';
        }

        $output = apply_filters('the_content', $post->post_content);

        if ($atts['show_meta']) {
            $meta = get_post_meta($post->ID);
            $output .= '<div class="ai-content-meta">';
            $output .= '<h4>' . __('Generation Details', 'ai-content-suite') . '</h4>';
            $output .= '<ul>';

            foreach ($meta as $key => $value) {
                if (strpos($key, '_ai_') === 0) {
                    $output .= '<li><strong>' . esc_html(substr($key, 4)) . ':</strong> ';
                    $output .= esc_html(is_array($value) ? implode(', ', $value) : $value);
                    $output .= '</li>';
                }
            }

            $output .= '</ul></div>';
        }

        if ($atts['show_prompt']) {
            $prompt = get_post_meta($post->ID, '_ai_generation_prompt', true);
            if ($prompt) {
                $output .= '<div class="ai-content-prompt">';
                $output .= '<h4>' . __('Original Prompt', 'ai-content-suite') . '</h4>';
                $output .= '<p>' . esc_html($prompt) . '</p>';
                $output .= '</div>';
            }
        }

        return $output;
    }
}

// Initialize the post type
AI_Content_Suite_Content_Post_Type::init();

// Register shortcode
add_shortcode('ai_content', array('AI_Content_Suite_Content_Post_Type', 'shortcode_handler'));







// Key Features of This Implementation:
// Custom Post Type:

// Dedicated ai_content post type for all AI-generated content

// Supports titles, content, featured images, and custom fields

// REST API enabled for modern editing interfaces

// Taxonomy System:

// ai_content_type (hierarchical) - Categories for different content types

// ai_model (non-hierarchical) - Tags for tracking which AI models were used

// Comprehensive Meta Management:

// Stores original prompts and generation settings

// Tracks API usage and generation statistics

// Records cost and performance metrics

// Admin Interface Enhancements:

// Custom columns in post listings

// Specialized row actions (regenerate, quick edit)

// Detailed meta boxes for generation details

// Content Management Utilities:

// Programmatic post creation method

// Prompt hash lookup system

// Shortcode for easy content embedding

// Security & Best Practices:

// Nonce verification for all form submissions

// Proper data sanitization

// Capability checks

// Translation-ready text strings

// Usage Examples:
// Creating AI Content Programmatically:

// php
// Copy
// $post_id = AI_Content_Suite_Content_Post_Type::create_post([
//     'title' => 'How AI is Changing Content Creation',
//     'content' => $generated_content,
//     'status' => 'publish',
//     'meta' => [
//         'generation_prompt' => 'Write a blog post about AI in content creation',
//         'api_used' => 'OpenAI',
//         'model_used' => 'GPT-4',
//         'generation_time' => 4.2,
//         'word_count' => 1250
//     ]
// ]);
// Using the Shortcode:

// php
// Copy
// // Display just the content
// echo do_shortcode('[ai_content id="123"]');

// // Display content with generation details
// echo do_shortcode('[ai_content id="123" show_meta="true"]');
// Finding Existing Content:

// php
// Copy
// // By prompt hash
// $post = AI_Content_Suite_Content_Post_Type::get_by_prompt_hash($hash);
// if ($post) {
//     // Content already exists
// }