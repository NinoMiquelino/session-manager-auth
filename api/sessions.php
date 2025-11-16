<?php
require_once '../config/database.php';
require_once '../classes/SecurityLogger.php';
require_once '../classes/SessionManager.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha na conexão com o banco de dados']);
    exit;
}

$logger = new SecurityLogger($conn);
$sessionManager = new SessionManager($conn, $logger);

// Verificar autenticação
$session = $sessionManager->validateSession();

if (!$session) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        getSessions();
        break;
    case 'POST':
        if ($action === 'revoke') {
            revokeSession();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}

function getSessions() {
    global $sessionManager, $session;
    
    $active_sessions = $sessionManager->getActiveSessions($session['user_id']);
    
    // Marcar sessão atual
    $sessions_with_current = array_map(function($sess) use ($session) {
        $sess['is_current'] = $sess['session_token'] === $session['session_token'];
        return $sess;
    }, $active_sessions);
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions_with_current
    ]);
}

function revokeSession() {
    global $sessionManager, $session;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $session_token = $data['session_token'] ?? '';
    
    if (empty($session_token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'É necessário um token de sessão.']);
        return;
    }
    
    $success = $sessionManager->revokeSession($session_token, $session['user_id']);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Sessão revogada com sucesso.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Sessão revogada com sucesso.']);
    }
}
?>