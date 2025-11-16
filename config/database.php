<?php
class Database {
    private $db_file;
    private $conn;

    public function __construct() {    	    
        $this->db_file = __DIR__ . '/../database/sessions.db';
        
        // Garantir que o diretório existe
        if (!is_dir(dirname($this->db_file))) {
            mkdir(dirname($this->db_file), 0755, true);
        }
    }

    public function connect() {
        try {
            $this->conn = new PDO("sqlite:" . $this->db_file);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec('PRAGMA foreign_keys = ON');
            
            return $this->conn;
        } catch(PDOException $e) {
            error_log("SQLite connection error: " . $e->getMessage());
            return null;
        }
    }

    public function initDatabase() {
        try {
            $conn = $this->connect();
            
            // Tabela de usuários
            $conn->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_login DATETIME NULL
                )
            ");

            // Tabela de sessões
            $conn->exec("
                CREATE TABLE IF NOT EXISTS user_sessions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    session_token VARCHAR(128) UNIQUE NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                    is_active BOOLEAN DEFAULT 1,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");

            // Tabela de auditoria
            $conn->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NULL,
                    action VARCHAR(50) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    details TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                )
            ");

            // Tabela de rate limiting
            $conn->exec("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    identifier VARCHAR(128) NOT NULL,
                    action_type VARCHAR(50) NOT NULL,
                    attempt_count INTEGER DEFAULT 1,
                    first_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
                    is_blocked BOOLEAN DEFAULT 0,
                    block_until DATETIME NULL,
                    UNIQUE (identifier, action_type)
                )
            ");

            // Inserir usuário demo
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO users (username, email, password_hash) 
                    VALUES ('admin', 'admin@system.com', ?)
                ");
                $stmt->execute([$password_hash]);
            }

            return true;

        } catch(PDOException $e) {
            error_log("Database init error: " . $e->getMessage());
            return false;
        }
    }

    public function getDatabaseSize() {
        if (file_exists($this->db_file)) {
            return round(filesize($this->db_file) / 1024, 2) . ' KB';
        }
        return 'N/A';
    }
}
?>