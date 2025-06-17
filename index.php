<?php
session_start();

// Basit yönlendirme sistemi
$page = $_GET['page'] ?? 'home';

// Giriş kontrolü
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Ana sayfa için özel kontrol
if ($page === 'home' && !$isLoggedIn) {
    header('Location: ?page=login');
    exit;
}

// Korumalı sayfalar
$protectedPages = ['home', 'upload', 'timeline', 'memories', 'export', 'settings', 'download_pdf', 'print_album'];

if (in_array($page, $protectedPages) && !$isLoggedIn) {
    header('Location: ?page=login');
    exit;
}

// Veritabanı bağlantısı
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kızımın Hatıra Albümü 💝</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php if ($isLoggedIn): ?>
    <div class="app-layout">
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
        <?php include 'pages/sidebar.php'; ?>
        <button id="toggle-sidebar" class="sidebar-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <main class="main-content">
    <?php else: ?>
    <main class="main-content">
    <?php endif; ?>
        <?php
        switch ($page) {
            case 'settings':
                include 'pages/settings.php';
                break;
            
            case 'export':
                include 'pages/export.php';
                break;
            
            case 'download_pdf':
                include 'pages/download_pdf.php';
                break;
            
            case 'print_album':
                include 'pages/print_album.php';
                break;
            
            default:
                $pagePath = "pages/{$page}.php";
                if (file_exists($pagePath)) {
                    include $pagePath;
                } else {
                    include "pages/404.php";
                }
        }
        ?>
    </main>
    
    <?php if ($isLoggedIn): ?>
    </div> <!-- app-layout sonu -->
    <?php endif; ?>

    <script src="assets/js/main.js"></script>
</body>
</html> 