<?php
if (!defined('ABSPATH')) exit(__("Direct access not allowed.", 'woocommerce-1c-integration'));

// Configuration constants with secure defaults
if (!defined('WC1C_SUPPRESS_NOTICES')) define('WC1C_SUPPRESS_NOTICES', true);
if (!defined('WC1C_FILE_LIMIT')) define('WC1C_FILE_LIMIT', '100M');
if (!defined('WC1C_XML_CHARSET')) define('WC1C_XML_CHARSET', 'UTF-8');
if (!defined('WC1C_DISABLE_VARIATIONS')) define('WC1C_DISABLE_VARIATIONS', false);
if (!defined('WC1C_OUTOFSTOCK_STATUS')) define('WC1C_OUTOFSTOCK_STATUS', 'outofstock');
if (!defined('WC1C_MANAGE_STOCK')) define('WC1C_MANAGE_STOCK', 'yes');
if (!defined('WC1C_CLEANUP_GARBAGE')) define('WC1C_CLEANUP_GARBAGE', true);
if (!defined('WC1C_ENABLE_LOGGING')) define('WC1C_ENABLE_LOGGING', true);
if (!defined('WC1C_MAX_EXECUTION_TIME')) define('WC1C_MAX_EXECUTION_TIME', 300);
if (!defined('WC1C_RATE_LIMIT')) define('WC1C_RATE_LIMIT', 60); // requests per hour

define('WC1C_TIMESTAMP', time());

function wc1c_query_vars($query_vars) {
  $query_vars[] = 'wc1c';
  return $query_vars;
}
add_filter('query_vars', 'wc1c_query_vars');

add_action('init', 'wc1c_add_rewrite_rules', 1000);


function wc1c_wpdb_end($is_commit = false, $no_check = false) {
  global $wpdb, $wc1c_is_transaction;

  if (empty($wc1c_is_transaction)) return;

  $wc1c_is_transaction = false;

  $sql_query = !$is_commit ? "ROLLBACK" : "COMMIT";
  $wpdb->query($sql_query);
  if (!$no_check) wc1c_check_wpdb_error();

  if (function_exists('wc1c_log')) {
    wc1c_log("Transaction " . strtolower($sql_query), 'DEBUG');
  }
  
  if (wc1c_is_debug()) echo "\n" . strtolower($sql_query);
}

function wc1c_full_request_uri() {
  $uri = 'http';
  if (@$_SERVER['HTTPS'] == 'on') $uri .= 's';
  $uri .= "://{$_SERVER['SERVER_NAME']}";
  if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
    $uri .= ":{$_SERVER['SERVER_PORT']}";
  }
  if (isset($_SERVER['REQUEST_URI'])) $uri .= $_SERVER['REQUEST_URI'];

  return $uri;
}

function wc1c_error($message, $type = "Error", $no_exit = false) {
  global $wc1c_is_error;

  $wc1c_is_error = true;

  $message = "$type: $message";
  $last_char = substr($message, -1);
  if (!in_array($last_char, array('.', '!', '?'))) $message .= '.';

  error_log($message);
  if (function_exists('wc1c_log')) {
    wc1c_log($message, 'ERROR');
  }
  echo "$message\n";

  if (wc1c_is_debug()) {
    echo "\n";
    debug_print_backtrace();

    $info = array(
      "Request URI" => wc1c_full_request_uri(),
      "Server API" => PHP_SAPI,
      "Memory limit" => ini_get('memory_limit'),
      "Memory usage" => function_exists('size_format') ? size_format(memory_get_usage(true)) : memory_get_usage(true),
      "Maximum POST size" => ini_get('post_max_size'),
      "PHP version" => PHP_VERSION,
      "WordPress version" => get_bloginfo('version'),
      "Plugin version" => defined('WC1C_VERSION') ? WC1C_VERSION : 'unknown',
    );
    echo "\n";
    foreach ($info as $info_name => $info_value) {
      echo "$info_name: $info_value\n";
    }
  }

  if (!$no_exit) {
    wc1c_wpdb_end();
    exit;
  }
}

