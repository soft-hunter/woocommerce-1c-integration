<?php
/**
 * Authentication functionality for 1C integration
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange
 * @author     Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Authentication functionality for 1C integration
 */
class WC1C_Auth {

    /**
     * Session cookie name
     */
    const COOKIE_NAME = 'wc1c_auth_session';

    /**
     * Session timeout (in seconds)
     */
    const SESSION_TIMEOUT = 3600; // 1 hour

    /**
     * Maximum login attempts per IP
     */
    const MAX_ATTEMPTS = 5;

    /**
     * Lockout duration (in seconds)
     */
    const LOCKOUT_DURATION = 900; // 15 minutes

    /**
     * Authenticate user
     *
     * @return bool
     */
    public function authenticate() {
        // Check rate limiting
        if (!$this->check_rate_limit()) {
            WC1C_Logger::log('Authentication blocked due to rate limiting', 'warning', array(
                'ip' => $this->get_client_ip()
            ));
            return false;
        }

        // Get credentials
        $credentials = $this->get_credentials();
        
        if (!$credentials) {
            WC1C_Logger::log('No authentication credentials provided', 'warning');
            return false;
        }

        // Authenticate user
        $user = wp_authenticate($credentials['username'], $credentials['password']);
        
        if (is_wp_error($user)) {
            $this->record_failed_attempt();
            WC1C_Logger::log('Authentication failed', 'warning', array(
                'username' => $credentials['username'],
                'ip' => $this->get_client_ip(),
                'error' => $user->get_error_message()
            ));
            return false;
        }

        // Check permissions
        if (!$this->check_permissions($user)) {
            WC1C_Logger::log('User lacks required permissions', 'warning', array(
                'username' => $user->user_login,
                'ip' => $this->get_client_ip()
            ));
            return false;
        }

        // Clear failed attempts on successful login
        $this->clear_failed_attempts();

        WC1C_Logger::log('Authentication successful', 'info', array(
            'username' => $user->user_login,
            'ip' => $this->get_client_ip()
        ));

        return true;
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function is_authenticated() {
        // Check for HTTP Authorization header
        if ($this->authenticate_via_header()) {
            return true;
        }

        // Check for session cookie
        if ($this->authenticate_via_cookie()) {
            return true;
        }

        // Check current user
        if ($this->authenticate_current_user()) {
            return true;
        }

        return false;
    }

    /**
     * Generate session cookie
     *
     * @return string
     */
    public function generate_session_cookie() {
        $user = wp_get_current_user();
        
        if (!$user->exists()) {
            throw new Exception('No authenticated user found');
        }

        $expiration = time() + self::SESSION_TIMEOUT;
        $session_data = array(
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'ip' => $this->get_client_ip(),
            'expires' => $expiration
        );

        $session_token = wp_generate_password(32, false);
        $session_hash = hash_hmac('sha256', wp_json_encode($session_data), wp_salt());

        // Store session
        set_transient('wc1c_session_' . $session_token, $session_data, self::SESSION_TIMEOUT);

        return base64_encode($session_token . ':' . $session_hash);
    }

    /**
     * Validate session cookie
     *
     * @param string $cookie Session cookie
     * @return bool
     */
    public function validate_session_cookie($cookie) {
        $decoded = base64_decode($cookie);
        
        if (!$decoded) {
            return false;
        }

        $parts = explode(':', $decoded, 2);
        
        if (count($parts) !== 2) {
            return false;
        }

        list($session_token, $provided_hash) = $parts;

        // Get session data
        $session_data = get_transient('wc1c_session_' . $session_token);
        
        if (!$session_data) {
            return false;
        }

        // Verify hash
        $expected_hash = hash_hmac('sha256', wp_json_encode($session_data), wp_salt());
        
        if (!hash_equals($expected_hash, $provided_hash)) {
            return false;
        }

        // Check expiration
        if (time() > $session_data['expires']) {
            delete_transient('wc1c_session_' . $session_token);
            return false;
        }

        // Check IP (optional security measure)
        if (get_option('wc1c_check_ip', 'yes') === 'yes' && $session_data['ip'] !== $this->get_client_ip()) {
            return false;
        }

        // Extend session
        $session_data['expires'] = time() + self::SESSION_TIMEOUT;
        set_transient('wc1c_session_' . $session_token, $session_data, self::SESSION_TIMEOUT);

        return true;
    }

    /**
     * Test connection
     *
     * @return bool
     */
    public function test_connection() {
        try {
            // Test basic authentication
            if (!$this->authenticate()) {
                return false;
            }

            // Test database connection
            global $wpdb;
            $wpdb->get_var("SELECT 1");
            
            if ($wpdb->last_error) {
                WC1C_Logger::log('Database connection test failed: ' . $wpdb->last_error, 'error');
                return false;
            }

            // Test WooCommerce availability
            if (!class_exists('WooCommerce')) {
                WC1C_Logger::log('WooCommerce not available', 'error');
                return false;
            }

            // Test file system permissions
            $upload_dir = wp_upload_dir();
            $test_dir = $upload_dir['basedir'] . '/woocommerce-1c-integration';
            
            if (!is_writable($test_dir)) {
                WC1C_Logger::log('Data directory not writable: ' . $test_dir, 'error');
                return false;
            }

            WC1C_Logger::log('Connection test successful', 'info');
            return true;

        } catch (Exception $e) {
            WC1C_Logger::log('Connection test failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get authentication credentials
     *
     * @return array|false
     */
    private function get_credentials() {
        // Check Authorization header
        $auth_header = $this->get_authorization_header();
        
        if ($auth_header) {
            return $this->parse_authorization_header($auth_header);
        }

        // Check PHP_AUTH variables
        if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            return array(
                'username' => $_SERVER['PHP_AUTH_USER'],
                'password' => $_SERVER['PHP_AUTH_PW']
            );
        }

        return false;
    }

    /**
     * Get Authorization header
     *
     * @return string|false
     */
    private function get_authorization_header() {
        $headers = array(
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION'
        );

        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }

        return false;
    }

    /**
     * Parse Authorization header
     *
     * @param string $header Authorization header
     * @return array|false
     */
    private function parse_authorization_header($header) {
        if (strpos($header, 'Basic ') !== 0) {
            return false;
        }

        $encoded = substr($header, 6);
        $decoded = base64_decode($encoded);
        
        if (!$decoded) {
            return false;
        }

        $parts = explode(':', $decoded, 2);
        
        if (count($parts) !== 2) {
            return false;
        }

        return array(
            'username' => $parts[0],
            'password' => $parts[1]
        );
    }

    /**
     * Check user permissions
     *
     * @param WP_User $user User object
     * @return bool
     */
    private function check_permissions($user) {
        return user_can($user, 'manage_woocommerce') || user_can($user, 'administrator');
    }

    /**
     * Authenticate via HTTP header
     *
     * @return bool
     */
    private function authenticate_via_header() {
        $credentials = $this->get_credentials();
        
        if (!$credentials) {
            return false;
        }

        $user = wp_authenticate($credentials['username'], $credentials['password']);
        
        if (is_wp_error($user)) {
            return false;
        }

        return $this->check_permissions($user);
    }

    /**
     * Authenticate via session cookie
     *
     * @return bool
     */
    private function authenticate_via_cookie() {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }

        return $this->validate_session_cookie($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Authenticate current user
     *
     * @return bool
     */
    private function authenticate_current_user() {
        $user = wp_get_current_user();
        
        if (!$user->exists()) {
            return false;
        }

        return $this->check_permissions($user);
    }

    /**
     * Check rate limiting
     *
     * @return bool
     */
    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $attempts_key = 'wc1c_auth_attempts_' . md5($ip);
        $lockout_key = 'wc1c_auth_lockout_' . md5($ip);

        // Check if IP is locked out
        if (get_transient($lockout_key)) {
            return false;
        }

        return true;
    }

    /**
     * Record failed authentication attempt
     */
    private function record_failed_attempt() {
        $ip = $this->get_client_ip();
        $attempts_key = 'wc1c_auth_attempts_' . md5($ip);
        $lockout_key = 'wc1c_auth_lockout_' . md5($ip);

        $attempts = get_transient($attempts_key) ?: 0;
        $attempts++;

        set_transient($attempts_key, $attempts, 3600); // 1 hour

        if ($attempts >= self::MAX_ATTEMPTS) {
            set_transient($lockout_key, true, self::LOCKOUT_DURATION);
            
            WC1C_Logger::log('IP locked out due to too many failed attempts', 'warning', array(
                'ip' => $ip,
                'attempts' => $attempts
            ));
        }
    }

    /**
     * Clear failed attempts
     */
    private function clear_failed_attempts() {
        $ip = $this->get_client_ip();
        $attempts_key = 'wc1c_auth_attempts_' . md5($ip);
        
        delete_transient($attempts_key);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Generate secure token
     *
     * @param int $length Token length
     * @return string
     */
    public function generate_secure_token($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        } else {
            return wp_generate_password($length, false);
        }
    }

    /**
     * Hash password securely
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public function hash_password($password) {
        return wp_hash_password($password);
    }

    /**
     * Verify password
     *
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool
     */
    public function verify_password($password, $hash) {
        return wp_check_password($password, $hash);
    }

    /**
     * Clean up expired sessions
     */
    public function cleanup_expired_sessions() {
        global $wpdb;

        // Clean up expired transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wc1c_session_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );

        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_wc1c_session_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );

        WC1C_Logger::log('Cleaned up expired authentication sessions', 'debug');
    }

    /**
     * Get active sessions count
     *
     * @return int
     */
    public function get_active_sessions_count() {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wc1c_session_%'"
        );
    }

