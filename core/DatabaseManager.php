<?php
/**
 * Enhanced Database Manager with Multi-Database Support
 * Provides unified interface for MySQL, SQLite with connection pooling and failover
 */

class DatabaseManager {
    private static $instance = null;
    private $connections = [];
    private $config;
    private $currentDriver = 'mysql';
    private $logFile;
    
    // Connection pool settings
    private $maxConnections = 10;
    private $connectionPool = [];
    private $usedConnections = 0;
    
    private function __construct() {
        $this->logFile = __DIR__ . '/../logs/database.log';
        $this->ensureLogDirectory();
        $this->loadConfiguration();
        $this->initializeDatabase();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    private function loadConfiguration() {
        $this->config = [
            'mysql' => [
                'host' => getenv('DB_HOST') ?: 'localhost',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'database' => getenv('DB_DATABASE') ?: 'painter_near_me',
                'port' => (int)(getenv('DB_PORT') ?: 3306),
                'charset' => 'utf8mb4'
            ],
            'sqlite' => [
                'database' => __DIR__ . '/../database/painter_near_me.sqlite',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            ]
        ];
    }
    
    private function initializeDatabase() {
        // Try MySQL first, fallback to SQLite
        if (!$this->testMySQLConnection()) {
            $this->log("MySQL connection failed, switching to SQLite");
            $this->currentDriver = 'sqlite';
            $this->ensureSQLiteDatabase();
        }
    }
    
    private function testMySQLConnection() {
        try {
            $config = $this->config['mysql'];
            $dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
            
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            // Try to create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}`");
            $pdo->exec("USE `{$config['database']}`");
            
            $this->log("MySQL connection successful");
            return true;
            
        } catch (Exception $e) {
            $this->log("MySQL connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function ensureSQLiteDatabase() {
        $dbFile = $this->config['sqlite']['database'];
        $dbDir = dirname($dbFile);
        
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        if (!file_exists($dbFile)) {
            $this->createSQLiteSchema();
        }
    }
    
    private function createSQLiteSchema() {
        try {
            $pdo = $this->getConnection();
            
            // Create tables
            $this->createTables($pdo);
            $this->insertDefaultData($pdo);
            
            $this->log("SQLite database and schema created successfully");
            
        } catch (Exception $e) {
            $this->log("Error creating SQLite schema: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function createTables($pdo) {
        $tables = [
            'admin_users' => "
                CREATE TABLE IF NOT EXISTS admin_users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(100) UNIQUE NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    role VARCHAR(50) DEFAULT 'admin',
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_login DATETIME
                )
            ",
            'painters' => "
                CREATE TABLE IF NOT EXISTS painters (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    company_name VARCHAR(255) NOT NULL,
                    contact_name VARCHAR(255),
                    phone VARCHAR(20),
                    address TEXT,
                    postcode VARCHAR(10),
                    description TEXT,
                    website VARCHAR(255),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    is_active BOOLEAN DEFAULT 1,
                    email_verified BOOLEAN DEFAULT 0,
                    verification_token VARCHAR(255),
                    reset_token VARCHAR(255),
                    reset_token_expires DATETIME
                )
            ",
            'leads' => "
                CREATE TABLE IF NOT EXISTS leads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    job_title VARCHAR(255) NOT NULL,
                    job_description TEXT,
                    location VARCHAR(255),
                    postcode VARCHAR(10),
                    customer_name VARCHAR(255),
                    customer_email VARCHAR(255),
                    customer_phone VARCHAR(20),
                    property_type VARCHAR(100),
                    job_type VARCHAR(100),
                    budget_range VARCHAR(100),
                    timeline VARCHAR(100),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    is_active BOOLEAN DEFAULT 1,
                    assigned_painter_id INTEGER,
                    status VARCHAR(50) DEFAULT 'active',
                    payment_count INTEGER DEFAULT 0,
                    is_payment_active BOOLEAN DEFAULT 1,
                    lead_price DECIMAL(10,2) DEFAULT 15.00,
                    max_payments INTEGER DEFAULT 3,
                    FOREIGN KEY (assigned_painter_id) REFERENCES painters(id)
                )
            ",
            'payment_config' => "
                CREATE TABLE IF NOT EXISTS payment_config (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    config_key VARCHAR(100) NOT NULL UNIQUE,
                    config_value TEXT NOT NULL,
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'painter_payment_methods' => "
                CREATE TABLE IF NOT EXISTS painter_payment_methods (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    painter_id INTEGER NOT NULL,
                    stripe_customer_id VARCHAR(255) NOT NULL,
                    stripe_payment_method_id VARCHAR(255) NOT NULL UNIQUE,
                    payment_method_type VARCHAR(50) DEFAULT 'card',
                    card_brand VARCHAR(50),
                    card_last4 VARCHAR(4),
                    is_default BOOLEAN DEFAULT 0,
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE
                )
            ",
            'lead_payments' => "
                CREATE TABLE IF NOT EXISTS lead_payments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    lead_id INTEGER NOT NULL,
                    painter_id INTEGER NOT NULL,
                    stripe_payment_intent_id VARCHAR(255) NOT NULL UNIQUE,
                    stripe_customer_id VARCHAR(255) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(3) DEFAULT 'GBP',
                    payment_status VARCHAR(20) DEFAULT 'pending',
                    payment_method_id VARCHAR(255),
                    payment_number INTEGER NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
                    FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE
                )
            ",
            'lead_access' => "
                CREATE TABLE IF NOT EXISTS lead_access (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    lead_id INTEGER NOT NULL,
                    painter_id INTEGER NOT NULL,
                    payment_id INTEGER NOT NULL,
                    accessed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
                    FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE,
                    FOREIGN KEY (payment_id) REFERENCES lead_payments(id) ON DELETE CASCADE,
                    UNIQUE(lead_id, painter_id)
                )
            "
        ];
        
        foreach ($tables as $tableName => $sql) {
            $pdo->exec($sql);
            $this->log("Created table: $tableName");
        }
    }
    
    private function insertDefaultData($pdo) {
        // Default admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("
            INSERT OR IGNORE INTO admin_users (username, email, password, role) 
            VALUES (?, ?, ?, ?)
        ")->execute(['admin', 'admin@painter-near-me.co.uk', $adminPassword, 'admin']);
        
        // Default payment configuration
        $configs = [
            ['stripe_publishable_key', '', 'Stripe publishable key for frontend'],
            ['stripe_secret_key', '', 'Stripe secret key for backend'],
            ['stripe_webhook_secret', '', 'Stripe webhook endpoint secret'],
            ['default_lead_price', '15.00', 'Default price per lead access in GBP'],
            ['max_payments_per_lead', '3', 'Maximum number of payments before lead deactivation'],
            ['payment_enabled', 'true', 'Whether payment system is enabled'],
            ['auto_deactivate_leads', 'true', 'Auto deactivate leads after max payments reached'],
            ['email_notifications_enabled', 'true', 'Enable payment email notifications'],
            ['daily_summary_enabled', 'true', 'Enable daily payment summary emails']
        ];
        
        $stmt = $pdo->prepare("
            INSERT OR IGNORE INTO payment_config (config_key, config_value, description) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($configs as $config) {
            $stmt->execute($config);
        }
    }
    
    public function getConnection() {
        if ($this->currentDriver === 'mysql') {
            return $this->getMySQLConnection();
        } else {
            return $this->getSQLiteConnection();
        }
    }
    
    private function getMySQLConnection() {
        try {
            $config = $this->config['mysql'];
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            
            return new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
        } catch (Exception $e) {
            $this->log("MySQL connection failed: " . $e->getMessage());
            // Fallback to SQLite
            $this->currentDriver = 'sqlite';
            return $this->getSQLiteConnection();
        }
    }
    
    private function getSQLiteConnection() {
        $dbFile = $this->config['sqlite']['database'];
        $dsn = "sqlite:$dbFile";
        
        return new PDO($dsn, null, null, $this->config['sqlite']['options']);
    }
    
    public function query($sql, $params = []) {
        try {
            $pdo = $this->getConnection();
            
            if (empty($params)) {
                return $pdo->query($sql);
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt;
            
        } catch (Exception $e) {
            $this->log("Query failed: " . $e->getMessage() . " | SQL: $sql");
            throw $e;
        }
    }
    
    public function queryOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function queryAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        $keys = array_keys($data);
        $placeholders = ':' . implode(', :', $keys);
        $fields = implode(', ', $keys);
        
        $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
        
        $stmt = $this->query($sql, $data);
        return $this->getConnection()->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = "$key = :$key";
        }
        $setClause = implode(', ', $sets);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        
        return $this->query($sql, array_merge($data, $whereParams));
    }
    
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $whereParams);
    }
    
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }
    
