<?php require 'includes/config.php'; // Yetki kontrolü if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student') { header("Location: index.php"); exit(); }

$message_id = $_GET['id'];

// Duyuru detaylarını getir
$stmt = $db->prepare("SELECT m.*, u.full_name as sender_name 
                     FROM messages m 
                     JOIN users u ON m.sender_id = u.id 
                     WHERE m.id = ? AND m.is_announcement = 1");
$stmt->execute([$message_id]);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$announcement) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Sadece mesaj içeriğini döndür (AJAX istekleri için)
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo '<div class="message-content">' . nl2br(htmlspecialchars($announcement['message'])) . '</div>';
    exit();
}

// Normal sayfa yüklenmesi için (eğer direkt erişilirse)
header("Location: mesajst.php");
exit();
?>