<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'teacher') {
    header("Location: index.php");
    exit();
}

require_once 'includes/config.php';

// Akademik unvanlar
$academicTitles = [
    'prof' => 'Prof. Dr.',
    'assoc_prof' => 'Doç. Dr.',
    'assist_prof' => 'Dr. Öğr. Üyesi',
    'lecturer' => 'Öğr. Gör.',
    'res_assist' => 'Arş. Gör.'
];

// Yeni ödev oluşturma
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_assignment'])) {
    try {
        $title = htmlspecialchars(trim($_POST['title']));
        $description = htmlspecialchars(trim($_POST['description']));
        $course = htmlspecialchars(trim($_POST['course']));
        $start_date = $_POST['start_date'];
        $due_date = $_POST['due_date'];
        $assignment_type = $_POST['assignment_type'];
        $teacher_id = $_SESSION['user']['id'];
        
        $stmt = $db->prepare("INSERT INTO assignments (title, description, course, teacher_id, start_date, due_date, assignment_type) 
                             VALUES (:title, :description, :course, :teacher_id, :start_date, :due_date, :assignment_type)");
        
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':course' => $course,
            ':teacher_id' => $teacher_id,
            ':start_date' => $start_date,
            ':due_date' => $due_date,
            ':assignment_type' => $assignment_type
        ]);
        
        $assignment_id = $db->lastInsertId();
        
        // Dosya yükleme işlemi
        if (!empty($_FILES['assignment_files']['name'][0])) {
            $upload_dir = 'uploads/assignments/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['assignment_files']['tmp_name'] as $key => $tmp_name) {
                $file_name = basename($_FILES['assignment_files']['name'][$key]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $new_file_name = uniqid() . '_' . $file_name;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $stmt = $db->prepare("INSERT INTO assignment_files (assignment_id, file_name, file_path) 
                                             VALUES (:assignment_id, :file_name, :file_path)");
                        $stmt->execute([
                            ':assignment_id' => $assignment_id,
                            ':file_name' => $file_name,
                            ':file_path' => $file_path
                        ]);
                    }
                }
            }
        }
        
        $_SESSION['success'] = "Ödev başarıyla oluşturuldu!";
        header("Location: odevler.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Ödev oluşturulurken bir hata oluştu: " . $e->getMessage();
        header("Location: odevler.php");
        exit();
    }
}