function wc1c_set_strict_mode() {
  set_error_handler('wc1c_strict_error_handler');
  set_exception_handler('wc1c_strict_exception_handler');
}

function wc1c_output_callback($buffer) {
  global $wc1c_is_error;

  if (!headers_sent()) {
    $is_xml = @$_GET['mode'] == 'query';
    $content_type = !$is_xml || $wc1c_is_error ? 'text/plain' : 'text/xml';
    header("Content-Type: $content_type; charset=" . WC1C_XML_CHARSET);
    
    // Security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
  }

  if (WC1C_XML_CHARSET == 'UTF-8') {
    $buffer = "\xEF\xBB\xBF$buffer";
  } else {
    $buffer = mb_convert_encoding($buffer, WC1C_XML_CHARSET, 'UTF-8');
  }

  return $buffer;
}

function wc1c_set_output_callback() {
  ob_start('wc1c_output_callback');
}

function wc1c_strict_error_handler($errno, $errstr, $errfile = '', $errline = 0, $errcontext = null) {
  if (error_reporting() === 0) return false;

  switch ($errno) {
    case E_ERROR:
    case E_USER_ERROR:
      $type = "Fatal Error";
      break;
    case E_WARNING:
    case E_USER_WARNING:
      $type = "Warning";
      break;
    default:
      $type = "Unknown Error";
  }

  $message = sprintf("%s in %s on line %d", $errstr, $errfile, $errline);
  wc1c_error($message, "PHP $type");
}

function wc1c_strict_exception_handler($exception) {
  $message = sprintf("%s in %s on line %d", $exception->getMessage(), $exception->getFile(), $exception->getLine());
  wc1c_error($message, "Exception");
}

function wc1c_fix_fastcgi_get() {
  if (!$_GET && isset($_SERVER['REQUEST_URI'])) {
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    parse_str($query, $_GET);
  }
}

function wc1c_cleanup_dir($path_dir) {
  if (!is_dir($path_dir)) return;
  
  $files = array_diff(scandir($path_dir), array('.', '..'));
  foreach ($files as $file) {
    $path = "$path_dir/$file";
    (is_dir($path) ? wc1c_cleanup_dir($path) : unlink($path));
  }
}

function wc1c_ensure_directories() {
  $directories = array(
    WC1C_DATA_DIR,
    WC1C_DATA_DIR . 'catalog',
    WC1C_DATA_DIR . 'sale',
    WC1C_DATA_DIR . 'logs',
    WC1C_DATA_DIR . 'temp'
  );

  foreach ($directories as $dir) {
    if (!is_dir($dir)) {
      wp_mkdir_p($dir);
      
      // Add security files
      file_put_contents($dir . '/index.html', '');
      
      // Add .htaccess for data directories (not logs)
      if (!in_array(basename($dir), array('logs', 'temp'))) {
        file_put_contents($dir . '/.htaccess', "Deny from all\n<Files \"*.xml\">\n  Allow from all\n</Files>");
      }
    }
  }
}

function wc1c_check_permissions($user) {
  if (!user_can($user, 'shop_manager') && !user_can($user, 'administrator')) {
    if (function_exists('wc1c_log')) {
      wc1c_log("Permission denied for user: " . $user->user_login, 'SECURITY');
    }
    wc1c_error("No permissions");
  }
}

function wc1c_wp_error($wp_error, $only_error_code = null) {
  $messages = array();
  foreach ($wp_error->get_error_codes() as $error_code) {
    if ($only_error_code && $error_code != $only_error_code) continue;

    $wp_error_messages = implode(", ", $wp_error->get_error_messages($error_code));
    $wp_error_messages = strip_tags($wp_error_messages);
    $messages[] = sprintf("%s: %s", $error_code, $wp_error_messages);
  }

  wc1c_error(implode("; ", $messages), "WP Error");
}

function wc1c_check_wp_error($wp_error) {
  if (is_wp_error($wp_error)) wc1c_wp_error($wp_error);
}

