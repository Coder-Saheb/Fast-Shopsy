<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success' => false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$broadcastId = intval($data['broadcast_id'] ?? 0);

if ($broadcastId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT IGNORE INTO broadcast_reads (broadcast_id, user_id) VALUES (?, ?)");
    $stmt->execute([$broadcastId, $_SESSION['user_id']]);
}
echo json_encode(['success' => true]);
?>
