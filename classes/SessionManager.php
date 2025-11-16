<?php
class SessionManager {
    private $db;
    private $logger;
    private $session_timeout = 1800; // 30 minutos

    public function __construct($db, $logger) {
        $this->db = $db;
        $this->logger = $logger;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->cleanExpiredSessions();
    }

    public function createSession($user_id, $username) {
        try {
            // Gerar token único
            $session_token = bin2hex(random_bytes(64));                                 
            $expires_at = date('Y-m-d H:i:s', time() + $this->session_timeout);               
                                 
            // Inserir sessão no banco
            $stmt = $this->db->prepare("
                INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $session_token,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $expires_at
            ]);            
            
             // Atualizar último login do usuário
            $stmt = $this->db->prepare("
                UPDATE users SET last_login = datetime('now') WHERE id = ?
            ");
            $stmt->execute([$user_id]);            
            
            // Configurar sessão PHP
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['session_token'] = $session_token;
            $_SESSION['last_activity'] = time();
            
            // Log de login bem-sucedido
            $this->logger->logAction($user_id, 'LOGIN_SUCCESS', "Sessão criada para usuário: {$username}");
            
            return $session_token;
            
        } catch(PDOException $e) {
            error_log("Create session error: " . $e->getMessage());
            return false;
        }
    }

    public function validateSession() {
        if (!isset($_SESSION['user_id'], $_SESSION['session_token'], $_SESSION['last_activity'])) {
            return false;
        }       
        
        // Verificar timeout da sessão PHP
        if (time() - $_SESSION['last_activity'] > $this->session_timeout) {
        	error_log("DEBUG: Falha na validacao PHP: Timeout");
            $this->destroySession();            
            return false;
        }
        
        // Atualizar atividade
        $_SESSION['last_activity'] = time();
                
        try {      	      
            // Verificar sessão no banco
            $stmt = $this->db->prepare("
                SELECT us.*, u.username, u.is_active 
                FROM user_sessions us 
                JOIN users u ON us.user_id = u.id 
                WHERE us.session_token = ? 
                AND us.is_active = 1 
                AND us.expires_at > datetime('now')               
            ");
            
            $stmt->execute([$_SESSION['session_token']]);                
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session && $session['is_active']) {
            	error_log("DEBUG: Validacao de sessao no DB OK.");
                // Atualizar última atividade no banco
                $stmt = $this->db->prepare("
                    UPDATE user_sessions 
                    SET last_activity = datetime('now')                    
                    WHERE session_token = ?
                ");
                                                               
                $stmt->execute([$_SESSION['session_token']]);                       
                return $session;
            }
            
        } catch(PDOException $e) {
            error_log("Validate session error: " . $e->getMessage());
        }
        
        error_log("DEBUG: Falha na validacao DB. Sessao Inativa ou Expirada.");
        $this->destroySession();        
        return false;
    }

    public function destroySession() {
        if (isset($_SESSION['session_token'])) {
            try {
                // Marcar sessão como inativa no banco
                $stmt = $this->db->prepare("
                    UPDATE user_sessions 
                    SET is_active = 0 
                    WHERE session_token = ?
                ");
                $stmt->execute([$_SESSION['session_token']]);
                
                // Log de logout
                if (isset($_SESSION['user_id'])) {
                    $this->logger->logAction($_SESSION['user_id'], 'LOGOUT', "Sessão finalizada");
                }
                
            } catch(PDOException $e) {
                error_log("Destroy session error: " . $e->getMessage());
            }
        }
        
        // Destruir sessão PHP
        $_SESSION = [];
        session_destroy();
    }

    public function getActiveSessions($user_id = null) {
        try {
            $sql = "
                SELECT us.*, u.username 
                FROM user_sessions us 
                JOIN users u ON us.user_id = u.id 
                WHERE us.is_active = 1 AND us.expires_at > datetime('now')
            ";
            
            $params = [];
            
            if ($user_id) {
                $sql .= " AND us.user_id = ?";
                $params[] = $user_id;
            }
            
            $sql .= " ORDER BY us.last_activity DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Get active sessions error: " . $e->getMessage());
            return [];
        }
    }

    public function revokeSession($session_token, $current_user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_sessions 
                SET is_active = 0 
                WHERE session_token = ?
            ");
            $stmt->execute([$session_token]);
            
            // Log da revogação
            $this->logger->logAction($current_user_id, 'SESSION_REVOKED', "Sessão revogada: {$session_token}");
            
            return true;
            
        } catch(PDOException $e) {
            error_log("Revoke session error: " . $e->getMessage());
            return false;
        }
    }

    public function getSessionStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_sessions,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(*) as active_sessions
                FROM user_sessions 
                WHERE is_active = 1 AND expires_at > datetime('now')
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get session stats error: " . $e->getMessage());
            return [];
        }
    }

    private function cleanExpiredSessions() {
        // Limpar a cada 10% das requisições (performance)
        if (rand(1, 10) === 1) {
            try {            	                       
                $stmt = $this->db->prepare("
                    UPDATE user_sessions 
                    SET is_active = 0 
                    WHERE expires_at <= datetime('now') OR is_active = 0
                ");
                $stmt->execute();                
            } catch(PDOException $e) {
                error_log("Clean expired sessions error: " . $e->getMessage());
            }
        }
    }

    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
?>