function wc1c_check_rate_limit() {
  if (!defined('WC1C_RATE_LIMIT') || WC1C_RATE_LIMIT <= 0) return;
  
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $cache_key = "wc1c_rate_limit_$ip";
  $requests = wp_cache_get($cache_key);
  
  if ($requests === false) {
    $requests = 1;
    wp_cache_set($cache_key, $requests, '', 3600); // 1 hour
  } else {
    $requests++;
    wp_cache_set($cache_key, $requests, '', 3600);
  }
  
  if ($requests > WC1C_RATE_LIMIT) {
    if (function_exists('wc1c_log')) {
      wc1c_log("Rate limit exceeded for IP: $ip", 'SECURITY');
    }
    wc1c_error("Rate limit exceeded");
  }
}

function wc1c_mode_checkauth() {
  wc1c_check_rate_limit();
  
  // Check for Authorization header
  foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $server_key) {
    if (!isset($_SERVER[$server_key])) continue;

    list(, $auth_value) = explode(' ', $_SERVER[$server_key], 2);
    $auth_value = base64_decode($auth_value);
    list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $auth_value, 2);
    break;
  }

  if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
    if (function_exists('wc1c_log')) {
      wc1c_log("No authentication credentials provided", 'SECURITY');
    }
    wc1c_error("No authentication credentials");
  }

  $user = wp_authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
  wc1c_check_wp_error($user);
  wc1c_check_permissions($user);

  if (function_exists('wc1c_log')) {
    wc1c_log("Successful authentication for user: " . $user->user_login, 'INFO');
  }

  $expiration = time() + apply_filters('auth_cookie_expiration', DAY_IN_SECONDS, $user->ID, false);
  $auth_cookie = wp_generate_auth_cookie($user->ID, $expiration);

  exit("success\nwc1c-auth\n$auth_cookie");
}

function wc1c_check_auth() {
  // Check for HTTP Authorization header first
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    list(, $auth_value) = explode(' ', $_SERVER['HTTP_AUTHORIZATION'], 2);
    $auth_value = base64_decode($auth_value);
    list($username, $password) = explode(':', $auth_value, 2);
    
    $user = wp_authenticate($username, $password);
    if (!is_wp_error($user)) {
      wc1c_check_permissions($user);
      return;
    }
  }

  // Check for REDIRECT_HTTP_AUTHORIZATION
  if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    list(, $auth_value) = explode(' ', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 2);
    $auth_value = base64_decode($auth_value);
    list($username, $password) = explode(':', $auth_value, 2);
    
    $user = wp_authenticate($username, $password);
    if (!is_wp_error($user)) {
      wc1c_check_permissions($user);
      return;
    }
  }

  // Check PHP_AUTH_USER/PHP_AUTH_PW
  if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    $user = wp_authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    if (!is_wp_error($user)) {
      wc1c_check_permissions($user);
      return;
    }
  }

  // Fallback to cookie auth
  if (!empty($_COOKIE['wc1c-auth'])) {
    $user_id = wp_validate_auth_cookie($_COOKIE['wc1c-auth'], 'auth');
    if ($user_id) {
      $user = get_user_by('id', $user_id);
      if ($user && $user->exists()) {
        wc1c_check_permissions($user);
        return;
      }
    }
  }

  // Check current user as last resort
  $user = wp_get_current_user();
  if ($user && $user->ID) {
    wc1c_check_permissions($user);
    return;
  }

  if (function_exists('wc1c_log')) {
    wc1c_log("Authentication failed", 'SECURITY');
  }
  wc1c_error("Not logged in");
}

function wc1c_filesize_to_bytes($filesize) {
  switch (substr($filesize, -1)) {
    case 'G':
    case 'g':
      return (int) $filesize * 1073741824;
    case 'M':
    case 'm':
      return (int) $filesize * 1048576;
    case 'K':
    case 'k':
      return (int) $filesize * 1024;
    default:
      return (int) $filesize;
  }
}

