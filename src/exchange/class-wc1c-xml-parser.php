<?php
/**
 * XML parsing functionality for 1C integration
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * XML parsing functionality for 1C integration
 */
class WC1C_XML_Parser {

    /**
     * XML parser resource
     *
     * @var resource
     */
    private $parser;

    /**
     * Current element stack
     *
     * @var array
     */
    private $element_stack = array();

    /**
     * Current depth
     *
     * @var int
     */
    private $depth = 0;

    /**
     * Character data buffer
     *
     * @var string
     */
    private $char_data = '';

    /**
     * Parsing context
     *
     * @var array
     */
    private $context = array();

    /**
     * Event handlers
     *
     * @var array
     */
    private $handlers = array();

    /**
     * Parsing statistics
     *
     * @var array
     */
    private $stats = array(
        'elements_processed' => 0,
        'memory_peak' => 0,
        'start_time' => 0,
        'end_time' => 0
    );

    /**
     * Memory management settings
     *
     * @var array
     */
    private $memory_settings = array(
        'cleanup_interval' => 1000,
        'memory_limit_percent' => 80
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_parser();
        $this->init_context();
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->cleanup_parser();
    }

    /**
     * Parse XML file
     *
     * @param string $file_path Path to XML file
     * @param string $namespace Parsing namespace
     * @return array Parsing results
     */
    public function parse_file($file_path, $namespace = 'default') {
        if (!file_exists($file_path)) {
            throw new Exception("XML file not found: {$file_path}");
        }

        $this->stats['start_time'] = microtime(true);
        $this->context['namespace'] = $namespace;
        $this->context['file_path'] = $file_path;

        WC1C_Logger::log("Starting XML parsing", 'debug', array(
            'file' => basename($file_path),
            'namespace' => $namespace,
            'size' => filesize($file_path)
        ));

        try {
            $fp = fopen($file_path, 'r');
            if (!$fp) {
                throw new Exception("Failed to open XML file: {$file_path}");
            }

            // Parse file header to determine exchange type
            $this->parse_header($fp);

            // Reset file pointer
            rewind($fp);

            // Parse the entire file
            $this->parse_stream($fp);

            fclose($fp);

            $this->stats['end_time'] = microtime(true);
            $this->stats['memory_peak'] = memory_get_peak_usage(true);

            WC1C_Logger::log("XML parsing completed", 'info', array(
                'file' => basename($file_path),
                'elements' => $this->stats['elements_processed'],
                'time' => round($this->stats['end_time'] - $this->stats['start_time'], 2),
                'memory_peak' => size_format($this->stats['memory_peak'])
            ));

            return $this->get_parsing_results();

        } catch (Exception $e) {
            WC1C_Logger::log("XML parsing failed: " . $e->getMessage(), 'error', array(
                'file' => basename($file_path),
                'line' => xml_get_current_line_number($this->parser)
            ));
            throw $e;
        }
    }

