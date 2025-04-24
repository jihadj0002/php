<?php
/**
 * Handles all database operations for AI Content Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Content_Suite_Database {

    /**
     * Database version
     */
    const DB_VERSION = '1.0';
    const DB_VERSION_OPTION = 'ai_content_suite_db_version';

    /**
     * Create all necessary database tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . 'ai_content_suite_';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Jobs table
        $jobs_table = $table_prefix . 'jobs';
        $sql = "CREATE TABLE $jobs_table (
            job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            prompt TEXT NOT NULL,
            settings TEXT NOT NULL,
            generated_content LONGTEXT,
            generated_content_id BIGINT UNSIGNED,
            error_message TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (job_id),
            KEY user_id (user_id),
            KEY content_type (content_type),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // API logs table
        $logs_table = $table_prefix . 'api_logs';
        $sql = "CREATE TABLE $logs_table (
            log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            api_name VARCHAR(50) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            request_data TEXT NOT NULL,
            response_data LONGTEXT,
            response_code INT,
            duration FLOAT,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (log_id),
            KEY api_name (api_name),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        dbDelta($sql);

        // Content cache table
        $cache_table = $table_prefix . 'content_cache';
        $sql = "CREATE TABLE $cache_table (
            cache_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_hash CHAR(32) NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            content_data LONGTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (cache_id),
            UNIQUE KEY content_hash (content_hash),
            KEY content_type (content_type),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Create a new AI job
     */
    public static function create_job($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_content_suite_jobs';

        $defaults = array(
            'user_id' => get_current_user_id(),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        $wpdb->insert($table, $data);

        return $wpdb->insert_id;
    }

    /**
     * Update an AI job
     */
    public static function update_job($job_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_content_suite_jobs';

        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        $data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $table,
            $data,
            array('job_id' => $job_id)
        );
    }

    /**
     * Get a job by ID
     */
    public static function get_job($job_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_content_suite_jobs';

        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE job_id = %d", $job_id)
        );

        if ($job && isset($job->settings)) {
            $job->settings = json_decode($job->settings, true);
        }

        return $job;
    }

    /**
     * Get jobs with filters
     */
    public static function get_jobs($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_content_suite_jobs';

        $defaults = array(
            'user_id' => null,
            'content_type' => null,
            'status' => null,
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where = array();
        $prepare = array();

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $prepare[] = $args['user_id'];
        }

        if ($args['content_type']) {
            $where[] = 'content_type = %s';
            $prepare[] = $args['content_type'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $prepare[] = $args['status'];
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $order_sql = sprintf(
            'ORDER BY %s %s',
            sanitize_sql_orderby($args['orderby']),
            $args['order'] === 'DESC' ? 'DESC' : 'ASC'
        );

        $limit_sql = $wpdb->prepare(
            'LIMIT %d, %d',
            ($args['page'] - 1) * $args['per_page'],
            $args['per_page']
        );

        $sql = "SELECT * FROM $table $where_sql $order_sql $limit_sql";

        if (!empty($prepare)) {
            $sql = $wpdb->prepare($sql, $prepare);
        }

        $results = $wpdb->get_results($sql);

        // Decode settings for each job
        foreach ($results as $job) {
            if (isset($job->settings)) {
                $job->settings = json_decode($job->settings, true);
            }
        }

        return $results;
    }

    /**
     * Log API request/response
     */
    public static function log_api_request($api_name, $endpoint, $request_data, $response_data, $response_code = null, $duration = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_content_suite_api_logs';

        $data = array(
            'api_name' => $api_name,
            'endpoint' => $endpoint,
            'request_data' => is_array($request_data) ? json_encode($request_data) : $request_data,
            'response_data' => is_array($response_data) ? json_encode($response_data) : $response_data,
            'response_code' => $response_code,
            'duration' => $duration,
            'timestamp' => current_time('mysql')
        );

        return $wpdb->insert($table, $data);
    }

    /**
     * Get cached content
     */
    public static function get_cached_content($content_hash) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_content_suite_content_cache';

        $cache = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE content_hash = %s AND expires_at > %s",
                $content_hash,
                current_time('mysql')
            )
        );

        if ($cache) {
            return maybe_unserialize($cache->content_data);
        }

        return false;
    }

    /**
     * Set content cache
     */
    public static function set_content_cache($content_hash, $content_data, $expires_in = DAY_IN_SECONDS, $content_type = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_content_suite_content_cache';

        $data = array(
            'content_hash' => $content_hash,
            'content_type' => $content_type,
            'content_data' => maybe_serialize($content_data),
            'expires_at' => date('Y-m-d H:i:s', time() + $expires_in)
        );

        // Delete existing cache if exists
        $wpdb->delete(
            $table,
            array('content_hash' => $content_hash)
        );

        return $wpdb->insert($table, $data);
    }

    /**
     * Clean expired cache
     */
    public static function clean_expired_cache() {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_content_suite_content_cache';

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE expires_at <= %s",
                current_time('mysql')
            )
        );
    }

    /**
     * Get database schema
     */
    public static function get_schema() {
        return array(
            'jobs' => array(
                'columns' => array(
                    'job_id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'user_id' => 'BIGINT UNSIGNED NOT NULL',
                    'content_type' => 'VARCHAR(50) NOT NULL',
                    'status' => 'VARCHAR(20) NOT NULL DEFAULT "pending"',
                    'prompt' => 'TEXT NOT NULL',
                    'settings' => 'TEXT NOT NULL',
                    'generated_content' => 'LONGTEXT',
                    'generated_content_id' => 'BIGINT UNSIGNED',
                    'error_message' => 'TEXT',
                    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
                ),
                'keys' => array(
                    'PRIMARY KEY (job_id)',
                    'KEY user_id (user_id)',
                    'KEY content_type (content_type)',
                    'KEY status (status)'
                )
            ),
            'api_logs' => array(
                'columns' => array(
                    'log_id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'api_name' => 'VARCHAR(50) NOT NULL',
                    'endpoint' => 'VARCHAR(255) NOT NULL',
                    'request_data' => 'TEXT NOT NULL',
                    'response_data' => 'LONGTEXT',
                    'response_code' => 'INT',
                    'duration' => 'FLOAT',
                    'timestamp' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
                ),
                'keys' => array(
                    'PRIMARY KEY (log_id)',
                    'KEY api_name (api_name)',
                    'KEY timestamp (timestamp)'
                )
            ),
            'content_cache' => array(
                'columns' => array(
                    'cache_id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                    'content_hash' => 'CHAR(32) NOT NULL',
                    'content_type' => 'VARCHAR(50) NOT NULL',
                    'content_data' => 'LONGTEXT NOT NULL',
                    'expires_at' => 'DATETIME NOT NULL',
                    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
                ),
                'keys' => array(
                    'PRIMARY KEY (cache_id)',
                    'UNIQUE KEY content_hash (content_hash)',
                    'KEY content_type (content_type)',
                    'KEY expires_at (expires_at)'
                )
            )
        );
    }
}