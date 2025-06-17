<?php
// GPS koordinatlarını ondalık sayıya çeviren fonksiyon
function gps2Num($coordPart) {
    if (!is_array($coordPart) || count($coordPart) < 3) {
        return 0; // Geçersiz koordinat
    }
    
    // Derece
    if (is_string($coordPart[0]) && strpos($coordPart[0], '/') !== false) {
        $parts = explode('/', $coordPart[0]);
        $degrees = count($parts) > 1 ? floatval($parts[0]) / floatval($parts[1]) : floatval($parts[0]);
    } else {
        $degrees = floatval($coordPart[0]);
    }
    
    // Dakika
    if (is_string($coordPart[1]) && strpos($coordPart[1], '/') !== false) {
        $parts = explode('/', $coordPart[1]);
        $minutes = count($parts) > 1 ? floatval($parts[0]) / floatval($parts[1]) : floatval($parts[0]);
    } else {
        $minutes = floatval($coordPart[1]);
    }
    
    // Saniye
    if (is_string($coordPart[2]) && strpos($coordPart[2], '/') !== false) {
        $parts = explode('/', $coordPart[2]);
        $seconds = count($parts) > 1 ? floatval($parts[0]) / floatval($parts[1]) : floatval($parts[0]);
    } else {
        $seconds = floatval($coordPart[2]);
    }
    
    return $degrees + ($minutes / 60) + ($seconds / 3600);
}

// Özel günleri veritabanından çek
try {
    $milestones = $db->query("SELECT * FROM milestones ORDER BY title")->fetchAll();
} catch(PDOException $e) {
    $milestones = [];
}

// Hata ayıklama için PHP ayarlarını göster
$debug_info = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'memory_limit' => ini_get('memory_limit')
];

// Boyut dönüştürme fonksiyonu
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Sunucu ayarlarına göre boyut sınırlarını hesapla
$max_upload = formatBytes(return_bytes('16G')); // upload_max_filesize: 16G
$max_post = formatBytes(return_bytes('128M')); // post_max_size: 128M
$memory_limit = formatBytes(return_bytes('512M')); // memory_limit: 512M
$upload_mb = formatBytes(return_bytes('16G')); // upload_max_filesize: 16G