    /**
     * Parse XML string
     *
     * @param string $xml_string XML content
     * @param string $namespace Parsing namespace
     * @return array Parsing results
     */
    public function parse_string($xml_string, $namespace = 'default') {
        $this->stats['start_time'] = microtime(true);
        $this->context['namespace'] = $namespace;

        try {
            if (!xml_parse($this->parser, $xml_string, true)) {
                $this->handle_xml_error();
            }

            $this->stats['end_time'] = microtime(true);
            $this->stats['memory_peak'] = memory_get_peak_usage(true);

            return $this->get_parsing_results();

        } catch (Exception $e) {
            WC1C_Logger::log("XML string parsing failed: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Register event handler
     *
     * @param string $event Event name
     * @param callable $handler Event handler
     */
    public function register_handler($event, $handler) {
        if (!isset($this->handlers[$event])) {
            $this->handlers[$event] = array();
        }
        $this->handlers[$event][] = $handler;
    }

    /**
     * Set memory management settings
     *
     * @param array $settings Memory settings
     */
    public function set_memory_settings($settings) {
        $this->memory_settings = array_merge($this->memory_settings, $settings);
    }

    /**
     * Get parsing statistics
     *
     * @return array Parsing statistics
     */
    public function get_stats() {
        return $this->stats;
    }

    /**
     * Initialize XML parser
     */
    private function init_parser() {
        $this->parser = xml_parser_create('UTF-8');
        
        if (!$this->parser) {
            throw new Exception('Failed to create XML parser');
        }

        // Set parser options
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
        xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, false);

        // Set element handlers
        xml_set_element_handler($this->parser, array($this, 'start_element'), array($this, 'end_element'));
        xml_set_character_data_handler($this->parser, array($this, 'character_data'));
    }

    /**
     * Initialize parsing context
     */
    private function init_context() {
        $this->context = array(
            'namespace' => 'default',
            'is_full_exchange' => null,
            'is_moysklad' => false,
            'exchange_type' => null,
            'document_version' => null,
            'current_element' => null,
            'data' => array()
        );
    }

    /**
     * Cleanup XML parser
     */
    private function cleanup_parser() {
        if ($this->parser) {
            xml_parser_free($this->parser);
            $this->parser = null;
        }
    }

    /**
     * Parse file header
     *
     * @param resource $fp File pointer
     */
    private function parse_header($fp) {
        $header_lines = 0;
        $max_header_lines = 20;

        while (($line = fgets($fp)) !== false && $header_lines < $max_header_lines) {
            $header_lines++;

            // Check for MoySklad integration
            if (strpos($line, 'СинхронизацияТоваров=') !== false) {
                $this->context['is_moysklad'] = true;
            }

            // Check for full/partial exchange
            if (strpos($line, 'СодержитТолькоИзменения=') !== false || 
                strpos($line, '<СодержитТолькоИзменения>') !== false) {
                
                $this->context['is_full_exchange'] = 
                    strpos($line, 'СодержитТолькоИзменения="false"') !== false ||
                    strpos($line, '<СодержитТолькоИзменения>false<') !== false;
                break;
            }
        }

        WC1C_Logger::log("XML header parsed", 'debug', array(
            'is_full_exchange' => $this->context['is_full_exchange'],
            'is_moysklad' => $this->context['is_moysklad']
        ));
    }

    /**
     * Parse XML stream
     *
     * @param resource $fp File pointer
     */
    private function parse_stream($fp) {
        $chunk_size = 8192;

        while (!feof($fp)) {
            $data = fread($fp, $chunk_size);
            
            if ($data === false) {
                throw new Exception('Failed to read XML data');
            }

            $is_final = feof($fp);
            
            if (!xml_parse($this->parser, $data, $is_final)) {
                $this->handle_xml_error();
            }

            // Memory management
            $this->manage_memory();
        }
    }

    /**
     * Handle XML parsing error
     */
    private function handle_xml_error() {
        $error_code = xml_get_error_code($this->parser);
        $error_string = xml_error_string($error_code);
        $line_number = xml_get_current_line_number($this->parser);
        $column_number = xml_get_current_column_number($this->parser);

        throw new Exception(
            "XML Error: {$error_string} at line {$line_number}, column {$column_number}"
        );
    }

    /**
     * Start element handler
     *
     * @param resource $parser XML parser
     * @param string $name Element name
     * @param array $attrs Element attributes
     */
    public function start_element($parser, $name, $attrs) {
        $this->stats['elements_processed']++;
        $this->depth++;
        $this->element_stack[] = $name;
        $this->char_data = '';

        // Update context
        $this->context['current_element'] = $name;

        // Trigger event handlers
        $this->trigger_event('start_element', array(
            'name' => $name,
            'attributes' => $attrs,
            'depth' => $this->depth,
            'path' => $this->get_element_path()
        ));

        // Handle specific elements
        $this->handle_start_element($name, $attrs);
    }

    /**
     * End element handler
     *
     * @param resource $parser XML parser
     * @param string $name Element name
     */
    public function end_element($parser, $name) {
        $this->depth--;
        array_pop($this->element_stack);

        // Trigger event handlers
        $this->trigger_event('end_element', array(
            'name' => $name,
            'data' => trim($this->char_data),
            'depth' => $this->depth,
            'path' => $this->get_element_path()
        ));

        // Handle specific elements
        $this->handle_end_element($name, trim($this->char_data));

        $this->char_data = '';
    }

    /**
     * Character data handler
     *
     * @param resource $parser XML parser
     * @param string $data Character data
     */
    public function character_data($parser, $data) {
        $this->char_data .= $data;
    }

    /**
     * Handle start element
     *
     * @param string $name Element name
     * @param array $attrs Element attributes
     */
    private function handle_start_element($name, $attrs) {
        $path = $this->get_element_path();

        switch ($name) {
            case 'КоммерческаяИнформация':
                $this->context['document_version'] = $attrs['ВерсияСхемы'] ?? null;
                break;

            case 'Каталог':
                $this->context['exchange_type'] = 'catalog';
                break;

            case 'ПакетПредложений':
                $this->context['exchange_type'] = 'offers';
                break;

            case 'Документ':
                $this->context['exchange_type'] = 'orders';
                break;
        }

        // Initialize data structures based on context
        $this->init_data_structure($name, $path);
    }

    /**
     * Handle end element
     *
     * @param string $name Element name
     * @param string $data Element data
     */
    private function handle_end_element($name, $data) {
        $path = $this->get_element_path();

        // Store element data
        $this->store_element_data($name, $data, $path);

        // Process completed structures
        $this->process_completed_structure($name);
    }

    /**
     * Initialize data structure
     *
     * @param string $name Element name
     * @param string $path Element path
     */
    private function init_data_structure($name, $path) {
        // Initialize arrays for collection elements
        $collection_elements = array(
            'Группы' => 'groups',
            'Товары' => 'products',
            'Свойства' => 'properties',
            'Предложения' => 'offers',
            'ТипыЦен' => 'price_types'
        );

        if (isset($collection_elements[$name])) {
            $key = $collection_elements[$name];
            if (!isset($this->context['data'][$key])) {
                $this->context['data'][$key] = array();
            }
        }
    }

    /**
     * Store element data
     *
     * @param string $name Element name
     * @param string $data Element data
     * @param string $path Element path
     */
    private function store_element_data($name, $data, $path) {
        if (empty($data)) {
            return;
        }

        // Store data based on current context
        $this->set_nested_value($this->context['data'], $path, $data);
    }

    /**
     * Process completed structure
     *
     * @param string $name Element name
     */
    private function process_completed_structure($name) {
        switch ($name) {
            case 'Группа':
                $this->process_group();
                break;

            case 'Товар':
                $this->process_product();
                break;

            case 'Свойство':
                $this->process_property();
                break;

            case 'Предложение':
                $this->process_offer();
                break;

            case 'Документ':
                $this->process_document();
                break;
        }
    }

    /**
     * Process group element
     */
    private function process_group() {
        $this->trigger_event('group_processed', array(
            'data' => $this->get_current_structure_data('group')
        ));
    }

    /**
     * Process product element
     */
    private function process_product() {
        $this->trigger_event('product_processed', array(
            'data' => $this->get_current_structure_data('product')
        ));
    }

    /**
     * Process property element
     */
    private function process_property() {
        $this->trigger_event('property_processed', array(
            'data' => $this->get_current_structure_data('property')
        ));
    }

    /**
     * Process offer element
     */
    private function process_offer() {
        $this->trigger_event('offer_processed', array(
            'data' => $this->get_current_structure_data('offer')
        ));
    }

    /**
     * Process document element
     */
    private function process_document() {
        $this->trigger_event('document_processed', array(
            'data' => $this->get_current_structure_data('document')
        ));
    }

    /**
     * Get current structure data
     *
     * @param string $type Structure type
     * @return array Structure data
     */
    private function get_current_structure_data($type) {
        // This would extract the current structure data based on type
        // Implementation depends on the specific data structure
        return array();
    }

    /**
     * Get element path
     *
     * @return string Element path
     */
    private function get_element_path() {
        return implode('/', $this->element_stack);
    }

    /**
     * Set nested value in array
     *
     * @param array &$array Target array
     * @param string $path Dot-separated path
     * @param mixed $value Value to set
     */
    private function set_nested_value(&$array, $path, $value) {
        $keys = explode('/', $path);
        $current = &$array;

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                $current[$key] = array();
            }
            $current = &$current[$key];
        }

        $current = $value;
    }