function wc1c_mode_init($type) {
  // Ensure directories exist
  wc1c_ensure_directories();
  
  // Validate type parameter
  $allowed_types = array('catalog', 'sale');
  if (!in_array($type, $allowed_types)) {
    wc1c_error("Invalid type parameter");
  }

  if (WC1C_CLEANUP_GARBAGE) wc1c_cleanup_dir(WC1C_DATA_DIR . $type);
  
  @exec("which unzip", $_, $status);
  $is_zip = @$status === 0 || class_exists('ZipArchive');
  if (!$is_zip) wc1c_error("The PHP extension zip is required.");

  $file_limits = array(
    wc1c_filesize_to_bytes('10M'),
    wc1c_filesize_to_bytes(ini_get('post_max_size')),
    wc1c_filesize_to_bytes(ini_get('memory_limit')),
  );
  
  @exec("grep ^MemFree: /proc/meminfo", $output, $status);
  if (@$status === 0 && $output) {
    $output = preg_split("/\s+/", $output[0]);
    $file_limits[] = intval($output[1] * 1000 * 0.7);
  }
  
  if (WC1C_FILE_LIMIT) $file_limits[] = wc1c_filesize_to_bytes(WC1C_FILE_LIMIT);
  $file_limit = min($file_limits);

  if (function_exists('wc1c_log')) {
    wc1c_log("Init mode for type: $type, file_limit: $file_limit", 'INFO');
  }

  exit("zip=yes\nfile_limit=$file_limit");
}

function wc1c_mode_file($type, $filename) {
  if ($filename) {
    // Validate filename
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', basename($filename))) {
      wc1c_error("Invalid filename");
    }

    // Prevent path traversal
    $filename = basename($filename);
    $allowed_types = array('catalog', 'sale');
    if (!in_array($type, $allowed_types)) {
      wc1c_error("Invalid type");
    }

    $path = WC1C_DATA_DIR . "$type/" . $filename;
    $path_dir = dirname($path);

    // Ensure directory exists
    if (!is_dir($path_dir)) {
      wp_mkdir_p($path_dir) or wc1c_error(sprintf("Failed to create directories for file %s", $filename));
    }

    // Validate file extension
    $allowed_extensions = array('xml', 'zip');
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_extensions)) {
      wc1c_error("Invalid file type");
    }

    $input_file = fopen("php://input", 'r');
    $temp_path = "$path~";
    $temp_file = fopen($temp_path, 'w');
    
    if (!$temp_file) {
      wc1c_error(sprintf("Failed to create temp file for %s", $filename));
    }
    
    stream_copy_to_stream($input_file, $temp_file);
    fclose($temp_file);
    fclose($input_file);

    if (is_file($path)) {
      $temp_header = file_get_contents($temp_path, false, null, 0, 32);
      if (strpos($temp_header, "<?xml ") !== false) unlink($path);
    }

    $temp_file = fopen($temp_path, 'r');
    $file = fopen($path, 'a');
    
    if (!$file) {
      wc1c_error(sprintf("Failed to open file %s for writing", $filename));
    }
    
    stream_copy_to_stream($temp_file, $file);
    fclose($temp_file);
    fclose($file);
    unlink($temp_path);

    if (function_exists('wc1c_log')) {
      wc1c_log("File uploaded: $filename, size: " . filesize($path), 'INFO');
    }
  }

  if ($type == 'catalog') {
    exit("success");
  } elseif ($type == 'sale') {
    wc1c_unpack_files($type);

    $data_dir = WC1C_DATA_DIR . $type;
    foreach (glob("$data_dir/*.xml") as $path) {
      $filename = basename($path);
      wc1c_mode_import($type, $filename, 'orders');
    }
  }
}

function wc1c_check_wpdb_error() {
  global $wpdb;

  if (!$wpdb->last_error) return;

  wc1c_error(sprintf("%s for query \"%s\"", $wpdb->last_error, $wpdb->last_query), "DB Error", true);

  wc1c_wpdb_end(false, true);

  exit;
}

function wc1c_disable_time_limit() {
  $disabled_functions = explode(',', ini_get('disable_functions'));
  if (!in_array('set_time_limit', $disabled_functions)) {
    @set_time_limit(WC1C_MAX_EXECUTION_TIME);
  }
}

