<?php
/**
 * File handling functionality for 1C integration
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
 * File handling functionality for 1C integration
 */
class WC1C_File_Handler {

    /**
     * Data directory path
     *
     * @var string
     */
    private $data_dir;

    /**
     * Allowed file extensions
     *
     * @var array
     */
    private $allowed_extensions = array('xml', 'zip');

    /**
     * Maximum file size (in bytes)
     *
     * @var int
     */
    private $max_file_size;

    /**
     * Constructor
     *
     * @param string $data_dir Data directory path
     */
    public function __construct($data_dir) {
        $this->data_dir = rtrim($data_dir, '/');
        $this->max_file_size = $this->get_max_file_size();
        
        $this->ensure_directories();
    }

    /**
     * Handle file upload
     *
     * @param string $type Exchange type (catalog/sale)
     * @param string $filename Filename
     * @return bool Success status
     */
    public function handle_upload($type, $filename) {
        try {
            // Validate parameters
            $this->validate_upload_params($type, $filename);

            // Get file path
            $file_path = $this->get_file_path($type, $filename);

            // Handle the upload
            $result = $this->process_upload($file_path);

            if ($result) {
                WC1C_Logger::log("File uploaded successfully: {$filename}", 'info', array(
                    'type' => $type,
                    'size' => filesize($file_path),
                    'path' => $file_path
                ));

                // Process ZIP files immediately
                if ($this->is_zip_file($filename)) {
                    $this->extract_zip_file($file_path, dirname($file_path));
                }
            }

            return $result;

        } catch (Exception $e) {
            WC1C_Logger::log("File upload failed: " . $e->getMessage(), 'error', array(
                'type' => $type,
                'filename' => $filename
            ));
            return false;
        }
    }

    /**
     * Extract ZIP file
     *
     * @param string $zip_path Path to ZIP file
     * @param string $extract_to Extraction directory
     * @return bool Success status
     */
    public function extract_zip_file($zip_path, $extract_to) {
        if (!file_exists($zip_path)) {
            throw new Exception("ZIP file not found: {$zip_path}");
        }

        // Try using system unzip command first
        if ($this->extract_with_system_unzip($zip_path, $extract_to)) {
            unlink($zip_path);
            return true;
        }

        // Fallback to PHP ZipArchive
        if (class_exists('ZipArchive')) {
            return $this->extract_with_zip_archive($zip_path, $extract_to);
        }

        throw new Exception('No ZIP extraction method available');
    }

