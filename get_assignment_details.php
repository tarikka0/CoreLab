<?php
session_start();
require_once 'includes/config.php';

$assignment_id = intval($_GET['id']);
$role = $_SESSION['user']['role'];

try {
    // Ödev bilgilerini getir
    if ($role == 'teacher') {
        $stmt = $db->prepare("SELECT a.*, u.full_name as teacher_name 
                            FROM assignments a
                            JOIN users u ON a.teacher_id = u.id
                            WHERE a.id = :id AND a.teacher_id = :teacher_id");
        $stmt->execute([
            ':id' => $assignment_id,
            ':teacher_id' => $_SESSION['user']['id']
        ]);
    } else {
        $stmt = $db->prepare("SELECT a.*, u.full_name as teacher_name 
                            FROM assignments a
                            JOIN users u ON a.teacher_id = u.id
                            WHERE a.id = :id");
        $stmt->execute([':id' => $assignment_id]);
    }
    
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        throw new Exception("Ödev bulunamadı veya erişim izniniz yok.");
    }

    // Ödev dosyalarını getir (akademisyenin eklediği dosyalar)
    $stmt = $db->prepare("SELECT * FROM assignment_files WHERE assignment_id = ?");
    $stmt->execute([$assignment_id]);
    $assignment_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($role == 'teacher') {
        // Akademisyen için - öğrenci gönderimlerini getir
        $stmt = $db->prepare("SELECT s.*, u.full_name as student_name, u.email as student_email,
                            (SELECT COUNT(*) FROM submission_files WHERE submission_id = s.id) as file_count
                            FROM submissions s
                            JOIN users u ON s.student_id = u.id
                            WHERE s.assignment_id = ?
                            ORDER BY s.submitted_at DESC");
        $stmt->execute([$assignment_id]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Öğrenci için - kendi gönderimini getir
        $stmt = $db->prepare("SELECT s.* FROM submissions s
                            WHERE s.assignment_id = ? AND s.student_id = ?");
        $stmt->execute([$assignment_id, $_SESSION['user']['id']]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // HTML çıktısı
    echo '<div class="assignment-detail">';
    echo '<h4>'.htmlspecialchars($assignment['title']).'</h4>';
    
    // Ödev bilgileri
    echo '<div class="assignment-info">';
    echo '<div class="info-item"><i class="fas fa-book"></i> <strong>Ders:</strong> '.htmlspecialchars($assignment['course']).'</div>';
    echo '<div class="info-item"><i class="fas fa-user-tie"></i> <strong>Akademisyen:</strong> '.htmlspecialchars($assignment['teacher_name']).'</div>';
    echo '<div class="info-item"><i class="fas fa-calendar-alt"></i> <strong>Başlangıç:</strong> '.date('d M Y H:i', strtotime($assignment['start_date'])).'</div>';
    echo '<div class="info-item"><i class="fas fa-calendar-check"></i> <strong>Teslim Tarihi:</strong> '.date('d M Y H:i', strtotime($assignment['due_date'])).'</div>';
    echo '</div>';
    
    // Ödev açıklaması
    echo '<div class="description-section">';
    echo '<h5>Açıklama</h5>';
    echo '<div class="description-content">'.nl2br(htmlspecialchars($assignment['description'])).'</div>';
    echo '</div>';
    
    // Akademisyenin eklediği dosyalar (her iki rol de görür)
    if (!empty($assignment_files)) {
        echo '<div class="file-section">';
        echo '<h5><i class="fas fa-paperclip"></i> Ek Dosyalar</h5>';
        foreach ($assignment_files as $file) {
            echo '<a href="'.htmlspecialchars($file['file_path']).'" class="file-link" download>';
            echo '<i class="fas fa-file-download"></i> ';
            echo htmlspecialchars($file['file_name']);
            echo '</a>';
        }
        echo '</div>';
    }
    
    if ($role == 'teacher') {
        // AKADEMİSYEN GÖRÜNÜMÜ - Öğrenci gönderimleri
        echo '<div class="submissions-section">';
        echo '<h5><i class="fas fa-users"></i> Öğrenci Gönderimleri ('.count($submissions).')</h5>';
        
        if (!empty($submissions)) {
            echo '<div class="submissions-list">';
            foreach ($submissions as $sub) {
                echo '<div class="submission-item">';
                echo '<div class="student-info">';
                echo '<div class="student-name">'.htmlspecialchars($sub['student_name']).'</div>';
                echo '<div class="student-email">'.htmlspecialchars($sub['student_email']).'</div>';
                echo '<div class="submission-date">'.date('d M Y H:i', strtotime($sub['submitted_at'])).'</div>';
                
                if (!empty($sub['note'])) {
                    echo '<div class="submission-note"><strong>Açıklama:</strong> '.nl2br(htmlspecialchars($sub['note'])).'</div>';
                }
                echo '</div>';
                
                echo '<div class="submission-actions">';
                // Gönderilen dosyaları listele
                $stmt = $db->prepare("SELECT * FROM submission_files WHERE submission_id = ?");
                $stmt->execute([$sub['id']]);
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($files)) {
                    echo '<div class="submission-files">';
                    foreach ($files as $file) {
                        echo '<a href="'.htmlspecialchars($file['file_path']).'" class="btn btn-outline btn-sm" download>';
                        echo '<i class="fas fa-download"></i> '.htmlspecialchars($file['file_name']);
                        echo '</a>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                
                echo '</div>'; // submission-item
            }
            echo '</div>'; // submissions-list
        } else {
            echo '<div class="no-submissions">';
            echo '<i class="fas fa-inbox"></i>';
            echo '<p>Henüz gönderim yapılmadı</p>';
            echo '</div>';
        }
        echo '</div>'; // submissions-section
        
    } else {
        // ÖĞRENCİ GÖRÜNÜMÜ - Kendi gönderimi
        echo '<div class="student-submission-section">';
        
        if (!empty($submission)) {
            echo '<h5><i class="fas fa-upload"></i> Senin Gönderimin</h5>';
            echo '<div class="submission-info">';
            echo '<div class="info-item"><strong>Gönderim Tarihi:</strong> '.date('d M Y H:i', strtotime($submission['submitted_at'])).'</div>';
            
            if (!empty($submission['note'])) {
                echo '<div class="info-item"><strong>Açıklaman:</strong> '.nl2br(htmlspecialchars($submission['note'])).'</div>';
            }
            
            // Gönderilen dosyaları listele
            $stmt = $db->prepare("SELECT * FROM submission_files WHERE submission_id = ?");
            $stmt->execute([$submission['id']]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($files)) {
                echo '<div class="submission-files">';
                echo '<h6>Teslim Edilen Dosyalar:</h6>';
                foreach ($files as $file) {
                    echo '<a href="'.htmlspecialchars($file['file_path']).'" class="btn btn-outline" download>';
                    echo '<i class="fas fa-download"></i> '.htmlspecialchars($file['file_name']);
                    echo '</a>';
                }
                echo '</div>';
            }
            
            echo '</div>';
        } else {
            echo '<div class="alert alert-info">Bu ödevi henüz teslim etmediniz.</div>';
        }
        echo '</div>'; // student-submission-section
    }
    
    echo '</div>'; // assignment-detail

} catch (Exception $e) {
    echo '<div class="alert alert-danger">'.htmlspecialchars($e->getMessage()).'</div>';
}
?>