function wc1c_set_transaction_mode() {
  global $wpdb, $wc1c_is_transaction;

  wc1c_disable_time_limit();

  register_shutdown_function('wc1c_transaction_shutdown_function');

  $wpdb->show_errors(false);

  $wc1c_is_transaction = true;
  $wpdb->query("START TRANSACTION");
  wc1c_check_wpdb_error();

  if (function_exists('wc1c_log')) {
    wc1c_log("Transaction started", 'DEBUG');
  }
}

function wc1c_transaction_shutdown_function() {
  $error = error_get_last();
  $is_commit = !$error || $error['type'] < E_ERROR;

  wc1c_wpdb_end($is_commit);
}

function wc1c_unpack_files($type) {
  $data_dir = WC1C_DATA_DIR . $type;
  $zip_paths = glob("$data_dir/*.zip");
  if (!$zip_paths) return;
  
  if (ob_get_level()) ob_end_clean();

  $command = sprintf("unzip -qqo %s -d %s", implode(' ', array_map('escapeshellarg', $zip_paths)), escapeshellarg($data_dir));
  @exec($command, $_, $status);

  if (@$status !== 0) {
    foreach ($zip_paths as $zip_path) {
      $zip = new ZipArchive();
      $result = $zip->open($zip_path);
      if ($result !== true) wc1c_error(sprintf("Failed open archive %s with error code %d", $zip_path, $result));

      $zip->extractTo($data_dir) or wc1c_error(sprintf("Failed to extract from archive %s", $zip_path));
      $zip->close() or wc1c_error(sprintf("Failed to close archive %s", $zip_path));
    }
  }

  foreach ($zip_paths as $zip_path) {
    unlink($zip_path) or wc1c_error(sprintf("Failed to unlink file %s", $zip_path));
  }

  if (function_exists('wc1c_log')) {
    wc1c_log("Unpacked " . count($zip_paths) . " archive(s) for type: $type", 'INFO');
  }

  if ($type == 'catalog') exit("progress");
}

function wc1c_xml_start_element_handler($parser, $name, $attrs) {
  global $wc1c_namespace, $wc1c_is_full, $wc1c_names, $wc1c_depth;

  $wc1c_names[] = $name;
  $wc1c_depth++;

  call_user_func("wc1c_{$wc1c_namespace}_start_element_handler", $wc1c_is_full, $wc1c_names, $wc1c_depth, $name, $attrs);

  static $element_number = 0;
  $element_number++;

  // Optimize memory management
  if ($element_number > 500) {
    $element_number = 0;

    // Clear only object cache, not all caches
    wp_cache_delete_group('posts');
    wp_cache_delete_group('terms');
    wp_cache_delete_group('post_meta');
    wp_cache_delete_group('term_meta');

    // Force garbage collection
    if (function_exists('gc_collect_cycles')) {
      gc_collect_cycles();
    }
  }
}

function wc1c_xml_character_data_handler($parser, $data) {
  global $wc1c_namespace, $wc1c_is_full, $wc1c_names, $wc1c_depth;

  $name = $wc1c_names[$wc1c_depth];

  call_user_func("wc1c_{$wc1c_namespace}_character_data_handler", $wc1c_is_full, $wc1c_names, $wc1c_depth, $name, $data);
}

function wc1c_xml_end_element_handler($parser, $name) {
  global $wc1c_namespace, $wc1c_is_full, $wc1c_names, $wc1c_depth;

  call_user_func("wc1c_{$wc1c_namespace}_end_element_handler", $wc1c_is_full, $wc1c_names, $wc1c_depth, $name);

  array_pop($wc1c_names);
  $wc1c_depth--;
}

function wc1c_xml_parse($fp) {
  $parser = xml_parser_create();

  xml_set_element_handler($parser, 'wc1c_xml_start_element_handler', 'wc1c_xml_end_element_handler');
  xml_set_character_data_handler($parser, 'wc1c_xml_character_data_handler');

  $meta_data = stream_get_meta_data($fp);
  $filename = basename($meta_data['uri']);

  if (function_exists('wc1c_log')) {
    wc1c_log("Starting XML parse for file: $filename", 'DEBUG');
  }

  while (!($is_final = feof($fp))) {
    if (($data = fread($fp, 4096)) === false) wc1c_error(sprintf("Failed to read from file %s", $filename));
    if (!xml_parse($parser, $data, $is_final)) {
      $message = sprintf("%s in %s on line %d", xml_error_string(xml_get_error_code($parser)), $filename, xml_get_current_line_number($parser));
      wc1c_error($message, "XML Error");
    }
  }

  xml_parser_free($parser);

  if (function_exists('wc1c_log')) {
    wc1c_log("Completed XML parse for file: $filename", 'DEBUG');
  }
}

