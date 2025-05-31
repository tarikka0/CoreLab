<?php
session_start();
require_once 'includes/config.php';

if ($_SESSION['user']['role'] != 'teacher') {
    die(json_encode(['success' => false, 'error' => 'Yetkisiz erişim']));
}

$submission_id = intval($_POST['submission_id']);
$grade = isset($_POST['grade']) ? floatval($_POST['grade']) : null;

try {
    $stmt = $db->prepare("UPDATE submissions 
                         SET grade = :grade
                         WHERE id = :submission_id");

    $stmt->execute([
        ':grade' => $grade,
        ':submission_id' => $submission_id
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]));
}
