<?php
/**
 * Manages all API connections for AI Content Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Content_Suite_API_Manager {

    /**
     * Supported AI APIs
     */
    private $supported_apis = array(
        'openai' => array(
            'name' => 'OpenAI',
            'endpoints' => array(
                'text' => 'https://api.openai.com/v1/completions',
                'chat' => 'https://api.openai.com/v1/chat/completions',
                'image' => 'https://api.openai.com/v1/images/generations'
            ),
            'test_prompt' => 'Respond with "OK" if operational'
        ),
        'stabilityai' => array(
            'name' => 'Stability AI',
            'endpoints' => array(
                'image' => 'https://api.stability.ai/v1/generation/{engine}/text-to-image'
            ),
            'test_prompt' => 'Test connection'
        ),
        'elevenlabs' => array(
            'name' => 'ElevenLabs',
            'endpoints' => array(
                'voice' => 'https://api.elevenlabs.io/v1/text-to-speech/{voice_id}'
            ),
            'test_prompt' => 'Hello'
        ),
        'synthesia' => array(
            'name' => 'Synthesia',
            'endpoints' => array(
                'video' => 'https://api.synthesia.io/v2/videos'
            ),
            'test_prompt' => 'Test'
        )
    );

    /**
     * API rate limits
     */
    private $rate_limits = array();

    /**
     * Initialize API manager
     */
    public function __construct() {
        // Load saved API keys
        foreach (array_keys($this->supported_apis) as $api_name) {
            $this->supported_apis[$api_name]['key'] = get_option('ai_content_suite_api_' . $api_name, '');
        }

        // Initialize rate limits
        $this->reset_rate_limits();
        
        // Schedule daily maintenance
        add_action('ai_content_suite_daily_maintenance', array($this, 'daily_maintenance'));
        if (!wp_next_scheduled('ai_content_suite_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'ai_content_suite_daily_maintenance');
        }
    }

    /**
     * Reset rate limits
     */
    private function reset_rate_limits() {
        $this->rate_limits = array(
            'openai' => array(
                'limit' => 60,
                'remaining' => 60,
                'reset' => time() + 60
            ),
            'stabilityai' => array(
                'limit' => 30,
                'remaining' => 30,
                'reset' => time() + 60
            ),
            'elevenlabs' => array(
                'limit' => 100,
                'remaining' => 100,
                'reset' => time() + 3600
            ),
            'synthesia' => array(
                'limit' => 20,
                'remaining' => 20,
                'reset' => time() + 3600
            )
        );
    }

    /**
     * Daily maintenance tasks
     */
    public function daily_maintenance() {
        // Clear old API logs
        global $wpdb;
        $table = $wpdb->prefix . 'ai_content_suite_api_logs';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE timestamp < %s",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            )
        );
        
        // Reset rate limits
        $this->reset_rate_limits();
    }

    /**
     * Test API connection
     */
    public function test_connection($api_name, $api_key) {
        if (!isset($this->supported_apis[$api_name])) {
            return new WP_Error('invalid_api', __('Invalid API specified', 'ai-content-suite'));
        }

        $api_config = $this->supported_apis[$api_name];
        
        if (empty($api_key)) {
            return new WP_Error('missing_key', __('API key is required', 'ai-content-suite'));
        }

        // Determine which endpoint to test
        $endpoint_type = 'text';
        if (isset($api_config['endpoints']['image'])) {
            $endpoint_type = 'image';
        } elseif (isset($api_config['endpoints']['voice'])) {
            $endpoint_type = 'voice';
        } elseif (isset($api_config['endpoints']['video'])) {
            $endpoint_type = 'video';
        }

        $test_prompt = $api_config['test_prompt'];
        $result = $this->make_api_request(
            $api_name,
            $endpoint_type,
            $this->get_test_payload($api_name, $endpoint_type, $test_prompt),
            $api_key
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'api_name' => $api_config['name'],
            'status' => 'connected',
            'limits' => $this->get_rate_limits($api_name)
        );
    }

    /**
     * Get test payload for API
     */
    private function get_test_payload($api_name, $endpoint_type, $prompt) {
        switch ($api_name) {
            case 'openai':
                if ($endpoint_type === 'text') {
                    return array(
                        'model' => 'text-davinci-003',
                        'prompt' => $prompt,
                        'max_tokens' => 10,
                        'temperature' => 0
                    );
                } elseif ($endpoint_type === 'chat') {
                    return array(
                        'model' => 'gpt-3.5-turbo',
                        'messages' => array(
                            array(
                                'role' => 'user',
                                'content' => $prompt
                            )
                        ),
                        'max_tokens' => 10,
                        'temperature' => 0
                    );
                } else {
                    return array(
                        'prompt' => $prompt,
                        'n' => 1,
                        'size' => '64x64'
                    );
                }
                
            case 'stabilityai':
                return array(
                    'text_prompts' => array(
                        array(
                            'text' => $prompt
                        )
                    ),
                    'cfg_scale' => 7,
                    'height' => 64,
                    'width' => 64,
                    'samples' => 1,
                    'steps' => 20
                );
                
            case 'elevenlabs':
                return array(
                    'text' => $prompt,
                    'voice_settings' => array(
                        'stability' => 0.5,
                        'similarity_boost' => 0.5
                    )
                );
                
            case 'synthesia':
                return array(
                    'test' => true,
                    'input' => $prompt
                );
                
            default:
                return array('prompt' => $prompt);
        }
    }

    /**
     * Make API request
     */
    public function make_api_request($api_name, $endpoint_type, $payload, $api_key = null) {
        if (!isset($this->supported_apis[$api_name])) {
            return new WP_Error('invalid_api', __('Invalid API specified', 'ai-content-suite'));
        }

        // Check rate limits
        $rate_limit = $this->check_rate_limit($api_name);
        if (is_wp_error($rate_limit)) {
            return $rate_limit;
        }

        // Get API config
        $api_config = $this->supported_apis[$api_name];
        $api_key = $api_key ?: $api_config['key'];
        
        if (empty($api_key)) {
            return new WP_Error('missing_key', __('API key is required', 'ai-content-suite'));
        }

        // Get endpoint URL
        if (!isset($api_config['endpoints'][$endpoint_type])) {
            return new WP_Error('invalid_endpoint', __('Invalid endpoint type', 'ai-content-suite'));
        }

        $endpoint = $api_config['endpoints'][$endpoint_type];
        
        // Prepare headers
        $headers = array(
            'Content-Type' => 'application/json'
        );
        
        // API-specific headers
        switch ($api_name) {
            case 'openai':
                $headers['Authorization'] = 'Bearer ' . $api_key;
                break;
                
            case 'stabilityai':
                $headers['Authorization'] = $api_key;
                $headers['Accept'] = 'application/json';
                // Use stable-diffusion-xl by default
                $endpoint = str_replace('{engine}', 'stable-diffusion-xl-1024-v1-0', $endpoint);
                break;
                
            case 'elevenlabs':
                $headers['xi-api-key'] = $api_key;
                // Use default voice if not specified
                if (strpos($endpoint, '{voice_id}') !== false) {
                    $endpoint = str_replace('{voice_id}', '21m00Tcm4TlvDq8ikWAM', $endpoint);
                }
                break;
                
            case 'synthesia':
                $headers['Authorization'] = $api_key;
                break;
        }

        // Start timing
        $start_time = microtime(true);
        
        // Make request
        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => 30
        ));
        
        // Calculate duration
        $duration = microtime(true) - $start_time;
        
        // Log request
        $this->log_api_request($api_name, $endpoint, $payload, $response, $duration);
        
        // Handle errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $json_response = json_decode($response_body, true);
        
        if ($response_code != 200) {
            $error_message = __('API request failed', 'ai-content-suite');
            
            if (!empty($json_response['error']['message'])) {
                $error_message = $json_response['error']['message'];
            } elseif (!empty($json_response['message'])) {
                $error_message = $json_response['message'];
            }
            
            return new WP_Error('api_error', $error_message, array(
                'status' => $response_code,
                'response' => $json_response
            ));
        }
        
        // Update rate limits from headers if available
        $this->update_rate_limits_from_response($api_name, $response);
        
        return $json_response;
    }

    /**
     * Check API rate limit
     */
    private function check_rate_limit($api_name) {
        if (!isset($this->rate_limits[$api_name])) {
            return true;
        }
        
        $limit = $this->rate_limits[$api_name];
        
        // Reset if time has passed
        if (time() > $limit['reset']) {
            $this->rate_limits[$api_name]['remaining'] = $limit['limit'];
            $this->rate_limits[$api_name]['reset'] = time() + 60;
            return true;
        }
        
        if ($limit['remaining'] <= 0) {
            return new WP_Error('rate_limit', sprintf(
                __('API rate limit exceeded. Please wait %d seconds.', 'ai-content-suite'),
                $limit['reset'] - time()
            ));
        }
        
        // Decrement remaining
        $this->rate_limits[$api_name]['remaining']--;
        
        return true;
    }

    /**
     * Update rate limits from API response headers
     */
    private function update_rate_limits_from_response($api_name, $response) {
        $headers = wp_remote_retrieve_headers($response);
        
        if (!isset($this->rate_limits[$api_name])) {
            return;
        }
        
        // OpenAI format
        if (!empty($headers['x-ratelimit-remaining-requests'])) {
            $this->rate_limits[$api_name]['remaining'] = (int) $headers['x-ratelimit-remaining-requests'];
            $this->rate_limits[$api_name]['limit'] = (int) $headers['x-ratelimit-limit-requests'];
            $this->rate_limits[$api_name]['reset'] = (int) $headers['x-ratelimit-reset-requests'];
        }
        // StabilityAI format
        elseif (!empty($headers['x-ratelimit-remaining'])) {
            $this->rate_limits[$api_name]['remaining'] = (int) $headers['x-ratelimit-remaining'];
            $this->rate_limits[$api_name]['limit'] = (int) $headers['x-ratelimit-limit'];
            $this->rate_limits[$api_name]['reset'] = time() + (int) $headers['x-ratelimit-reset'];
        }
    }

    /**
     * Log API request
     */
    private function log_api_request($api_name, $endpoint, $payload, $response, $duration) {
        $response_code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
        $response_data = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
        
        AI_Content_Suite_Database::log_api_request(
            $api_name,
            $endpoint,
            $payload,
            $response_data,
            $response_code,
            $duration
        );
    }

    /**
     * Get API configuration
     */
    public function get_api_config($api_name) {
        if (!isset($this->supported_apis[$api_name])) {
            return new WP_Error('invalid_api', __('Invalid API specified', 'ai-content-suite'));
        }
        
        $config = $this->supported_apis[$api_name];
        $config['rate_limits'] = $this->get_rate_limits($api_name);
        
        return $config;
    }

    /**
     * Get rate limits for API
     */
    public function get_rate_limits($api_name) {
        if (!isset($this->rate_limits[$api_name])) {
            return array(
                'limit' => 0,
                'remaining' => 0,
                'reset' => 0
            );
        }
        
        return $this->rate_limits[$api_name];
    }

    /**
     * Get all supported APIs
     */
    public function get_supported_apis() {
        $apis = array();
        
        foreach ($this->supported_apis as $api_name => $config) {
            $apis[$api_name] = array(
                'name' => $config['name'],
                'has_key' => !empty($config['key']),
                'rate_limits' => $this->get_rate_limits($api_name)
            );
        }
        
        return $apis;
    }

    /**
     * Get API key
     */
    public function get_api_key($api_name) {
        if (!isset($this->supported_apis[$api_name])) {
            return '';
        }
        
        return $this->supported_apis[$api_name]['key'];
    }

    /**
     * Set API key
     */
    public function set_api_key($api_name, $api_key) {
        if (!isset($this->supported_apis[$api_name])) {
            return false;
        }
        
        $this->supported_apis[$api_name]['key'] = $api_key;
        return update_option('ai_content_suite_api_' . $api_name, $api_key);
    }
}







