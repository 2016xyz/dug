<?php
// get_server_logs.php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare("SELECT * FROM server_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

    $countStmt = $pdo->query("SELECT COUNT(*) FROM server_logs");
    $total = $countStmt->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'data' => $logs,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
