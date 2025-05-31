<?php
// Oturum kontrolü
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hata raporlama (geliştirme aşamasında açık tutun)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Veritabanı bağlantısı
try {
    $db = new PDO('mysql:host=localhost;dbname=corelab1_db;charset=utf8', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Tablo kontrolü (opsiyonel)
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('teacher','student') NOT NULL,
        student_number VARCHAR(20) NULL,
        academic_title VARCHAR(50) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

// Kayıt işlemi
if(isset($_POST['register'])) {
    try {
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $student_number = ($role == 'student') ? htmlspecialchars(trim($_POST['student_number'])) : null;
        $academic_title = ($role == 'teacher') ? $_POST['academic_title'] : null;

        // Email kontrolü
        $checkEmail = $db->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        
        if($checkEmail->rowCount() > 0) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Bu email adresi zaten kayıtlı!'];
            header('Location: index.php');
            exit();
        }

        // Öğrenci numarası kontrolü
        if($role == 'student' && !empty($student_number)) {
            $checkStudentNo = $db->prepare("SELECT id FROM users WHERE student_number = ?");
            $checkStudentNo->execute([$student_number]);
            
            if($checkStudentNo->rowCount() > 0) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Bu öğrenci numarası zaten kayıtlı!'];
                header('Location: index.php');
                exit();
            }
        }

        // Kullanıcıyı kaydet
        $query = $db->prepare("INSERT INTO users 
                             (full_name, email, password, role, student_number, academic_title) 
                             VALUES 
                             (:full_name, :email, :password, :role, :student_number, :academic_title)");
        
        $query->execute([
            ':full_name' => $full_name,
            ':email' => $email,
            ':password' => $password,
            ':role' => $role,
            ':student_number' => $student_number,
            ':academic_title' => $academic_title
        ]);
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Kayıt başarılı! Giriş yapabilirsiniz.'];
        header('Location: index.php');
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Kayıt hatası: '.$e->getMessage()];
        header('Location: index.php');
        exit();
    }
}

// Giriş işlemi
if(isset($_POST['login'])) {
    try {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header("Location: " . ($user['role'] == 'teacher' ? 'teacher.php' : 'student.php'));
            exit();
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Hatalı e-posta veya şifre!'];
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Giriş hatası: '.$e->getMessage()];
        header('Location: index.php');
        exit();
    }
}
?>