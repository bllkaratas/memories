<?php
// Sadece giriş yapmış kullanıcılar için
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ?page=login');
    exit;
}

// Dışa aktarma işlemi
$exportType = $_POST['export_type'] ?? '';
$exportStatus = '';
$exportMessage = '';

// Tüm fotoğrafları ve bilgileri al
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

    // Toplam fotoğraf sayısı
    $totalPhotos = count($photos);
    $totalMemories = 0;
    $totalYears = count($photosByYear);

    // Anı notu olan fotoğrafları say
    foreach ($photos as $photo) {
        if (!empty($photo['memory_note'])) {
            $totalMemories++;
        }
    }

    // POST işlemi ise dışa aktarma yap
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($exportType)) {
        switch ($exportType) {
            case 'pdf':
                // PDF olarak dışa aktarma
                $exportStatus = 'success';
                $exportMessage = 'PDF dosyası hazırlanıyor, indirme otomatik olarak başlayacak...';
                
                // PDF indirme için yönlendir
                header('Refresh: 2; URL=?page=download_pdf');
                break;
                
            case 'html':
                // HTML olarak dışa aktarma
                $exportDir = 'exports/html/' . uniqid();
                if (!file_exists($exportDir)) {
                    mkdir($exportDir, 0777, true);
                }
                
                // Stil dosyasını kopyala
                copy('assets/css/style.css', $exportDir . '/style.css');
                
                // Ana HTML dosyasını oluştur
                $html = '<!DOCTYPE html>
                <html lang="tr">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Hatıra Albümü</title>
                    <link rel="stylesheet" href="style.css">
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                </head>
                <body>
                    <div class="container">
                        <div class="export-header">
                            <h1>Hatıra Albümü</h1>
                            <p>Sevgili kızım, büyüdüğün yolculuğun anıları...</p>
                        </div>';
                
                // Timeline oluştur
                $html .= '<div class="timeline">';
                
                foreach ($photosByYear as $year => $yearPhotos) {
                    $html .= '<div class="timeline-year">
                        <h2 class="year-title">' . $year . '</h2>
                        <div class="year-photos">';
                        
                    foreach ($yearPhotos as $photo) {
                        $photoPath = 'photo_' . $photo['id'] . '.jpg';
                        // Fotoğrafı kopyala
                        copy('uploads/' . $photo['filename'], $exportDir . '/' . $photoPath);
                        
                        $html .= '<div class="timeline-item">
                            <div class="timeline-marker"><i class="fas fa-heart"></i></div>
                            <div class="timeline-content">
                                <div class="timeline-photo">
                                    <img src="' . $photoPath . '" alt="' . htmlspecialchars($photo['title'] ?? 'Fotoğraf') . '">
                                </div>
                                <div class="timeline-info">
                                    <h3>' . htmlspecialchars($photo['title'] ?? 'İsimsiz') . '</h3>
                                    <div class="timeline-meta">
                                        <span class="date"><i class="fas fa-calendar"></i> ' . date('d.m.Y', strtotime($photo['photo_date'])) . '</span>';
                                        
                        if ($photo['age_years'] !== null) {
                            $html .= '<span class="age-badge">' . $photo['age_years'] . ' yaş ' . ($photo['age_months'] % 12) . ' ay</span>';
                        }
                        
                        $html .= '</div>';
                        
                        if (!empty($photo['milestones'])) {
                            $html .= '<div class="timeline-milestones">';
                            $milestones = explode(', ', $photo['milestones']);
                            foreach ($milestones as $milestone) {
                                $html .= '<span class="milestone-badge"><i class="fas fa-star"></i> ' . htmlspecialchars($milestone) . '</span>';
                            }
                            $html .= '</div>';
                        }
                        
                        if (!empty($photo['description'])) {
                            $html .= '<p class="timeline-description">' . htmlspecialchars($photo['description']) . '</p>';
                        }
                        
                        if (!empty($photo['memory_note'])) {
                            $html .= '<div class="memory-note">
                                <i class="fas fa-quote-left"></i>
                                <p>' . nl2br(htmlspecialchars($photo['memory_note'])) . '</p>
                                <i class="fas fa-quote-right"></i>
                            </div>';
                        }
                        
                        $html .= '</div></div></div>';
                    }
                    
                    $html .= '</div></div>';
                }
                
                $html .= '</div>'; // timeline sonu
                
                $html .= '<div class="export-footer">
                        <p>Bu hatıra albümü özenle hazırlandı - ' . date('Y') . '</p>
                    </div>
                    </div>
                </body>
                </html>';
                
                file_put_contents($exportDir . '/index.html', $html);
                
                // ZIP dosyası oluştur
                $zipFile = 'exports/hatira_albumu_' . date('Ymd_His') . '.zip';
                $zip = new ZipArchive();
                if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                    $dirFiles = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($exportDir),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    foreach ($dirFiles as $name => $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = substr($filePath, strlen($exportDir) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                    $zip->close();
                    
                    // Geçici dosyaları temizle
                    $rmDir = function($dir) use (&$rmDir) {
                        $files = array_diff(scandir($dir), array('.', '..'));
                        foreach ($files as $file) {
                            is_dir("$dir/$file") ? $rmDir("$dir/$file") : unlink("$dir/$file");
                        }
                        return rmdir($dir);
                    };
                    $rmDir($exportDir);
                    
                    $exportStatus = 'success';
                    $exportMessage = 'HTML albümü başarıyla ZIP olarak oluşturuldu, indirme otomatik olarak başlayacak...';
                    
                    // ZIP indirme için yönlendir
                    header('Refresh: 2; URL=' . $zipFile);
                } else {
                    $exportStatus = 'error';
                    $exportMessage = 'ZIP dosyası oluşturulamadı.';
                }
                break;
                
            case 'print':
                // Baskı için optimize edilmiş görünüm
                $exportStatus = 'success';
                $exportMessage = 'Yazdırılabilir sayfa hazırlanıyor, yazdırma penceresi açılacak...';
                
                // Yazdırma sayfasına yönlendir
                header('Refresh: 2; URL=?page=print_album');
                break;
        }
    }
    
} catch(PDOException $e) {
    $photos = [];
    $photosByYear = [];
    $totalPhotos = 0;
    $totalMemories = 0;
    $totalYears = 0;
}