// Byte değerine çevirme fonksiyonu
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo']) && is_array($_FILES['photo']['name'])) {
    $uploadDir = 'uploads/';
    $tempDir = 'uploads/temp/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Form verilerini al
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $memory_note = $_POST['memory_note'] ?? '';
    $photo_date = $_POST['photo_date'] ?? date('Y-m-d');
    $location = $_POST['location'] ?? '';
    $milestone_ids = $_POST['milestones'] ?? [];
    
    // GPS koordinatlarını al ve sayısal değerlere dönüştür
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    
    // Hata ayıklama
    error_log("Form'dan gelen koordinatlar - Lat: " . var_export($latitude, true) . ", Lon: " . var_export($longitude, true));
    
    // Yaş hesaplama
    $birthDate = $_POST['birth_date'] ?? '2020-04-08'; // Kızınızın doğum tarihini buraya girin
    $photoDateTime = new DateTime($photo_date);
    $birthDateTime = new DateTime($birthDate);
    $age = $birthDateTime->diff($photoDateTime);
    $age_years = $age->y;
    $age_months = ($age->y * 12) + $age->m;
    
    // Yüklenen dosyaların sayısını kontrol et
    $fileCount = count($_FILES['photo']['name']);
    $successCount = 0;
    $errorMessages = [];
    
    // Toplam dosya boyutunu kontrol et
    $totalSize = 0;
    for ($i = 0; $i < $fileCount; $i++) {
        $totalSize += $_FILES['photo']['size'][$i];
    }
    
    // Hata ayıklama için dosya boyutlarını logla
    error_log("Toplam dosya boyutu: " . formatBytes($totalSize));
    error_log("POST_MAX_SIZE: 128M");
    error_log("UPLOAD_MAX_FILESIZE: 16G");
    
    // POST_MAX_SIZE kontrolü
    $maxPostSize = return_bytes('128M'); // Sunucu ayarı: post_max_size = 128M
    if ($totalSize > $maxPostSize) {
        $errorMessages[] = "Toplam dosya boyutu (" . formatBytes($totalSize) . ") izin verilen maksimum boyutu (" . formatBytes($maxPostSize) . ") aşıyor.";
    } else {
        // Her bir dosyayı işle
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $_FILES['photo']['name'][$i],
                'type' => $_FILES['photo']['type'][$i],
                'tmp_name' => $_FILES['photo']['tmp_name'][$i],
                'error' => $_FILES['photo']['error'][$i],
                'size' => $_FILES['photo']['size'][$i]
            ];
            
            // Dosya boyutu kontrolü
            $maxFileSize = return_bytes('16G'); // Sunucu ayarı: upload_max_filesize = 16G
            if ($file['size'] > $maxFileSize) {
                $errorMessages[] = $file['name'] . ": Dosya boyutu (" . formatBytes($file['size']) . ") izin verilen maksimum boyutu (" . formatBytes($maxFileSize) . ") aşıyor.";
                continue;
            }
            
            // Dosya yükleme hatası kontrolü
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = array(
                    UPLOAD_ERR_INI_SIZE => "Dosya boyutu PHP tarafından belirtilen limiti aşıyor.",
                    UPLOAD_ERR_FORM_SIZE => "Dosya boyutu form tarafından belirtilen limiti aşıyor.",
                    UPLOAD_ERR_PARTIAL => "Dosya yalnızca kısmen yüklendi.",
                    UPLOAD_ERR_NO_FILE => "Lütfen bir dosya seçin.",
                    UPLOAD_ERR_NO_TMP_DIR => "Geçici klasör eksik.",
                    UPLOAD_ERR_CANT_WRITE => "Dosya diske yazılamadı.",
                    UPLOAD_ERR_EXTENSION => "PHP uzantısı dosya yüklemesini durdurdu."
                );
                $errorMessages[] = $file['name'] . ": " . ($uploadErrors[$file['error']] ?? "Bilinmeyen bir dosya yükleme hatası oluştu.");
                continue;
            }
            
            // Dosya boş değilse devam et
            if (empty($file['tmp_name']) || !file_exists($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                $errorMessages[] = $file['name'] . ": Geçersiz dosya.";
                continue;
            }
            
            // Dosya uzantısını ve türünü kontrol et
            $originalFileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // mime_content_type yerine daha güvenli bir yöntem
            try {
                if (function_exists('mime_content_type')) {
                    $fileType = mime_content_type($file['tmp_name']);
                } elseif (function_exists('finfo_file')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $fileType = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                } else {
                    // MIME tipi belirlenemiyorsa, uzantıya göre varsayalım
                    $fileType = '';
                    $mimeTypes = [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        'heic' => 'image/heic',
                        'heif' => 'image/heif'
                    ];
                    if (isset($mimeTypes[$originalFileExt])) {
                        $fileType = $mimeTypes[$originalFileExt];
                    }
                }
            } catch (Exception $e) {
                // MIME tipi belirleme hatası
                $fileType = '';
            }
            
            $uniqueId = uniqid();
            $convertedFileName = $uniqueId . '.jpg'; // Standart olarak JPG'ye çevireceğiz
            $finalFilePath = $uploadDir . $convertedFileName;
            
            $uploadSuccess = false;
            
            // HEIC veya HEIF formatlarını işleme
            if ($originalFileExt === 'heic' || $originalFileExt === 'heif' || strpos($fileType, 'heic') !== false || strpos($fileType, 'heif') !== false) {
                // HEIC dosyasını geçici olarak yükle
                $tempFilePath = $tempDir . $uniqueId . '.' . $originalFileExt;
                if (move_uploaded_file($file['tmp_name'], $tempFilePath)) {
                    // GD Kütüphanesi ile dönüştürme (basit yöntem)
                    if (function_exists('imagecreatefromjpeg')) {
                        try {
                            // Bazı sunucularda ImageMagick varsa onunla dönüştür
                            if (extension_loaded('imagick')) {
                                $imagick = new Imagick();
                                $imagick->readImage($tempFilePath);
                                $imagick->setImageFormat('jpg');
                                $imagick->writeImage($finalFilePath);
                                $imagick->clear();
                                $imagick->destroy();
                                $uploadSuccess = true;
                            } 
                            // ImageMagick yoksa, PHP exec ile dönüştürmeyi dene
                            else if (function_exists('exec')) {
                                // heif-convert kullanarak dönüştür (sunucuda yüklü olmalı)
                                exec("heif-convert $tempFilePath $finalFilePath", $output, $returnVar);
                                if ($returnVar === 0) {
                                    $uploadSuccess = true;
                                }
                                // Alternatif olarak sips komutu (macOS'ta)
                                else {
                                    exec("sips -s format jpeg $tempFilePath --out $finalFilePath", $output, $returnVar);
                                    if ($returnVar === 0) {
                                        $uploadSuccess = true;
                                    }
                                }
                            }
                            
                            if ($uploadSuccess) {
                                // Geçici dosyayı sil
                                @unlink($tempFilePath);
                            } else {
                                // Dönüştürme olmadı, direkt JPG gibi kaydet deneyelim
                                if (copy($tempFilePath, $finalFilePath)) {
                                    $uploadSuccess = true;
                                    @unlink($tempFilePath);
                                } else {
                                    // HEIC formatını desteklemeyen sunucularda hata göster
                                    $errorMessages[] = $file['name'] . ": HEIC formatındaki fotoğraf JPG'ye dönüştürülemedi.";
                                    continue;
                                }
                            }
                        } catch (Exception $e) {
                            $errorMessages[] = $file['name'] . ": Fotoğraf dönüştürme hatası: " . $e->getMessage();
                            continue;
                        }
                    } else {
                        $errorMessages[] = $file['name'] . ": Sunucuda gerekli görüntü işleme kütüphaneleri yüklü değil.";
                        continue;
                    }
                } else {
                    $errorMessages[] = $file['name'] . ": Geçici dosya yükleme hatası!";
                    continue;
                }
            } 
            // Bilinen formatları doğrudan kaydet
            else {
                // İzin verilen formatları kontrol et
                $allowedFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($originalFileExt, $allowedFormats)) {
                    // Standart dosya uzantısını koru
                    $finalFilePath = $uploadDir . $uniqueId . '.' . $originalFileExt;
                    
                    if (move_uploaded_file($file['tmp_name'], $finalFilePath)) {
                        $uploadSuccess = true;
                        $convertedFileName = $uniqueId . '.' . $originalFileExt;
                    } else {
                        $errorMessages[] = $file['name'] . ": Dosya yükleme hatası!";
                        continue;
                    }
                } else {
                    $errorMessages[] = $file['name'] . ": Desteklenmeyen dosya formatı: ." . $originalFileExt;
                    continue;
                }
            }
            
            // Fotoğraf başarıyla yüklendiyse EXIF bilgilerini oku ve veritabanına ekle
            if ($uploadSuccess) {
                // İlk fotoğraf için EXIF bilgilerini oku (eğer henüz okunmadıysa)
                if ($i === 0) {
                    $currentLatitude = $latitude;
                    $currentLongitude = $longitude;
                    
                    // EXIF bilgilerini oku
                    $exifData = [];
                    $exifDate = null;
                    $exifLocation = '';
                    
                    try {
                        if (function_exists('exif_read_data') && file_exists($finalFilePath)) {
                            // Test sayfasındaki gibi ANY_TAG ve true parametreleriyle daha kapsamlı veri alalım
                            $exifData = @exif_read_data($finalFilePath, 'ANY_TAG', true);
                            error_log("EXIF verileri okundu: " . ($exifData ? "Başarılı" : "Başarısız"));
                            
                            // Cihaz türünü tespit et
                            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                            $isAndroid = (strpos($userAgent, 'Android') !== false);
                            if ($isAndroid) {
                                error_log("Android cihazdan yükleme tespit edildi: $userAgent");
                            }
                            
                            if ($exifData !== false) {
                                // Bulunan EXIF bölümlerini logla
                                error_log("EXIF bölümleri: " . implode(", ", array_keys($exifData)));
                                
                                // Tarih bilgisini EXIF'ten al (eğer form boşsa)
                                if (empty($_POST['photo_date']) || $_POST['photo_date'] == date('Y-m-d')) {
                                    $exifDateFields = ['DateTimeOriginal', 'DateTime', 'DateTimeDigitized'];
                                    
                                    // Önce EXIF bölümünde ara
                                    if (isset($exifData['EXIF'])) {
                                        foreach ($exifDateFields as $field) {
                                            if (isset($exifData['EXIF'][$field])) {
                                                $exifDateStr = $exifData['EXIF'][$field];
                                                // EXIF tarih formatı: "2023:12:25 14:30:15"
                                                $exifDateStr = str_replace(':', '-', substr($exifDateStr, 0, 10));
                                                $exifDate = date('Y-m-d', strtotime($exifDateStr));
                                                if ($exifDate && $exifDate != '1970-01-01') {
                                                    $photo_date = $exifDate;
                                                    error_log("EXIF tarih bulundu (EXIF bölümü): $exifDate");
                                                    // Yaş hesaplamasını yeniden yap
                                                    $photoDateTime = new DateTime($photo_date);
                                                    $age = $birthDateTime->diff($photoDateTime);
                                                    $age_years = $age->y;
                                                    $age_months = ($age->y * 12) + $age->m;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Kök seviyede de ara (bazı Android cihazlar için)
                                    if (!$exifDate) {
                                        foreach ($exifDateFields as $field) {
                                            if (isset($exifData[$field])) {
                                                $exifDateStr = $exifData[$field];
                                                $exifDateStr = str_replace(':', '-', substr($exifDateStr, 0, 10));
                                                $exifDate = date('Y-m-d', strtotime($exifDateStr));
                                                if ($exifDate && $exifDate != '1970-01-01') {
                                                    $photo_date = $exifDate;
                                                    error_log("EXIF tarih bulundu (kök seviye): $exifDate");
                                                    // Yaş hesaplamasını yeniden yap
                                                    $photoDateTime = new DateTime($photo_date);
                                                    $age = $birthDateTime->diff($photoDateTime);
                                                    $age_years = $age->y;
                                                    $age_months = ($age->y * 12) + $age->m;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                // GPS koordinatlarını al (eğer konum boşsa)
                                if (empty($_POST['location'])) {
                                    // GPS bölümünü kontrol et
                                    if (isset($exifData['GPS'])) {
                                        error_log("GPS bölümü bulundu: " . implode(", ", array_keys($exifData['GPS'])));
                                        
                                        // GPS verilerini detaylı kontrol et - standart format
                                        if (isset($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLongitude'], 
                                                 $exifData['GPS']['GPSLatitudeRef'], $exifData['GPS']['GPSLongitudeRef'])) {
                                            
                                            error_log("Tüm GPS alanları mevcut");
                                            
                                            // Ham GPS verilerini logla
                                            error_log("Ham GPS Lat: " . print_r($exifData['GPS']['GPSLatitude'], true));
                                            error_log("Ham GPS Lon: " . print_r($exifData['GPS']['GPSLongitude'], true));
                                            
                                            // Koordinatları hesapla
                                            $lat = gps2Num($exifData['GPS']['GPSLatitude']);
                                            $lon = gps2Num($exifData['GPS']['GPSLongitude']);
                                            
                                            if ($exifData['GPS']['GPSLatitudeRef'] == 'S') $lat = -$lat;
                                            if ($exifData['GPS']['GPSLongitudeRef'] == 'W') $lon = -$lon;
                                            
                                            // Hesaplanan koordinatları logla
                                            error_log("Hesaplanan GPS Lat: $lat");
                                            error_log("Hesaplanan GPS Lon: $lon");
                                            
                                            // Koordinatların sayısal olduğundan emin ol
                                            $currentLatitude = floatval($lat);
                                            $currentLongitude = floatval($lon);
                                            
                                            // Geçerli koordinatlar mı kontrol et (0,0 geçersizdir)
                                            if ($currentLatitude != 0 || $currentLongitude != 0) {
                                                // Koordinatları adrese çevir (basit format)
                                                $exifLocation = "GPS: " . round($currentLatitude, 6) . ", " . round($currentLongitude, 6);
                                                
                                                // Eğer internet bağlantısı varsa, koordinatları adrese çevirmeyi dene
                                                try {
                                                    $geocodeUrl = "https://api.bigdatacloud.net/data/reverse-geocode-client?latitude={$currentLatitude}&longitude={$currentLongitude}&localityLanguage=tr";
                                                    $context = stream_context_create([
                                                        'http' => [
                                                            'timeout' => 3,
                                                            'ignore_errors' => true
                                                        ]
                                                    ]);
                                                    $geocodeResponse = @file_get_contents($geocodeUrl, false, $context);
                                                    if ($geocodeResponse) {
                                                        $geocodeData = json_decode($geocodeResponse, true);
                                                        if (isset($geocodeData['locality']) || isset($geocodeData['city'])) {
                                                            $city = $geocodeData['locality'] ?? $geocodeData['city'] ?? '';
                                                            $country = $geocodeData['countryName'] ?? '';
                                                            if ($city) {
                                                                $exifLocation = $city . ($country ? ", " . $country : "");
                                                                error_log("Konum bulundu: $exifLocation");
                                                            }
                                                        }
                                                    }
                                                } catch (Exception $e) {
                                                    // Geocoding hatası, GPS koordinatlarını kullan
                                                    error_log("Geocoding hatası: " . $e->getMessage());
                                                }
                                                
                                                if ($exifLocation) {
                                                    $location = $exifLocation;
                                                }
                                                
                                                // Hata ayıklama
                                                error_log("Veritabanına kaydedilecek GPS Koordinatları: Lat=$currentLatitude, Lon=$currentLongitude");
                                            } else {
                                                error_log("Geçersiz koordinatlar: 0,0");
                                            }
                                        } 
                                        // Android için alternatif GPS formatı kontrolü
                                        else if ($isAndroid && isset($exifData['GPS']['GPSLatitude']) && !is_array($exifData['GPS']['GPSLatitude'])) {
                                            error_log("Android alternatif GPS formatı tespit edildi");
                                            
                                            // Android bazı cihazlarda GPS bilgilerini farklı formatta saklayabilir
                                            $latStr = $exifData['GPS']['GPSLatitude'] ?? '';
                                            $lonStr = $exifData['GPS']['GPSLongitude'] ?? '';
                                            $latRef = $exifData['GPS']['GPSLatitudeRef'] ?? 'N';
                                            $lonRef = $exifData['GPS']['GPSLongitudeRef'] ?? 'E';
                                            
                                            if (!empty($latStr) && !empty($lonStr)) {
                                                error_log("Android GPS string değerleri: Lat=$latStr, Lon=$lonStr");
                                                
                                                // String formatından sayısal değere çevirme
                                                $lat = floatval($latStr);
                                                $lon = floatval($lonStr);
                                                
                                                if ($latRef == 'S') $lat = -$lat;
                                                if ($lonRef == 'W') $lon = -$lon;
                                                
                                                if ($lat != 0 || $lon != 0) {
                                                    $currentLatitude = $lat;
                                                    $currentLongitude = $lon;
                                                    $exifLocation = "GPS: " . round($lat, 6) . ", " . round($lon, 6);
                                                    $location = $exifLocation;
                                                    error_log("Android GPS işlendi: $exifLocation");
                                                }
                                            }
                                        }
                                        else {
                                            error_log("GPS alanları eksik: " . implode(", ", array_keys($exifData['GPS'])));
                                        }
                                    } else {
                                        error_log("GPS bölümü bulunamadı");
                                        
                                        // Android cihazlar için diğer bölümleri kontrol et
                                        if ($isAndroid) {
                                            foreach ($exifData as $section => $content) {
                                                if (is_array($content) && isset($content['GPSLatitude'])) {
                                                    error_log("GPS bilgisi alternatif bölümde bulundu: $section");
                                                    // Bu bölümü GPS bölümü gibi işle
                                                    // [Bu kısmı gerekirse genişletebilirsiniz]
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // EXIF okuma hatası, devam et
                        error_log("EXIF okuma hatası: " . $e->getMessage());
                    }
                }
                
                try {
                    // Veritabanına kaydet
                    $stmt = $db->prepare("INSERT INTO photos (filename, original_name, title, description, memory_note, photo_date, age_years, age_months, location, latitude, longitude, uploaded_by) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    // Koordinat değerlerini kontrol et
                    if (isset($currentLatitude) && $currentLatitude != 0 && isset($currentLongitude) && $currentLongitude != 0) {
                        error_log("Koordinatlar geçerli: Lat=$currentLatitude, Lon=$currentLongitude");
                    } else {
                        error_log("Koordinatlar geçersiz veya sıfır");
                        $currentLatitude = null;
                        $currentLongitude = null;
                    }
                    
                    $stmt->execute([
                        $convertedFileName, 
                        $file['name'], 
                        $title, 
                        $description, 
                        $memory_note, 
                        $photo_date, 
                        $age_years, 
                        $age_months, 
                        $location, 
                        $currentLatitude,
                        $currentLongitude,
                        $_SESSION['user_id']
                    ]);
                    
                    $photo_id = $db->lastInsertId();
                    
                    // Özel günleri kaydet
                    if (!empty($milestone_ids)) {
                        $stmt = $db->prepare("INSERT INTO photo_milestones (photo_id, milestone_id) VALUES (?, ?)");
                        foreach ($milestone_ids as $milestone_id) {
                            $stmt->execute([$photo_id, $milestone_id]);
                        }
                    }
                    
                    $successCount++;
                } catch(PDOException $e) {
                    $errorMessages[] = $file['name'] . ": Veritabanı hatası: " . $e->getMessage();
                }
            }
        }
        
        // Sonuçları göster
        if ($successCount > 0) {
            $success = $successCount . " fotoğraf başarıyla yüklendi!";
        }
        
        if (!empty($errorMessages)) {
            $error = implode("<br>", $errorMessages);
        }
    }
}
?>

<div class="container">
    <div class="page-header">
        <h1>Fotoğraf Yükle</h1>
        <p>Kızınızın güzel anılarını albüme ekleyin</p>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= $error ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= $success ?>
    </div>
    <?php endif; ?>
    
    <!-- PHP Ayarları Bilgisi (Hata ayıklama için) -->
    <div class="card mb-4">
        <h3>PHP Yükleme Ayarları</h3>
        <div class="debug-info">
            <ul>
                <li><strong>upload_max_filesize:</strong> 16G (<?= $max_upload ?>)</li>
                <li><strong>post_max_size:</strong> 128M (<?= $max_post ?>)</li>
                <li><strong>max_file_uploads:</strong> <?= $debug_info['max_file_uploads'] ?></li>
                <li><strong>memory_limit:</strong> 512M (<?= $memory_limit ?>)</li>
            </ul>
            <p class="text-muted">Not: Çoklu dosya yüklemede, tüm dosyaların toplam boyutu post_max_size değerini aşmamalıdır.</p>
            <p class="text-muted"><strong>Mobil cihazlarda büyük dosyalar yüklerken sorun yaşarsanız:</strong></p>
            <ul class="text-muted">
                <li>Daha küçük boyutlu veya daha az sayıda dosya seçmeyi deneyin</li>
                <li>Mobil internet yerine WiFi kullanın</li>
                <li>Fotoğrafların kalitesini düşürmeyi deneyin</li>
                <li>Dosya başına maksimum boyut: <?= $max_upload ?></li>
                <li>Toplam maksimum boyut: <?= $max_post ?></li>
            </ul>
        </div>
        <style>
            .debug-info {
                background: #f8f9fa;
                padding: 1rem;
                border-radius: 8px;
                margin: 1rem 0;
            }
            .debug-info ul {
                margin-bottom: 1rem;
                padding-left: 1.5rem;
            }
            .debug-info li {
                margin-bottom: 0.5rem;
            }
        </style>
    </div>
    
    <form class="upload-form" action="" method="post" enctype="multipart/form-data">
        <div class="card">
            <h3>Fotoğraf Seçin</h3>
            
            <div class="upload-area" onclick="document.getElementById('photo').click();">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Fotoğraf Yüklemek İçin Tıklayın</h3>
                <p>veya sürükleyip bırakın</p>
                <p class="text-muted"><small>Birden fazla fotoğraf seçebilirsiniz</small></p>
                <input type="file" id="photo" name="photo[]" accept="image/*,.heic,.heif" style="display: none;" onchange="previewImages(this);" required multiple>
            </div>
            
            <div class="supported-formats">
                <p>Desteklenen formatlar: JPG, PNG, GIF, WEBP, HEIC (iPhone)</p>
            </div>
            
            <div id="preview-container" style="display: none;">
                <div id="preview-images" class="preview-grid"></div>
                <p class="text-muted mt-2"><span id="selected-count">0</span> fotoğraf seçildi</p>
            </div>
        </div>
        
        <div class="card">
            <h3>Fotoğraf Bilgileri</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="title" class="form-label">Başlık</label>
                    <input type="text" id="title" name="title" class="form-control" placeholder="Örn: İlk adımları">
                </div>
                
                <div class="form-group">
                    <label for="photo_date" class="form-label">
                        Fotoğraf Tarihi 
                        <small class="text-muted">📷 EXIF bilgisinden otomatik doldurulur</small>
                    </label>
                    <input type="date" id="photo_date" name="photo_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="location" class="form-label">
                    Konum 
                    <small class="text-muted">🌍 GPS bilgisinden otomatik doldurulur</small>
                </label>
                <input type="text" id="location" name="location" class="form-control" placeholder="Örn: İstanbul, Türkiye veya Ev, Park, Okul">
                <small class="form-help">Bu bilgi anasayfa, timeline ve anılar sayfasında görüntülenecek</small>
            </div>
            
            <div class="form-group">
                <label for="description" class="form-label">Açıklama</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Bu fotoğraf hakkında kısa bir açıklama..."></textarea>
            </div>
        </div>
        
        <div class="card">
            <h3>Özel Anı Notu 💝</h3>
            <p class="text-muted">Kızınız büyüdüğünde okuyacağı özel bir not ekleyin</p>
            
            <div class="form-group">
                <textarea id="memory_note" name="memory_note" class="form-control" rows="5" 
                    placeholder="Sevgili kızım, bu fotoğrafta..."></textarea>
            </div>
        </div>
        
        <?php if (!empty($milestones)): ?>
        <div class="card">
            <h3>Özel Günler</h3>
            <p class="text-muted">Bu fotoğraf bir özel güne ait mi?</p>
            
            <div class="milestone-grid">
                <?php foreach($milestones as $milestone): ?>
                    <label class="milestone-option">
                        <input type="checkbox" name="milestones[]" value="<?= $milestone['id'] ?>">
                        <span class="milestone-badge">
                            <i class="fas fa-<?= $milestone['icon'] ?>"></i>
                            <?= htmlspecialchars($milestone['title']) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Anıyı Kaydet
            </button>
            <a href="?page=home" class="btn btn-outline">İptal</a>
        </div>
    </form>
</div>

<style>
.page-header {
    text-align: center;
    margin-bottom: 2rem;
}

.page-header h1 {
    color: var(--dark-color);
    margin-bottom: 0.5rem;
}

.upload-form .card {
    margin-bottom: 2rem;
}

.upload-form h3 {
    color: var(--dark-color);
    margin-bottom: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.text-muted {
    color: #666;
    font-size: 0.9rem;
}

.form-help {
    color: #888;
    font-size: 0.8rem;
    margin-top: 0.25rem;
    display: block;
}

.test-instructions {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
}

.test-instructions ol {
    margin: 0;
    padding-left: 1.5rem;
}

.test-instructions li {
    margin-bottom: 0.5rem;
    color: #555;
}

.test-status {
    background: #e3f2fd;
    border: 1px solid #2196f3;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.test-status p {
    margin: 0;
    color: #1565c0;
    font-weight: 500;
}

.supported-formats {
    margin-top: 10px;
    text-align: center;
    font-size: 0.9rem;
    color: #666;
}

.milestone-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.milestone-option {
    cursor: pointer;
}

.milestone-option input[type="checkbox"] {
    display: none;
}

.milestone-option input[type="checkbox"]:checked + .milestone-badge {
    background: var(--primary-color);
    color: white;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.alert {
    padding: 1rem;
    margin-bottom: 2rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

.alert-warning {
    background: #fff8e1;
    color: #f57c00;
    border: 1px solid #ffca28;
}

.alert-info {
    background: #e3f2fd;
    color: #1565c0;
    border: 1px solid #2196f3;
}

.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.upload-area {
    border: 2px dashed #ccc;
    border-radius: 10px;
    padding: 3rem;
    text-align: center;
    margin-bottom: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}
.upload-area:hover {
    border-color: #999;
    background-color: #f9f9f9;
}
.upload-area.drag-over {
    border-color: #6c757d;
    background-color: #f0f0f0;
}
.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 1rem;
}
.preview-item {
    position: relative;
    height: 150px;
    overflow: hidden;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Dosya boyutu göstergesi */
.file-size {
    position: absolute;
    bottom: 0;
    right: 0;
    background: rgba(0,0,0,0.6);
    color: white;
    padding: 3px 8px;
    font-size: 0.8rem;
    border-top-left-radius: 8px;
}

.size-warning {
    background: rgba(255,0,0,0.7);
}

.oversized::after {
    content: "⚠️";
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 1.2rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', setupDragAndDrop);

// Çoklu resim seçildiğinde önizleme ve EXIF okuma
function previewImages(input) {
    const previewContainer = document.getElementById('preview-container');
    const previewGrid = document.getElementById('preview-images');
    const selectedCount = document.getElementById('selected-count');
    
    // Önizleme alanını temizle
    previewGrid.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        // Dosya boyutu kontrolü
        let totalSize = 0;
        const maxFileSize = <?= return_bytes('16G') ?>; // Sunucu ayarı: upload_max_filesize = 16G
        const maxPostSize = <?= return_bytes('128M') ?>; // Sunucu ayarı: post_max_size = 128M
        const maxFileCount = <?= ini_get('max_file_uploads') ?>;
        let oversizedFiles = [];
        
        // Toplam boyut ve büyük dosyaları kontrol et
        if (input.files.length > maxFileCount) {
            showInfo(`⚠️ Dikkat: Bir seferde en fazla ${maxFileCount} dosya yükleyebilirsiniz. İlk ${maxFileCount} dosya işlenecek.`, 'warning');
        }
        
        Array.from(input.files).forEach(file => {
            totalSize += file.size;
            if (file.size > maxFileSize) {
                oversizedFiles.push({
                    name: file.name,
                    size: formatFileSize(file.size)
                });
            }
        });
        
        // Toplam boyut kontrolü
        if (totalSize > maxPostSize) {
            showInfo(`⚠️ Hata: Toplam dosya boyutu (${formatFileSize(totalSize)}) izin verilen maksimum boyutu (${formatFileSize(maxPostSize)}) aşıyor. Daha az dosya seçin veya dosya boyutlarını küçültün.`, 'error');
        }
        
        // Büyük dosya uyarısı
        if (oversizedFiles.length > 0) {
            let message = `⚠️ Aşağıdaki dosyalar çok büyük ve yüklenemeyebilir (maksimum: ${formatFileSize(maxFileSize)}):<br>`;
            oversizedFiles.forEach(file => {
                message += `- ${file.name} (${file.size})<br>`;
            });
            message += "Lütfen daha küçük dosyalar seçin veya bu dosyaların boyutunu küçültün.";
            showInfo(message, 'warning');
        }
        
        previewContainer.style.display = 'block';
        selectedCount.textContent = input.files.length;
        
        // İlk dosyanın EXIF verilerini al (konum ve tarih için)
        const firstFile = input.files[0];
        fetchExifData(firstFile);
        
        // Tüm dosyaların önizlemesini göster
        Array.from(input.files).forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = e => {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'preview-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = `Fotoğraf ${index + 1}`;
                
                // Dosya boyutu göstergesi ekle
                const sizeIndicator = document.createElement('span');
                sizeIndicator.className = 'file-size';
                sizeIndicator.textContent = formatFileSize(file.size);
                
                // Dosya boyutu çok büyükse uyarı ekle
                if (file.size > maxFileSize) {
                    sizeIndicator.classList.add('size-warning');
                    imgContainer.classList.add('oversized');
                }
                
                imgContainer.appendChild(img);
                imgContainer.appendChild(sizeIndicator);
                previewGrid.appendChild(imgContainer);
            };
            
            reader.readAsDataURL(file);
        });
    } else {
        previewContainer.style.display = 'none';
        selectedCount.textContent = '0';
    }
}

// Dosya boyutunu formatla (KB, MB, GB)
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// EXIF verilerini sunucudan almak için AJAX isteği
function fetchExifData(file) {
    showInfo('⏳ Fotoğraf bilgileri alınıyor, lütfen bekleyin...', 'info');
    
    const formData = new FormData();
    formData.append('photo', file);
    
    fetch('pages/ajax_exif.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Yanıt JSON formatında mı kontrol et
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        }
        throw new Error('Sunucudan geçersiz yanıt formatı: JSON bekleniyor');
    })
    .then(result => {
        console.log('EXIF yanıtı:', result); // Hata ayıklama için
        
        if (result.success && result.data) {
            const { date, location, gps } = result.data;
            let infoMessages = [];
            
            if (date) {
                document.getElementById('photo_date').value = date;
                infoMessages.push('📅 Tarih otomatik ayarlandı');
            }
            
            if (location) {
                document.getElementById('location').value = location;
                infoMessages.push('🌍 Konum otomatik ayarlandı');
            } else if (gps) {
                // Konum adı bulunamadı ama GPS koordinatları var
                const locationText = `GPS: ${gps.lat.toFixed(6)}, ${gps.lon.toFixed(6)}`;
                document.getElementById('location').value = locationText;
                infoMessages.push('🌍 GPS koordinatları ayarlandı');
            }
            
            // GPS koordinatları sunucu tarafında işlenecek
            if (gps && gps.lat && gps.lon) {
                console.log('GPS koordinatları bulundu:', gps.lat, gps.lon);
            }
            
            if (infoMessages.length > 0) {
                showInfo(infoMessages.join(' & ') + '.', 'success');
            } else {
                showInfo('Bu fotoğrafta EXIF verisi bulunamadı.', 'warning');
            }
        } else {
            throw new Error(result.message || 'Sunucudan geçersiz yanıt alındı.');
        }
    })
    .catch(error => {
        console.error('EXIF alma hatası:', error);
        showInfo(`Hata: ${error.message}`, 'error');
    });
}

// Kullanıcıya bilgi mesajı göster
function showInfo(message, type = 'info') {
    const existingAlert = document.querySelector('.exif-alert');
    if (existingAlert) existingAlert.remove();

    const alert = document.createElement('div');
    const alertTypes = {
        'info': 'alert-info', 'success': 'alert-success', 
        'warning': 'alert-warning', 'error': 'alert-danger'
    };
    alert.className = `alert ${alertTypes[type] || 'alert-info'} exif-alert fade-in`;
    alert.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;

    const form = document.querySelector('.upload-form');
    if (form) {
        // Find the first card and insert before it
        const firstCard = form.querySelector('.card');
        if(firstCard){
            form.insertBefore(alert, firstCard);
        } else {
            form.prepend(alert);
        }
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.opacity = '0';
                setTimeout(() => alert.parentNode?.removeChild(alert), 300);
            }
        }, 6000);
    }
}

// Sürükle-bırak fonksiyonları
function setupDragAndDrop() {
    const uploadArea = document.querySelector('.upload-area');
    const fileInput = document.getElementById('photo');
    if (!uploadArea || !fileInput) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, e => {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => uploadArea.classList.add('highlight'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('highlight'), false);
    });

    uploadArea.addEventListener('drop', e => {
        // Birden fazla dosya sürüklenebilir
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            previewImages(fileInput);
        }
    }, false);
}
</script>