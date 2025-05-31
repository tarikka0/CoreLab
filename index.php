<?php 
require __DIR__ . '/includes/config.php';

if (isset($_SESSION['user'])) {
    header("Location: " . ($_SESSION['user']['role'] == 'teacher' ? 'teacher.php' : 'student.php'));
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CoreLab | Akademik Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #1a252f;
            --accent-color: #3498db;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --text-dark: #2b2d42;
            --text-light: #f8f9fa;
            --border-color: #dee2e6;
            --dark-bg: #121212;
            --dark-card: #1e1e1e;
            --dark-border: #333;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
            line-height: 1.6;
            transition: all 0.3s ease;
        }

        body.dark-mode {
            background-color: var(--dark-bg);
            color: var(--text-light);
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 999;
            background: var(--primary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: scale(1.1);
            background: var(--secondary-color);
        }

        .logo {
            text-align: center;
            margin: 20px 0 30px;
            animation: fadeIn 0.5s ease;
        }

        .logo-icon {
            font-size: 3.5rem;
            color: #6c5ce7;
            margin-bottom: 10px;
        }

        .logo h1 {
            font-size: 2.9rem;
            margin: 0;
            font-weight: 700;
        }

        .logo h1 span {
            color: #6c5ce7;
        }

        .logo p {
            margin-top: 8px;
            font-size: 0.95rem;
            color: #666;
        }

        body.dark-mode .logo p {
            color: #aaa;
        }

        .alert {
            padding: 12px 15px;
            margin: 0 auto 20px;
            border-radius: 8px;
            font-weight: 500;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }

        .error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }

        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }

        body.dark-mode .error {
            background-color: #2a1a1a;
            color: #ff6b6b;
            border-left-color: #ff6b6b;
        }

        body.dark-mode .success {
            background-color: #1a2a1a;
            color: #6bff6b;
            border-left-color: #6bff6b;
        }

        .form-container {
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
            margin-bottom: 30px;
            animation: fadeIn 0.8s ease;
        }

        .login-form, .register-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 380px;
        }

        body.dark-mode .login-form, 
        body.dark-mode .register-form {
            background: var(--dark-card);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border: 1px solid var(--dark-border);
        }

        .login-form h2, .register-form h2 {
            color: var(--primary-color);
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.5rem;
        }

        /* YENİ INPUT GROUP STİLLERİ */
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            height: 52px;
            padding-left: 44px;
        }

        body.dark-mode .input-group {
            background: #2a2a2a;
            border-color: var(--dark-border);
        }

        .input-group i {
            position: absolute;
            left: 0;
            width: 44px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 16px;
        }

        body.dark-mode .input-group i {
            color: #aaa;
        }

        .input-group input {
            flex: 1;
            height: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: 15px;
            padding: 0 15px;
            margin-left: -44px;
            padding-left: 44px;
        }

        .input-group select {
            flex: 1;
            height: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: 15px;
            padding: 0 15px;
            margin-left: -44px;
            padding-left: 44px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
        }

        /* SELECT GRUBU İÇİN ÖZEL STİL */
        .input-group.select-group::after {
            content: "\f078";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none;
        }

        body.dark-mode .input-group input,
        body.dark-mode .input-group select {
            color: var(--text-light);
        }

        .input-group input::placeholder {
            color: #999;
            font-size: 0.9rem;
        }

        body.dark-mode .input-group input::placeholder {
            color: #777;
        }

        .input-group .toggle-password {
            cursor: pointer;
            color: #777;
            transition: all 0.2s ease;
            margin-left: 8px;
            padding: 0 12px;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .input-group:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(44, 62, 80, 0.1);
        }

        /* AKADEMİK UNVAN GRUBU İÇİN ÖZEL DÜZENLEME */
        #academic-title-group {
            margin-top: -10px;
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        #academic-title-group .input-group {
            margin-bottom: 0;
        }

        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            font-size: 0.85rem;
        }

        .options label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            color: #555;
        }

        body.dark-mode .options label {
            color: #bbb;
        }

        .options a {
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }

        .options a:hover {
            text-decoration: underline;
        }

        .btn-login, .btn-register {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background: var(--primary-color);
            color: white;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover, .btn-register:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .terms {
            margin: 15px 0;
            text-align: center;
            font-size: 0.8rem;
        }

        .terms label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            color: #555;
            cursor: pointer;
        }

        body.dark-mode .terms label {
            color: #bbb;
        }

        .terms a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        .footer-notice {
            margin: 30px auto;
            padding: 20px;
            text-align: center;
            font-size: 0.8rem;
            color: #666;
            max-width: 700px;
            background-color: rgba(255,255,255,0.9);
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.1);
            animation: fadeIn 1s ease;
        }

        body.dark-mode .footer-notice {
            background-color: rgba(30,30,30,0.9);
            color: #ccc;
            border-color: rgba(255,255,255,0.1);
        }

        .footer-notice hr {
            border: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, #ccc, transparent);
            margin: 15px auto;
            width: 60%;
        }

        body.dark-mode .footer-notice hr {
            background: linear-gradient(to right, transparent, #555, transparent);
        }

        .footer-notice p {
            margin: 12px 0;
            line-height: 1.5;
        }

        .footer-notice strong {
            color: var(--text-dark);
            font-weight: 600;
        }

        body.dark-mode .footer-notice strong {
            color: var(--text-light);
        }

        .footer-notice a {
            color: var(--primary-color);
            text-decoration: none;
            margin: 0 5px;
        }

        .footer-notice a:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { 
                opacity: 0;
                transform: translateY(-20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .form-container {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }
            
            .login-form, .register-form {
                max-width: 100%;
                padding: 20px;
            }
            
            .logo h1 {
                font-size: 2rem;
            }
            
            .logo-icon {
                font-size: 2.5rem;
            }
            
            .footer-notice {
                margin: 20px 15px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleDarkMode()">
        <i class="fas fa-moon"></i>
    </button>

    <div class="particles"></div>
    
    <div class="container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-atom"></i>
            </div>
            <h1>Core<span>Lab</span></h1>
            <p>Akademik Laboratuvar Yönetim Sistemi</p>
        </div>

        <?php if(isset($_SESSION['message'])): ?>
            <div class="alert <?= $_SESSION['message']['type'] ?>">
                <i class="fas <?= $_SESSION['message']['type'] == 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                <?= $_SESSION['message']['text'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="form-container">
            <form class="login-form" method="POST">
                <input type="hidden" name="login" value="1">
                <h2><i class="fas fa-sign-in-alt"></i> Giriş Yap</h2>
                
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Üniversite E-postası" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Şifre" required id="passwordField">
                    <span class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>

                <div class="options">
                    <label>
                        <input type="checkbox" name="remember"> Beni hatırla
                    </label>
                    <a href="forgot-password.php">Şifremi unuttum?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-arrow-right"></i> Giriş Yap
                </button>
            </form>

            <form class="register-form" method="POST">
                <input type="hidden" name="register" value="1">
                <h2><i class="fas fa-user-plus"></i> Kayıt Ol</h2>
                
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="full_name" placeholder="Ad Soyad" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Üniversite E-postası" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Şifre (en az 8 karakter)" required minlength="8">
                </div>
                
                <div class="input-group select-group">
                    <i class="fas fa-user-tag"></i>
                    <select name="role" id="role-select" required onchange="toggleFields()">
                        <option value="" disabled selected>Hesap Türü</option>
                        <option value="teacher">Akademisyen</option>
                        <option value="student">Öğrenci</option>
                    </select>
                </div>

                <div id="student-number-group" style="display:none;">
                    <div class="input-group">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="student_number" placeholder="Öğrenci Numarası (Örnek: 2023123456)">
                    </div>
                </div>

                <div id="academic-title-group" style="display:none;">
                    <div class="input-group select-group">
                        <i class="fas fa-graduation-cap"></i>
                        <select name="academic_title">
                            <option value="" disabled selected>Akademik Unvan Seçiniz</option>
                            <option value="prof">Profesör</option>
                            <option value="assoc_prof">Doçent</option>
                            <option value="assist_prof">Dr. Öğretim Üyesi</option>
                            <option value="lecturer">Öğretim Görevlisi</option>
                            <option value="research_asst">Araştırma Görevlisi</option>
                        </select>
                    </div>
                </div>

                <div class="terms">
                    <label>
                        <input type="checkbox" name="terms" required>
                        <a href="terms.php">Kullanım koşullarını</a> okudum ve kabul ediyorum
                    </label>
                </div>

                <button type="submit" class="btn-register">
                    <i class="fas fa-paper-plane"></i> Kayıt Ol
                </button>
            </form>
        </div>

        <div style="text-align: center; margin-top: 40px; padding: 20px; font-size: 14px; color: #777;">
            <hr style="margin-bottom: 15px;">
            <p><strong>Uyarı:</strong> Bu sistem yalnızca yetkili akademik personel ve öğrenciler tarafından kullanılmak üzere tasarlanmıştır. Lütfen yetkiniz dışında kayıt oluşturmayınız veya yanlış bilgi girişi yapmayınız. Aksi durumlar tespit edildiğinde ilgili işlemler sistem yöneticisi tarafından kayıt altına alınarak işlem yapılabilir.</p>
            <p style="margin-top: 10px;">© 2025 CoreLab - Tüm hakları saklıdır.</p>
        </div>
    </div>

    <script>
    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        const icon = document.querySelector('.theme-toggle i');
        
        if (document.body.classList.contains('dark-mode')) {
            icon.classList.replace('fa-moon', 'fa-sun');
            localStorage.setItem('theme', 'dark');
        } else {
            icon.classList.replace('fa-sun', 'fa-moon');
            localStorage.setItem('theme', 'light');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            document.querySelector('.theme-toggle i').classList.replace('fa-moon', 'fa-sun');
        }
    });

    function togglePassword() {
        const passwordField = document.getElementById('passwordField');
        const icon = document.querySelector('.toggle-password i');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passwordField.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    function toggleFields() {
        const role = document.getElementById('role-select').value;
        const studentGroup = document.getElementById('student-number-group');
        const academicGroup = document.getElementById('academic-title-group');
        
        if(role === 'student') {
            studentGroup.style.display = 'block';
            studentGroup.querySelector('input').setAttribute('required', '');
            academicGroup.style.display = 'none';
            academicGroup.querySelector('select').removeAttribute('required');
        } 
        else if(role === 'teacher') {
            academicGroup.style.display = 'block';
            academicGroup.querySelector('select').setAttribute('required', '');
            studentGroup.style.display = 'none';
            studentGroup.querySelector('input').removeAttribute('required');
        }
        else {
            studentGroup.style.display = 'none';
            academicGroup.style.display = 'none';
        }
    }

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
            submitBtn.disabled = true;
        });
    });
    </script>
</body>
</html>