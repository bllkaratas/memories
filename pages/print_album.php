<?php
// Sadece giriş yapmış kullanıcılar için
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ?page=login');
    exit;
}

// Fotoğrafları ve bilgileri al
try {
    $query = "SELECT p.*, 
                GROUP_CONCAT(m.title SEPARATOR ', ') as milestones
              FROM photos p
              LEFT JOIN photo_milestones pm ON p.id = pm.photo_id
              LEFT JOIN milestones m ON pm.milestone_id = m.id
              GROUP BY p.id
              ORDER BY p.photo_date";
    
    $photos = $db->query($query)->fetchAll();
    
    // Yıllara göre grupla
    $photosByYear = [];
    foreach ($photos as $photo) {
        $year = date('Y', strtotime($photo['photo_date']));
        if (!isset($photosByYear[$year])) {
            $photosByYear[$year] = [];
        }
        $photosByYear[$year][] = $photo;
    }
    
} catch(PDOException $e) {
    $photos = [];
    $photosByYear = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hatıra Albümü - Yazdırılabilir Sürüm</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Yazdırma için özel stiller */
        @media print {
            body {
                font-family: 'DejaVu Sans', 'Arial', sans-serif;
                color: #000;
                background-color: #fff;
                margin: 0;
                padding: 0;
            }
            
            .header-controls,
            .no-print {
                display: none !important;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .print-page {
                page-break-after: always;
                padding: 20px;
            }
            
            .print-page:last-child {
                page-break-after: avoid;
            }
            
            .cover-page {
                height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                text-align: center;
            }
            
            .year-header {
                margin-top: 30px;
                margin-bottom: 20px;
            }
            
            .photo-item {
                page-break-inside: avoid;
                margin-bottom: 30px;
            }
            
            img {
                max-width: 100%;
                height: auto;
                display: block;
                margin: 0 auto;
            }
            
            .message-page {
                height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 50px;
            }
            
            h1, h2, h3 {
                color: #333;
            }
        }
        
        /* Ekran görüntüleme için stiller */
        body.print-preview {
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        .header-controls {
            background-color: #fff;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .print-page {
            background-color: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            max-width: 794px; /* A4 genişliği */
            margin-left: auto;
            margin-right: auto;
        }
        
        .cover-page h1 {
            font-size: 36px;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .cover-page p {
            font-size: 18px;
            margin-bottom: 10px;
            color: #555;
        }
        
        .year-header h2 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--light-color);
            padding-bottom: 10px;
            font-size: 28px;
        }
        
        .photo-item {
            margin-bottom: 50px;
        }
        
        .photo-item h3 {
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .photo-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
        }
        
        .photo-image {
            margin-bottom: 15px;
            text-align: center;
        }
        
        .photo-image img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .photo-description {
            margin-top: 15px;
            color: #555;
        }
        
        .memory-note {
            background-color: #f9f9f9;
            border-left: 3px solid var(--primary-color);
            padding: 15px;
            margin-top: 15px;
            font-style: italic;
            color: #666;
        }
        
        .milestones {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .milestone {
            background-color: var(--light-color);
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        /* Message page */
        .message-page h2 {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-size: 28px;
            text-align: center;
        }
        
        .message-content {
            font-size: 16px;
            line-height: 1.6;
            color: #444;
        }
        
        .signature {
            margin-top: 40px;
            font-style: italic;
            text-align: right;
            font-size: 18px;
        }
    </style>
</head>
<body class="print-preview">
    <div class="header-controls no-print">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-print"></i> Yazdırılabilir Hatıra Albümü</h2>
                <p>Bu sayfayı yazdırmak için tarayıcınızın yazdırma özelliğini kullanın (Ctrl+P / Cmd+P)</p>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-primary btn-lg"><i class="fas fa-print"></i> Yazdır</button>
                <a href="?page=export" class="btn btn-outline-secondary ml-2"><i class="fas fa-arrow-left"></i> Geri Dön</a>
            </div>
        </div>
    </div>
    
    <div class="print-content">
        <!-- Kapak Sayfası -->
        <div class="print-page cover-page">
            <h1>Kızımın Büyüme Hikayesi</h1>
            <p>Sevgili kızım, büyüme yolculuğundaki özel anılar...</p>
            <p class="subtitle"><?php echo date('Y'); ?></p>
        </div>
        
        <!-- Her yıl için fotoğraflar -->
        <?php foreach ($photosByYear as $year => $yearPhotos): ?>
            <div class="print-page">
                <div class="year-header">
                    <h2><?php echo $year; ?></h2>
                </div>
                
                <?php foreach ($yearPhotos as $photo): ?>
                <div class="photo-item">
                    <h3><?php echo htmlspecialchars($photo['title'] ?? 'İsimsiz'); ?></h3>
                    
                    <div class="photo-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d.m.Y', strtotime($photo['photo_date'])); ?></span>
                        
                        <?php if ($photo['age_years'] !== null): ?>
                        <span><i class="fas fa-birthday-cake"></i> <?php echo $photo['age_years']; ?> yaş <?php echo $photo['age_months'] % 12; ?> ay</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($photo['milestones'])): ?>
                    <div class="milestones">
                        <?php foreach (explode(', ', $photo['milestones']) as $milestone): ?>
                        <span class="milestone"><i class="fas fa-star"></i> <?php echo htmlspecialchars($milestone); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="photo-image">
                        <img src="uploads/<?php echo $photo['filename']; ?>" alt="<?php echo htmlspecialchars($photo['title'] ?? 'Fotoğraf'); ?>">
                    </div>
                    
                    <?php if (!empty($photo['description'])): ?>
                    <div class="photo-description">
                        <p><?php echo htmlspecialchars($photo['description']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($photo['memory_note'])): ?>
                    <div class="memory-note">
                        <p><?php echo nl2br(htmlspecialchars($photo['memory_note'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        
        <!-- Son Mesaj Sayfası -->
        <div class="print-page message-page">
            <h2>Sevgili Kızıma</h2>
            
            <div class="message-content">
                <p>Bu albümü büyük bir sevgiyle hazırladım ve senin için sakladım. İçindeki her bir fotoğraf, senin hayatının birer parçası ve benim için çok değerli anılar.</p>
                
                <p>Seninle geçirdiğim her an benim için çok kıymetli. Bu albümdeki fotoğraflar ve notlar, seni ne kadar çok sevdiğimi ve büyüme serüveninde yanında olmaktan ne kadar mutluluk duyduğumu anlatıyor.</p>
                
                <p>Umarım bu albümü incelemek sana geçmiş günleri hatırlatır ve gülümsemene vesile olur.</p>
            </div>
            
            <div class="signature">
                <p>Sevgiyle,</p>
                <p>Annen</p>
            </div>
        </div>
    </div>
    
    <script>
        // Sayfa yüklendiğinde baskı önizlemesi yapılmasını sağla
        document.addEventListener('DOMContentLoaded', function() {
            // Sayfanın yüklendiğini bildir
            console.log('Yazdırma sayfası hazır');
        });
    </script>
</body>
</html> 