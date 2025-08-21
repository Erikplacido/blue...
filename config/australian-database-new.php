<?php
/**
 * =========================================================
 * AUSTRALIAN DATABASE CONNECTION - HOSTINGER INTEGRATION
 * =========================================================
 * 
 * @file australian-database.php
 * @description Conexão com banco de dados Hostinger para sistema dinâmico
 * @version 3.0 - HOSTINGER DYNAMIC CONNECTION
 * @date 2025-08-09
 */

class AustralianDatabase {
    private static $instance = null;
    private $connection = null;
    private $config = null;

    private function __construct() {
        $this->loadConfig();
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Carrega configuração do arquivo .env
     */
    private function loadConfig() {
        $envFile = __DIR__ . '/../.env';
        
        if (!file_exists($envFile)) {
            throw new Exception("Environment file not found: {$envFile}");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];

        foreach ($lines as $line) {
            if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"');
            $config[$key] = $value;
        }

        $this->config = $config;
    }

    /**
     * Estabelece conexão com banco Hostinger
     */
    private function connect() {
        try {
            $host = $this->config['DB_HOST'] ?? 'srv1417.hstgr.io';
            $port = $this->config['DB_PORT'] ?? '3306';
            $dbname = $this->config['DB_DATABASE'] ?? 'u979853733_rose';
            $username = $this->config['DB_USERNAME'] ?? 'u979853733_rose';
            $password = $this->config['DB_PASSWORD'] ?? 'BlueM@rketing33';
            $charset = $this->config['DB_CHARSET'] ?? 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_PERSISTENT => false
            ];

            $this->connection = new PDO($dsn, $username, $password, $options);

            // Configurar timezone australiano
            $this->connection->exec("SET time_zone = '+10:00'");
            $this->connection->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

            error_log("✅ Database connection successful: {$dbname}@{$host}");

        } catch (PDOException $e) {
            error_log("❌ Database connection failed: " . $e->getMessage());
            throw new Exception("Failed to connect to database: " . $e->getMessage());
        }
    }

    /**
     * Retorna conexão PDO
     */
    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Testa a conexão com o banco
     */
    public function testConnection() {
        try {
            $stmt = $this->connection->query('SELECT 1 as test');
            $result = $stmt->fetch();
            return $result['test'] === 1;
        } catch (Exception $e) {
            error_log("Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter informações do banco
     */
    public function getDatabaseInfo() {
        try {
            $stmt = $this->connection->query('SELECT DATABASE() as db_name, VERSION() as version');
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get database info: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Listar tabelas disponíveis
     */
    public function getTables() {
        try {
            $stmt = $this->connection->query('SHOW TABLES');
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Failed to list tables: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar se uma tabela existe
     */
    public function tableExists($tableName) {
        try {
            $stmt = $this->connection->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Failed to check table existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fechar conexão (destructor)
     */
    public function __destruct() {
        $this->connection = null;
    }
}

/**
 * Função global para obter instância do banco
 */
function getDatabase() {
    return AustralianDatabase::getInstance();
}

/**
 * Função para testar conexão rapidamente
 */
function testDatabaseConnection() {
    try {
        $db = AustralianDatabase::getInstance();
        $connection = $db->getConnection();
        
        if ($db->testConnection()) {
            $info = $db->getDatabaseInfo();
            echo "✅ Conexão estabelecida com sucesso!\n";
            echo "📂 Banco: {$info['db_name']}\n";
            echo "🔢 Versão: {$info['version']}\n";
            
            $tables = $db->getTables();
            echo "📋 Tabelas encontradas: " . count($tables) . "\n";
            foreach ($tables as $table) {
                echo "   - {$table}\n";
            }
            
            return true;
        } else {
            echo "❌ Falha no teste de conexão\n";
            return false;
        }
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
        return false;
    }
}