// Akademisyenin ödevlerini getir
try {
    $teacher_id = $_SESSION['user']['id'];
    $stmt = $db->prepare("SELECT a.*, 
                         (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count,
                         (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id AND grade IS NOT NULL) as graded_count
                         FROM assignments a 
                         WHERE a.teacher_id = :teacher_id
                         ORDER BY a.due_date DESC");
    $stmt->execute([':teacher_id' => $teacher_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Ödevler yüklenirken bir hata oluştu: " . $e->getMessage();
    $assignments = [];
}
// Ödev silme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_assignment'])) {
    try {
        $assignment_id = $_POST['assignment_id'];
        $teacher_id = $_SESSION['user']['id'];
        
        // Ödevin bu akademisyene ait olduğunu kontrol et
        $stmt = $db->prepare("SELECT id FROM assignments WHERE id = :id AND teacher_id = :teacher_id");
        $stmt->execute([':id' => $assignment_id, ':teacher_id' => $teacher_id]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) {
            throw new Exception("Ödev bulunamadı veya silme yetkiniz yok.");
        }
        
        // Ödev dosyalarını sil
        $stmt = $db->prepare("SELECT file_path FROM assignment_files WHERE assignment_id = :assignment_id");
        $stmt->execute([':assignment_id' => $assignment_id]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($files as $file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        // Ödev dosya kayıtlarını sil
        $stmt = $db->prepare("DELETE FROM assignment_files WHERE assignment_id = :assignment_id");
        $stmt->execute([':assignment_id' => $assignment_id]);
        
        // Öğrenci gönderimlerini ve dosyalarını sil
        $stmt = $db->prepare("SELECT id FROM submissions WHERE assignment_id = :assignment_id");
        $stmt->execute([':assignment_id' => $assignment_id]);
        $submissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($submissions)) {
            // Gönderim dosyalarını sil
            $stmt = $db->prepare("SELECT file_path FROM submission_files WHERE submission_id IN (".implode(',', $submissions).")");
            $stmt->execute();
            $submission_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($submission_files as $file) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
            }
            
            // Gönderim dosya kayıtlarını sil
            $stmt = $db->prepare("DELETE FROM submission_files WHERE submission_id IN (".implode(',', $submissions).")");
            $stmt->execute();
            
            // Gönderimleri sil
            $stmt = $db->prepare("DELETE FROM submissions WHERE assignment_id = :assignment_id");
            $stmt->execute([':assignment_id' => $assignment_id]);
        }
        
        // Ödevi sil
        $stmt = $db->prepare("DELETE FROM assignments WHERE id = :id");
        $stmt->execute([':id' => $assignment_id]);
        
        $_SESSION['success'] = "Ödev başarıyla silindi!";
        header("Location: odevler.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Ödev silinirken bir hata oluştu: " . $e->getMessage();
        header("Location: odevler.php");
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödev Yönetimi | CoreLab Akademik Yönetim</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Sidebar Stilleri */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--white);
            box-shadow: var(--shadow);
            z-index: 1000;
        }

        .logo-container {
            padding: 25px;
            text-align: center;
            border-bottom: 1px solid var(--light-gray);
        }

        .logo {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo-icon {
            font-size: 3.2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
        }

        .logo-text span {
            color: var(--dark);
        }

        .logo-subtext {
            font-size: 0.85rem;
            color: var(--dark-light);
            margin-top: 5px;
        }

        .nav-menu {
            padding: 20px 15px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            margin-bottom: 8px;
            border-radius: 8px;
            color: var(--dark-light);
            transition: var(--transition);
        }

        .nav-item i {
            width: 24px;
            text-align: center;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .nav-item:hover {
            background: var(--primary-light);
            color: var(--white);
            transform: translateX(5px);
        }

        .nav-item.active {
            background: var(--primary);
            color: var(--white);
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }

        .page-title {
            font-size: 1.8rem;
            color: var(--dark);
            font-weight: 700;
        }

        /* Butonlar */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            outline: none;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 10px rgba(108, 92, 231, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 92, 231, 0.4);
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

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        /* Ödev Listesi */
        .assignment-list-container {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .assignment-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-controls {
            display: flex;
            gap: 15px;
        }

        .filter-select {
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            background: var(--white);
            outline: none;
        }

        /* Ödev Kartları */
        .assignment-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .assignment-card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            position: relative;
        }

        .assignment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .assignment-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .assignment-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-right: 15px;
        }

        .assignment-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(0, 184, 148, 0.1);
            color: var(--success);
        }

        .status-ended {
            background: rgba(214, 48, 49, 0.1);
            color: var(--danger);
        }

        .status-upcoming {
            background: rgba(253, 203, 110, 0.2);
            color: #e17055;
        }

        .assignment-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: var(--dark-light);
        }

        .meta-item i {
            margin-right: 8px;
            color: var(--primary);
        }

        .assignment-description {
            color: var(--dark-light);
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .assignment-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--light-gray);
            padding-top: 15px;
        }

        .submission-info {
            font-size: 0.9rem;
            color: var(--dark-light);
        }

        .submission-info strong {
            color: var(--dark);
        }

        .assignment-actions {
            display: flex;
            gap: 10px;
        }

        /* Modal Stilleri */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--white);
            width: 600px;
            max-width: 90%;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px;
            background: var(--primary);
            color: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            font-family: inherit;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.2);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .file-upload {
            border: 2px dashed var(--light-gray);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-upload:hover {
            border-color: var(--primary);
        }

        .file-upload i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .file-upload p {
            margin: 0;
            color: var(--dark-light);
        }

        .modal-footer {
            padding: 15px 20px;
            background: var(--light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Kullanıcı Menüsü */
        .user-menu {
            display: flex;
            align-items: center;
        }

        .user-info {
            text-align: right;
            margin-right: 15px;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
        }

        .user-title {
            font-size: 0.85rem;
            color: var(--dark-light);
        }

        .user-email {
            font-size: 0.85rem;
            color: var(--dark-light);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .dropdown-menu {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: var(--white);
            min-width: 200px;
            box-shadow: var(--shadow);
            border-radius: 8px;
            z-index: 1001;
            overflow: hidden;
            margin-top: 10px;
        }

        .dropdown-item {
            display: block;
            padding: 12px 20px;
            color: var(--dark);
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }

        .dropdown-item:hover {
            background: var(--primary);
            color: var(--white);
        }

        .dropdown-menu:hover .dropdown-content {
            display: block;
        }

        /* Side Panel Stilleri */
        .side-panel {
            position: fixed;
            top: 0;
            right: -450px;
            width: 450px;
            height: 100vh;
            background: var(--white);
            box-shadow: -5px 0 15px rgba(0,0,0,0.1);
            z-index: 2000;
            transition: var(--transition);
            overflow-y: auto;
        }

        .side-panel.active {
            right: 0;
        }

        .side-panel-header {
            padding: 20px;
            background: var(--primary);
            color: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .side-panel-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }

        .side-panel-close {
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
        }

        .side-panel-content {
            padding: 20px;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1999;
            display: none;
        }

        .overlay.active {
            display: block;
        }

        /* Detay Sayfası Stilleri */
        .assignment-detail {
            margin-bottom: 20px;
        }

        .assignment-detail h4 {
            font-size: 1.4rem;
            margin-bottom: 15px;
            color: var(--primary-dark);
        }

        .file-section {
            margin: 20px 0;
            padding: 15px;
            background: var(--light);
            border-radius: 8px;
        }

        .file-section h5 {
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .file-link {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: var(--white);
            border-radius: 6px;
            transition: var(--transition);
            text-decoration: none;
            color: var(--dark);
            border: 1px solid var(--light-gray);
        }

        .file-link:hover {
            background: var(--primary-light);
            color: var(--white);
            border-color: var(--primary-light);
        }

        .file-link i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .description-section {
            margin: 20px 0;
        }

        .description-section h5 {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .description-section p {
            line-height: 1.6;
            color: var(--dark);
        }

        .submissions-section {
            margin-top: 30px;
        }

        .submissions-section h5 {
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--light-gray);
        }

        .submissions-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .submission-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 10px;
            background: var(--light);
            border-radius: 8px;
            transition: var(--transition);
        }

        

        .student-name {
            font-weight: 600;
        }

        .submission-date {
            font-size: 0.85rem;
            color: var(--dark-light);
        }

        

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .grade-info {
            margin-top: 5px;
            font-size: 0.9rem;
        }

        .grade-info strong {
            color: var(--primary-dark);
        }

        .no-submissions {
            text-align: center;
            padding: 20px;
            color: var(--dark-light);
        }

        .no-submissions i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-light);
        }
    </style>
</head>
<body>

    <!-- 📌 SIDEBAR -->
    <div class="sidebar">
        <div class="logo-container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-atom"></i>
                </div>
                <div class="logo-text">Core<span>Lab</span></div>
                <div class="logo-subtext">Akademisyen Paneli</div>
            </div>
        </div>
        
        <div class="nav-menu">
            <a href="teacher.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Ana Sayfa</span>
            </a>
            <a href="odevler.php" class="nav-item active">
                <i class="fas fa-tasks"></i>
                <span>Ödevler</span>
            </a>
            <a href="students.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Öğrenciler</span>
            </a>
            <a href="mesajlar.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Mesajlar</span>
            </a>
            <a href="takvim.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Takvim</span>
            </a>
             <a href="profile.php" class="nav-item">
        <i class="fas fa-user-cog"></i>
        <span>Profil</span>
    </a>
</div>
        </div>
    </div>

     <!-- 📌 ANA İÇERİK -->
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Hoş Geldiniz, <?= htmlspecialchars($_SESSION['user']['full_name'] ?? '') ?></h1>
            
            <div class="user-menu">
                <div class="user-info"> 
                    <div class="user-name"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></div>
                    <div class="user-title">
                        <?= $academicTitles[$_SESSION['user']['academic_title']] ?? 'Akademisyen' ?>
                    </div>
                   <div class="user-email"><?= htmlspecialchars($_SESSION['user']['email']) ?>
                </div>
                </div>
                
                <div class="dropdown-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user']['email'], 0, 1)) ?>
                    </div>
                    <div class="dropdown-content">
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-user-cog"></i> Profil
                        </a>
                    
                         <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hata ve Başarı Mesajları -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="padding: 15px; background: #ffebee; color: #c62828; border-radius: 8px; margin-bottom: 20px;">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" style="padding: 15px; background: #e8f5e9; color: #2e7d32; border-radius: 8px; margin-bottom: 20px;">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="assignment-list-container">
            <div class="assignment-list-header">
                <div class="filter-controls">
                    <select class="filter-select">
                        <option>Tüm Dersler</option>
                        <option>Veritabanı Yönetimi</option>
                        <option>Web Programlama</option>
                        <option>Algoritma Analizi</option>
                    </select>
                    <select class="filter-select">
                        <option>Tüm Durumlar</option>
                        <option>Aktif Ödevler</option>
                        <option>Tamamlananlar</option>
                        <option>Yaklaşanlar</option>
                    </select>
                </div>
                <button class="btn btn-primary" id="newAssignmentBtn">
                    <i class="fas fa-plus"></i> Yeni Ödev
                </button>
            </div>
            
            <div class="assignment-cards">
                <?php if (empty($assignments)): ?>
                    <div class="no-assignment" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                        <i class="fas fa-tasks" style="font-size: 3rem; color: var(--primary-light); margin-bottom: 15px;"></i>
                        <h3 style="color: var(--dark-light); margin-bottom: 10px;">Henüz ödev oluşturmadınız</h3>
                        <p style="color: var(--dark-light);">Yeni bir ödev oluşturmak için "Yeni Ödev" butonuna tıklayın.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment): 
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
                    ?>
                    <div class="assignment-card">
                        <div class="assignment-card-header">
                            <h3 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h3>
                            <span class="assignment-status <?= $status_class ?>"><?= $status_text ?></span>
                        </div>
                        <div class="assignment-meta">
                            <div class="meta-item">
                                <i class="fas fa-book"></i>
                                <span><?= htmlspecialchars($assignment['course']) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Teslim: <?= date('d M Y', strtotime($assignment['due_date'])) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-users"></i>
                                <span><?= $assignment['assignment_type'] == 'individual' ? 'Bireysel' : 'Grup Çalışması' ?></span>
                            </div>
                        </div>
                        <div class="assignment-description">
                            <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                        </div>
                        <div class="assignment-footer">
                            <div class="submission-info">
                                <strong><?= $assignment['submission_count'] ?? 0 ?></strong> teslim edildi, 
                                <strong><?= ($assignment['submission_count'] ?? 0) - ($assignment['graded_count'] ?? 0) ?></strong> bekliyor
                            </div>
                            <div class="assignment-actions">
                                <a href="#" class="btn btn-outline detail-btn" data-id="<?= $assignment['id'] ?>">
                                    <i class="fas fa-eye"></i> Detay
                                </a>
<button class="btn btn-danger delete-btn" data-id="<?= $assignment['id'] ?>">
    <i class="fas fa-trash-alt"></i> Sil
</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 📌 YENİ ÖDEV MODALI -->
    <div class="modal" id="assignmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Yeni Ödev Oluştur</h3>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignmentForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="create_assignment" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="assignmentTitle" class="form-label">Ödev Başlığı</label>
                            <input type="text" id="assignmentTitle" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="assignmentCourse" class="form-label">Ders</label>
                            <select id="assignmentCourse" name="course" class="form-control" required>
                                <option value="">Seçiniz</option>
                                <option>Veritabanı Yönetimi</option>
                                <option>Web Programlama</option>
                                <option>Algoritma Analizi</option>
                                <option>Nesne Yönelimli Programlama</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="startDate" class="form-label">Başlangıç Tarihi</label>
                            <input type="datetime-local" id="startDate" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="dueDate" class="form-label">Teslim Tarihi</label>
                            <input type="datetime-local" id="dueDate" name="due_date" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignmentType" class="form-label">Ödev Türü</label>
                        <select id="assignmentType" name="assignment_type" class="form-control" required>
                            <option value="individual">Bireysel</option>
                            <option value="group">Grup Çalışması</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignmentDesc" class="form-label">Açıklama</label>
                        <textarea id="assignmentDesc" name="description" class="form-control" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Dosya Ekle (Opsiyonel)</label>
                        <div class="file-upload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Dosyaları sürükleyip bırakın veya tıklayarak seçin</p>
                            <input type="file" id="assignmentFiles" name="assignment_files[]" multiple style="display: none;">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelBtn">İptal</button>
                <button type="submit" form="assignmentForm" class="btn btn-primary">Kaydet</button>
            </div>
        </div>
    </div>
<!-- 📌 SİLME ONAY MODALI -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Ödev Silme Onayı</h3>
            <button class="modal-close" id="deleteModalClose">&times;</button>
        </div>
        <div class="modal-body">
            <p>Bu ödevi silmek istediğinize emin misiniz? Bu işlem geri alınamaz!</p>
            <p><strong>Ödev başlığı:</strong> <span id="assignmentTitleToDelete"></span></p>
            <p>Bu ödevle birlikte tüm öğrenci gönderimleri ve dosyaları da silinecektir.</p>
            
            <form id="deleteForm" method="POST">
                <input type="hidden" name="delete_assignment" value="1">
                <input type="hidden" id="assignmentIdToDelete" name="assignment_id" value="">
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" id="cancelDeleteBtn">Vazgeç</button>
            <button type="submit" form="deleteForm" class="btn btn-danger">Sil</button>
        </div>
    </div>
</div>
    <!-- 📌 ÖDEV DETAY SIDE PANEL -->
    <div class="side-panel" id="assignmentDetailPanel">
        <div class="side-panel-header">
            <h3>Ödev Detayları</h3>
            <button class="side-panel-close">&times;</button>
        </div>
        <div class="side-panel-content">
            <!-- Dinamik içerik buraya yüklenecek -->
        </div>
    </div>

    <!-- Overlay -->
    <div class="overlay"></div>
<!-- 📌 SİLME ONAY MODALI -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Ödev Silme Onayı</h3>
            <button class="modal-close" id="deleteModalClose">&times;</button>
        </div>
        <div class="modal-body">
            <p>Bu ödevi silmek istediğinize emin misiniz? Bu işlem geri alınamaz!</p>
            <p><strong>Ödev başlığı:</strong> <span id="assignmentTitleToDelete"></span></p>
            <p>Bu ödevle birlikte tüm öğrenci gönderimleri ve dosyaları da silinecektir.</p>
            
            <form id="deleteForm" method="POST">
                <input type="hidden" name="delete_assignment" value="1">
                <input type="hidden" id="assignmentIdToDelete" name="assignment_id" value="">
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" id="cancelDeleteBtn">Vazgeç</button>
            <button type="submit" form="deleteForm" class="btn btn-danger">Sil</button>
        </div>
    </div>
</div>
    <script>
        // Modal yönetimi
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('assignmentModal');
            const newBtn = document.getElementById('newAssignmentBtn');
            const closeBtn = document.getElementById('modalClose');
            const cancelBtn = document.getElementById('cancelBtn');
            
            // Modal açma
            newBtn.addEventListener('click', function() {
                modal.style.display = 'flex';
            });
            
            // Modal kapatma
            function closeModal() {
                modal.style.display = 'none';
            }
            
            closeBtn.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);
            
            // Dışarı tıklayarak kapatma
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
            
            // Dosya yükleme alanı
            const fileUpload = document.querySelector('.file-upload');
            const fileInput = document.getElementById('assignmentFiles');
            
             fileUpload.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    fileUpload.innerHTML = `
                        <i class="fas fa-check-circle" style="color: var(--success)"></i>
                        <p>${fileInput.files.length} dosya seçildi</p>
                    `;
                }
            });
            
            // Dropdown menüyü tıklanınca aç/kapa
            const dropdowns = document.querySelectorAll('.dropdown-menu');
            
            dropdowns.forEach(dropdown => {
                const avatar = dropdown.querySelector('.user-avatar');
                const menu = dropdown.querySelector('.dropdown-content');
                
                avatar.addEventListener('click', function(e) {
                    e.stopPropagation();
                    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                });
            });
            
            // Sayfada herhangi bir yere tıklandığında dropdown'ları kapat
            document.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-content').forEach(menu => {
                    menu.style.display = 'none';
                });
            });
            
            // Ödev detay panelini yönetme
            const detailPanel = document.getElementById('assignmentDetailPanel');
            const overlay = document.querySelector('.overlay');
            const detailCloseBtn = document.querySelector('.side-panel-close');
            const detailBtns = document.querySelectorAll('.detail-btn');
            
            // Detay panelini açma fonksiyonu
            function openDetailPanel(assignmentId) {
                fetch(`get_assignment_details.php?id=${assignmentId}`)
                    .then(response => response.text())
                    .then(data => {
                        document.querySelector('.side-panel-content').innerHTML = data;
                        detailPanel.classList.add('active');
                        overlay.classList.add('active');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Ödev detayları yüklenirken bir hata oluştu.');
                    });
            }
            
            // Detay panelini kapatma fonksiyonu
            function closeDetailPanel() {
                detailPanel.classList.remove('active');
                overlay.classList.remove('active');
            }
            
            // Detay butonlarına tıklama eventi ekle
            detailBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const assignmentId = this.getAttribute('data-id');
                    openDetailPanel(assignmentId);
                });
            });
            
            // Kapatma butonlarına event ekle
            detailCloseBtn.addEventListener('click', closeDetailPanel);
            overlay.addEventListener('click', closeDetailPanel);
            
            // ESC tuşuna basınca paneli kapat
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDetailPanel();
                }
            });
        });
        // Silme modalını yönetme
const deleteModal = document.getElementById('deleteModal');
const deleteBtns = document.querySelectorAll('.delete-btn');
const deleteModalClose = document.getElementById('deleteModalClose');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

// Silme butonlarına tıklama
deleteBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        const assignmentId = this.getAttribute('data-id');
        const assignmentTitle = this.closest('.assignment-card').querySelector('.assignment-title').textContent;
        
        document.getElementById('assignmentIdToDelete').value = assignmentId;
        document.getElementById('assignmentTitleToDelete').textContent = assignmentTitle;
        deleteModal.style.display = 'flex';
    });
});

// Silme modalını kapatma
function closeDeleteModal() {
    deleteModal.style.display = 'none';
}

deleteModalClose.addEventListener('click', closeDeleteModal);
cancelDeleteBtn.addEventListener('click', closeDeleteModal);

// Dışarı tıklayarak kapatma
window.addEventListener('click', function(e) {
    if (e.target === deleteModal) {
        closeDeleteModal();
    }
});
    </script>
</body>
</html>