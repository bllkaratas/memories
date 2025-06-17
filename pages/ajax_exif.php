<?php
session_start();
require_once '../config/database.php';

// Helper function to convert GPS coordinates
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

// Prepare response
header('Content-Type: application/json');
$response = ['success' => false, 'data' => null, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['photo'])) {
    $response['message'] = 'Geçersiz istek.';
    echo json_encode($response);
    exit;
}

$file = $_FILES['photo'];
$tempDir = '../uploads/temp/';

if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}

if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    $response['message'] = 'Dosya yükleme hatası.';
    echo json_encode($response);
    exit;
}

// Dosya türünü ve cihaz bilgisini logla
$fileType = mime_content_type($file['tmp_name']);
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
error_log("EXIF - Dosya türü: $fileType, Cihaz: $userAgent");

$originalFileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$uniqueId = uniqid('exif_');
$tempFilePath = $tempDir . $uniqueId . '.' . $originalFileExt;
$processedFilePath = $tempFilePath; // Default path

if (!move_uploaded_file($file['tmp_name'], $tempFilePath)) {
    $response['message'] = 'Geçici dosya taşınamadı.';
    echo json_encode($response);
    exit;
}

// HEIC/HEIF Conversion
if (strpos($fileType, 'heic') !== false || strpos($fileType, 'heif') !== false) {
    $convertedFilePath = $tempDir . $uniqueId . '.jpg';
    $command = "heif-convert $tempFilePath $convertedFilePath";
    @exec($command, $output, $returnVar);

    if ($returnVar === 0 && file_exists($convertedFilePath)) {
        $processedFilePath = $convertedFilePath;
    } else {
        if (extension_loaded('imagick')) {
             try {
                $imagick = new Imagick($tempFilePath);
                $imagick->setImageFormat('jpg');
                $imagick->writeImage($convertedFilePath);
                $imagick->clear();
                $imagick->destroy();
                $processedFilePath = $convertedFilePath;
             } catch (Exception $e) {
                // Imagick failed
                error_log("EXIF - Imagick dönüşüm hatası: " . $e->getMessage());
             }
        }
    }
}

// Read EXIF Data
$exifData = [];
$exifDate = null;
$exifLocation = null;
$gpsCoords = null;