function wc1c_xml_parse_head($fp) {
  $is_full = null;
  $is_moysklad = null;
  while (($buffer = fgets($fp)) !== false) {
    if (strpos($buffer, " СинхронизацияТоваров=") !== false) $is_moysklad = true;

    if (strpos($buffer, " СодержитТолькоИзменения=") === false && strpos($buffer, "<СодержитТолькоИзменения>") === false) continue;

    $is_full = strpos($buffer, " СодержитТолькоИзменения=\"false\"") !== false || strpos($buffer, "<СодержитТолькоИзменения>false<") !== false;
    break;
  }

  $meta_data = stream_get_meta_data($fp);
  $filename = basename($meta_data['uri']);

  rewind($fp) or wc1c_error(sprintf("Failed to rewind on file %s", $filename));

  return array($is_full, $is_moysklad);
}

function wc1c_mode_import($type, $filename, $namespace = null) {
  global $wc1c_namespace, $wc1c_is_full, $wc1c_is_moysklad, $wc1c_names, $wc1c_depth;

  // Validate type parameter
  $allowed_types = array('catalog', 'sale');
  if (!in_array($type, $allowed_types)) {
    wc1c_error("Invalid type parameter");
  }

  // Validate and sanitize filename
  if (!$filename || !is_string($filename)) {
    wc1c_error("Invalid filename parameter");
  }

  // Prevent path traversal
  $filename = basename($filename);
  if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    wc1c_error("Invalid filename - path traversal detected");
  }

  // Validate file extension
  $allowed_extensions = array('xml');
  $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  if (!in_array($file_extension, $allowed_extensions)) {
    wc1c_error("Invalid file extension");
  }

  if ($type == 'catalog') wc1c_unpack_files($type);

  $path = WC1C_DATA_DIR . $type . '/' . $filename;

  // Verify the resolved path is within allowed directory
  $real_path = realpath($path);
  $allowed_dir = realpath(WC1C_DATA_DIR . $type);
  if (!$real_path || !$allowed_dir || strpos($real_path, $allowed_dir) !== 0) {
    wc1c_error("Path traversal attempt detected");
  }

  $fp = fopen($path, 'r') or wc1c_error(sprintf("Failed to open file %s", $filename));
  flock($fp, LOCK_EX) or wc1c_error(sprintf("Failed to lock file %s", $filename));

  wc1c_set_transaction_mode();

  if (!$namespace) $namespace = preg_replace("/^([a-zA-Z]+).+/", '$1', $filename);
  if (!in_array($namespace, array('import', 'offers', 'orders'))) wc1c_error(sprintf("Unknown import file type: %s", $namespace));

  $wc1c_namespace = $namespace;
  list($wc1c_is_full, $wc1c_is_moysklad) = wc1c_xml_parse_head($fp);
  $wc1c_names = array();
  $wc1c_depth = -1;

  if (function_exists('wc1c_log')) {
    wc1c_log("Starting import: type=$type, file=$filename, namespace=$namespace, is_full=" . ($wc1c_is_full ? 'true' : 'false'), 'INFO');
  }

  require_once sprintf(WC1C_PLUGIN_DIR . "exchange/%s.php", $namespace);

  wc1c_xml_parse($fp);

  flock($fp, LOCK_UN) or wc1c_error(sprintf("Failed to unlock file %s", $filename));
  fclose($fp) or wc1c_error(sprintf("Failed to close file %s", $filename));

  if (function_exists('wc1c_log')) {
    wc1c_log("Completed import: type=$type, file=$filename", 'INFO');
  }

  exit("success");
}

