<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'teacher') {
    header("Location: index.php");
    exit();
}

// Database connection
require_once 'includes/config.php';

// Handle student deletion
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$_GET['delete_id']]);
        $_SESSION['success'] = "Öğrenci başarıyla silindi.";
        header("Location: students.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Öğrenci silinirken hata oluştu: " . $e->getMessage();
    }
}

// Handle student addition/editing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $student_number = trim($_POST['student_number']);
    $department = trim($_POST['department']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($full_name) || empty($email) || empty($student_number)) {
        $_SESSION['error'] = "Lütfen gerekli alanları doldurun.";
    } else {
        try {
            if ($id) {
                // Update existing student
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, student_number = ?, department = ?, password = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $student_number, $department, $hashed_password, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, student_number = ?, department = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $student_number, $department, $id]);
                }
                $_SESSION['success'] = "Öğrenci bilgileri güncellendi.";
            } else {
                // Add new student
                if (empty($password)) {
                    $_SESSION['error'] = "Yeni öğrenci için şifre gereklidir.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (full_name, email, student_number, department, password, role) VALUES (?, ?, ?, ?, ?, 'student')");
                    $stmt->execute([$full_name, $email, $student_number, $department, $hashed_password]);
                    $_SESSION['success'] = "Yeni öğrenci eklendi.";
                }
            }
            header("Location: students.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "İşlem sırasında hata oluştu: " . $e->getMessage();
        }
    }
}

