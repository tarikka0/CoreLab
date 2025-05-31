<?php
// Oturum başlatma ve güvenlik kontrolleri
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hata raporlama (geliştirme aşamasında açık)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Giriş kontrolü
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Rol kontrolü
if ($_SESSION['user']['role'] !== 'student') {
    header("Location: unauthorized.php");
    exit();
}

// Veritabanı bağlantısı
require_once 'includes/config.php';

// Form gönderildiğinde profil bilgilerini güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $errors = [];
        $student_id = $_SESSION['user']['id'];
        
        // Form verilerini al ve temizle
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $student_number = trim($_POST['student_number'] ?? '');
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $department = trim($_POST['department'] ?? '');
        
        // Doğrulamalar
        if (empty($full_name)) {
            $errors[] = "Ad soyad boş bırakılamaz";
        }
        
        if (empty($email)) {
            $errors[] = "E-posta boş bırakılamaz";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Geçersiz e-posta formatı";
        }
        
        if (empty($student_number)) {
            $errors[] = "Öğrenci numarası boş bırakılamaz";
        }
        
        // E-posta değiştiyse ve başka bir kullanıcı tarafından kullanılıyorsa kontrol et
        if ($email !== $_SESSION['user']['email']) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $email, ':id' => $student_id]);
            if ($stmt->fetch()) {
                $errors[] = "Bu e-posta adresi zaten kullanımda";
            }
        }
        
        // Şifre değiştirilmek isteniyorsa
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $errors[] = "Mevcut şifrenizi girmelisiniz";
            } else {
                // Mevcut şifreyi doğrula
                $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
                $stmt->execute([':id' => $student_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user || !password_verify($current_password, $user['password'])) {
                    $errors[] = "Mevcut şifreniz yanlış";
                } elseif (strlen($new_password) < 8) {
                    $errors[] = "Yeni şifre en az 8 karakter olmalıdır";
                } elseif ($new_password !== $confirm_password) {
                    $errors[] = "Yeni şifreler eşleşmiyor";
                }
            }
        }
        
        // Hata yoksa güncelleme yap
        if (empty($errors)) {
            // Şifre güncelleme varsa
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET full_name = :full_name, email = :email, 
                                      student_number = :student_number, password = :password, 
                                      department = :department WHERE id = :id");
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':student_number' => $student_number,
                    ':password' => $hashed_password,
                    ':department' => $department,
                    ':id' => $student_id
                ]);
            } else {
                // Şifre güncelleme yoksa
                $stmt = $db->prepare("UPDATE users SET full_name = :full_name, email = :email, 
                                    student_number = :student_number, department = :department 
                                    WHERE id = :id");
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':student_number' => $student_number,
                    ':department' => $department,
                    ':id' => $student_id
                ]);
            }
            
            // Oturum bilgilerini güncelle
            $_SESSION['user']['full_name'] = $full_name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['student_number'] = $student_number;
            $_SESSION['user']['department'] = $department;
            
            $success = "Profil bilgileriniz başarıyla güncellendi";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $errors[] = "Veritabanı hatası: " . $e->getMessage();
    }
}

// Kullanıcı bilgilerini veritabanından çek (güncel olması için)
try {
    $student_id = $_SESSION['user']['id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header("Location: logout.php");
        exit();
    }
    
    // Oturum bilgilerini güncelle (veritabanındaki en güncel hali)
    $_SESSION['user'] = $student;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $errors[] = "Veritabanı hatası: " . $e->getMessage();
}

