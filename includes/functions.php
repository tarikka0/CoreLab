<?php
function getCategoryColor($category) {
    switch ($category) {
        case 'exam': return '#e74c3c';
        case 'homework': return '#3498db';
        case 'event': return '#2ecc71';
        default: return '#9b59b6';
    }
}

function getCategoryName($category) {
    switch ($category) {
        case 'exam': return 'Sınav';
        case 'homework': return 'Ödev';
        case 'event': return 'Etkinlik';
        default: return 'Genel';
    }
}

function formatDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) return 'Az önce';
    if ($diff < 3600) return floor($diff/60) . ' dakika önce';
    if ($diff < 86400) return floor($diff/3600) . ' saat önce';
    if ($diff < 604800) return floor($diff/86400) . ' gün önce';
    
    return date('d.m.Y H:i', $timestamp);
}
?>