// Fetch student list
try {
    $stmt = $db->prepare("SELECT id, full_name, email, student_number, department, created_at 
                         FROM users 
                         WHERE role = 'student'
                         ORDER BY full_name ASC");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $students = [];
    error_log("Database error: " . $e->getMessage());
}

// Fetch student data for editing
$edit_student = null;
if (isset($_GET['edit_id'])) {
    try {
        $stmt = $db->prepare("SELECT id, full_name, email, student_number, department FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$_GET['edit_id']]);
        $edit_student = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Öğrenci bilgileri alınırken hata oluştu: " . $e->getMessage();
    }
}

// Departments list
$departments = [
    'Bilgisayar Mühendisliği',
    'Elektrik-Elektronik Mühendisliği',
    'Makine Mühendisliği',
    'Endüstri Mühendisliği',
    'Kimya Mühendisliği',
    'Fizik',
    'Matematik',
    'Biyoloji'
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenciler | CoreLab Akademik Yönetim</title>
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
            overflow-x: hidden;
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
            transition: var(--transition);
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
            transition: var(--transition);
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
            box-shadow: 0 6px 15px rgba(108, 92, 231, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
        }

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn i {
            font-size: 0.9rem;
        }

        /* 📌 ÖĞRENCİ LİSTESİ */
        .student-list-container {
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-top: 20px;
        }

        .student-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .student-list-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
        }

        .search-filter-container {
            display: flex;
            gap: 15px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 15px 10px 40px;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            width: 250px;
            transition: var(--transition);
        }

        .search-box input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.2);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-light);
        }

        .filter-dropdown select {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            background: var(--white);
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-dropdown select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.2);
        }

        .student-table {
            width: 100%;
            border-collapse: collapse;
        }

        .student-table th {
            text-align: left;
            padding: 12px 15px;
            background: var(--primary);
            color: var(--white);
            font-weight: 600;
        }

        .student-table th:first-child {
            border-top-left-radius: 8px;
        }

        .student-table th:last-child {
            border-top-right-radius: 8px;
        }

        .student-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
            vertical-align: middle;
        }

        .student-table tr:last-child td {
            border-bottom: none;
        }

        .student-table tr:hover td {
            background: rgba(108, 92, 231, 0.05);
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .student-name {
            font-weight: 600;
            margin-left: 10px;
        }

        .student-info {
            display: flex;
            align-items: center;
        }

        .student-email, .student-number {
            font-size: 0.9rem;
            color: var(--dark-light);
        }

        .student-department {
            display: inline-block;
            padding: 4px 8px;
            background: rgba(108, 92, 231, 0.1);
            color: var(--primary);
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .student-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .action-btn.edit {
            background: rgba(253, 203, 110, 0.1);
            color: var(--warning);
        }

        .action-btn.edit:hover {
            background: var(--warning);
            color: var(--white);
        }

        .action-btn.delete {
            background: rgba(214, 48, 49, 0.1);
            color: var(--danger);
        }

        .action-btn.delete:hover {
            background: var(--danger);
            color: var(--white);
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }

        .pagination-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--white);
            color: var(--dark);
            border: 1px solid var(--light-gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .pagination-btn:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .pagination-btn.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--dark-light);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--primary-light);
            margin-bottom: 15px;
        }

        .empty-state p {
            margin-bottom: 15px;
        }

        /* 📌 DROPDOWN MENÜ */
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

        /* 📌 MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: 12px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark-light);
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* 📌 FORM ELEMANLARI */
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
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.2);
        }

        /* 📌 ALERT MESSAGES */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.1);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.2);
        }

        .alert-danger {
            background: rgba(214, 48, 49, 0.1);
            color: var(--danger);
            border: 1px solid rgba(214, 48, 49, 0.2);
        }

        /* 📌 RESPONSIVE AYARLAR */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
        }

        @media (max-width: 768px) {
            .student-list-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .search-filter-container {
                width: 100%;
                flex-direction: column;
                gap: 10px;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .filter-dropdown select {
                width: 100%;
            }
            
            .student-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
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
            <a href="odevler.php" class="nav-item">
                <i class="fas fa-tasks"></i>
                <span>Ödevler</span>
            </a>
            <a href="students.php" class="nav-item active">
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
            <h1 class="page-title">Öğrenci Yönetimi</h1>
            
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'Öğretmen') ?></div>
                    <div class="user-role">Öğretmen</div>
                </div>
                
                <div class="dropdown-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user']['email'], 0, 1)) ?>
                    </div>
                    <div class="dropdown-content">
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-user-cog"></i> Profil
                        </a>
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-cog"></i> Ayarlar
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
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="student-list-container">
            <div class="student-list-header">
                <h2 class="student-list-title">Öğrenci Listesi</h2>
                
                <div class="search-filter-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Öğrenci ara...">
                    </div>
                    
                    <div class="filter-dropdown">
                        <select>
                            <option value="">Tüm Bölümler</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Yeni Öğrenci
                    </button>
                </div>
            </div>
            
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-graduate"></i>
                    <p>Kayıtlı öğrenci bulunamadı</p>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Öğrenci Ekle
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th>Öğrenci</th>
                                <th>Öğrenci No</th>
                                <th>Bölüm</th>
                                <th>Kayıt Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="student-name"><?= htmlspecialchars($student['full_name']) ?></div>
                                            <div class="student-email"><?= htmlspecialchars($student['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="student-number"><?= htmlspecialchars($student['student_number'] ?? 'Belirtilmemiş') ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($student['department'])): ?>
                                        <span class="student-department"><?= htmlspecialchars($student['department']) ?></span>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('d.m.Y', strtotime($student['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="student-actions">
                                        <button class="action-btn edit" title="Düzenle" onclick="openEditModal(
                                            <?= $student['id'] ?>, 
                                            '<?= htmlspecialchars(addslashes($student['full_name'])) ?>',
                                            '<?= htmlspecialchars(addslashes($student['email'])) ?>',
                                            '<?= htmlspecialchars(addslashes($student['student_number'])) ?>',
                                            '<?= htmlspecialchars(addslashes($student['department'] ?? '')) ?>'
                                        )">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete" title="Sil" onclick="confirmDelete(<?= $student['id'] ?>, '<?= htmlspecialchars(addslashes($student['full_name'])) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination">
                    <button class="pagination-btn disabled">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Silme Onay Modalı -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Öğrenci Silme</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Bu öğrenciyi silmek istediğinizden emin misiniz?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('deleteModal')">İptal</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Sil</a>
            </div>
        </div>
    </div>

    <!-- Öğrenci Ekle/Düzenle Modalı -->
    <div class="modal" id="studentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Yeni Öğrenci Ekle</h3>
                <button class="modal-close" onclick="closeModal('studentModal')">&times;</button>
            </div>
            <form id="studentForm" method="POST" action="students.php">
                <input type="hidden" name="id" id="studentId">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="full_name" class="form-label">Tam Adı*</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">E-posta*</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="student_number" class="form-label">Öğrenci Numarası*</label>
                        <input type="text" class="form-control" id="student_number" name="student_number" required>
                    </div>
                    <div class="form-group">
                        <label for="department" class="form-label">Bölüm</label>
                        <select class="form-control" id="department" name="department">
                            <option value="">Seçiniz</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label" id="passwordLabel">Şifre*</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small id="passwordHelp" class="text-muted">Yalnızca şifreyi değiştirmek istiyorsanız doldurun</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('studentModal')">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Dropdown menüyü tıklanınca aç/kapa
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Arama kutusu işlevselliği
            const searchInput = document.querySelector('.search-box input');
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.student-table tbody tr');
                
                rows.forEach(row => {
                    const studentName = row.querySelector('.student-name').textContent.toLowerCase();
                    const studentNumber = row.querySelector('.student-number').textContent.toLowerCase();
                    const studentEmail = row.querySelector('.student-email').textContent.toLowerCase();
                    
                    if (studentName.includes(searchTerm) || studentNumber.includes(searchTerm) || studentEmail.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Bölüm filtreleme
            const departmentFilter = document.querySelector('.filter-dropdown select');
            departmentFilter.addEventListener('change', function() {
                const selectedDept = this.value;
                const rows = document.querySelectorAll('.student-table tbody tr');
                
                rows.forEach(row => {
                    const dept = row.querySelector('.student-department')?.textContent || '';
                    
                    if (selectedDept === '' || dept === selectedDept) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
        
        // Silme onay modalı
        function confirmDelete(id, name) {
            const modal = document.getElementById('deleteModal');
            const message = document.getElementById('deleteMessage');
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            
            message.textContent = `"${name}" adlı öğrenciyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`;
            deleteBtn.href = `students.php?delete_id=${id}`;
            modal.style.display = 'flex';
        }
        
        // Öğrenci ekleme modalını aç
        function openAddModal() {
            const modal = document.getElementById('studentModal');
            const form = document.getElementById('studentForm');
            const title = document.getElementById('modalTitle');
            const passwordLabel = document.getElementById('passwordLabel');
            const passwordHelp = document.getElementById('passwordHelp');
            
            // Formu sıfırla
            form.reset();
            document.getElementById('studentId').value = '';
            
            // Başlık ve şifre alanını ayarla
            title.textContent = 'Yeni Öğrenci Ekle';
            passwordLabel.textContent = 'Şifre*';
            passwordHelp.style.display = 'none';
            document.getElementById('password').required = true;
            modal.style.display = 'flex';
        }

        // Öğrenci düzenleme modalını aç
        function openEditModal(id, name, email, number, department) {
            const modal = document.getElementById('studentModal');
            const form = document.getElementById('studentForm');
            const title = document.getElementById('modalTitle');
            const passwordLabel = document.getElementById('passwordLabel');
            const passwordHelp = document.getElementById('passwordHelp');

            // Form verilerini doldur
            document.getElementById('studentId').value = id;
            document.getElementById('full_name').value = name;
            document.getElementById('email').value = email;
            document.getElementById('student_number').value = number;
            document.getElementById('department').value = department;
            
            // Başlık ve şifre alanını ayarla
            title.textContent = 'Öğrenci Düzenle';
            passwordLabel.textContent = 'Şifre Değiştir';
            passwordHelp.style.display = 'block';
            document.getElementById('password').required = false;
            modal.style.display = 'flex';
        }

        // Modal kapatma fonksiyonu
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Modal dışına tıklandığında kapat
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });

        // Form gönderiminde şifre kontrolü
        document.getElementById('studentForm').addEventListener('submit', function(e) {
            const isEditMode = !!document.getElementById('studentId').value;
            const passwordField = document.getElementById('password');
            
            if (!isEditMode && passwordField.value === '') {
                e.preventDefault();
                alert('Yeni öğrenci için şifre gereklidir!');
                passwordField.focus();
            }
        });
    </script>
</body>
</html>