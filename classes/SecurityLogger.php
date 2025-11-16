<?php
class SecurityLogger {
    private $db;
    private $logFile;

    public function __construct($db) {
        $this->db = $db;
        $this->logFile = __DIR__ . '/../logs/security.log';
        
        // Garantir que os diretórios existem
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    public function logAction($user_id, $action, $details = '') {
        $this->logToDatabase($user_id, $action, $details);
        $this->logToFile($user_id, $action, $details);
    }

    private function logToDatabase($user_id, $action, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action, ip_address, user_agent, details) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $action,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $details
            ]);
        } catch(PDOException $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }

    private function logToFile($user_id, $action, $details) {
        //$timestamp = date('Y-m-d H:i:s');
        $timestamp = date('d/m/Y H:i:s'); // Formato brasileiro
        $ip = $this->getClientIP();
        $logEntry = "[{$timestamp}] IP: {$ip} | User: {$user_id} | Action: {$action} | Details: {$details}\n";
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function getAuditLogs($limit = 100) {
        try {
            $stmt = $this->db->prepare("
                SELECT al.*, u.username 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                ORDER BY al.created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get audit logs error: " . $e->getMessage());
            return [];
        }
    }

    public function getLogStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT user_id) as unique_users
                FROM audit_logs 
                WHERE created_at >= datetime('now', '-1 day')
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get log stats error: " . $e->getMessage());
            return [];
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