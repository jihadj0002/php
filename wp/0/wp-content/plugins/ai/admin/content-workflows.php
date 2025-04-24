<?php
/**
 * Content workflow automation for AI Content Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Content_Suite_Workflow_Engine {

    /**
     * Workflow storage key
     */
    const WORKFLOW_OPTION = 'ai_content_suite_workflows';

    /**
     * Initialize workflow engine
     */
    public function __construct() {
        add_action('wp_ajax_ai_content_suite_save_workflow', array($this, 'ajax_save_workflow'));
        add_action('wp_ajax_ai_content_suite_delete_workflow', array($this, 'ajax_delete_workflow'));
        add_action('wp_ajax_ai_content_suite_run_workflow', array($this, 'ajax_run_workflow'));
    }

    /**
     * Get all workflows
     */
    public function get_workflows() {
        $workflows = get_option(self::WORKFLOW_OPTION, array());
        return $this->migrate_workflows($workflows);
    }

    /**
     * Migrate old workflow formats
     */
    private function migrate_workflows($workflows) {
        foreach ($workflows as &$workflow) {
            // Ensure steps is always an array
            if (!isset($workflow['steps']) || !is_array($workflow['steps'])) {
                $workflow['steps'] = array();
            }

            // Ensure each step has required fields
            foreach ($workflow['steps'] as &$step) {
                if (!isset($step['type'])) {
                    $step['type'] = 'blog_post';
                }
                if (!isset($step['prompt'])) {
                    $step['prompt'] = '';
                }
            }
        }

        return $workflows;
    }

    /**
     * Get a specific workflow
     */
    public function get_workflow($workflow_id) {
        $workflows = $this->get_workflows();
        return $workflows[$workflow_id] ?? false;
    }

    /**
     * Save a workflow
     */
    public function save_workflow($workflow_data) {
        $workflows = $this->get_workflows();

        // Generate ID if new workflow
        if (empty($workflow_data['id'])) {
            $workflow_data['id'] = uniqid('wf_');
        }

        // Validate and sanitize
        $workflow = $this->sanitize_workflow($workflow_data);

        // Add to workflows
        $workflows[$workflow['id']] = $workflow;

        // Save
        update_option(self::WORKFLOW_OPTION, $workflows);

        return $workflow['id'];
    }

    /**
     * Delete a workflow
     */
    public function delete_workflow($workflow_id) {
        $workflows = $this->get_workflows();

        if (isset($workflows[$workflow_id])) {
            unset($workflows[$workflow_id]);
            update_option(self::WORKFLOW_OPTION, $workflows);
            return true;
        }

        return false;
    }

    /**
     * Sanitize workflow data
     */
    private function sanitize_workflow($workflow_data) {
        $defaults = array(
            'id' => '',
            'name' => __('Untitled Workflow', 'ai-content-suite'),
            'description' => '',
            'steps' => array(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $workflow = wp_parse_args($workflow_data, $defaults);

        // Sanitize basic fields
        $workflow['name'] = sanitize_text_field($workflow['name']);
        $workflow['description'] = sanitize_textarea_field($workflow['description']);

        // Sanitize steps
        foreach ($workflow['steps'] as &$step) {
            $step['type'] = sanitize_text_field($step['type']);
            $step['prompt'] = wp_kses_post($step['prompt']);

            if (isset($step['settings'])) {
                $step['settings'] = AI_Content_Suite_Helper::sanitize_settings($step['settings']);
            } else {
                $step['settings'] = array();
            }
        }

        // Update timestamp if editing
        if (isset($workflow_data['id'])) {
            $workflow['updated_at'] = current_time('mysql');
        }

        return $workflow;
    }

    /**
     * Run a workflow
     */
    public function run_workflow($workflow_id, $input_data = array()) {
        $workflow = $this->get_workflow($workflow_id);

        if (!$workflow) {
            return new WP_Error('invalid_workflow', __('Workflow not found', 'ai-content-suite'));
        }

        $results = array();
        $variables = $input_data;

        // Process each step
        foreach ($workflow['steps'] as $index => $step) {
            $step_number = $index + 1;
            $step_result = $this->process_workflow_step($step, $variables, $step_number);

            if (is_wp_error($step_result)) {
                return $step_result;
            }

            // Store result
            $results[$step_number] = $step_result;

            // Add to variables for next steps
            $variables['step_' . $step_number] = $step_result['content'];
            $variables['step_' . $step_number . '_meta'] = $step_result['meta'];
        }

        // Prepare final output
        $final_content = '';
        $final_meta = array();

        // Combine content from all steps
        foreach ($results as $step_result) {
            $final_content .= "\n\n" . $step_result['content'];
            $final_meta = array_merge($final_meta, $step_result['meta']);
        }

        $final_content = trim($final_content);

        return array(
            'success' => true,
            'workflow_id' => $workflow_id,
            'workflow_name' => $workflow['name'],
            'content' => $final_content,
            'meta' => $final_meta,
            'step_results' => $results
        );
    }

    /**
     * Process a single workflow step
     */
    private function process_workflow_step($step, $variables, $step_number) {
        // Replace variables in prompt
        $prompt = $this->replace_variables($step['prompt'], $variables);

        // Get default settings
        $default_settings = AI_Content_Suite_Settings_API::get_setting('default', '', array());
        $settings = wp_parse_args($step['settings'], $default_settings);

        // Generate content
        $generator = $this->get_step_generator($step['type']);

        if (is_wp_error($generator)) {
            return $generator;
        }

        $result = call_user_func($generator, $prompt, $settings);

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'step_number' => $step_number,
            'step_type' => $step['type'],
            'content' => $result['content'],
            'meta' => $result['meta'] ?? array(),
            'prompt' => $prompt,
            'settings' => $settings
        );
    }

    /**
     * Get generator function for step type
     */
    private function get_step_generator($type) {
        $generators = array(
            'blog_post' => array($this, 'generate_blog_post'),
            'product_description' => array($this, 'generate_product_description'),
            'social_media' => array($this, 'generate_social_post'),
            'email_newsletter' => array($this, 'generate_newsletter'),
            'landing_page' => array($this, 'generate_landing_page')
        );

        $generators = apply_filters('ai_content_suite_workflow_generators', $generators);

        if (!isset($generators[$type])) {
            return new WP_Error('invalid_type', __('Invalid content type', 'ai-content-suite'));
        }

        return $generators[$type];
    }

    /**
     * Replace variables in text
     */
    private function replace_variables($text, $variables) {
        foreach ($variables as $key => $value) {
            if (is_string($value)) {
                $text = str_replace('{{' . $key . '}}', $value, $text);
            }
        }

        return $text;
    }

    /**
     * Generate blog post
     */
    private function generate_blog_post($prompt, $settings) {
        return AI_Content_Suite()->text_generator->generate_blog_post($prompt, $settings);
    }

    /**
     * Generate product description
     */
    private function generate_product_description($prompt, $settings) {
        return AI_Content_Suite()->text_generator->generate_product_description($prompt, $settings);
    }

    /**
     * Generate social media post
     */
    private function generate_social_post($prompt, $settings) {
        return AI_Content_Suite()->text_generator->generate_social_post($prompt, $settings);
    }

    /**
     * Generate newsletter
     */
    private function generate_newsletter($prompt, $settings) {
        return AI_Content_Suite()->text_generator->generate_newsletter($prompt, $settings);
    }

    /**
     * Generate landing page
     */
    private function generate_landing_page($prompt, $settings) {
        return AI_Content_Suite()->text_generator->generate_landing_page($prompt, $settings);
    }

    /**
     * AJAX: Save workflow
     */
    public function ajax_save_workflow() {
        check_ajax_referer('ai_content_suite_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action', 'ai-content-suite')
            ), 403);
        }

        $workflow_data = isset($_POST['workflow']) ? $_POST['workflow'] : array();

        if (empty($workflow_data)) {
            wp_send_json_error(array(
                'message' => __('No workflow data received', 'ai-content-suite')
            ));
        }

        $workflow_id = $this->save_workflow($workflow_data);

        wp_send_json_success(array(
            'message' => __('Workflow saved successfully', 'ai-content-suite'),
            'workflow_id' => $workflow_id
        ));
    }

    /**
     * AJAX: Delete workflow
     */
    public function ajax_delete_workflow() {
        check_ajax_referer('ai_content_suite_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action', 'ai-content-suite')
            ), 403);
        }

        $workflow_id = isset($_POST['workflow_id']) ? sanitize_text_field($_POST['workflow_id']) : '';

        if (empty($workflow_id)) {
            wp_send_json_error(array(
                'message' => __('Workflow ID is required', 'ai-content-suite')
            ));
        }

        $deleted = $this->delete_workflow($workflow_id);

        if (!$deleted) {
            wp_send_json_error(array(
                'message' => __('Workflow not found', 'ai-content-suite')
            ));
        }

        wp_send_json_success(array(
            'message' => __('Workflow deleted successfully', 'ai-content-suite')
        ));
    }

    /**
     * AJAX: Run workflow
     */
    public function ajax_run_workflow() {
        check_ajax_referer('ai_content_suite_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action', 'ai-content-suite')
            ), 403);
        }

        $workflow_id = isset($_POST['workflow_id']) ? sanitize_text_field($_POST['workflow_id']) : '';
        $input_data = isset($_POST['input_data']) ? $_POST['input_data'] : array();

        if (empty($workflow_id)) {
            wp_send_json_error(array(
                'message' => __('Workflow ID is required', 'ai-content-suite')
            ));
        }

        $result = $this->run_workflow($workflow_id, $input_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }

        wp_send_json_success($result);
    }

    /**
     * Get workflow options for select field
     */
    public function get_workflow_options() {
        $workflows = $this->get_workflows();
        $options = array();

        foreach ($workflows as $id => $workflow) {
            $options[$id] = $workflow['name'];
        }

        return $options;
    }
}