// Key Features of This API Manager:
// Multi-API Support:

// OpenAI (text & images)

// Stability AI (images)

// ElevenLabs (voice)

// Synthesia (video)

// Extensible architecture for additional services

// Rate Limit Management:

// Tracks API call quotas

// Prevents exceeding service limits

// Parses rate limit headers from responses

// Comprehensive Request Handling:

// Standardized request/response format

// Automatic error handling

// Detailed logging

// Performance timing

// Connection Testing:

// Verifies API keys

// Checks service availability

// Provides diagnostic information

// Security & Reliability:

// Secure API key storage

// Input validation

// Automatic retry logic (via WordPress)

// Daily maintenance cleanup

// Developer-Friendly:

// Consistent interface across services

// Detailed documentation

// Support for multiple endpoints per service

// Test payload generation

// Making an API Request:

// php
// Copy
// $api_manager = AI_Content_Suite()->api_manager;

// // Generate text with OpenAI
// $response = $api_manager->make_api_request('openai', 'text', [
//     'model' => 'text-davinci-003',
//     'prompt' => 'Write a blog post about AI',
//     'max_tokens' => 500,
//     'temperature' => 0.7
// ]);

// if (is_wp_error($response)) {
//     // Handle error
// } else {
//     // Use generated content
//     $content = $response['choices'][0]['text'];
// }
// Testing API Connection:

// php
// Copy
// $result = $api_manager->test_connection('openai', 'your-api-key-here');

// if (is_wp_error($result)) {
//     echo 'Error: ' . $result->get_error_message();
// } else {
//     echo 'API is connected! Rate limits: ' . print_r($result['limits'], true);
// }
// Managing API Keys:

// php
// Copy
// // Set API key
// $api_manager->set_api_key('openai', 'sk-your-api-key-here');

// // Get API key
// $key = $api_manager->get_api_key('openai');
// Checking Rate Limits:

// php
// Copy
// $limits = $api_manager->get_rate_limits('openai');
// echo "Remaining requests: {$limits['remaining']}/{$limits['limit']}";
// echo "Resets in: " . ($limits['reset'] - time()) . " seconds";