<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student') {
    header("Location: index.php");
    exit();
}

require_once 'includes/config.php';

// Ödev teslim etme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_assignment'])) {
    try {
        $assignment_id = $_POST['assignment_id'];
        $student_id = $_SESSION['user']['id'];
        $note = htmlspecialchars(trim($_POST['note']));
        
        // Ödevin teslim tarihini kontrol et
        $stmt = $db->prepare("SELECT due_date FROM assignments WHERE id = :assignment_id");
        $stmt->execute([':assignment_id' => $assignment_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $due_date = new DateTime($assignment['due_date']);
        $now = new DateTime();
        
        if ($now > $due_date) {
            $_SESSION['error'] = "Teslim tarihi geçmiş ödevleri teslim edemezsiniz!";
            header("Location: odevst.php");
            exit();
        }
        
        // Teslim kaydını oluştur
        $stmt = $db->prepare("INSERT INTO submissions (assignment_id, student_id, note) 
                             VALUES (:assignment_id, :student_id, :note)");
        $stmt->execute([
            ':assignment_id' => $assignment_id,
            ':student_id' => $student_id,
            ':note' => $note
        ]);
        
        $submission_id = $db->lastInsertId();
        
        // Dosya yükleme işlemi
        if (!empty($_FILES['submission_files']['name'][0])) {
            $upload_dir = 'uploads/submissions/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['submission_files']['tmp_name'] as $key => $tmp_name) {
                $file_name = basename($_FILES['submission_files']['name'][$key]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $new_file_name = uniqid() . '_' . $file_name;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $stmt = $db->prepare("INSERT INTO submission_files (submission_id, file_name, file_path) 
                                             VALUES (:submission_id, :file_name, :file_path)");
                        $stmt->execute([
                            ':submission_id' => $submission_id,
                            ':file_name' => $file_name,
                            ':file_path' => $file_path
                        ]);
                    }
                }
            }
        }
        
        $_SESSION['success'] = "Ödev başarıyla teslim edildi!";
        header("Location: odevler.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Ödev teslim edilirken bir hata oluştu: " . $e->getMessage();
        header("Location: odevler.php");
        exit();
    }
}

// Öğrencinin derslerini al (ödevleri filtrelemek için)
$student_courses = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT course FROM assignments");
    $stmt->execute();
    $student_courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $_SESSION['error'] = "Dersler yüklenirken bir hata oluştu: " . $e->getMessage();
}

// Öğrencinin teslim ettiği ödevleri al
$submitted_assignments = [];
try {
    $stmt = $db->prepare("SELECT assignment_id FROM submissions WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $_SESSION['user']['id']]);
    $submitted_assignments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $_SESSION['error'] = "Teslim edilen ödevler yüklenirken bir hata oluştu: " . $e->getMessage();
}

// Tüm ödevleri getir (öğrencinin derslerine göre)
try {
    $query = "SELECT a.*, 
              u.full_name as teacher_name,
              u.academic_title as teacher_title,
              (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count
              FROM assignments a
              JOIN users u ON a.teacher_id = u.id
              ORDER BY a.due_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Ödevler yüklenirken bir hata oluştu: " . $e->getMessage();
    $assignments = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödevler | CoreLab Akademik Yönetim</title>
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

.submission-info-section {
    margin-top: 30px;
}

.submission-info-section h5 {
    font-size: 1.1rem;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--light-gray);
}

.submission-status {
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
}

.submission-status.submitted {
    background: rgba(0, 184, 148, 0.1);
    color: var(--success);
}

.submission-status.not-submitted {
    background: rgba(214, 48, 49, 0.1);
    color: var(--danger);
}

.submission-files {
    margin-top: 15px;
}

.submission-note {
    margin-top: 15px;
    padding: 15px;
    background: var(--light);
    border-radius: 8px;
}

.submission-note h6 {
    margin-bottom: 10px;
    font-size: 1rem;
}
        /* 📌 TEMEL STİLLER */
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

        /* 📌 SIDEBAR */
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

        /* 📌 ANA İÇERİK */
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

        /* 📌 BUTONLAR */
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

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-success:hover {
            background: #00a884;
        }

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        /* 📌 ÖDEV LİSTESİ */
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

        /* 📌 ÖDEV KARTLARI */
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

        /* 📌 TESLİM MODALI */
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

        /* 📌 KULLANICI MENÜSÜ */
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

        .user-role {
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

        /* Akademik unvan stilleri */
        .teacher-title {
            font-weight: 600;
            color: var(--primary-dark);
        }
        
    </style>
</head>
<body>

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
    <!-- 📌 SIDEBAR -->
    <div class="sidebar">
        <div class="logo-container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-atom"></i>
                </div>
                <div class="logo-text">Core<span>Lab</span></div>
                <div class="logo-subtext">Öğrenci Paneli</div>
            </div>
        </div>
        
        <div class="nav-menu">
            <a href="student.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Ana Sayfa</span>
            </a>
            <a href="odevst.php" class="nav-item active">
                <i class="fas fa-tasks"></i>
                <span>Ödevlerim</span>
            </a>
            <a href="mesajst.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Duyurular / Mesajlar</span>
            </a>
            <a href="takvimst.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Takvim</span>
            </a>
             <a href="profilst.php" class="nav-item">
        <i class="fas fa-user-cog"></i>
        <span>Profil</span>
    </a>
        </div>
    </div>

    <!-- 📌 ANA İÇERİK -->
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Ödevlerim</h1>
            
            <div class="user-menu">
                <div class="user-info"> 
                    <div class="user-name"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></div>
                    <div class="user-role">Öğrenci</div>
                    <div class="user-email"><?= htmlspecialchars($_SESSION['user']['email']) ?></div>
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
                    <select class="filter-select" id="courseFilter">
                        <option value="">Tüm Dersler</option>
                        <?php foreach ($student_courses as $course): ?>
                            <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">Tüm Durumlar</option>
                        <option value="active">Aktif Ödevler</option>
                        <option value="upcoming">Yaklaşanlar</option>
                        <option value="ended">Tamamlananlar</option>
                        <option value="submitted">Teslim Edilenler</option>
                    </select>
                </div>
            </div>
            
            <div class="assignment-cards">
                <?php if (empty($assignments)): ?>
                    <div class="no-assignment" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                        <i class="fas fa-tasks" style="font-size: 3rem; color: var(--primary-light); margin-bottom: 15px;"></i>
                        <h3 style="color: var(--dark-light); margin-bottom: 10px;">Henüz ödev bulunmamaktadır</h3>
                        <p style="color: var(--dark-light);">Öğretmenleriniz tarafından yüklenen ödevler burada görünecektir.</p>
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
                        
                        // Öğrencinin bu ödevi teslim edip etmediğini kontrol et
                        $is_submitted = in_array($assignment['id'], $submitted_assignments);
                    ?>
                    <div class="assignment-card" 
                         data-course="<?= htmlspecialchars($assignment['course']) ?>" 
                         data-status="<?= ($now > $due_date) ? 'ended' : (($now >= $start_date) ? 'active' : 'upcoming') ?>"
                         data-submitted="<?= $is_submitted ? 'true' : 'false' ?>">
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
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span class="teacher-title">
                                    <?php 
                                        $teacher_title = '';
                                        switch ($assignment['teacher_title']) {
                                            case 'prof': $teacher_title = 'Prof. Dr.'; break;
                                            case 'assoc_prof': $teacher_title = 'Doç. Dr.'; break;
                                            case 'assist_prof': $teacher_title = 'Dr. Öğr. Üyesi'; break;
                                            case 'lecturer': $teacher_title = 'Öğr. Gör.'; break;
                                            case 'res_assist': $teacher_title = 'Arş. Gör.'; break;
                                            default: $teacher_title = '';
                                        }
                                        echo $teacher_title . ' ' . htmlspecialchars($assignment['teacher_name']);
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="assignment-description">
                            <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                        </div>
                      <div class="assignment-footer">
    <div class="submission-info">
        <strong><?= $assignment['submission_count'] ?></strong> öğrenci teslim etti
    </div>
    <div class="assignment-actions">
        <!-- 📌 BURAYA EKLİYORSUN -->
        <button class="btn btn-outline detail-btn" data-id="<?= $assignment['id'] ?>">
            <i class="fas fa-info-circle"></i> Detay
        </button>

        <?php if ($is_submitted): ?>
            <span class="btn btn-success">
                <i class="fas fa-check"></i> Teslim Edildi
            </span>
        <?php elseif ($now > $due_date): ?>
            <span class="btn btn-danger">
                <i class="fas fa-times"></i> Teslim Süresi Doldu
            </span>
        <?php else: ?>
            <button class="btn btn-primary submit-btn" data-id="<?= $assignment['id'] ?>">
                <i class="fas fa-upload"></i> Teslim Et
            </button>
        <?php endif; ?>
    </div>
</div>

                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 📌 TESLİM MODALI -->
    <div class="modal" id="submissionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ödev Teslim Et</h3>
                <button class="modal-close" id="submissionModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <form id="submissionForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="submit_assignment" value="1">
                    <input type="hidden" id="assignmentId" name="assignment_id" value="">
                    
                    <div class="form-group">
                        <label for="submissionNote" class="form-label">Açıklama (Opsiyonel)</label>
                        <textarea id="submissionNote" name="note" class="form-control"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Dosya Ekle</label>
                        <div class="file-upload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Dosyaları sürükleyip bırakın veya tıklayarak seçin</p>
                            <input type="file" id="submissionFiles" name="submission_files[]" multiple style="display: none;">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelSubmissionBtn">İptal</button>
                <button type="submit" form="submissionForm" class="btn btn-primary">Teslim Et</button>
            </div>
        </div>
    </div>

    <script>
        // Ödev detay panelini yönetme
const detailPanel = document.getElementById('assignmentDetailPanel');
const overlay = document.querySelector('.overlay');
const detailCloseBtn = document.querySelector('.side-panel-close');
const detailBtns = document.querySelectorAll('.detail-btn');

// Detay panelini açma fonksiyonu
function openDetailPanel(assignmentId) {
    fetch(`get_assignment_details_student.php?id=${assignmentId}`)
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
        // Modal yönetimi
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('submissionModal');
            const closeBtn = document.getElementById('submissionModalClose');
            const cancelBtn = document.getElementById('cancelSubmissionBtn');
            const submitBtns = document.querySelectorAll('.submit-btn');
            
            // Teslim et butonlarına tıklama
            submitBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const assignmentId = this.getAttribute('data-id');
                    document.getElementById('assignmentId').value = assignmentId;
                    modal.style.display = 'flex';
                });
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
            const fileUpload = document.querySelector('#submissionModal .file-upload');
            const fileInput = document.getElementById('submissionFiles');
            
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
            
            // Filtreleme işlemleri
            const courseFilter = document.getElementById('courseFilter');
            const statusFilter = document.getElementById('statusFilter');
            const assignmentCards = document.querySelectorAll('.assignment-card');
            
            function filterAssignments() {
                const selectedCourse = courseFilter.value;
                const selectedStatus = statusFilter.value;
                
                assignmentCards.forEach(card => {
                    const course = card.getAttribute('data-course');
                    const status = card.getAttribute('data-status');
                    const submitted = card.getAttribute('data-submitted');
                    
                    const courseMatch = selectedCourse === '' || course === selectedCourse;
                    let statusMatch = true;
                    
                    if (selectedStatus === 'submitted') {
                        statusMatch = submitted === 'true';
                    } else if (selectedStatus !== '') {
                        statusMatch = status === selectedStatus;
                    }
                    
                    if (courseMatch && statusMatch) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            
            courseFilter.addEventListener('change', filterAssignments);
            statusFilter.addEventListener('change', filterAssignments);
        });
    </script>
</body>
</html>