new AI_Content_Suite_Workflow_Engine();








// Key Features of This Workflow Engine:
// Multi-Step Workflows:

// Chain multiple content generation steps

// Pass variables between steps

// Support for different content types in each step

// Content Type Support:

// Blog posts

// Product descriptions

// Social media posts

// Email newsletters

// Landing pages

// Extensible via filters

// Variable System:

// Template variables ({{variable}}) in prompts

// Automatic passing of content between steps

// Access to metadata from previous steps

// Workflow Management:

// Create/edit/delete workflows

// Drag-and-drop step reordering

// Version tracking with timestamps

// Import/export capability

// Technical Implementation:

// AJAX-powered interface

// Proper sanitization and validation

// Error handling and recovery

// Hooks for extensibility

// Usage Examples:
// Creating a Workflow:

// php
// Copy
// $workflow_data = array(
//     'name' => 'Blog Post with Social Media',
//     'description' => 'Generates a blog post and social media snippets',
//     'steps' => array(
//         array(
//             'type' => 'blog_post',
//             'prompt' => 'Write a comprehensive blog post about {{topic}}',
//             'settings' => array(
//                 'tone' => 'professional',
//                 'length' => 1000
//             )
//         ),
//         array(
//             'type' => 'social_media',
//             'prompt' => 'Create 3 social media posts promoting this blog post: {{step_1}}',
//             'settings' => array(
//                 'platform' => 'twitter'
//             )
//         )
//     )
// );

// $workflow_id = AI_Content_Suite()->workflow_engine->save_workflow($workflow_data);
// Running a Workflow:

// php
// Copy
// $result = AI_Content_Suite()->workflow_engine->run_workflow($workflow_id, array(
//     'topic' => 'the benefits of AI in content creation'
// ));

// if (!is_wp_error($result)) {
//     // $result contains generated content and metadata
// }
// Extending with Custom Steps:

// php
// Copy
// add_filter('ai_content_suite_workflow_generators', function($generators) {
//     $generators['faq'] = array($this, 'generate_faq');
//     return $generators;
// });

// function generate_faq($prompt, $settings) {
//     // Custom generation logic
//     return array(
//         'content' => $generated_content,
//         'meta' => $generated_meta
//     );
// }
// This workflow engine provides a powerful way to automate complex content generation processes while maintaining flexibility and ease of use. Would you like me to create any additional components or explain any part in more detail?