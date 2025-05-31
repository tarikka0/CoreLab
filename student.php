<?php
session_start();

// Giriş kontrolü ve yetki kontrolü
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

require_once 'includes/config.php';

// Veritabanından istatistikleri çekme
try {
    $student_id = $_SESSION['user']['id'];
    
    // Devam eden ders sayısı (basitleştirilmiş)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT course) as active_courses 
                         FROM assignments 
                         WHERE id IN (SELECT assignment_id FROM submissions WHERE student_id = :student_id)");
    $stmt->execute([':student_id' => $student_id]);
    $stats['active_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_courses'];
    
    // Bekleyen ödev sayısı (teslim edilmiş ama not verilmemiş)
    $stmt = $db->prepare("SELECT COUNT(*) as pending_assignments 
                         FROM submissions 
                         WHERE student_id = :student_id AND grade IS NULL");
    $stmt->execute([':student_id' => $student_id]);
    $stats['pending_assignments'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_assignments'];
    
    // Okunmamış mesaj sayısı
    $stmt = $db->prepare("SELECT COUNT(*) as unread_messages 
                         FROM messages 
                         WHERE receiver_id = :student_id AND is_read = 0");
    $stmt->execute([':student_id' => $student_id]);
    $stats['unread_messages'] = $stmt->fetch(PDO::FETCH_ASSOC)['unread_messages'];
    
    // Yaklaşan ödev sayısı (3 gün içinde)
    $stmt = $db->prepare("SELECT COUNT(*) as upcoming_assignments 
                         FROM assignments 
                         WHERE due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
                         AND id IN (SELECT assignment_id FROM submissions WHERE student_id = :student_id)");
    $stmt->execute([':student_id' => $student_id]);
    $stats['upcoming_assignments'] = $stmt->fetch(PDO::FETCH_ASSOC)['upcoming_assignments'];
    
    // Yaklaşan ödevler listesi
    $stmt = $db->prepare("SELECT a.*
                         FROM assignments a
                         JOIN submissions s ON a.id = s.assignment_id
                         WHERE s.student_id = :student_id
                         AND a.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                         ORDER BY a.due_date ASC LIMIT 5");
    $stmt->execute([':student_id' => $student_id]);
    $upcoming_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Son gönderilen ödevler
    $stmt = $db->prepare("SELECT a.title, a.course, s.submitted_at, s.grade
                         FROM submissions s
                         JOIN assignments a ON s.assignment_id = a.id
                         WHERE s.student_id = :student_id
                         ORDER BY s.submitted_at DESC LIMIT 5");
    $stmt->execute([':student_id' => $student_id]);
    $recent_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grafik verileri
    $stmt = $db->prepare("SELECT 
        DATE_FORMAT(s.submitted_at, '%Y-%m') as month,
        COUNT(s.id) as submitted,
        AVG(s.grade) as average_grade
        FROM submissions s
        WHERE s.student_id = :student_id
        AND s.submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(s.submitted_at, '%Y-%m')
        ORDER BY month ASC");
    $stmt->execute([':student_id' => $student_id]);
    $submission_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_data = [
        'labels' => [],
        'submitted' => [],
        'average_grade' => []
    ];
    
    foreach ($submission_stats as $stat) {
        $chart_data['labels'][] = date('F', strtotime($stat['month']));
        $chart_data['submitted'][] = (int)$stat['submitted'];
        $chart_data['average_grade'][] = (float)$stat['average_grade'];
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $stats = [
        'active_courses' => 0,
        'pending_assignments' => 0,
        'unread_messages' => 0,
        'upcoming_assignments' => 0
    ];
    $upcoming_assignments = [];
    $recent_submissions = [];
    $chart_data = [
        'labels' => ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran'],
        'submitted' => [0, 0, 0, 0, 0, 0],
        'average_grade' => [0, 0, 0, 0, 0, 0]
    ];
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
    <title>Ana Sayfa | CoreLab Öğrenci Paneli</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
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

        /* DASHBOARD GRID */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        /* STATS CARDS */
        .stats-container {
            grid-column: span 12;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-title {
            font-size: 1rem;
            color: var(--dark-light);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(108, 92, 231, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-description {
            font-size: 0.85rem;
            color: var(--dark-light);
        }

        /* Card colors */
        .stat-card.courses::before { background: var(--info); }
        .stat-card.courses .stat-icon { background: rgba(9, 132, 227, 0.1); color: var(--info); }
        
        .stat-card.pending::before { background: var(--warning); }
        .stat-card.pending .stat-icon { background: rgba(253, 203, 110, 0.2); color: var(--warning); }
        
        .stat-card.messages::before { background: var(--danger); }
        .stat-card.messages .stat-icon { background: rgba(214, 48, 49, 0.1); color: var(--danger); }
        
        .stat-card.upcoming::before { background: var(--success); }
        .stat-card.upcoming .stat-icon { background: rgba(0, 184, 148, 0.1); color: var(--success); }

        /* CHART AND ASSIGNMENTS */
        .chart-container {
            grid-column: span 8;
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            height: 350px;
        }

        .assignments-container {
            grid-column: span 4;
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            height: 350px;
            display: flex;
            flex-direction: column;
        }

        /* UPCOMING ASSIGNMENTS */
        .section-title {
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
        }

        .assignment-list {
            list-style: none;
            flex: 1;
            overflow-y: auto;
            padding-right: 10px;
        }

        .assignment-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid var(--light-gray);
            align-items: center;
            transition: var(--transition);
        }

        .assignment-item:hover {
            background: rgba(108, 92, 231, 0.05);
        }

        .assignment-item:last-child {
            border-bottom: none;
        }

        .assignment-date {
            width: 60px;
            text-align: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .assignment-day {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .assignment-month {
            font-size: 0.8rem;
            color: var(--dark-light);
            text-transform: uppercase;
        }

        .assignment-details {
            flex: 1;
        }

        .assignment-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .assignment-course {
            font-size: 0.85rem;
            color: var(--primary-dark);
            margin-bottom: 3px;
        }

        .assignment-time {
            font-size: 0.9rem;
            color: var(--dark-light);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .assignment-actions {
            margin-left: 15px;
            flex-shrink: 0;
        }

        .btn-outline {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--primary);
            color: var(--primary);
            background: transparent;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
        }

        /* RECENT SUBMISSIONS */
        .submissions-container {
            grid-column: span 12;
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .submissions-list {
            margin-top: 20px;
        }

        .submission-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 10px;
            background: var(--light);
            border-radius: 8px;
            transition: var(--transition);
        }

        .submission-item:hover {
            background: var(--primary-light);
            color: var(--white);
        }

        .submission-info {
            flex: 1;
        }

        .submission-title {
            font-weight: 600;
            margin-bottom: 3px;
        }

        .submission-meta {
            font-size: 0.85rem;
            color: var(--dark-light);
            display: flex;
            gap: 15px;
        }

        .submission-item:hover .submission-meta {
            color: rgba(255,255,255,0.8);
        }

        .submission-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .submission-grade {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .submission-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(253, 203, 110, 0.2);
            color: var(--warning);
        }

        .status-completed {
            background: rgba(0, 184, 148, 0.1);
            color: var(--success);
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
        @media (max-width: 1200px) {
            .chart-container {
                grid-column: span 7;
            }
            
            .assignments-container {
                grid-column: span 5;
            }
        }

        @media (max-width: 992px) {
            .chart-container,
            .assignments-container {
                grid-column: span 12;
                height: auto;
            }
            
            .assignments-container {
                height: 350px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
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

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 30px;
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
            <a href="student.php" class="nav-item active">
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
            <a href="profilst.php" class="nav-item">
                <i class="fas fa-user-cog"></i>
                <span>Profil</span>
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="header">
            <div>
                <h1 class="page-title">Hoş Geldin, <?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'Öğrenci', ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="welcome-message">Öğrenci panelinize erişiminiz var. Bugün <?= date('d.m.Y') ?></p>
            </div>
            
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'Öğrenci', ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="user-email"><?= htmlspecialchars($_SESSION['user']['email'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                
                <div class="dropdown-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user']['email'], 0, 1)) ?>
                    </div>
                    <div class="dropdown-content">
                        <a href="profilst.php" class="dropdown-item">
                            <i class="fas fa-user-cog"></i> Profil
                        </a>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- STATS CARDS -->
        <div class="stats-container">
            <div class="stat-card courses">
                <div class="stat-header">
                    <div class="stat-title">Devam Eden Dersler</div>
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                </div>
                <div class="stat-value"><?= $stats['active_courses'] ?></div>
                <div class="stat-description">Şu anda devam eden ders sayınız</div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-title">Bekleyen Ödevler</div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value"><?= $stats['pending_assignments'] ?></div>
                <div class="stat-description">Değerlendirme bekleyen ödevleriniz</div>
            </div>
            
            <div class="stat-card messages">
                <div class="stat-header">
                    <div class="stat-title">Okunmamış Mesaj</div>
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
                <div class="stat-value"><?= $stats['unread_messages'] ?></div>
                <div class="stat-description">Okunmamış mesaj sayınız</div>
            </div>
            
            <div class="stat-card upcoming">
                <div class="stat-header">
                    <div class="stat-title">Yaklaşan Ödevler</div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
                <div class="stat-value"><?= $stats['upcoming_assignments'] ?></div>
                <div class="stat-description">Önümüzdeki 3 gün içindeki ödevler</div>
            </div>
        </div>
        
        <!-- CHART AND ASSIGNMENTS -->
        <div class="dashboard-grid">
            <div class="chart-container">
                <h2 class="section-title">
                    <i class="fas fa-chart-line"></i> Ödev İstatistiklerim
                </h2>
                <canvas id="assignmentsChart"></canvas>
            </div>
            
            <div class="assignments-container">
                <h2 class="section-title">
                    <i class="fas fa-tasks"></i> Yaklaşan Ödevler
                </h2>
                
                <ul class="assignment-list">
                    <?php if (empty($upcoming_assignments)): ?>
                        <li class="assignment-item">
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>Yaklaşan ödev bulunmamaktadır</p>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php foreach ($upcoming_assignments as $assignment): 
                            $due_date = new DateTime($assignment['due_date']);
                        ?>
                        <li class="assignment-item">
                            <div class="assignment-date">
                                <div class="assignment-day"><?= $due_date->format('d') ?></div>
                                <div class="assignment-month"><?= strtoupper($due_date->format('M')) ?></div>
                            </div>
                            <div class="assignment-details">
                                <div class="assignment-title"><?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="assignment-course"><?= htmlspecialchars($assignment['course'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="assignment-time">
                                    <i class="far fa-clock"></i> <?= date('H:i', strtotime($assignment['due_date'])) ?>
                                </div>
                            </div>
                            <div class="assignment-actions">
                                <a href="odevst.php" class="btn-outline">Detay</a>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- RECENT SUBMISSIONS -->
            <div class="submissions-container">
                <h2 class="section-title">
                    <i class="fas fa-file-upload"></i> Son Gönderilen Ödevler
                </h2>
                
                <div class="submissions-list">
                    <?php if (empty($recent_submissions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Son gönderilen ödev bulunmamaktadır</p>
                            <a href="odevst.php" class="btn-outline">Ödevler Sayfasına Git</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_submissions as $submission): ?>
                        <div class="submission-item">
                            <div class="submission-info">
                                <div class="submission-title"><?= htmlspecialchars($submission['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="submission-meta">
                                    <span class="submission-date">
                                        <i class="far fa-clock"></i> <?= date('d.m.Y H:i', strtotime($submission['submitted_at'])) ?>
                                    </span>
                                    <span class="submission-grade">
                                        <i class="fas fa-star"></i> 
                                        <?= $submission['grade'] ? $submission['grade'] : 'Değerlendirme bekleniyor' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="submission-status <?= $submission['grade'] ? 'status-completed' : 'status-pending' ?>">
                                <?= $submission['grade'] ? 'Tamamlandı' : 'Bekliyor' ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Grafik oluşturma
            const ctx = document.getElementById('assignmentsChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_data['labels']) ?>,
                    datasets: [
                        {
                            label: 'Teslim Edilen Ödevler',
                            data: <?= json_encode($chart_data['submitted']) ?>,
                            backgroundColor: 'rgba(108, 92, 231, 0.2)',
                            borderColor: 'rgba(108, 92, 231, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Ortalama Not',
                            data: <?= json_encode($chart_data['average_grade']) ?>,
                            backgroundColor: 'rgba(253, 203, 110, 0.2)',
                            borderColor: 'rgba(253, 203, 110, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Teslim Edilen Ödev Sayısı'
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Ortalama Not'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>