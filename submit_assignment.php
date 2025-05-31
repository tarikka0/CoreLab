<?php
session_start();
require_once 'includes/config.php';

if ($_SESSION['user']['role'] != 'student') {
    die(json_encode(['success' => false, 'error' => 'Yetkisiz erişim']));
}

$assignment_id = intval($_POST['assignment_id']);
$student_id = $_SESSION['user']['id'];
$note = isset($_POST['note']) ? trim($_POST['note']) : null;

// Dosya yükleme
$upload_dir = 'uploads/submissions/';
$file_path = null;

if (!empty($_FILES['submission_file']['name'])) {
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = basename($_FILES['submission_file']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'rar'];
    
    if (in_array($file_ext, $allowed_ext)) {
        $new_file_name = uniqid() . '_' . $file_name;
        $file_path = $upload_dir . $new_file_name;
        
        if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $file_path)) {
            die(json_encode(['success' => false, 'error' => 'Dosya yüklenirken hata oluştu']));
        }
    } else {
        die(json_encode(['success' => false, 'error' => 'Geçersiz dosya formatı']));
    }
}

try {
    // Daha önce gönderim yapılmış mı kontrol et
    $stmt = $db->prepare("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?");
    $stmt->execute([$assignment_id, $student_id]);
    
    if ($stmt->fetch()) {
        // Güncelleme
        $stmt = $db->prepare("UPDATE submissions 
                             SET note = ?, file_path = ?, submitted_at = NOW()
                             WHERE assignment_id = ? AND student_id = ?");
        $stmt->execute([$note, $file_path, $assignment_id, $student_id]);
    } else {
        // Yeni gönderim
        $stmt = $db->prepare("INSERT INTO submissions 
                             (assignment_id, student_id, note, file_path, submitted_at)
                             VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$assignment_id, $student_id, $note, $file_path]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>