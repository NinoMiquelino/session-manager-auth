<?php
require_once 'config/database.php';
require_once 'classes/SecurityLogger.php';
require_once 'classes/RateLimiter.php';
require_once 'classes/SessionManager.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na conexão com o banco de dados']);
    exit;
}

// Inicializar banco se necessário
$db->initDatabase();

// Inicializar componentes
$logger = new SecurityLogger($conn);
$rateLimiter = new RateLimiter($conn);
$sessionManager = new SessionManager($conn, $logger);

// Verificar rate limiting
$client_ip = $_SERVER['REMOTE_ADDR'];
$rate_limit = $rateLimiter->checkRateLimit($client_ip, 'LOGIN_ATTEMPT', 5, 300);

if (!$rate_limit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'message' => $rate_limit['message'],
        'reset_time' => $rate_limit['reset_time']
    ]);
    exit;
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $logger->logAction(null, 'LOGIN_FAILED', 'Credenciais vazias');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Usuário e senha são obrigatórios']);
        exit;
    }
    
    try {
        // Buscar usuário
        $stmt = $conn->prepare("
            SELECT id, username, password_hash, is_active 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            // Login bem-sucedido
            $session_token = $sessionManager->createSession($user['id'], $user['username']);
            
            if ($session_token) {
                // Limpar rate limit em caso de sucesso
                $rateLimiter->clearRateLimit($client_ip, 'LOGIN_ATTEMPT');
                
                // DEBUG: Verificar se a sessão foi criada
                error_log("Session created: " . $session_token);
                error_log("User ID: " . $user['id']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login realizado com sucesso',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username']
                    ]
                ]);
            } else {
            	error_log("Failed to create session for user: " . $user['id']);
                throw new Exception('Falha ao criar sessão');
            }
            
        } else {
            // Login falhou
            $logger->logAction($user['id'] ?? null, 'LOGIN_FAILED', "Credenciais inválidas para: {$username}");
            
            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'message' => 'Usuário ou senha inválidos',
                'remaining_attempts' => $rate_limit['remaining']
            ]);
        }
        
    } catch(PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $logger->logAction(null, 'LOGIN_ERROR', 'Erro no banco de dados');
        
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>