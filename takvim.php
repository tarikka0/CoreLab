<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'teacher') {
    header("Location: index.php");
    exit();
}

// Örnek etkinlik verileri (Gerçek uygulamada veritabanından çekilecek)
$events = [
    [
        'id' => 1,
        'title' => 'Vize Sınavı',
        'course' => 'Veritabanı Yönetimi',
        'date' => '2023-06-15',
        'time' => '09:00 - 11:00',
        'location' => 'B-205',
        'type' => 'exam',
        'priority' => 'high'
    ],
    [
        'id' => 2,
        'title' => 'Proje Teslimi',
        'course' => 'Web Programlama',
        'date' => '2023-06-20',
        'time' => '23:59',
        'location' => 'Online',
        'type' => 'assignment',
        'priority' => 'high'
    ],
    [
        'id' => 3,
        'title' => 'Bölüm Toplantısı',
        'course' => 'Genel',
        'date' => '2023-06-10',
        'time' => '14:00 - 16:00',
        'location' => 'Toplantı Odası',
        'type' => 'meeting',
        'priority' => 'medium'
    ],
    [
        'id' => 4,
        'title' => 'Ödev Kontrol',
        'course' => 'Algoritma Analizi',
        'date' => '2023-06-05',
        'time' => '09:00 - 17:00',
        'location' => 'Ofis',
        'type' => 'task',
        'priority' => 'low'
    ]
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Takvim | CoreLab Akademik Yönetim</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
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

        /* 📌 TAKVİM ALANI */
        .calendar-container {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .fc { /* FullCalendar override */
            font-family: inherit;
        }

        .fc-toolbar-title {
            font-size: 1.4rem;
            color: var(--dark);
            font-weight: 700;
        }

        .fc-button {
            background: var(--primary) !important;
            border: none !important;
            padding: 8px 12px !important;
            font-weight: 600 !important;
            text-transform: capitalize !important;
        }

        .fc-button:hover {
            background: var(--primary-dark) !important;
        }

        .fc-button:active {
            background: var(--primary-dark) !important;
        }

        .fc-button-primary:not(:disabled).fc-button-active {
            background: var(--primary-dark) !important;
        }

        /* Etkinlik renkleri */
        .fc-event {
            border-radius: 6px !important;
            padding: 3px 6px !important;
            font-size: 0.85rem !important;
            border: none !important;
        }

        .fc-event-main {
            display: flex;
            align-items: center;
        }

        .event-exam {
            background: var(--danger) !important;
            border-left: 3px solid darken(#d63031, 10%) !important;
        }

        .event-assignment {
            background: var(--warning) !important;
            border-left: 3px solid darken(#fdcb6e, 20%) !important;
            color: var(--dark) !important;
        }

        .event-meeting {
            background: var(--success) !important;
            border-left: 3px solid darken(#00b894, 10%) !important;
        }

        .event-task {
            background: var(--primary) !important;
            border-left: 3px solid var(--primary-dark) !important;
        }

        /* Yaklaşan etkinlik vurgusu */
        .fc-daygrid-day.fc-day-today {
            background: rgba(108, 92, 231, 0.1) !important;
        }

        .fc-daygrid-day-highlight {
            background: rgba(253, 203, 110, 0.2);
            position: relative;
        }

        .fc-daygrid-day-highlight::after {
            content: '';
            position: absolute;
            top: 2px;
            right: 2px;
            width: 8px;
            height: 8px;
            background: var(--warning);
            border-radius: 50%;
        }

        /* 📌 ETKİNLİK DETAY POPUP */
        .event-modal {
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

        .event-modal-content {
            background: var(--white);
            width: 500px;
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

        .event-modal-header {
            padding: 20px;
            background: var(--primary);
            color: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .event-modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }

        .event-modal-close {
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .event-modal-body {
            padding: 20px;
        }

        .event-info-item {
            display: flex;
            margin-bottom: 15px;
        }

        .event-info-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary);
        }

        .event-info-text h4 {
            margin: 0 0 5px 0;
            font-size: 1rem;
            color: var(--dark-light);
        }

        .event-info-text p {
            margin: 0;
            font-size: 1.1rem;
            color: var(--dark);
            font-weight: 600;
        }

        .event-priority {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .priority-high {
            background: rgba(214, 48, 49, 0.1);
            color: var(--danger);
        }

        .priority-medium {
            background: rgba(253, 203, 110, 0.2);
            color: darken(#fdcb6e, 30%);
        }

        .priority-low {
            background: rgba(108, 92, 231, 0.1);
            color: var(--primary);
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
                <div class="logo-subtext">Akademisyen Paneli
                </div>
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
            <a href="mesajlar.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Mesajlar</span>
            </a>
            <a href="takvim.php" class="nav-item active">
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
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-cog"></i> Ayarlar
                        </a>
                         <a href="logout.php" class="dropdown-item">  <!-- BU SATIRI SİLMENİZ GEREKİYOR -->
        <i class="fas fa-sign-out-alt"></i> Çıkış Yap
    </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="calendar-container">
            <div id="calendar"></div>
        </div>
    </div>

    <!-- 📌 ETKİNLİK DETAY MODALI -->
    <div class="event-modal" id="eventModal">
        <div class="event-modal-content">
            <div class="event-modal-header">
                <h3 class="event-modal-title" id="modalEventTitle">Etkinlik Başlığı</h3>
                <button class="event-modal-close" id="modalClose">&times;</button>
            </div>
            <div class="event-modal-body">
                <div class="event-info-item">
                    <div class="event-info-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="event-info-text">
                        <h4>Ders</h4>
                        <p id="modalEventCourse">-</p>
                    </div>
                </div>
                <div class="event-info-item">
                    <div class="event-info-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="event-info-text">
                        <h4>Tarih & Saat</h4>
                        <p id="modalEventDateTime">-</p>
                    </div>
                </div>
                <div class="event-info-item">
                    <div class="event-info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="event-info-text">
                        <h4>Konum</h4>
                        <p id="modalEventLocation">-</p>
                    </div>
                </div>
                <div class="event-info-item">
                    <div class="event-info-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="event-info-text">
                        <h4>Öncelik</h4>
                        <p><span class="event-priority" id="modalEventPriority">-</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/tr.min.js"></script>
    <script>
        // Etkinlik verilerini PHP'dan JS'e aktarma
        const events = <?php echo json_encode($events); ?>;
        
        // Yaklaşan etkinlikleri işaretleme (3 gün içinde olanlar)
        const today = new Date();
        const highlightDays = [];
        
        events.forEach(event => {
            const eventDate = new Date(event.date);
            const diffTime = eventDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays >= 0 && diffDays <= 3) {
                highlightDays.push(event.date);
            }
        });

        // Takvim başlatma
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            
            const calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'tr',
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: events.map(event => ({
                    id: event.id,
                    title: event.title,
                    start: event.date,
                    extendedProps: {
                        course: event.course,
                        time: event.time,
                        location: event.location,
                        type: event.type,
                        priority: event.priority
                    },
                    className: `event-${event.type}`,
                    backgroundColor: getEventColor(event.type),
                    borderColor: getEventBorderColor(event.type)
                })),
                eventClick: function(info) {
                    openEventModal(info.event);
                },
                dayCellDidMount: function(arg) {
                    const dateStr = arg.date.toISOString().split('T')[0];
                    if (highlightDays.includes(dateStr)) {
                        arg.el.classList.add('fc-daygrid-day-highlight');
                    }
                }
            });
            
            calendar.render();
            
            // Modal işlemleri
            const modal = document.getElementById('eventModal');
            const closeBtn = document.getElementById('modalClose');
            
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        function openEventModal(event) {
            const modal = document.getElementById('eventModal');
            const priorityClass = {
                'high': 'priority-high',
                'medium': 'priority-medium',
                'low': 'priority-low'
            };
            
            document.getElementById('modalEventTitle').textContent = event.title;
            document.getElementById('modalEventCourse').textContent = event.extendedProps.course;
            document.getElementById('modalEventDateTime').textContent = 
                `${event.start.toLocaleDateString('tr-TR')} • ${event.extendedProps.time}`;
            document.getElementById('modalEventLocation').textContent = event.extendedProps.location;
            
            const priorityElement = document.getElementById('modalEventPriority');
            priorityElement.textContent = 
                event.extendedProps.priority === 'high' ? 'Yüksek' :
                event.extendedProps.priority === 'medium' ? 'Orta' : 'Düşük';
            priorityElement.className = `event-priority ${priorityClass[event.extendedProps.priority]}`;
            
            modal.style.display = 'flex';
        }
        
        function getEventColor(type) {
            switch(type) {
                case 'exam': return 'var(--danger)';
                case 'assignment': return 'var(--warning)';
                case 'meeting': return 'var(--success)';
                default: return 'var(--primary)';
            }
        }
        
        function getEventBorderColor(type) {
            switch(type) {
                case 'exam': return 'darken(var(--danger), 10%)';
                case 'assignment': return 'darken(var(--warning), 20%)';
                case 'meeting': return 'darken(var(--success), 10%)';
                default: return 'var(--primary-dark)';
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