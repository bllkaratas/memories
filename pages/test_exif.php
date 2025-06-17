<?php
session_start();
require_once '../config/database.php';

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

// Yüklü fotoğrafları veritabanından çek
try {
    $photos = $db->query("SELECT * FROM photos ORDER BY photo_date DESC LIMIT 10")->fetchAll();
} catch(PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
    $photos = [];
}

// Fotoğraf yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_photo'])) {
    $file = $_FILES['test_photo'];
    $tempDir = '../uploads/temp/';
    
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
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
        $error = $uploadErrors[$file['error']] ?? "Bilinmeyen bir dosya yükleme hatası oluştu.";
    } 
    // Dosya boş değilse devam et
    elseif (!empty($file['tmp_name']) && file_exists($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
        $originalFileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uniqueId = uniqid('test_');
        $tempFilePath = $tempDir . $uniqueId . '.' . $originalFileExt;
        $processedFilePath = $tempFilePath; // Varsayılan yol
        
        if (move_uploaded_file($file['tmp_name'], $tempFilePath)) {
            // HEIC/HEIF Dönüştürme
            $fileType = mime_content_type($tempFilePath);
            if (strpos($fileType, 'heic') !== false || strpos($fileType, 'heif') !== false) {
                $convertedFilePath = $tempDir . $uniqueId . '.jpg';
                
                // İlk olarak heif-convert ile dene
                $command = "heif-convert $tempFilePath $convertedFilePath";
                @exec($command, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($convertedFilePath)) {
                    $processedFilePath = $convertedFilePath;
                } 
                // Sonra Imagick ile dene
                else if (extension_loaded('imagick')) {
                    try {
                        $imagick = new Imagick($tempFilePath);
                        $imagick->setImageFormat('jpg');
                        $imagick->writeImage($convertedFilePath);
                        $imagick->clear();
                        $imagick->destroy();
                        $processedFilePath = $convertedFilePath;
                    } catch (Exception $e) {
                        // Imagick hatası
                        $error = "HEIC dönüştürme hatası: " . $e->getMessage();
                    }
                } else {
                    $error = "HEIC formatı dönüştürülemedi. heif-convert veya ImageMagick gerekli.";
                }
            }
            
            // EXIF Verilerini Oku ve Göster
            $exifData = [];
            $exifDate = null;
            $exifLocation = null;
            $gpsCoords = null;
            $exifDebug = [];
            
            try {
                if (function_exists('exif_read_data') && file_exists($processedFilePath)) {
                    $exif = @exif_read_data($processedFilePath, 'ANY_TAG', true);
                    
                    if ($exif) {
                        // EXIF bölümlerini logla
                        $exifDebug['exif_sections'] = array_keys($exif);
                        
                        // GPS bölümünü özellikle incele
                        if (isset($exif['GPS'])) {
                            $exifDebug['gps_section'] = $exif['GPS'];
                        }
                        
                        // Tarih bilgisini al
                        $dateFields = ['DateTimeOriginal', 'DateTime', 'DateTimeDigitized'];
                        foreach ($dateFields as $field) {
                            if (isset($exif['EXIF'][$field])) {
                                $dateStr = str_replace(':', '-', substr($exif['EXIF'][$field], 0, 10));
                                $dateObj = date_create($dateStr);
                                if ($dateObj) {
                                    $exifDate = $dateObj->format('Y-m-d');
                                    $exifDebug['date_field'] = $field;
                                    $exifDebug['date_value'] = $exif['EXIF'][$field];
                                    break;
                                }
                            } else if (isset($exif['IFD0'][$field])) {
                                $dateStr = str_replace(':', '-', substr($exif['IFD0'][$field], 0, 10));
                                $dateObj = date_create($dateStr);
                                if ($dateObj) {
                                    $exifDate = $dateObj->format('Y-m-d');
                                    $exifDebug['date_field'] = 'IFD0.' . $field;
                                    $exifDebug['date_value'] = $exif['IFD0'][$field];
                                    break;
                                }
                            }
                        }
                        
                        // GPS bilgilerini al
                        if (isset($exif['GPS'])) {
                            $exifDebug['has_gps_section'] = true;
                            
                            if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLongitude'], 
                                    $exif['GPS']['GPSLatitudeRef'], $exif['GPS']['GPSLongitudeRef'])) {
                                
                                $lat = gps2Num($exif['GPS']['GPSLatitude']);
                                $lon = gps2Num($exif['GPS']['GPSLongitude']);
                                
                                if ($exif['GPS']['GPSLatitudeRef'] == 'S') $lat = -$lat;
                                if ($exif['GPS']['GPSLongitudeRef'] == 'W') $lon = -$lon;
                                
                                // Koordinatları logla
                                $exifDebug['raw_lat'] = $exif['GPS']['GPSLatitude'];
                                $exifDebug['raw_lon'] = $exif['GPS']['GPSLongitude'];
                                $exifDebug['calculated_lat'] = $lat;
                                $exifDebug['calculated_lon'] = $lon;
                                
                                $gpsCoords = ['lat' => $lat, 'lon' => $lon];
                                
                                // Geocode
                                $exifLocation = "GPS: " . round($lat, 6) . ", " . round($lon, 6);
                                
                                try {
                                    $geocodeUrl = "https://api.bigdatacloud.net/data/reverse-geocode-client?latitude={$lat}&longitude={$lon}&localityLanguage=tr";
                                    $geocodeResponse = @file_get_contents($geocodeUrl);
                                    
                                    if ($geocodeResponse) {
                                        $geoData = json_decode($geocodeResponse, true);
                                        
                                        $city = $geoData['locality'] ?? $geoData['city'] ?? $geoData['principalSubdivision'] ?? '';
                                        $country = $geoData['countryName'] ?? '';
                                        
                                        if ($city) {
                                            $exifLocation = $city . ($country ? ", " . $country : "");
                                        }
                                    }
                                } catch (Exception $e) {
                                    $exifDebug['geocode_error'] = $e->getMessage();
                                }
                            } else {
                                $exifDebug['missing_gps_fields'] = true;
                                if (isset($exif['GPS'])) {
                                    $exifDebug['available_gps_keys'] = array_keys($exif['GPS']);
                                }
                            }
                        } else {
                            $exifDebug['has_gps_section'] = false;
                        }
                    } else {
                        $exifDebug['exif_error'] = "EXIF verileri okunamadı";
                    }
                } else {
                    $exifDebug['exif_function_exists'] = function_exists('exif_read_data');
                    $exifDebug['file_exists'] = file_exists($processedFilePath);
                    $exifDebug['file_path'] = $processedFilePath;
                }
            } catch (Exception $e) {
                $exifDebug['error'] = "EXIF okuma hatası: " . $e->getMessage();
            }
            
            // Sonuçları göster
            $testResult = [
                'filename' => $file['name'],
                'file_type' => $fileType,
                'processed_path' => $processedFilePath,
                'exif_date' => $exifDate,
                'exif_location' => $exifLocation,
                'gps_coords' => $gpsCoords,
                'debug_info' => $exifDebug
            ];
            
            // Geçici dosyaları temizle
            if ($processedFilePath !== $tempFilePath) {
                @unlink($tempFilePath);
            }
            @unlink($processedFilePath);
            
        } else {
            $error = "Geçici dosya yükleme hatası!";
        }
    } else {
        $error = "Lütfen bir fotoğraf seçin.";
    }
}
?>

