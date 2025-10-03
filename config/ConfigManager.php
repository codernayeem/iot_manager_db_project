<?php
/**
 * Configuration Manager
 * Handles loading, saving, and managing database configuration
 */

class ConfigManager {
    private $configFile;
    private $config;
    
    public function __construct($configFile = null) {
        $this->configFile = $configFile ?: __DIR__ . '/db_config.json';
        $this->loadConfig();
    }
    
    /**
     * Load configuration from JSON file
     */
    private function loadConfig() {
        if (file_exists($this->configFile)) {
            $json = file_get_contents($this->configFile);
            $this->config = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in config file: ' . json_last_error_msg());
            }
        } else {
            // Create default configuration if file doesn't exist
            $this->config = $this->getDefaultConfig();
            $this->saveConfig();
        }
    }
    
    /**
     * Get default configuration values
     */
    private function getDefaultConfig() {
        return [
            'host' => 'localhost',
            'db_name' => 'iot_device_manager',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'port' => 3306,
            'options' => [
                'PDO::ATTR_ERRMODE' => 'PDO::ERRMODE_EXCEPTION',
                'PDO::ATTR_DEFAULT_FETCH_MODE' => 'PDO::FETCH_ASSOC',
                'PDO::ATTR_EMULATE_PREPARES' => false
            ]
        ];
    }
    
    /**
     * Save configuration to JSON file
     */
    public function saveConfig() {
        $json = json_encode($this->config, JSON_PRETTY_PRINT);
        if (file_put_contents($this->configFile, $json) === false) {
            throw new Exception('Failed to save configuration file');
        }
        return true;
    }
    
    /**
     * Get a configuration value
     */
    public function get($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
    
    /**
     * Set a configuration value
     */
    public function set($key, $value) {
        $this->config[$key] = $value;
        return $this;
    }
    
    /**
     * Update multiple configuration values
     */
    public function update($values) {
        foreach ($values as $key => $value) {
            $this->config[$key] = $value;
        }
        return $this;
    }
    
    /**
     * Get all configuration values
     */
    public function getAll() {
        return $this->config;
    }
    
    /**
     * Test database connection with current configuration
     */
    public function testConnection() {
        try {
            $dsn = "mysql:host=" . $this->get('host') . 
                   ";port=" . $this->get('port') . 
                   ";charset=" . $this->get('charset');
            
            $pdo = new PDO($dsn, $this->get('username'), $this->get('password'));
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            return [
                'success' => true,
                'message' => 'Connection successful'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test database connection with custom parameters
     */
    public function testConnectionWith($host, $username, $password, $port = 3306) {
        try {
            $dsn = "mysql:host=$host;port=$port;charset=utf8";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            return [
                'success' => true,
                'message' => 'Connection successful'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get database connection string for display
     */
    public function getConnectionString() {
        return "mysql:host=" . $this->get('host') . 
               ";port=" . $this->get('port') . 
               ";dbname=" . $this->get('db_name') . 
               ";charset=" . $this->get('charset');
    }
    
    /**
     * Validate configuration values
     */
    public function validate() {
        $errors = [];
        
        if (empty($this->get('host'))) {
            $errors[] = 'Host is required';
        }
        
        if (empty($this->get('db_name'))) {
            $errors[] = 'Database name is required';
        }
        
        if (empty($this->get('username'))) {
            $errors[] = 'Username is required';
        }
        
        if (!is_numeric($this->get('port')) || $this->get('port') < 1 || $this->get('port') > 65535) {
            $errors[] = 'Port must be a valid number between 1 and 65535';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
?>