    /**
     * Get file list from directory
     *
     * @param string $type Exchange type
     * @param string $extension File extension filter
     * @return array File list
     */
    public function get_file_list($type, $extension = null) {
        $dir_path = $this->data_dir . '/' . $type;
        
        if (!is_dir($dir_path)) {
            return array();
        }

        $files = array();
        $iterator = new DirectoryIterator($dir_path);

        foreach ($iterator as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }

            $filename = $file->getFilename();
            
            if ($extension && pathinfo($filename, PATHINFO_EXTENSION) !== $extension) {
                continue;
            }

            $files[] = array(
                'name' => $filename,
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
                'path' => $file->getPathname()
            );
        }

        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });

        return $files;
    }

    /**
     * Delete file
     *
     * @param string $type Exchange type
     * @param string $filename Filename
     * @return bool Success status
     */
    public function delete_file($type, $filename) {
        $file_path = $this->get_file_path($type, $filename);
        
        if (!file_exists($file_path)) {
            return false;
        }

        $result = unlink($file_path);
        
        if ($result) {
            WC1C_Logger::log("File deleted: {$filename}", 'info', array(
                'type' => $type,
                'path' => $file_path
            ));
        }

        return $result;
    }

    /**
     * Clean up old files
     *
     * @param string $type Exchange type
     * @param int $max_age Maximum age in seconds
     * @return int Number of files deleted
     */
    public function cleanup_old_files($type, $max_age = 86400) {
        $dir_path = $this->data_dir . '/' . $type;
        
        if (!is_dir($dir_path)) {
            return 0;
        }

        $deleted_count = 0;
        $cutoff_time = time() - $max_age;
        $iterator = new DirectoryIterator($dir_path);

        foreach ($iterator as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }

            if ($file->getMTime() < $cutoff_time) {
                if (unlink($file->getPathname())) {
                    $deleted_count++;
                }
            }
        }

        if ($deleted_count > 0) {
            WC1C_Logger::log("Cleaned up {$deleted_count} old files", 'info', array(
                'type' => $type,
                'max_age' => $max_age
            ));
        }

        return $deleted_count;
    }

    /**
     * Get file content
     *
     * @param string $type Exchange type
     * @param string $filename Filename
     * @return string|false File content or false on failure
     */
    public function get_file_content($type, $filename) {
        $file_path = $this->get_file_path($type, $filename);
        
        if (!file_exists($file_path)) {
            return false;
        }

        return file_get_contents($file_path);
    }

    /**
     * Write file content
     *
     * @param string $type Exchange type
     * @param string $filename Filename
     * @param string $content File content
     * @return bool Success status
     */
    public function write_file_content($type, $filename, $content) {
        $this->validate_filename($filename);
        
        $file_path = $this->get_file_path($type, $filename);
        $result = file_put_contents($file_path, $content, LOCK_EX);
        
        if ($result !== false) {
            WC1C_Logger::log("File written: {$filename}", 'debug', array(
                'type' => $type,
                'size' => strlen($content)
            ));
        }

        return $result !== false;
    }

    /**
     * Create backup of file
     *
     * @param string $type Exchange type
     * @param string $filename Filename
     * @return bool Success status
     */
    public function create_backup($type, $filename) {
        $source_path = $this->get_file_path($type, $filename);
        
        if (!file_exists($source_path)) {
            return false;
        }

        $backup_dir = $this->data_dir . '/backup/' . $type;
        wp_mkdir_p($backup_dir);

        $backup_filename = date('Y-m-d_H-i-s') . '_' . $filename;
        $backup_path = $backup_dir . '/' . $backup_filename;

        $result = copy($source_path, $backup_path);
        
        if ($result) {
            WC1C_Logger::log("File backup created: {$backup_filename}", 'debug', array(
                'original' => $filename,
                'backup_path' => $backup_path
            ));
        }

        return $result;
    }

    /**
     * Restore file from backup
     *
     * @param string $type Exchange type
     * @param string $backup_filename Backup filename
     * @param string $restore_filename Target filename
     * @return bool Success status
     */
    public function restore_from_backup($type, $backup_filename, $restore_filename) {
        $backup_path = $this->data_dir . '/backup/' . $type . '/' . $backup_filename;
        
        if (!file_exists($backup_path)) {
            return false;
        }

        $restore_path = $this->get_file_path($type, $restore_filename);
        $result = copy($backup_path, $restore_path);
        
        if ($result) {
            WC1C_Logger::log("File restored from backup", 'info', array(
                'backup' => $backup_filename,
                'restored' => $restore_filename
            ));
        }

        return $result;
    }

    /**
     * Get directory size
     *
     * @param string $type Exchange type
     * @return int Directory size in bytes
     */
    public function get_directory_size($type) {
        $dir_path = $this->data_dir . '/' . $type;
        
        if (!is_dir($dir_path)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir_path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * Validate file integrity
     *
     * @param string $type Exchange type
     * @param string $filename Filename
     * @return bool File is valid
     */
    public function validate_file_integrity($type, $filename) {
        $file_path = $this->get_file_path($type, $filename);
        
        if (!file_exists($file_path)) {
            return false;
        }

        // Check if file is readable
        if (!is_readable($file_path)) {
            return false;
        }

        // Check file size
        $file_size = filesize($file_path);
        if ($file_size === false || $file_size === 0) {
            return false;
        }

        // For XML files, check if they're well-formed
        if ($this->is_xml_file($filename)) {
            return $this->validate_xml_file($file_path);
        }

        // For ZIP files, check if they're valid
        if ($this->is_zip_file($filename)) {
            return $this->validate_zip_file($file_path);
        }

        return true;
    }

    /**
     * Get file hash
     *
     * @param string $type Exchange type
     * @param string $filename Filename
     * @param string $algorithm Hash algorithm
     * @return string|false File hash or false on failure
     */
    public function get_file_hash($type, $filename, $algorithm = 'md5') {
        $file_path = $this->get_file_path($type, $filename);
        
        if (!file_exists($file_path)) {
            return false;
        }

        return hash_file($algorithm, $file_path);
    }

    /**
     * Validate upload parameters
     *
     * @param string $type Exchange type
     * @param string $filename Filename
     * @throws Exception If validation fails
     */
    private function validate_upload_params($type, $filename) {
        // Validate type
        $allowed_types = array('catalog', 'sale');
        if (!in_array($type, $allowed_types)) {
            throw new Exception("Invalid exchange type: {$type}");
        }

        // Validate filename
        $this->validate_filename($filename);
    }

    /**
     * Validate filename
     *
     * @param string $filename Filename
     * @throws Exception If validation fails
     */
    private function validate_filename($filename) {
        // Check for empty filename
        if (empty($filename)) {
            throw new Exception('Filename cannot be empty');
        }

        // Check for path traversal
        if (strpos($filename, '..') !== false || 
            strpos($filename, '/') !== false || 
            strpos($filename, '\\') !== false) {
            throw new Exception('Invalid filename - path traversal detected');
        }

        // Check file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            throw new Exception("Invalid file extension: {$extension}");
        }

        // Check filename length
        if (strlen($filename) > 255) {
            throw new Exception('Filename too long');
        }

        // Check for invalid characters
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            throw new Exception('Filename contains invalid characters');
        }
    }

    /**
     * Get file path
     *
     * @param string $type Exchange type
     * @param string $filename Filename
     * @return string File path
     */
    private function get_file_path($type, $filename) {
        return $this->data_dir . '/' . $type . '/' . $filename;
    }

    /**
     * Process file upload
     *
     * @param string $file_path Target file path
     * @return bool Success status
     */
    private function process_upload($file_path) {
        $input = fopen('php://input', 'r');
        if (!$input) {
            throw new Exception('Failed to open input stream');
        }

        $temp_path = $file_path . '.tmp';
        $temp_file = fopen($temp_path, 'w');
        if (!$temp_file) {
            fclose($input);
            throw new Exception('Failed to create temporary file');
        }

        // Copy data with size limit check
        $bytes_written = 0;
        while (!feof($input)) {
            $chunk = fread($input, 8192);
            if ($chunk === false) {
                break;
            }

            $chunk_size = strlen($chunk);
            if ($bytes_written + $chunk_size > $this->max_file_size) {
                fclose($input);
                fclose($temp_file);
                unlink($temp_path);
                throw new Exception('File size exceeds maximum allowed size');
            }

            fwrite($temp_file, $chunk);
            $bytes_written += $chunk_size;
        }

        fclose($input);
        fclose($temp_file);

        // Check if this is a new file or continuation
        if (file_exists($file_path)) {
            $existing_header = file_get_contents($temp_path, false, null, 0, 32);
            if (strpos($existing_header, '<?xml') !== false) {
                // New XML file, replace existing
                unlink($file_path);
            }
        }

        // Append or move temporary file
        if (file_exists($file_path)) {
            // Append to existing file
            $result = file_put_contents($file_path, file_get_contents($temp_path), FILE_APPEND | LOCK_EX);
            unlink($temp_path);
            return $result !== false;
        } else {
            // Move temporary file
            return rename($temp_path, $file_path);
        }
    }

    /**
     * Extract ZIP using system unzip command
     *
     * @param string $zip_path ZIP file path
     * @param string $extract_to Extraction directory
     * @return bool Success status
     */
    private function extract_with_system_unzip($zip_path, $extract_to) {
        if (!$this->has_unzip_command()) {
            return false;
        }

        $command = sprintf(
            'unzip -qqo %s -d %s 2>&1',
            escapeshellarg($zip_path),
            escapeshellarg($extract_to)
        );

        exec($command, $output, $return_code);

        if ($return_code === 0) {
            WC1C_Logger::log('ZIP extracted using system unzip', 'debug', array(
                'zip_path' => $zip_path,
                'extract_to' => $extract_to
            ));
            return true;
        }

        WC1C_Logger::log('System unzip failed', 'warning', array(
            'command' => $command,
            'output' => implode("\n", $output),
            'return_code' => $return_code
        ));

        return false;
    }

    /**
     * Extract ZIP using PHP ZipArchive
     *
     * @param string $zip_path ZIP file path
     * @param string $extract_to Extraction directory
     * @return bool Success status
     */
    private function extract_with_zip_archive($zip_path, $extract_to) {
        $zip = new ZipArchive();
        $result = $zip->open($zip_path);

        if ($result !== true) {
            throw new Exception("Failed to open ZIP file: error code {$result}");
        }

        // Security check: validate file paths in ZIP
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Check for path traversal
            if (strpos($filename, '..') !== false || strpos($filename, '/') === 0) {
                $zip->close();
                throw new Exception("ZIP contains unsafe file path: {$filename}");
            }
        }

        $extract_result = $zip->extractTo($extract_to);
        $zip->close();

        if (!$extract_result) {
            throw new Exception('Failed to extract ZIP file');
        }

        // Remove the ZIP file after successful extraction
        unlink($zip_path);

        WC1C_Logger::log('ZIP extracted using ZipArchive', 'debug', array(
            'zip_path' => $zip_path,
            'extract_to' => $extract_to
        ));

        return true;
    }

    /**
     * Check if unzip command is available
     *
     * @return bool
     */
    private function has_unzip_command() {
        exec('which unzip', $output, $return_code);
        return $return_code === 0;
    }

    /**
     * Check if file is XML
     *
     * @param string $filename Filename
     * @return bool
     */
    private function is_xml_file($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'xml';
    }

    /**
     * Check if file is ZIP
     *
     * @param string $filename Filename
     * @return bool
     */
    private function is_zip_file($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'zip';
    }

    /**
     * Validate XML file
     *
     * @param string $file_path File path
     * @return bool File is valid XML
     */
    private function validate_xml_file($file_path) {
        libxml_use_internal_errors(true);
        
        $doc = new DOMDocument();
        $result = $doc->load($file_path);
        
        if (!$result) {
            $errors = libxml_get_errors();
            WC1C_Logger::log('XML validation failed', 'warning', array(
                'file' => basename($file_path),
                'errors' => array_map(function($error) {
                    return trim($error->message);
                }, $errors)
            ));
            libxml_clear_errors();
            return false;
        }

        libxml_clear_errors();
        return true;
    }

    /**
     * Validate ZIP file
     *
     * @param string $file_path File path
     * @return bool File is valid ZIP
     */
    private function validate_zip_file($file_path) {
        if (!class_exists('ZipArchive')) {
            // Can't validate without ZipArchive, assume valid
            return true;
        }

        $zip = new ZipArchive();
        $result = $zip->open($file_path, ZipArchive::CHECKCONS);
        
        if ($result !== true) {
            WC1C_Logger::log('ZIP validation failed', 'warning', array(
                'file' => basename($file_path),
                'error_code' => $result
            ));
            return false;
        }

        $zip->close();
        return true;
    }

    /**
     * Get maximum file size
     *
     * @return int Maximum file size in bytes
     */
    private function get_max_file_size() {
        $limits = array(
            $this->filesize_to_bytes('10M'), // Default minimum
            $this->filesize_to_bytes(ini_get('post_max_size')),
            $this->filesize_to_bytes(ini_get('upload_max_filesize')),
        );

        // Add custom limit if set
        $custom_limit = get_option('wc1c_max_file_size', '100M');
        if ($custom_limit) {
            $limits[] = $this->filesize_to_bytes($custom_limit);
        }

        return min(array_filter($limits));
    }

    /**
     * Convert filesize string to bytes
     *
     * @param string $size Size string (e.g., '10M', '1G')
     * @return int Size in bytes
     */
    private function filesize_to_bytes($size) {
        if (empty($size)) {
            return 0;
        }

        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;

        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }

    /**
     * Ensure required directories exist
     */
    private function ensure_directories() {
        $directories = array(
            $this->data_dir,
            $this->data_dir . '/catalog',
            $this->data_dir . '/sale',
            $this->data_dir . '/temp',
            $this->data_dir . '/backup',
            $this->data_dir . '/backup/catalog',
            $this->data_dir . '/backup/sale'
        );

        foreach ($directories as $dir) {
            if (!wp_mkdir_p($dir)) {
                throw new Exception("Failed to create directory: {$dir}");
            }

            // Add security files
            $index_file = $dir . '/index.html';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '');
            }

            // Add .htaccess for non-backup directories
            if (strpos($dir, '/backup') === false) {
                $htaccess_file = $dir . '/.htaccess';
                if (!file_exists($htaccess_file)) {
                    $htaccess_content = "Deny from all\n<Files \"*.xml\">\n  Allow from all\n</Files>";
                    file_put_contents($htaccess_file, $htaccess_content);
                }
            }
        }
    }

    /**
     * Get file statistics
     *
     * @param string $type Exchange type
     * @return array File statistics
     */
    public function get_file_stats($type) {
        $dir_path = $this->data_dir . '/' . $type;
        
        if (!is_dir($dir_path)) {
            return array(
                'total_files' => 0,
                'total_size' => 0,
                'xml_files' => 0,
                'zip_files' => 0,
                'oldest_file' => null,
                'newest_file' => null
            );
        }

        $stats = array(
            'total_files' => 0,
            'total_size' => 0,
            'xml_files' => 0,
            'zip_files' => 0,
            'oldest_file' => null,
            'newest_file' => null
        );

        $iterator = new DirectoryIterator($dir_path);
        $oldest_time = PHP_INT_MAX;
        $newest_time = 0;

        foreach ($iterator as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }

            $stats['total_files']++;
            $stats['total_size'] += $file->getSize();

            $extension = strtolower($file->getExtension());
            if ($extension === 'xml') {
                $stats['xml_files']++;
            } elseif ($extension === 'zip') {
                $stats['zip_files']++;
            }

            $mtime = $file->getMTime();
            if ($mtime < $oldest_time) {
                $oldest_time = $mtime;
                $stats['oldest_file'] = array(
                    'name' => $file->getFilename(),
                    'time' => $mtime
                );
            }

            if ($mtime > $newest_time) {
                $newest_time = $mtime;
                $stats['newest_file'] = array(
                    'name' => $file->getFilename(),
                    'time' => $mtime
                );
            }
        }

        return $stats;
    }

    /**
     * Monitor file changes
     *
     * @param string $type Exchange type
     * @param callable $callback Callback function for file changes
     * @param int $interval Check interval in seconds
     * @param int $duration Monitoring duration in seconds
     */
    public function monitor_file_changes($type, $callback, $interval = 5, $duration = 300) {
        $dir_path = $this->data_dir . '/' . $type;
        $start_time = time();
        $last_check = array();

        while ((time() - $start_time) < $duration) {
            $current_files = array();
            
            if (is_dir($dir_path)) {
                $iterator = new DirectoryIterator($dir_path);
                
                foreach ($iterator as $file) {
                    if ($file->isDot() || $file->isDir()) {
                        continue;
                    }
                    
                    $filename = $file->getFilename();
                    $current_files[$filename] = $file->getMTime();
                }
            }

            // Check for new or modified files
            foreach ($current_files as $filename => $mtime) {
                if (!isset($last_check[$filename]) || $last_check[$filename] !== $mtime) {
                    $action = isset($last_check[$filename]) ? 'modified' : 'created';
                    call_user_func($callback, $filename, $action, $mtime);
                }
            }

            // Check for deleted files
            foreach ($last_check as $filename => $mtime) {
                if (!isset($current_files[$filename])) {
                    call_user_func($callback, $filename, 'deleted', null);
                }
            }

            $last_check = $current_files;
            sleep($interval);
        }
    }
}