// Exports klasörünü oluştur
if (!file_exists('exports')) {
    mkdir('exports', 0777, true);
}
if (!file_exists('exports/html')) {
    mkdir('exports/html', 0777, true);
}
?>

<div class="container">
    <div class="page-header">
        <h1>Albümü Dışa Aktar</h1>
        <p>Kızınız için hatıra albümünü farklı formatlarda dışa aktarın</p>
    </div>
    
    <?php if (!empty($exportStatus)): ?>
        <div class="alert alert-<?= $exportStatus === 'success' ? 'success' : 'danger' ?> fade-in">
            <i class="fas fa-<?= $exportStatus === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= $exportMessage ?>
        </div>
    <?php endif; ?>
    
    <div class="card stats-card">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $totalPhotos ?></h3>
                    <p>Toplam Fotoğraf</p>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $totalMemories ?></h3>
                    <p>Anı Notu</p>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $totalYears ?></h3>
                    <p>Yıl</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="export-options">
        <div class="export-option-card">
            <div class="export-option-icon">
                <i class="fas fa-globe"></i>
            </div>
            <h3>Dijital Sunum</h3>
            <p>Hem online olarak erişebilir, hem de offline olarak kullanılabilir bir HTML sürümü oluşturun. Fotoğraflar ve anılar interaktif bir zaman çizelgesinde sunulur.</p>
            <form method="POST">
                <input type="hidden" name="export_type" value="html">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-download"></i> HTML Olarak İndir
                </button>
            </form>
        </div>
        
        <div class="export-option-card">
            <div class="export-option-icon">
                <i class="fas fa-file-pdf"></i>
            </div>
            <h3>PDF Albüm</h3>
            <p>Albümünüzün PDF versiyonunu indirin. Bu formatı USB'de saklayabilir veya dilediğiniz zaman yazdırabilirsiniz. Tüm fotoğraflar ve anılar dahil edilir.</p>
            <form method="POST">
                <input type="hidden" name="export_type" value="pdf">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-file-download"></i> PDF Olarak İndir
                </button>
            </form>
        </div>
        
        <div class="export-option-card">
            <div class="export-option-icon">
                <i class="fas fa-print"></i>
            </div>
            <h3>Kitap Haline Getir</h3>
            <p>Yazdırmaya uygun bir format oluşturun. Basılı albüm olarak kızınıza hediye etmek istiyorsanız bu seçeneği kullanın.</p>
            <form method="POST">
                <input type="hidden" name="export_type" value="print">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-print"></i> Yazdırmaya Hazırla
                </button>
            </form>
        </div>
    </div>
    
    <div class="export-tips">
        <h3><i class="fas fa-lightbulb"></i> Hediye Etme İpuçları</h3>
        <ul>
            <li><strong>Dijital Sunum</strong> - Kişisel web alanınızda saklayıp özel bir alan adı ile erişim sağlayabilirsiniz (örn: www.kizininadi.com)</li>
            <li><strong>Offline Sunum</strong> - HTML veya PDF sürümü USB belleğe kaydedip özel bir kutu içinde hediye edebilirsiniz</li>
            <li><strong>Kitap Haline Getirme</strong> - Yazdırılabilir versiyonu yerel bir matbaada ciltletebilirsiniz</li>
        </ul>
    </div>
</div>

<style>
.page-header {
    text-align: center;
    margin-bottom: 2rem;
}

.page-header h1 {
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.stats-card {
    margin-bottom: 2rem;
    padding: 1.5rem;
}

.stats-grid {
    display: flex;
    gap: 2rem;
    justify-content: center;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: var(--gradient);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.stat-info h3 {
    font-size: 1.8rem;
    margin: 0;
    color: var(--dark-color);
}

.stat-info p {
    margin: 0;
    color: #666;
}

.export-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.export-option-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    padding: 2rem;
    text-align: center;
    transition: transform 0.3s;
}

.export-option-card:hover {
    transform: translateY(-5px);
}

.export-option-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: var(--light-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--primary-color);
}

.export-option-card h3 {
    color: var(--dark-color);
    margin-bottom: 1rem;
}

.export-option-card p {
    color: #666;
    margin-bottom: 1.5rem;
    height: 80px;
}

.export-tips {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    padding: 1.5rem 2rem;
    margin-top: 2rem;
}

.export-tips h3 {
    color: var(--primary-color);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.export-tips ul {
    padding-left: 1.5rem;
    margin-bottom: 0;
}

.export-tips li {
    margin-bottom: 0.5rem;
    color: #555;
}

.alert {
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-align: center;
    justify-content: center;
}

.alert-success {
    background: #e8f5e9;
    color: #2e7d32;
    border: 1px solid #4caf50;
}

.alert-danger {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ef5350;
}

@media (max-width: 992px) {
    .export-options {
        grid-template-columns: 1fr;
    }
    
    .export-option-card p {
        height: auto;
    }
    
    .stats-grid {
        flex-direction: column;
        align-items: center;
    }
}
</style> 