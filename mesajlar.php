
<<?php
require 'includes/config.php';

// Yetki kontrolü
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'teacher') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Mesaj gönderme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['send_message'])) {
        $receiver_id = $_POST['receiver_id'];
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        
        if (!empty($subject) && !empty($message)) {
            $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, is_announcement) 
                                 VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$user_id, $receiver_id, $subject, $message]);
            
            $_SESSION['success'] = "Mesaj başarıyla gönderildi!";
            header("Location: mesajlar.php");
            exit();
        } else {
            $_SESSION['error'] = "Lütfen başlık ve mesaj içeriği giriniz!";
        }
    } elseif (isset($_POST['send_announcement'])) {
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        
        if (!empty($subject) && !empty($message)) {
            $stmt = $db->prepare("INSERT INTO messages (sender_id, subject, message, is_announcement) 
                                 VALUES (?, ?, ?, 1)");
            $stmt->execute([$user_id, $subject, $message]);
            
            $_SESSION['success'] = "Duyuru başarıyla gönderildi!";
            header("Location: mesajlar.php");
            exit();
        } else {
            $_SESSION['error'] = "Lütfen başlık ve duyuru içeriği giriniz!";
        }
    }
}

// Akademisyenin gönderdiği duyurular
$stmt = $db->prepare("SELECT m.* 
                     FROM messages m 
                     WHERE m.sender_id = ? AND m.is_announcement = 1
                     ORDER BY m.created_at DESC");
$stmt->execute([$user_id]);
$sent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Akademisyenin gönderdiği kişisel mesajlar
$stmt = $db->prepare("SELECT m.*, u.full_name as receiver_name 
                     FROM messages m 
                     JOIN users u ON m.receiver_id = u.id 
                     WHERE m.sender_id = ? AND m.is_announcement = 0
                     ORDER BY m.created_at DESC");
$stmt->execute([$user_id]);
$sent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Akademisyene gelen kişisel mesajlar
$stmt = $db->prepare("SELECT m.*, u.full_name as sender_name 
                     FROM messages m 
                     JOIN users u ON m.sender_id = u.id 
                     WHERE m.receiver_id = ? AND m.is_announcement = 0
                     ORDER BY m.created_at DESC");
$stmt->execute([$user_id]);
$received_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış mesaj sayısı
$unread_stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
$unread_stmt->execute([$user_id]);
$unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

// Öğrenci listesi (mesaj göndermek için)
$students_stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE role = 'student' ORDER BY full_name");
$students_stmt->execute();
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Akademik unvanlar
$academicTitles = [
    'prof' => 'Profesör',
    'assoc_prof' => 'Doçent',
    'assist_prof' => 'Dr. Öğr. Üyesi',
    'lecturer' => 'Öğretim Görevlisi',
    'research_assist' => 'Araştırma Görevlisi'
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesajlar | Akademisyen Paneli</title>
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

        /* BUTTONS */
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

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn i {
            font-size: 0.9rem;
        }

        /* TABS */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: var(--transition);
        }

        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
        }

        .tab:hover:not(.active) {
            background: var(--light);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* MESSAGE STYLES */
        .message-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message-card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
        }

        .message-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .message-card.unread {
            border-left: 4px solid var(--primary);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .message-sender {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .message-time {
            font-size: 0.85rem;
            color: var(--dark-light);
        }

        .message-preview {
            color: var(--dark-light);
            font-size: 0.95rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .badge {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 20px;
            background: var(--primary-light);
            color: var(--white);
        }

        /* ANNOUNCEMENT STYLES */
        .announcement-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .announcement-card {
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            cursor: pointer;
        }

        .announcement-card h3 {
            color: var(--primary-dark);
            margin-bottom: 10px;
        }

        .announcement-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            font-size: 0.9rem;
            color: var(--dark-light);
        }

        /* MESSAGE FORM */
        .message-form {
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-family: inherit;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
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

        /* ALERTS */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: rgba(0, 184, 148, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background-color: rgba(214, 48, 49, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* UNREAD BADGE */
        .unread-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        /* MODAL STYLES */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            transition: var(--transition);
        }

        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 60%;
            max-width: 800px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close-modal {
            color: var(--dark-light);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-modal:hover {
            color: var(--danger);
        }

        .modal h2 {
            color: var(--primary-dark);
            margin-bottom: 15px;
            padding-right: 30px;
        }

        .modal-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            color: var(--dark-light);
            font-size: 0.95rem;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .modal-body {
            line-height: 1.7;
            font-size: 1rem;
            white-space: pre-line;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 100px;
            }
            
            .main-content {
                margin-left: 100px;
            }
            
            .logo-text, .logo-subtext, .nav-item span {
                display: none;
            }
            
            .nav-item {
                justify-content: center;
                padding: 12px 0;
            }
            
            .nav-item i {
                margin-right: 0;
            }
            
            .modal-content {
                width: 80%;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 15px;
            }
            
            .modal-content {
                width: 90%;
                margin: 10% auto;
                padding: 20px;
            }
            
            .modal h2 {
                font-size: 1.3rem;
            }
            
            .tabs {
                flex-wrap: wrap;
            }
            
            .tab {
                padding: 8px 12px;
                font-size: 0.9rem;
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
             <a href="students.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Öğrenciler</span>
            </a>
            <a href="mesajlar.php" class="nav-item active">
                <i class="fas fa-comments"></i>
                <span>Mesajlar</span>
                <?php if ($unread_count > 0): ?>
                    <span class="unread-badge"><?= $unread_count ?></span>
                <?php endif; ?>
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

    <!-- ANA İÇERİK -->
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Mesajlar</h1>
            
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></div>
                    <div class="user-role"><?= $academicTitles[$_SESSION['user']['academic_title']] ?? 'Akademisyen' ?></div>
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

        <!-- Hata/Success Mesajları -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= $_SESSION['success'] ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= $_SESSION['error'] ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Tablar -->
        <div class="tabs">
            <div class="tab" data-tab="send-announcement">Duyuru Gönder</div>
            <div class="tab" data-tab="announcements">Duyurularım</div>
            <div class="tab active" data-tab="received">Gelen Mesajlar</div>
            <div class="tab" data-tab="sent">Gönderilen Mesajlar</div>
            <div class="tab" data-tab="compose">Mesaj Gönder</div>
        </div>

        <!-- Gelen Mesajlar Tabı -->
        <div class="tab-content active" id="received">
            <h2>Gelen Mesajlar</h2>
            
            <?php if (empty($received_messages)): ?>
                <p>Gelen kutunuz boş.</p>
            <?php else: ?>
                <div class="message-list">
                    <?php foreach ($received_messages as $message): ?>
                    <div class="message-card <?= !$message['is_read'] ? 'unread' : '' ?>" onclick="openMessageModal(<?= $message['id'] ?>, '<?= htmlspecialchars($message['sender_name'], ENT_QUOTES) ?>', 'Öğrenci', '<?= htmlspecialchars($message['subject'], ENT_QUOTES) ?>', `<?= str_replace('`', '\`', htmlspecialchars($message['message'], ENT_QUOTES)) ?>`, '<?= date('d.m.Y H:i', strtotime($message['created_at'])) ?>')">
                        <div class="message-header">
                            <div class="message-sender">
                                <?= htmlspecialchars($message['sender_name']) ?>
                                <span class="badge">Öğrenci</span>
                            </div>
                            <div class="message-time">
                                <?= date('d.m.Y H:i', strtotime($message['created_at'])) ?>
                            </div>
                        </div>
                        <div class="message-preview">
                            <?= htmlspecialchars($message['subject']) ?> - <?= htmlspecialchars(substr($message['message'], 0, 100)) ?>...
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Gönderilen Mesajlar Tabı -->
        <div class="tab-content" id="sent">
            <h2>Gönderilen Mesajlar</h2>
            
            <?php if (empty($sent_messages)): ?>
                <p>Henüz mesaj göndermediniz.</p>
            <?php else: ?>
                <div class="message-list">
                    <?php foreach ($sent_messages as $message): ?>
                    <div class="message-card" onclick="openMessageModal(<?= $message['id'] ?>, '<?= htmlspecialchars($message['receiver_name'], ENT_QUOTES) ?>', 'Öğrenci', '<?= htmlspecialchars($message['subject'], ENT_QUOTES) ?>', `<?= str_replace('`', '\`', htmlspecialchars($message['message'], ENT_QUOTES)) ?>`, '<?= date('d.m.Y H:i', strtotime($message['created_at'])) ?>')">
                        <div class="message-header">
                            <div class="message-sender">
                                Alıcı: <?= htmlspecialchars($message['receiver_name']) ?>
                                <span class="badge">Öğrenci</span>
                            </div>
                            <div class="message-time">
                                <?= date('d.m.Y H:i', strtotime($message['created_at'])) ?>
                            </div>
                        </div>
                        <div class="message-preview">
                            <?= htmlspecialchars($message['subject']) ?> - <?= htmlspecialchars(substr($message['message'], 0, 100)) ?>...
                        </div>
                        <div class="announcement-meta">
                            <span><?= $message['is_read'] ? 'Okundu' : 'Okunmadı' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Duyurular Tabı -->
        <div class="tab-content" id="announcements">
            <h2>Gönderdiğim Duyurular</h2>
            
            <?php if (empty($sent_announcements)): ?>
                <p>Henüz duyuru göndermediniz.</p>
            <?php else: ?>
                <div class="announcement-list">
                    <?php foreach ($sent_announcements as $announcement): ?>
                    <div class="announcement-card" onclick="openAnnouncementModal('<?= htmlspecialchars($announcement['subject'], ENT_QUOTES) ?>', `<?= str_replace('`', '\`', htmlspecialchars($announcement['message'], ENT_QUOTES)) ?>`, '<?= date('d.m.Y H:i', strtotime($announcement['created_at'])) ?>')">
                        <h3><?= htmlspecialchars($announcement['subject']) ?></h3>
                        <p><?= nl2br(htmlspecialchars(substr($announcement['message'], 0, 200))) ?><?= strlen($announcement['message']) > 200 ? '...' : '' ?></p>
                        <div class="announcement-meta">
                            <span><?= date('d.m.Y H:i', strtotime($announcement['created_at'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mesaj Gönderme Tabı -->
        <div class="tab-content" id="compose">
            <div class="message-form">
                <h2>Öğrenciye Mesaj Gönder</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="receiver_id">Alıcı</label>
                        <select id="receiver_id" name="receiver_id" required>
                            <option value="">-- Öğrenci Seçin --</option>
                            <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subject">Konu</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Mesaj İçeriği</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Mesajı Gönder
                    </button>
                </form>
            </div>
        </div>

        <!-- Duyuru Gönderme Tabı -->
        <div class="tab-content" id="send-announcement">
            <div class="message-form">
                <h2>Yeni Duyuru Oluştur</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="subject">Duyuru Başlığı</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Duyuru İçeriği</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <button type="submit" name="send_announcement" class="btn btn-primary">
                        <i class="fas fa-bullhorn"></i> Duyuruyu Gönder
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Mesaj Detay Modalı -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 id="modalMessageTitle"></h2>
            <div class="modal-meta">
                <span id="modalMessageSender"></span>
                <span id="modalMessageDate"></span>
            </div>
            <div class="modal-body" id="modalMessageBody"></div>
        </div>
    </div>

    <!-- Duyuru Detay Modalı -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 id="modalAnnouncementTitle"></h2>
            <div class="modal-meta">
                <span id="modalAnnouncementDate"></span>
            </div>
            <div class="modal-body" id="modalAnnouncementBody"></div>
        </div>
    </div>

    <script>
        // Tab geçişleri
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Tüm tab ve içerikleri pasif yap
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    // Tıklanan tabı aktif yap
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });

            // URL'de hash varsa ilgili tabı aç
            if (window.location.hash) {
                const tabId = window.location.hash.substring(1);
                const tab = document.querySelector(`.tab[data-tab="${tabId}"]`);
                if (tab) {
                    tab.click();
                }
            }

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
        });

        // Mesaj modalını açma fonksiyonu
        function openMessageModal(id, sender, senderType, subject, message, date) {
            document.getElementById('modalMessageTitle').textContent = subject;
            document.getElementById('modalMessageSender').textContent = `Gönderen: ${sender} (${senderType})`;
            document.getElementById('modalMessageDate').textContent = date;
            document.getElementById('modalMessageBody').textContent = message;
            
            // Modalı göster
            document.getElementById('messageModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Mesajı okundu olarak işaretle (AJAX)
            const messageCard = document.querySelector(`.message-card[onclick*="${id}"]`);
            if (messageCard && messageCard.classList.contains('unread')) {
                markAsRead(id);
            }
        }

        // Duyuru modalını açma fonksiyonu
        function openAnnouncementModal(subject, message, date) {
            document.getElementById('modalAnnouncementTitle').textContent = subject;
            document.getElementById('modalAnnouncementDate').textContent = date;
            document.getElementById('modalAnnouncementBody').textContent = message;
            
            // Modalı göster
            document.getElementById('announcementModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Modalı kapatma fonksiyonu
        function closeModal() {
            document.getElementById('messageModal').style.display = 'none';
            document.getElementById('announcementModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Mesajı okundu olarak işaretleme fonksiyonu
        function markAsRead(messageId) {
            fetch('mark_as_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `message_id=${messageId}`
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Okunmamış mesaj sayısını güncelle
                    const unreadBadge = document.querySelector('.unread-badge');
                    if(unreadBadge) {
                        const currentCount = parseInt(unreadBadge.textContent);
                        if(currentCount > 1) {
                            unreadBadge.textContent = currentCount - 1;
                        } else {
                            unreadBadge.remove();
                        }
                    }
                    
                    // Mesaj kartındaki "unread" sınıfını kaldır
                    const messageCard = document.querySelector(`.message-card[onclick*="${messageId}"]`);
                    if(messageCard) {
                            messageCard.classList.remove('unread');
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Pencere dışına tıklanınca modalı kapat
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal();
            }
        });
    </script>
</body>
</html>