<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'teacher') {
    header("Location: index.php");
    exit();
}

$submission_id = intval($_GET['id']);

try {
    // Gönderim bilgilerini getir
    $stmt = $db->prepare("SELECT s.*, u.full_name as student_name, u.email as student_email, 
                         a.title as assignment_title, a.course as assignment_course
                         FROM submissions s
                         JOIN users u ON s.student_id = u.id
                         JOIN assignments a ON s.assignment_id = a.id
                         WHERE s.id = ? AND a.teacher_id = ?");
    $stmt->execute([$submission_id, $_SESSION['user']['id']]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        throw new Exception("Gönderim bulunamadı veya erişim izniniz yok.");
    }
    
    // Gönderim dosyalarını getir
    $stmt = $db->prepare("SELECT * FROM submission_files WHERE submission_id = ?");
    $stmt->execute([$submission_id]);
    $submission_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: odevler.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gönderim Detayları | CoreLab Akademik Yönetim</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-light: #a29bfe;
            --primary-dark: #5649c0;
            --dark: #2d3436;
            --dark-light: #636e72;
            --light: #f5f6fa;
            --light-gray: #dfe6e9;
            --white: #ffffff;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #d63031;
            --shadow: 0 5px 15px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--white);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
        }
        
        h1 {
            color: var(--primary-dark);
            margin-bottom: 20px;
        }
        
        .submission-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-item strong {
            display: block;
            color: var(--dark-light);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .file-section {
            margin: 30px 0;
        }
        
        .file-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .file-card {
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 15px;
            transition: var(--transition);
        }
        
        .file-card:hover {
            border-color: var(--primary-light);
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .file-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .file-name {
            font-weight: 600;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .file-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            outline: none;
            gap: 8px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary-light);
            color: var(--white);
        }
        
        .note-section {
            margin: 30px 0;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
        }
        
        .note-section h3 {
            margin-top: 0;
            color: var(--primary-dark);
        }
        
        .back-btn {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="submission-header">
            <h1>Gönderim Detayları</h1>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Öğrenci Adı</strong>
                    <div><?= htmlspecialchars($submission['student_name']) ?></div>
                </div>
                <div class="info-item">
                    <strong>Öğrenci Email</strong>
                    <div><?= htmlspecialchars($submission['student_email']) ?></div>
                </div>
                <div class="info-item">
                    <strong>Ödev Başlığı</strong>
                    <div><?= htmlspecialchars($submission['assignment_title']) ?></div>
                </div>
                <div class="info-item">
                    <strong>Ders</strong>
                    <div><?= htmlspecialchars($submission['assignment_course']) ?></div>
                </div>
                <div class="info-item">
                    <strong>Gönderim Tarihi</strong>
                    <div><?= date('d M Y H:i', strtotime($submission['submitted_at'])) ?></div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($submission['note'])): ?>
        <div class="note-section">
            <h3>Öğrenci Notu</h3>
            <p><?= nl2br(htmlspecialchars($submission['note'])) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="file-section">
            <h2>Gönderilen Dosyalar</h2>
            
            <?php if (!empty($submission_files)): ?>
                <div class="file-list">
                    <?php foreach ($submission_files as $file): ?>
                        <div class="file-card">
                            <div class="file-icon">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="file-name"><?= htmlspecialchars($file['file_name']) ?></div>
                            <div class="file-actions">
                                <a href="<?= htmlspecialchars($file['file_path']) ?>" class="btn btn-primary" download>
                                    <i class="fas fa-download"></i> İndir
                                </a>
                                <a href="<?= htmlspecialchars($file['file_path']) ?>" class="btn btn-outline" target="_blank">
                                    <i class="fas fa-eye"></i> Görüntüle
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Bu gönderimde dosya bulunmamaktadır.</p>
            <?php endif; ?>
        </div>
        
        <a href="odevler.php" class="btn btn-outline back-btn">
            <i class="fas fa-arrow-left"></i> Ödevlere Dön
        </a>
    </div>
</body>
</html>