// Android için daha kapsamlı EXIF okuma
if (function_exists('exif_read_data') && file_exists($processedFilePath)) {
    // ANY_TAG ve true parametreleriyle tüm EXIF verilerini alalım
    $exif = @exif_read_data($processedFilePath, 'ANY_TAG', true);
    
    // Hata ayıklama için tüm EXIF bölümlerini logla
    if ($exif !== false) {
        error_log("EXIF - Bulunan bölümler: " . implode(", ", array_keys($exif)));
        
        // Android cihazlar için özel kontrol
        $isAndroid = (strpos($userAgent, 'Android') !== false);
        if ($isAndroid) {
            error_log("EXIF - Android cihaz tespit edildi");
        }
        
        // Get Date
        $dateFields = ['DateTimeOriginal', 'DateTime', 'DateTimeDigitized'];
        foreach ($dateFields as $field) {
            if (isset($exif['EXIF'][$field])) {
                $dateStr = str_replace(':', '-', substr($exif['EXIF'][$field], 0, 10));
                $dateObj = date_create($dateStr);
                if ($dateObj) {
                    $exifDate = $dateObj->format('Y-m-d');
                    error_log("EXIF - Tarih bulundu: $exifDate ($field)");
                    break;
                }
            } else if (isset($exif[$field])) {
                // Bazı Android cihazlarda EXIF bölümü olmadan direkt kök seviyede olabilir
                $dateStr = str_replace(':', '-', substr($exif[$field], 0, 10));
                $dateObj = date_create($dateStr);
                if ($dateObj) {
                    $exifDate = $dateObj->format('Y-m-d');
                    error_log("EXIF - Tarih bulundu (kök seviye): $exifDate ($field)");
                    break;
                }
            }
        }

        // Get GPS
        if (isset($exif['GPS'])) {
            error_log("EXIF - GPS bölümü bulundu: " . implode(", ", array_keys($exif['GPS'])));
            
            // GPS verilerini detaylı kontrol et
            if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLongitude'], 
                     $exif['GPS']['GPSLatitudeRef'], $exif['GPS']['GPSLongitudeRef'])) {
                
                // Ham GPS verilerini logla
                error_log("EXIF - Ham GPS Lat: " . print_r($exif['GPS']['GPSLatitude'], true));
                error_log("EXIF - Ham GPS Lon: " . print_r($exif['GPS']['GPSLongitude'], true));
                
                $lat = gps2Num($exif['GPS']['GPSLatitude']);
                $lon = gps2Num($exif['GPS']['GPSLongitude']);
                
                if ($exif['GPS']['GPSLatitudeRef'] == 'S') $lat = -$lat;
                if ($exif['GPS']['GPSLongitudeRef'] == 'W') $lon = -$lon;
                
                // Koordinatların sayısal olduğundan emin ol
                $lat = floatval($lat);
                $lon = floatval($lon);
                
                // Hata ayıklama
                error_log("EXIF - İşlenmiş GPS Koordinatları: Lat=$lat, Lon=$lon");
                
                // Koordinatlar geçerli mi kontrol et (0,0 noktası geçersizdir)
                if ($lat != 0 || $lon != 0) {
                    $gpsCoords = ['lat' => $lat, 'lon' => $lon];

                    // Geocode
                    $geocodeUrl = "https://api.bigdatacloud.net/data/reverse-geocode-client?latitude={$lat}&longitude={$lon}&localityLanguage=tr";
                    $geocodeResponse = @file_get_contents($geocodeUrl);
                    if ($geocodeResponse) {
                        $geoData = json_decode($geocodeResponse, true);
                        $city = $geoData['locality'] ?? $geoData['city'] ?? $geoData['principalSubdivision'] ?? '';
                        $country = $geoData['countryName'] ?? '';
                        if ($city) {
                            $exifLocation = $city . ($country ? ", " . $country : "");
                            error_log("EXIF - Konum bulundu: $exifLocation");
                        }
                    }
                    if (!$exifLocation) {
                        $exifLocation = "GPS: " . round($lat, 6) . ", " . round($lon, 6);
                    }
                } else {
                    error_log("EXIF - Geçersiz koordinatlar: 0,0");
                }
            } else {
                // Android için alternatif GPS formatı kontrolü
                if ($isAndroid && isset($exif['GPS']['GPSLatitude']) && !is_array($exif['GPS']['GPSLatitude'])) {
                    error_log("EXIF - Android alternatif GPS formatı tespit edildi");
                    
                    // Android bazı cihazlarda GPS bilgilerini farklı formatta saklayabilir
                    $latStr = $exif['GPS']['GPSLatitude'] ?? '';
                    $lonStr = $exif['GPS']['GPSLongitude'] ?? '';
                    $latRef = $exif['GPS']['GPSLatitudeRef'] ?? 'N';
                    $lonRef = $exif['GPS']['GPSLongitudeRef'] ?? 'E';
                    
                    if (!empty($latStr) && !empty($lonStr)) {
                        error_log("EXIF - Android GPS string değerleri: Lat=$latStr, Lon=$lonStr");
                        
                        // String formatından sayısal değere çevirme
                        $lat = floatval($latStr);
                        $lon = floatval($lonStr);
                        
                        if ($latRef == 'S') $lat = -$lat;
                        if ($lonRef == 'W') $lon = -$lon;
                        
                        if ($lat != 0 || $lon != 0) {
                            $gpsCoords = ['lat' => $lat, 'lon' => $lon];
                            $exifLocation = "GPS: " . round($lat, 6) . ", " . round($lon, 6);
                            error_log("EXIF - Android GPS işlendi: $exifLocation");
                        }
                    }
                } else {
                    error_log("EXIF - GPS alanları eksik: " . implode(", ", array_keys($exif['GPS'])));
                }
            }
        } else {
            // Bazı Android cihazlar GPS bilgilerini farklı bölümlerde saklayabilir
            foreach ($exif as $section => $content) {
                if (is_array($content) && isset($content['GPSLatitude'])) {
                    error_log("EXIF - GPS bilgisi alternatif bölümde bulundu: $section");
                    // Bu bölümü GPS bölümü gibi işle
                    // [Bu kısmı gerekirse genişletebilirsiniz]
                }
            }
        }
    } else {
        error_log("EXIF - Veri okunamadı");
    }
}

// Clean up temp files
if ($processedFilePath !== $tempFilePath) {
    @unlink($tempFilePath);
}
@unlink($processedFilePath);

// Send response
$response['success'] = true;
$response['data'] = [
    'date' => $exifDate,
    'location' => $exifLocation,
    'gps' => $gpsCoords
];

// Debug bilgisi ekle
$response['debug'] = [
    'has_gps' => isset($exif['GPS']),
    'gps_keys' => isset($exif['GPS']) ? array_keys($exif['GPS']) : [],
    'exif_sections' => is_array($exif) ? array_keys($exif) : [],
    'file_type' => $fileType,
    'is_android' => (strpos($userAgent, 'Android') !== false),
    'user_agent' => $userAgent
];

echo json_encode($response);
?> 