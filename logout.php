<?php
require_once 'config/database.php';
require_once 'classes/SecurityLogger.php';
require_once 'classes/SessionManager.php';

$db = new Database();
$conn = $db->connect();

if ($conn) {
    $logger = new SecurityLogger($conn);
    $sessionManager = new SessionManager($conn, $logger);
    $sessionManager->destroySession();
}

// Redirecionar para login
header('Location: index.html');
exit;
?>