    /**
     * Revoke all sessions
     */
    public function revoke_all_sessions() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wc1c_session_%' 
             OR option_name LIKE '_transient_timeout_wc1c_session_%'"
        );

        WC1C_Logger::log('All authentication sessions revoked', 'info');
    }

    /**
     * Get authentication statistics
     *
     * @param int $days Number of days to look back
     * @return array
     */
    public function get_auth_stats($days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(CASE WHEN message LIKE '%Authentication successful%' THEN 1 END) as successful_logins,
                COUNT(CASE WHEN message LIKE '%Authentication failed%' THEN 1 END) as failed_logins,
                COUNT(DISTINCT JSON_EXTRACT(data, '$.ip')) as unique_ips
             FROM {$table_name} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             AND (message LIKE '%Authentication%' OR message LIKE '%authentication%')
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            $days
        ));
    }

    /**
     * Check if IP is whitelisted
     *
     * @param string $ip IP address
     * @return bool
     */
    private function is_ip_whitelisted($ip) {
        $whitelist = get_option('wc1c_ip_whitelist', array());
        
        if (empty($whitelist)) {
            return true; // No whitelist means all IPs allowed
        }

        foreach ($whitelist as $allowed_ip) {
            if ($this->ip_in_range($ip, $allowed_ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in range
     *
     * @param string $ip IP address to check
     * @param string $range IP range (supports CIDR notation)
     * @return bool
     */
    private function ip_in_range($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $mask) = explode('/', $range);
        
        if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }

        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int)$mask);
        
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }

    /**
     * Log security event
     *
     * @param string $event Event type
     * @param string $message Event message
     * @param array $data Additional data
     */
    private function log_security_event($event, $message, $data = array()) {
        $security_data = array_merge($data, array(
            'event' => $event,
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => current_time('mysql')
        ));

        WC1C_Logger::log($message, 'security', $security_data);
    }
}