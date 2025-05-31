<?php
session_start();
require_once 'includes/config.php';

if ($_SESSION['user']['role'] != 'teacher') {
    die(json_encode(['success' => false, 'error' => 'Yetkisiz erişim']));
}

$submission_id = intval($_GET['submission_id']);
$grade = floatval($_POST['grade']);
$feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : null;

// Notun geçerli aralıkta olduğunu kontrol et
if ($grade < 0 || $grade > 100) {
    die(json_encode(['success' => false, 'error' => 'Not 0-100 arasında olmalıdır']));
}

try {
    // Gönderimin bu akademisyene ait bir ödeve ait olduğunu kontrol et
    $stmt = $db->prepare("SELECT s.id 
                         FROM submissions s
                         JOIN assignments a ON s.assignment_id = a.id
                         WHERE s.id = :submission_id AND a.teacher_id = :teacher_id");
    $stmt->execute([
        ':submission_id' => $submission_id,
        ':teacher_id' => $_SESSION['user']['id']
    ]);
    
    if (!$stmt->fetch()) {
        die(json_encode(['success' => false, 'error' => 'Yetkisiz işlem']));
    }
    
    // Notu güncelle
    $stmt = $db->prepare("UPDATE submissions 
                         SET grade = :grade, feedback = :feedback
                         WHERE id = :submission_id");
    $stmt->execute([
        ':grade' => $grade,
        ':feedback' => $feedback,
        ':submission_id' => $submission_id
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>