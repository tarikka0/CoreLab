<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student') {
    die('Yetkisiz erişim!');
}

require_once 'includes/config.php';

$assignment_id = $_GET['id'] ?? 0;

try {
    // Ödev detaylarını getir
    $stmt = $db->prepare("SELECT a.*, u.full_name as teacher_name, u.academic_title as teacher_title 
                         FROM assignments a 
                         JOIN users u ON a.teacher_id = u.id 
                         WHERE a.id = :id");
    $stmt->execute([':id' => $assignment_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        die('Ödev bulunamadı!');
    }
    
    // Ödev dosyalarını getir
    $stmt = $db->prepare("SELECT * FROM assignment_files WHERE assignment_id = :assignment_id");
    $stmt->execute([':assignment_id' => $assignment_id]);
    $assignment_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Öğrencinin teslim bilgilerini getir
    $stmt = $db->prepare("SELECT * FROM submissions 
                         WHERE assignment_id = :assignment_id 
                         AND student_id = :student_id");
    $stmt->execute([
        ':assignment_id' => $assignment_id,
        ':student_id' => $_SESSION['user']['id']
    ]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Teslim edilen dosyaları getir
    $submission_files = [];
    if ($submission) {
        $stmt = $db->prepare("SELECT * FROM submission_files 
                             WHERE submission_id = :submission_id");
        $stmt->execute([':submission_id' => $submission['id']]);
        $submission_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Akademik unvanı formatla
    $teacher_title = '';
    switch ($assignment['teacher_title']) {
        case 'prof': $teacher_title = 'Prof. Dr.'; break;
        case 'assoc_prof': $teacher_title = 'Doç. Dr.'; break;
        case 'assist_prof': $teacher_title = 'Dr. Öğr. Üyesi'; break;
        case 'lecturer': $teacher_title = 'Öğr. Gör.'; break;
        case 'res_assist': $teacher_title = 'Arş. Gör.'; break;
        default: $teacher_title = '';
    }
    
    // Ödev durumunu belirle
    $now = new DateTime();
    $due_date = new DateTime($assignment['due_date']);
    $start_date = new DateTime($assignment['start_date']);
    
    if ($now > $due_date) {
        $status_class = 'status-ended';
        $status_text = 'Tamamlandı';
    } elseif ($now >= $start_date) {
        $status_class = 'status-active';
        $status_text = 'Aktif';
    } else {
        $status_class = 'status-upcoming';
        $status_text = 'Yaklaşıyor';
    }
    
    // Teslim durumu
    $is_submitted = $submission !== false;
    
    // HTML çıktısı oluştur
    echo '
    <div class="assignment-detail">
        <h4>'.htmlspecialchars($assignment['title']).'</h4>
        
        <div class="meta-info">
            <p><strong>Ders:</strong> '.htmlspecialchars($assignment['course']).'</p>
            <p><strong>Öğretmen:</strong> '.$teacher_title.' '.htmlspecialchars($assignment['teacher_name']).'</p>
            <p><strong>Başlangıç Tarihi:</strong> '.date('d.m.Y', strtotime($assignment['start_date'])).'</p>
            <p><strong>Teslim Tarihi:</strong> '.date('d.m.Y', strtotime($assignment['due_date'])).'</p>
            <p><strong>Durum:</strong> <span class="'.$status_class.'">'.$status_text.'</span></p>
        </div>
        
        <div class="description-section">
            <h5>Açıklama</h5>
            <p>'.nl2br(htmlspecialchars($assignment['description'])).'</p>
        </div>';
        
    if (!empty($assignment_files)) {
        echo '
        <div class="file-section">
            <h5>Ödev Dosyaları</h5>';
            
        foreach ($assignment_files as $file) {
            echo '
            <a href="'.htmlspecialchars($file['file_path']).'" class="file-link" target="_blank">
                <i class="fas fa-file-download"></i>
                '.htmlspecialchars($file['file_name']).'
            </a>';
        }
        
        echo '
        </div>';
    }
    
    echo '
        <div class="submission-info-section">
            <h5>Teslim Durumunuz</h5>';
    
    if ($is_submitted) {
        echo '
        <div class="submission-status submitted">
            <i class="fas fa-check-circle"></i> Ödeviniz teslim edilmiş
        </div>';
        
        echo '<p><strong>Teslim Tarihi:</strong> '.date('d.m.Y H:i', strtotime($submission['submitted_at'])).'</p>';
        
        if (!empty($submission['note'])) {
            echo '
            <div class="submission-note">
                <h6>Notunuz</h6>
                <p>'.nl2br(htmlspecialchars($submission['note'])).'</p>
            </div>';
        }
        
        if (!empty($submission_files)) {
            echo '
            <div class="submission-files">
                <h6>Teslim Edilen Dosyalar</h6>';
                
            foreach ($submission_files as $file) {
                echo '
                <a href="'.htmlspecialchars($file['file_path']).'" class="file-link" target="_blank">
                    <i class="fas fa-file-download"></i>
                    '.htmlspecialchars($file['file_name']).'
                </a>';
            }
            
            echo '
            </div>';
        }
    } else {
        echo '
            <div class="submission-status not-submitted">
                <i class="fas fa-exclamation-circle"></i> Ödeviniz henüz teslim edilmemiş
            </div>';
        
        if ($now <= $due_date) {
            echo '
            <button class="btn btn-primary submit-btn" data-id="'.$assignment_id.'" style="margin-top: 15px;">
                <i class="fas fa-paper-plane"></i> Teslim Et
            </button>';
        }
    }
    
    echo '
        </div>
    </div>';
    
} catch (PDOException $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}
?>