    public function commit() {
        return $this->getConnection()->commit();
    }
    
    public function rollback() {
        return $this->getConnection()->rollback();
    }
    
    public function getCurrentDriver() {
        return $this->currentDriver;
    }
    
    public function getDatabaseInfo() {
        $pdo = $this->getConnection();
        
        if ($this->currentDriver === 'sqlite') {
            $dbFile = $this->config['sqlite']['database'];
            return [
                'driver' => 'SQLite',
                'database' => $dbFile,
                'size' => file_exists($dbFile) ? filesize($dbFile) : 0,
                'version' => $pdo->query('SELECT sqlite_version()')->fetchColumn()
            ];
        } else {
            return [
                'driver' => 'MySQL',
                'database' => $this->config['mysql']['database'],
                'host' => $this->config['mysql']['host'],
                'version' => $pdo->query('SELECT VERSION()')->fetchColumn()
            ];
        }
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public function getStats() {
        try {
            $pdo = $this->getConnection();
            
            $stats = [
                'painters' => $pdo->query("SELECT COUNT(*) FROM painters")->fetchColumn(),
                'leads' => $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn(),
                'payments' => $pdo->query("SELECT COUNT(*) FROM lead_payments")->fetchColumn(),
                'total_revenue' => $pdo->query("SELECT SUM(amount) FROM lead_payments WHERE payment_status = 'succeeded'")->fetchColumn() ?: 0
            ];
            
            return $stats;
            
        } catch (Exception $e) {
            $this->log("Error getting stats: " . $e->getMessage());
            return ['painters' => 0, 'leads' => 0, 'payments' => 0, 'total_revenue' => 0];
        }
    }
}
?>