    /**
     * Trigger event
     *
     * @param string $event Event name
     * @param array $data Event data
     */
    private function trigger_event($event, $data) {
        if (!isset($this->handlers[$event])) {
            return;
        }

        foreach ($this->handlers[$event] as $handler) {
            if (is_callable($handler)) {
                call_user_func($handler, $data, $this->context);
            }
        }
    }

    /**
     * Manage memory usage
     */
    private function manage_memory() {
        if ($this->stats['elements_processed'] % $this->memory_settings['cleanup_interval'] !== 0) {
            return;
        }

        $memory_usage = memory_get_usage(true);
        $memory_limit = $this->get_memory_limit();
        $usage_percent = ($memory_usage / $memory_limit) * 100;

        if ($usage_percent > $this->memory_settings['memory_limit_percent']) {
            // Clear processed data
            $this->cleanup_processed_data();

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            WC1C_Logger::log("Memory cleanup performed", 'debug', array(
                'usage_before' => size_format($memory_usage),
                'usage_after' => size_format(memory_get_usage(true)),
                'usage_percent' => round($usage_percent, 2)
            ));
        }
    }

    /**
     * Get memory limit in bytes
     *
     * @return int Memory limit
     */
    private function get_memory_limit() {
        $limit = ini_get('memory_limit');
        
        if ($limit == -1) {
            return PHP_INT_MAX;
        }

        return $this->convert_to_bytes($limit);
    }

