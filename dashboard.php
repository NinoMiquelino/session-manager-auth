<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
date_default_timezone_set('America/Sao_Paulo');

require_once 'config/database.php';
require_once 'classes/SecurityLogger.php';
require_once 'classes/SessionManager.php';
require_once 'classes/RateLimiter.php';

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    die('Erro na conexão com o banco de dados');
}

// Inicializar banco se necessário
$db->initDatabase();

$logger = new SecurityLogger($conn);
$sessionManager = new SessionManager($conn, $logger);
$rateLimiter = new RateLimiter($conn);

// Verificar autenticação
$session = $sessionManager->validateSession();

if (!$session) {
    header('Location: index.html');
    exit;
}

// Obter dados para o dashboard
$active_sessions = $sessionManager->getActiveSessions($session['user_id']);
$audit_logs = $logger->getAuditLogs(50);
$session_stats = $sessionManager->getSessionStats();
$log_stats = $logger->getLogStats();
$rate_limit_stats = $rateLimiter->getRateLimitStats();
$db_size = $db->getDatabaseSize();

$total_sessions = count($active_sessions);

function formatDateBrazilian($dateString, $includeTime = true) {
   if ($includeTime) {
     return date('d/m/Y H:i:s', strtotime($dateString));
   }
   return date('d/m/Y', strtotime($dateString));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Manager Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-900">Session Manager</h1>
                    <span class="ml-3 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">SQLite</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">Olá, <strong><?php echo htmlspecialchars($session['username']); ?></strong></span>
                    <button onclick="logout()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>Sair
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg mr-4">
                        <i class="fas fa-user-clock text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Sessões Ativas</h3>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_sessions; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg mr-4">
                        <i class="fas fa-database text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Tamanho DB</h3>
                        <p class="text-lg font-semibold text-gray-900"><?php echo $db_size; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg mr-4">
                        <i class="fas fa-history text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Logs (24h)</h3>
                        <p class="text-lg font-semibold text-gray-900"><?php echo $log_stats['total_logs'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-orange-100 rounded-lg mr-4">
                        <i class="fas fa-network-wired text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Seu IP</h3>
                        <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($session['ip_address']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Sessões Ativas -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-semibold text-gray-900">Sessões Ativas</h2>
                    <button onclick="refreshSessions()" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                        <i class="fas fa-sync-alt mr-1"></i>Atualizar
                    </button>
                </div>
                
                <div class="space-y-4" id="sessions-container">
                    <?php foreach($active_sessions as $sess): ?>
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg <?php echo $sess['session_token'] === $session['session_token'] ? 'bg-blue-50 border-blue-200' : ''; ?>">
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($sess['ip_address']); ?>
                                    <?php if($sess['session_token'] === $session['session_token']): ?>
                                    <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Atual</span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-500">                               
                                          Última atividade: <?php echo formatDateBrazilian($sess['last_activity']); ?>
                                </p>
                                <p class="text-xs text-gray-400">                                 
                                         Expira: <?php echo formatDateBrazilian($sess['expires_at']); ?>
                                </p>
                            </div>
                        </div>
                        <?php if($sess['session_token'] !== $session['session_token']): ?>
                        <button onclick="revokeSession('<?php echo $sess['session_token']; ?>')" 
                                class="text-red-600 hover:text-red-700 text-sm font-medium">
                            <i class="fas fa-times mr-1"></i>Revogar
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($active_sessions)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-user-slash text-3xl mb-3"></i>
                        <p>Nenhuma sessão ativa encontrada</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Log de Atividades -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-semibold text-gray-900">Atividade Recente</h2>
                    <span class="text-xs text-gray-500">Últimas 50 ações</span>
                </div>
                
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach($audit_logs as $log): ?>
                    <div class="flex items-start space-x-3 p-3 border border-gray-100 rounded-lg hover:bg-gray-50">
                        <div class="w-2 h-2 <?php echo $log['action'] === 'LOGIN_SUCCESS' ? 'bg-green-500' : ($log['action'] === 'LOGIN_FAILED' ? 'bg-red-500' : 'bg-blue-500'); ?> rounded-full mt-2 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($log['username'] ?? 'Sistema'); ?>
                                <span class="text-gray-500 font-normal">• <?php echo htmlspecialchars($log['action']); ?></span>
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                <?php echo htmlspecialchars($log['ip_address']); ?> • 
                                <!--<?php echo date('d/m H:i', strtotime($log['created_at'])); ?>-->
                                <?php echo formatDateBrazilian($log['created_at']); ?>
                            </p>
                            <?php if(!empty($log['details'])): ?>
                            <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($log['details']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($audit_logs)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-clipboard-list text-3xl mb-3"></i>
                        <p>Nenhum log de atividade encontrado</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function logout() {
        if (confirm('Tem certeza que deseja sair?')) {
            window.location.href = 'logout.php';
        }
    }

    function revokeSession(sessionToken) {
        if (confirm('Tem certeza que deseja revogar esta sessão?')) {
            fetch('api/sessions.php?action=revoke', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ session_token: sessionToken })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocorreu um erro');
            });
        }
    }

    function refreshSessions() {
        fetch('api/sessions.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }   

    // Auto-refresh a cada 30 segundos
    setInterval(refreshSessions, 30000);
    </script>
</body>
</html>