// Önbellek kontrolü headers'ı
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Oturum sabitleme
session_regenerate_id(true);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Ayarları | CoreLab Öğrenci Paneli</title>
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
            --info: #0984e3;
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

        /* SIDEBAR */
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

        /* MAIN CONTENT */
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

        .welcome-message {
            font-size: 1rem;
            color: var(--dark-light);
            margin-top: 5px;
        }

        /* PROFILE CONTENT */
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        /* PROFILE CARD */
        .profile-card {
            background: var(--white);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
            text-align: center;
            height: fit-content;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            margin: 0 auto 20px;
            position: relative;
            overflow: hidden;
        }

        .profile-avatar-initials {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .profile-title {
            font-size: 1rem;
            color: var(--primary-dark);
            margin-bottom: 15px;
        }

        .profile-department {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--dark-light);
            margin-bottom: 20px;
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid var(--light-gray);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--dark-light);
        }

        /* PROFILE FORM */
        .profile-form-container {
            background: var(--white);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .form-section-title {
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section-title i {
            color: var(--primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--light);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.2);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23636e72' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px 12px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
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
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }

        /* ALERTS */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: rgba(214, 48, 49, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* PASSWORD TOGGLE */
        .password-toggle {
            position: relative;
        }

        .password-toggle-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--dark-light);
        }

        /* USER MENU */
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

        /* SCROLLBAR */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-gray);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-menu {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
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
            <a href="odevst.php" class="nav-item">
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
            <a href="profilst.php" class="nav-item active">
                <i class="fas fa-user-cog"></i>
                <span>Profil</span>
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="header">
            <div>
                <h1 class="page-title">Profil Ayarları</h1>
                <p class="welcome-message">Kişisel bilgilerinizi ve şifrenizi güncelleyebilirsiniz</p>
            </div>
            
            <div class="user-menu">
                <div class="user-info"> 
                    <div class="user-name"><?= htmlspecialchars($_SESSION['user']['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="user-title">Öğrenci</div>
                    <div class="user-email"><?= htmlspecialchars($_SESSION['user']['email'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                
                <div class="dropdown-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user']['email'], 0, 1)) ?>
                    </div>
                    <div class="dropdown-content">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-cog"></i> Profil
                        </a>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ALERTS -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <!-- PROFILE CONTENT -->
        <div class="profile-container">
            <!-- PROFILE CARD -->
            <div class="profile-card">
                <div class="profile-avatar">
                    <div class="profile-avatar-initials">
                        <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                    </div>
                </div>
                
                <h2 class="profile-name"><?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="profile-title">Öğrenci</div>
                
                <?php if (!empty($student['student_number'])): ?>
                    <div class="profile-department">
                        <i class="fas fa-id-card"></i>
                        <span><?= htmlspecialchars($student['student_number'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($student['department'])): ?>
                    <div class="profile-department">
                        <i class="fas fa-university"></i>
                        <span><?= htmlspecialchars($student['department'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?= date('Y') - date('Y', strtotime($student['created_at'])) ?></div>
                        <div class="stat-label">Yıllık Üyelik</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= strtoupper(substr($student['role'], 0, 1)) ?></div>
                        <div class="stat-label">Rol</div>
                    </div>
                </div>
            </div>
            
            <!-- PROFILE FORM -->
            <div class="profile-form-container">
                <form method="POST" action="profilst.php">
                    <h2 class="form-section-title">
                        <i class="fas fa-user-edit"></i> Kişisel Bilgiler
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Ad Soyad</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   value="<?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-posta Adresi</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="student_number">Öğrenci Numarası</label>
                            <input type="text" id="student_number" name="student_number" class="form-control" 
                                   value="<?= htmlspecialchars($student['student_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="department">Bölüm/Departman</label>
                            <input type="text" id="department" name="department" class="form-control" 
                                   value="<?= htmlspecialchars($student['department'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    
                    <h2 class="form-section-title" style="margin-top: 40px;">
                        <i class="fas fa-lock"></i> Şifre Değiştir
                    </h2>
                    
                    <div class="form-group">
                        <label for="current_password">Mevcut Şifre</label>
                        <div class="password-toggle">
                            <input type="password" id="current_password" name="current_password" class="form-control">
                            <i class="fas fa-eye password-toggle-icon" onclick="togglePassword('current_password')"></i>
                        </div>
                        <small class="text-muted">Şifrenizi değiştirmek istemiyorsanız boş bırakın</small>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="new_password">Yeni Şifre</label>
                            <div class="password-toggle">
                                <input type="password" id="new_password" name="new_password" class="form-control">
                                <i class="fas fa-eye password-toggle-icon" onclick="togglePassword('new_password')"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Yeni Şifre (Tekrar)</label>
                            <div class="password-toggle">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                <i class="fas fa-eye password-toggle-icon" onclick="togglePassword('confirm_password')"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-outline">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Şifre göster/gizle
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
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
        });
    </script>
</body>
</html>