<div class="container">
    <div class="page-header">
        <h1>EXIF ve GPS Test Sayfası</h1>
        <p>Fotoğrafların konum bilgilerini test etmek için bu sayfayı kullanın</p>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h3>Test Fotoğrafı Yükle</h3>
        <p class="text-muted">Konum bilgisi içeren bir fotoğraf seçin (tercihen akıllı telefon ile çekilmiş)</p>
        
        <form method="POST" enctype="multipart/form-data" class="test-form">
            <div class="form-group">
                <input type="file" id="test_photo" name="test_photo" accept="image/*,.heic,.heif" required>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> EXIF Verilerini Test Et
            </button>
        </form>
    </div>
    
    <?php if (isset($testResult)): ?>
        <div class="card">
            <h3>Test Sonuçları</h3>
            
            <div class="result-section">
                <h4>Dosya Bilgileri</h4>
                <ul>
                    <li><strong>Dosya Adı:</strong> <?= htmlspecialchars($testResult['filename']) ?></li>
                    <li><strong>Dosya Türü:</strong> <?= htmlspecialchars($testResult['file_type']) ?></li>
                    <li><strong>İşlenen Dosya:</strong> <?= htmlspecialchars($testResult['processed_path']) ?></li>
                </ul>
            </div>
            
            <div class="result-section">
                <h4>EXIF Verileri</h4>
                <ul>
                    <li><strong>Fotoğraf Tarihi:</strong> <?= $testResult['exif_date'] ?: 'Bulunamadı' ?></li>
                    <li><strong>Konum Bilgisi:</strong> <?= $testResult['exif_location'] ?: 'Bulunamadı' ?></li>
                    <?php if (isset($testResult['gps_coords']) && $testResult['gps_coords']): ?>
                        <li><strong>GPS Koordinatları:</strong> 
                            <?= round($testResult['gps_coords']['lat'], 6) ?>, 
                            <?= round($testResult['gps_coords']['lon'], 6) ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="result-section">
                <h4>Hata Ayıklama Bilgileri</h4>
                <div class="debug-info">
                    <pre><?= htmlspecialchars(print_r($testResult['debug_info'], true)) ?></pre>
                </div>
            </div>
            
            <div class="result-section">
                <h4>Harita</h4>
                <?php if (isset($testResult['gps_coords']) && $testResult['gps_coords']): ?>
                    <div id="map" style="height: 400px; width: 100%; border-radius: 10px;"></div>
                    <script>
                        function initMap() {
                            const lat = <?= $testResult['gps_coords']['lat'] ?>;
                            const lng = <?= $testResult['gps_coords']['lon'] ?>;
                            const map = new google.maps.Map(document.getElementById("map"), {
                                center: { lat, lng },
                                zoom: 15,
                            });
                            new google.maps.Marker({
                                position: { lat, lng },
                                map,
                                title: "Fotoğraf Konumu",
                            });
                        }
                    </script>
                    <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBwXTydUNxIWU6s_Y2AdH4b6Gu-wgJvIBk&callback=initMap"></script>
                <?php else: ?>
                    <p>Bu fotoğrafta GPS koordinatları bulunamadı.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h3>Sorun Giderme Adımları</h3>
        <ol>
            <li>Fotoğrafın GPS bilgisi içerdiğinden emin olun (çoğu akıllı telefon varsayılan olarak konum bilgisini kaydeder).</li>
            <li>Telefonunuzun kamera ayarlarında "Konum etiketleri" veya "Konum bilgisi" seçeneğinin açık olduğunu kontrol edin.</li>
            <li>HEIC formatındaki fotoğraflar için, sunucuda heif-convert veya ImageMagick kurulu olmalıdır.</li>
            <li>PHP'nin exif uzantısının etkin olduğundan emin olun (php.ini dosyasında extension=exif).</li>
            <li>Bazı fotoğraf düzenleme programları, fotoğrafı kaydederken EXIF verilerini silebilir.</li>
        </ol>
        
        <div class="php-info">
            <h4>PHP EXIF Durumu</h4>
            <p>
                EXIF Uzantısı Yüklü: <strong><?= extension_loaded('exif') ? 'Evet' : 'Hayır' ?></strong><br>
                exif_read_data Fonksiyonu Mevcut: <strong><?= function_exists('exif_read_data') ? 'Evet' : 'Hayır' ?></strong>
            </p>
        </div>
    </div>
    
    <?php if (!empty($photos)): ?>
    <div class="card">
        <h3>Son Yüklenen Fotoğraflar</h3>
        <p class="text-muted">Veritabanına kaydedilen son fotoğrafların konum bilgileri</p>
        
        <div class="photos-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fotoğraf</th>
                        <th>Başlık</th>
                        <th>Tarih</th>
                        <th>Konum</th>
                        <th>Koordinatlar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($photos as $photo): ?>
                    <tr>
                        <td><?= $photo['id'] ?></td>
                        <td>
                            <img src="../uploads/<?= htmlspecialchars($photo['filename']) ?>" 
                                 alt="<?= htmlspecialchars($photo['title']) ?>"
                                 class="thumbnail">
                        </td>
                        <td><?= htmlspecialchars($photo['title']) ?></td>
                        <td><?= htmlspecialchars($photo['photo_date']) ?></td>
                        <td><?= htmlspecialchars($photo['location'] ?: 'Konum bilgisi yok') ?></td>
                        <td>
                            <?php if ($photo['latitude'] && $photo['longitude']): ?>
                                <?= round($photo['latitude'], 6) ?>, <?= round($photo['longitude'], 6) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.card {
    margin-bottom: 2rem;
}

.result-section {
    margin-bottom: 1.5rem;
}

.result-section h4 {
    color: var(--dark-color);
    margin-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 0.5rem;
}

.debug-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    overflow: auto;
    max-height: 300px;
}

.debug-info pre {
    margin: 0;
    white-space: pre-wrap;
    font-size: 0.9rem;
}

.photos-table {
    overflow-x: auto;
}

.photos-table table {
    width: 100%;
    border-collapse: collapse;
}

.photos-table th, .photos-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.photos-table th {
    background-color: #f8f9fa;
}

.thumbnail {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
}

.php-info {
    background: #e3f2fd;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
}
</style>