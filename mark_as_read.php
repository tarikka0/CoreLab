<?php
require 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message_id'])) {
    $messageId = $_POST['message_id'];
    $userId = $_SESSION['user']['id'];
    
    $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$messageId, $userId]);
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);