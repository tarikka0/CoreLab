<?php
require 'includes/config.php';

// 1. Bağlantı testi
try {
    $test = $db->query("SELECT 1");
    echo "✅ Veritabanı bağlantısı BAŞARILI<br>";
} catch (PDOException $e) {
    die("❌ Veritabanı bağlantı HATASI: " . $e->getMessage());
}

// 2. Tablo kontrolü
$tables = $db->query("SHOW TABLES LIKE 'users'")->fetchAll();
if (count($tables) {
    echo "✅ 'users' tablosu MEVCUT<br>";
} else {
    echo "❌ 'users' tablosu YOK!<br>";
}

// 3. Manuel kayıt ekleme testi
try {
    $db->exec("INSERT INTO users (email, password, role) VALUES ('test@test.com', '".password_hash('123456', PASSWORD_BCRYPT)."', 'student')");
    echo "✅ Manuel kayıt EKLENDİ<br>";
} catch (PDOException $e) {
    echo "❌ Kayıt ekleme HATASI: " . $e->getMessage() . "<br>";
}

// 4. Son kaydı göster
$lastUser = $db->query("SELECT * FROM users ORDER BY id DESC LIMIT 1")->fetch();
echo "<pre>Son kayıt: "; print_r($lastUser); echo "</pre>";