    /**
     * Convert size string to bytes
     *
     * @param string $size Size string
     * @return int Size in bytes
     */
    private function convert_to_bytes($size) {
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
     * Cleanup processed data
     */
    private function cleanup_processed_data() {
        // Clear large data structures that are no longer needed
        $this->context['data'] = array_filter($this->context['data'], function($value, $key) {
            // Keep only essential data
            return in_array($key, array('current_item', 'counters'));
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get parsing results
     *
     * @return array Parsing results
     */
    private function get_parsing_results() {
        return array(
            'context' => $this->context,
            'stats' => $this->stats,
            'success' => true
        );
    }

    /**
     * Validate XML structure
     *
     * @param string $file_path XML file path
     * @return bool Validation result
     */
    public function validate_structure($file_path) {
        try {
            libxml_use_internal_errors(true);
            
            $doc = new DOMDocument();
            $doc->load($file_path);
            
            $errors = libxml_get_errors();
            
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    WC1C_Logger::log("XML validation error: " . trim($error->message), 'warning', array(
                        'line' => $error->line,
                        'column' => $error->column
                    ));
                }
                return false;
            }

            return true;

        } catch (Exception $e) {
            WC1C_Logger::log("XML validation failed: " . $e->getMessage(), 'error');
            return false;
        } finally {
            libxml_clear_errors();
        }
    }

    /**
     * Extract metadata from XML
     *
     * @param string $file_path XML file path
     * @return array Metadata
     */
    public function extract_metadata($file_path) {
        $metadata = array(
            'version' => null,
            'date' => null,
            'type' => null,
            'is_full' => null,
            'encoding' => null,
            'size' => filesize($file_path)
        );

        try {
            $fp = fopen($file_path, 'r');
            if (!$fp) {
                return $metadata;
            }

            // Read first few lines to extract metadata
            $lines_read = 0;
            while (($line = fgets($fp)) !== false && $lines_read < 50) {
                $lines_read++;

                // Extract encoding from XML declaration
                if (strpos($line, '<?xml') !== false && preg_match('/encoding="([^"]+)"/', $line, $matches)) {
                    $metadata['encoding'] = $matches[1];
                }

                // Extract version
                if (preg_match('/ВерсияСхемы="([^"]+)"/', $line, $matches)) {
                    $metadata['version'] = $matches[1];
                }

                // Extract date
                if (preg_match('/ДатаФормирования="([^"]+)"/', $line, $matches)) {
                    $metadata['date'] = $matches[1];
                }

                // Determine exchange type
                if (strpos($line, '<Каталог') !== false) {
                    $metadata['type'] = 'catalog';
                } elseif (strpos($line, '<ПакетПредложений') !== false) {
                    $metadata['type'] = 'offers';
                } elseif (strpos($line, '<Документ') !== false) {
                    $metadata['type'] = 'orders';
                }

                // Check if full exchange
                if (strpos($line, 'СодержитТолькоИзменения=') !== false) {
                    $metadata['is_full'] = strpos($line, 'СодержитТолькоИзменения="false"') !== false;
                }
            }

            fclose($fp);

        } catch (Exception $e) {
            WC1C_Logger::log("Failed to extract XML metadata: " . $e->getMessage(), 'warning');
        }

        return $metadata;
    }

    /**
     * Split large XML file into chunks
     *
     * @param string $file_path XML file path
     * @param int $chunk_size Chunk size in bytes
     * @return array Chunk file paths
     */
    public function split_xml_file($file_path, $chunk_size = 10485760) { // 10MB default
        $chunks = array();
        $chunk_dir = dirname($file_path) . '/chunks';
        wp_mkdir_p($chunk_dir);

        try {
            $reader = new XMLReader();
            $reader->open($file_path);

            $chunk_index = 0;
            $current_chunk_size = 0;
            $current_chunk_content = '';
            $header = '';
            $footer = '';

            // Extract header
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'КоммерческаяИнформация') {
                    $header = $reader->readOuterXML();
                    break;
                }
            }

            // Process elements
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    $element_xml = $reader->readOuterXML();
                    $element_size = strlen($element_xml);

                    if ($current_chunk_size + $element_size > $chunk_size && !empty($current_chunk_content)) {
                        // Save current chunk
                        $chunk_file = $this->save_chunk($chunk_dir, $chunk_index, $header, $current_chunk_content, $footer);
                        $chunks[] = $chunk_file;

                        // Start new chunk
                        $chunk_index++;
                        $current_chunk_content = $element_xml;
                        $current_chunk_size = $element_size;
                    } else {
                        $current_chunk_content .= $element_xml;
                        $current_chunk_size += $element_size;
                    }
                }
            }