function wc1c_post_id_by_meta($key, $value) {
  global $wpdb;

  if ($value === null) return;

  $cache_key = "wc1c_post_id_by_meta-$key-$value";
  $post_id = wp_cache_get($cache_key);
  if ($post_id === false) {
    $post_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta JOIN $wpdb->posts ON post_id = ID WHERE meta_key = %s AND meta_value = %s", $key, $value));
    wc1c_check_wpdb_error();

    if ($post_id) wp_cache_set($cache_key, $post_id);
  }

  return $post_id;
}

function wc1c_mode_query($type) {
  if (function_exists('wc1c_log')) {
    wc1c_log("Query mode for type: $type", 'INFO');
  }
  
  include WC1C_PLUGIN_DIR . "exchange/query.php";
  exit;
}

function wc1c_mode_success($type) {
  if (function_exists('wc1c_log')) {
    wc1c_log("Success mode for type: $type", 'INFO');
  }
  
  include WC1C_PLUGIN_DIR . "exchange/success.php";
  exit("success");
}

function wc1c_exchange() {
  wc1c_set_strict_mode();
  wc1c_set_output_callback();
  wc1c_fix_fastcgi_get();

  // Validate and sanitize type parameter
  if (empty($_GET['type'])) wc1c_error("No type");
  $type = sanitize_text_field($_GET['type']);
  $allowed_types = array('catalog', 'sale');
  if (!in_array($type, $allowed_types)) {
    wc1c_error("Invalid type parameter");
  }

  // Validate and sanitize mode parameter
  if (empty($_GET['mode'])) wc1c_error("No mode");
  $mode = sanitize_text_field($_GET['mode']);
  $allowed_modes = array('checkauth', 'init', 'file', 'import', 'query', 'success');
  if (!in_array($mode, $allowed_modes)) {
    wc1c_error("Invalid mode parameter");
  }

  if (function_exists('wc1c_log')) {
    wc1c_log("Exchange request: type=$type, mode=$mode", 'INFO');
  }

  if ($mode == 'checkauth') {
    wc1c_mode_checkauth();
  }

  wc1c_check_auth();

  // Validate filename parameter when required
  $filename = null;
  if (in_array($mode, array('file', 'import')) && isset($_GET['filename'])) {
    $filename = sanitize_file_name($_GET['filename']);
    if (empty($filename)) {
      wc1c_error("Invalid filename parameter");
    }
  }

  if ($mode == 'init') {
    wc1c_mode_init($type);
  } elseif ($mode == 'file') {
    wc1c_mode_file($type, $filename);
  } elseif ($mode == 'import') {
    wc1c_mode_import($type, $filename);
  } elseif ($mode == 'query') {
    wc1c_mode_query($type);
  } elseif ($mode == 'success') {
    wc1c_mode_success($type);
  }
}

function wc1c_template_redirect() {
  $value = get_query_var('wc1c');
  if (empty($value)) return;

  if (strpos($value, '?') !== false) {
    list($value, $query) = explode('?', $value, 2);
    parse_str($query, $query);
    $_GET = array_merge($_GET, $query);
  }
  $_GET['wc1c'] = $value;

  if ($value == 'exchange') {
    wc1c_exchange();
  } elseif ($value == 'clean') {
    require_once WC1C_PLUGIN_DIR . "clean.php";
    exit;
  }
}

function wc1c_check_memory_usage() {
  $memory_limit = ini_get('memory_limit');
  $memory_usage = memory_get_usage(true);
  $memory_peak = memory_get_peak_usage(true);

  // Convert memory limit to bytes
  $limit_bytes = wc1c_filesize_to_bytes($memory_limit);

  // If using more than 80% of memory limit, trigger cleanup
  if ($memory_usage > ($limit_bytes * 0.8)) {
    wp_cache_flush();
    if (function_exists('gc_collect_cycles')) {
      gc_collect_cycles();
    }

    // Log warning
    if (function_exists('wc1c_log')) {
      wc1c_log("High memory usage detected. Current: " . size_format($memory_usage) . " Peak: " . size_format($memory_peak) . " Limit: " . $memory_limit, 'WARNING');
    }
  }
}

add_action('template_redirect', 'wc1c_template_redirect', -10);