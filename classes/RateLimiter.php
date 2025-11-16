<?php
class RateLimiter {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function checkRateLimit($identifier, $action_type, $max_attempts = 5, $time_window = 300) {
        try {
            $current_time = date('Y-m-d H:i:s');
            
            // Limpar tentativas antigas
            $this->cleanOldAttempts($time_window);
            
            // Verificar se está bloqueado
            $stmt = $this->db->prepare("
                SELECT block_until 
                FROM rate_limits 
                WHERE identifier = ? AND action_type = ? AND is_blocked = 1
            ");
            $stmt->execute([$identifier, $action_type]);
            $block = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($block && strtotime($block['block_until']) > time()) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => $block['block_until'],
                    'message' => 'Muitas tentativas. Tente novamente mais tarde.'
                ];
            }
            
            // Obter tentativas atuais
            $stmt = $this->db->prepare("
                SELECT attempt_count, first_attempt 
                FROM rate_limits 
                WHERE identifier = ? AND action_type = ?
            ");
            $stmt->execute([$identifier, $action_type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $attempt_count = $result['attempt_count'] + 1;
                $first_attempt = $result['first_attempt'];
                
                // Atualizar contador
                $stmt = $this->db->prepare("
                    UPDATE rate_limits 
                    SET attempt_count = ?, last_attempt = ? 
                    WHERE identifier = ? AND action_type = ?
                ");
                $stmt->execute([
                    $attempt_count,
                    $current_time,
                    $identifier,
                    $action_type
                ]);
                
                // Verificar se excedeu o limite
                if ($attempt_count >= $max_attempts) {
                    $block_until = date('Y-m-d H:i:s', time() + 900); // 15 minutos
                    $stmt = $this->db->prepare("
                        UPDATE rate_limits 
                        SET is_blocked = 1, block_until = ? 
                        WHERE identifier = ? AND action_type = ?
                    ");
                    $stmt->execute([
                        $block_until,
                        $identifier,
                        $action_type
                    ]);
                    
                    return [
                        'allowed' => false,
                        'remaining' => 0,
                        'reset_time' => $block_until,
                        'message' => 'Muitas tentativas. Conta temporariamente bloqueada.'
                    ];
                }
                
                return [
                    'allowed' => true,
                    'remaining' => $max_attempts - $attempt_count,
                    'reset_time' => date('Y-m-d H:i:s', strtotime($first_attempt) + $time_window)
                ];
                
            } else {
                // Primeira tentativa
                $stmt = $this->db->prepare("
                    INSERT INTO rate_limits (identifier, action_type, first_attempt, last_attempt) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $identifier,
                    $action_type,
                    $current_time,
                    $current_time
                ]);
                
                return [
                    'allowed' => true,
                    'remaining' => $max_attempts - 1,
                    'reset_time' => date('Y-m-d H:i:s', time() + $time_window)
                ];
            }
            
        } catch(PDOException $e) {
            error_log("Rate limit error: " . $e->getMessage());
            return ['allowed' => true, 'remaining' => $max_attempts, 'reset_time' => null];
        }
    }

    private function cleanOldAttempts($time_window) {
        try {
            $cutoff_time = date('Y-m-d H:i:s', time() - $time_window);
            $stmt = $this->db->prepare("
                DELETE FROM rate_limits 
                WHERE last_attempt < ? AND is_blocked = 0
            ");
            $stmt->execute([$cutoff_time]);
        } catch(PDOException $e) {
            error_log("Clean old attempts error: " . $e->getMessage());
        }
    }

    public function clearRateLimit($identifier, $action_type) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM rate_limits 
                WHERE identifier = ? AND action_type = ?
            ");
            $stmt->execute([$identifier, $action_type]);
            return true;
        } catch(PDOException $e) {
            error_log("Clear rate limit error: " . $e->getMessage());
            return false;
        }
    }

    public function getRateLimitStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    action_type,
                    COUNT(*) as total_attempts,
                    SUM(CASE WHEN is_blocked = 1 THEN 1 ELSE 0 END) as blocked_count
                FROM rate_limits 
                GROUP BY action_type
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get rate limit stats error: " . $e->getMessage());
            return [];
        }
    }
}
?>