            // Save last chunk
            if (!empty($current_chunk_content)) {
                $chunk_file = $this->save_chunk($chunk_dir, $chunk_index, $header, $current_chunk_content, $footer);
                $chunks[] = $chunk_file;
            }

            $reader->close();

        } catch (Exception $e) {
            WC1C_Logger::log("Failed to split XML file: " . $e->getMessage(), 'error');
        }

        return $chunks;
    }

    /**
     * Save XML chunk to file
     *
     * @param string $chunk_dir Chunk directory
     * @param int $index Chunk index
     * @param string $header XML header
     * @param string $content XML content
     * @param string $footer XML footer
     * @return string Chunk file path
     */
    private function save_chunk($chunk_dir, $index, $header, $content, $footer) {
        $chunk_file = $chunk_dir . '/chunk_' . str_pad($index, 3, '0', STR_PAD_LEFT) . '.xml';
        
        $xml_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml_content .= $header . "\n";
        $xml_content .= $content . "\n";
        $xml_content .= '</КоммерческаяИнформация>';

        file_put_contents($chunk_file, $xml_content);

        return $chunk_file;
    }

    /**
     * Merge XML files
     *
     * @param array $file_paths Array of XML file paths
     * @param string $output_path Output file path
     * @return bool Success status
     */
    public function merge_xml_files($file_paths, $output_path) {
        if (empty($file_paths)) {
            return false;
        }

        try {
            $output = fopen($output_path, 'w');
            if (!$output) {
                throw new Exception("Failed to create output file: {$output_path}");
            }

            $header_written = false;
            $footer = '</КоммерческаяИнформация>';

            foreach ($file_paths as $file_path) {
                if (!file_exists($file_path)) {
                    continue;
                }

                $content = file_get_contents($file_path);
                
                if (!$header_written) {
                    // Write header from first file
                    $header_end = strpos($content, '>') + 1;
                    fwrite($output, substr($content, 0, $header_end) . "\n");
                    $content = substr($content, $header_end);
                    $header_written = true;
                }

                // Remove XML declaration and root element tags
                $content = preg_replace('/<\?xml[^>]*\?>/', '', $content);
                $content = preg_replace('/<КоммерческаяИнформация[^>]*>/', '', $content);
                $content = str_replace('</КоммерческаяИнформация>', '', $content);

                fwrite($output, trim($content) . "\n");
            }

            // Write footer
            fwrite($output, $footer);
            fclose($output);

            return true;

        } catch (Exception $e) {
            WC1C_Logger::log("Failed to merge XML files: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Convert XML to array
     *
     * @param string $xml_string XML string
     * @return array Converted array
     */
    public function xml_to_array($xml_string) {
        try {
            $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
            return json_decode(json_encode($xml), true);
        } catch (Exception $e) {
            WC1C_Logger::log("Failed to convert XML to array: " . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Convert array to XML
     *
     * @param array $array Input array
     * @param string $root_element Root element name
     * @return string XML string
     */
    public function array_to_xml($array, $root_element = 'root') {
        try {
            $xml = new SimpleXMLElement("<{$root_element}></{$root_element}>");
            $this->array_to_xml_recursive($array, $xml);
            return $xml->asXML();
        } catch (Exception $e) {
            WC1C_Logger::log("Failed to convert array to XML: " . $e->getMessage(), 'error');
            return '';
        }
    }

    /**
     * Recursively convert array to XML
     *
     * @param array $array Input array
     * @param SimpleXMLElement $xml XML element
     */
    private function array_to_xml_recursive($array, &$xml) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item';
                }
                $subnode = $xml->addChild($key);
                $this->array_to_xml_recursive($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * Validate XML against schema
     *
     * @param string $xml_file XML file path
     * @param string $schema_file Schema file path
     * @return bool Validation result
     */
    public function validate_against_schema($xml_file, $schema_file) {
        if (!file_exists($schema_file)) {
            WC1C_Logger::log("Schema file not found: {$schema_file}", 'warning');
            return false;
        }

        try {
            libxml_use_internal_errors(true);

            $doc = new DOMDocument();
            $doc->load($xml_file);

            $result = $doc->schemaValidate($schema_file);

            if (!$result) {
                $errors = libxml_get_errors();
                foreach ($errors as $error) {
                    WC1C_Logger::log("Schema validation error: " . trim($error->message), 'warning', array(
                        'line' => $error->line,
                        'column' => $error->column
                    ));
                }
            }

            return $result;

        } catch (Exception $e) {
            WC1C_Logger::log("Schema validation failed: " . $e->getMessage(), 'error');
            return false;
        } finally {
            libxml_clear_errors();
        }
    }

    /**
     * Extract specific elements from XML
     *
     * @param string $file_path XML file path
     * @param string $element_name Element name to extract
     * @param callable $callback Callback for each element
     * @return int Number of elements processed
     */
    public function extract_elements($file_path, $element_name, $callback) {
        $count = 0;

        try {
            $reader = new XMLReader();
            $reader->open($file_path);

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === $element_name) {
                    $element_xml = $reader->readOuterXML();
                    $element_array = $this->xml_to_array($element_xml);
                    
                    if (is_callable($callback)) {
                        call_user_func($callback, $element_array, $count);
                    }
                    
                    $count++;
                }
            }

            $reader->close();

        } catch (Exception $e) {
            WC1C_Logger::log("Failed to extract elements: " . $e->getMessage(), 'error');
        }

        return $count;
    }

    /**
     * Transform XML using XSLT
     *
     * @param string $xml_file XML file path
     * @param string $xsl_file XSL file path
     * @param string $output_file Output file path
     * @return bool Success status
     */
    public function transform_with_xslt($xml_file, $xsl_file, $output_file) {
        if (!class_exists('XSLTProcessor')) {
            WC1C_Logger::log("XSLTProcessor not available", 'error');
            return false;
        }

        try {
            $xml_doc = new DOMDocument();
            $xml_doc->load($xml_file);

            $xsl_doc = new DOMDocument();
            $xsl_doc->load($xsl_file);

            $processor = new XSLTProcessor();
            $processor->importStylesheet($xsl_doc);

            $result = $processor->transformToDoc($xml_doc);
            
            if ($result) {
                $result->save($output_file);
                return true;
            }

            return false;

        } catch (Exception $e) {
            WC1C_Logger::log("XSLT transformation failed: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get XML file statistics
     *
     * @param string $file_path XML file path
     * @return array File statistics
     */
    public function get_file_statistics($file_path) {
        $stats = array(
            'file_size' => 0,
            'element_count' => 0,
            'max_depth' => 0,
            'encoding' => null,
            'version' => null,
            'root_element' => null
        );

        if (!file_exists($file_path)) {
            return $stats;
        }

        $stats['file_size'] = filesize($file_path);

        try {
            $reader = new XMLReader();
            $reader->open($file_path);

            $current_depth = 0;

            while ($reader->read()) {
                switch ($reader->nodeType) {
                    case XMLReader::ELEMENT:
                        $stats['element_count']++;
                        $current_depth++;
                        
                        if ($current_depth > $stats['max_depth']) {
                            $stats['max_depth'] = $current_depth;
                        }

                        if (!$stats['root_element']) {
                            $stats['root_element'] = $reader->localName;
                        }
                        break;

                    case XMLReader::END_ELEMENT:
                        $current_depth--;
                        break;
                }
            }

            $reader->close();

        } catch (Exception $e) {
            WC1C_Logger::log("Failed to get XML statistics: " . $e->getMessage(), 'error');
        }

        return $stats;
    }

    /**
     * Clean up XML content
     *
     * @param string $xml_content XML content
     * @return string Cleaned XML content
     */
    public function clean_xml_content($xml_content) {
        // Remove BOM
        $xml_content = preg_replace('/^\xEF\xBB\xBF/', '', $xml_content);

        // Remove invalid characters
        $xml_content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml_content);

        // Fix encoding issues
        if (!mb_check_encoding($xml_content, 'UTF-8')) {
            $xml_content = mb_convert_encoding($xml_content, 'UTF-8', 'auto');
        }

        // Remove duplicate XML declarations
        $xml_content = preg_replace('/(<\?xml[^>]*\?>)\s*(<\?xml[^>]*\?>)+/', '$1', $xml_content);

        return $xml_content;
    }

    /**
     * Optimize XML for parsing
     *
     * @param string $file_path XML file path
     * @return string Optimized file path
     */
    public function optimize_for_parsing($file_path) {
        $optimized_path = $file_path . '.optimized';

        try {
            $content = file_get_contents($file_path);
            $content = $this->clean_xml_content($content);

            // Remove comments
            $content = preg_replace('/<!--.*?-->/s', '', $content);

            // Remove unnecessary whitespace
            $content = preg_replace('/>\s+</', '><', $content);

            file_put_contents($optimized_path, $content);

            return $optimized_path;

        } catch (Exception $e) {
            WC1C_Logger::log("Failed to optimize XML: " . $e->getMessage(), 'error');
            return $file_path;
        }
    }
}