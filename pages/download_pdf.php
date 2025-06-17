<?php
// Sadece giriş yapmış kullanıcılar için
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ?page=login');
    exit;
}

// TCPDF kütüphanesi yükleme kontrolü
if (!file_exists('lib/tcpdf/tcpdf.php')) {
    echo "<div class='alert alert-danger'>
            <i class='fas fa-exclamation-circle'></i> PDF oluşturmak için TCPDF kütüphanesi gerekiyor.
          </div>";
    echo "<div class='text-center mt-4'>
            <a href='?page=export' class='btn btn-primary'>Geri Dön</a>
          </div>";
    exit;
}

// TCPDF kütüphanesini dahil et
require_once('lib/tcpdf/tcpdf.php');

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

    // PDF oluştur
    class MYPDF extends TCPDF {
        // Sayfa üstbilgisi
        public function Header() {
            $this->SetFont('dejavusans', 'B', 16);
            $this->Cell(0, 15, 'Hatıra Albümü', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        }
        
        // Sayfa altbilgisi
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('dejavusans', 'I', 8);
            $this->Cell(0, 10, 'Sayfa '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    // PDF dokümanı oluştur
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Doküman bilgilerini ayarla
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Hatıra Albümü');
    $pdf->SetTitle('Kızımın Hatıra Albümü');
    $pdf->SetSubject('Hatıralar');
    $pdf->SetKeywords('Anılar, Fotoğraflar, Hatıralar');
    
    // Varsayılan üstbilgi/altbilgi ayarları
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Varsayılan monospace yazı tipi
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Kenar boşlukları
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Otomatik sayfa sonu
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Resim ölçekleme faktörü
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Giriş sayfası
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', 'B', 24);
    $pdf->Cell(0, 20, 'Kızımın Büyüme Hikayesi', 0, 1, 'C');
    
    $pdf->SetFont('dejavusans', '', 14);
    $pdf->Ln(20);
    $pdf->WriteHTML('<p style="text-align:center;">Sevgili kızım, bu albüm senin büyüme yolculuğunu kaydettiğim özel bir hatıra.</p>');
    $pdf->Ln(10);
    $pdf->WriteHTML('<p style="text-align:center;">İçinde büyüme sürecinin en özel anlarını, küçük başarılarını ve sevgi dolu anılarını bulacaksın.</p>');
    $pdf->Ln(20);
    
    // İçindekiler için yıl özeti
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 15, 'Yıllar', 0, 1, 'L');
    
    $pdf->SetFont('dejavusans', '', 12);
    foreach ($photosByYear as $year => $photos) {
        $pdf->Cell(0, 10, $year . ' - ' . count($photos) . ' fotoğraf', 0, 1, 'L');
    }
    
    // Her yıl için fotoğrafları ekle
    foreach ($photosByYear as $year => $yearPhotos) {
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', 'B', 20);
        $pdf->Cell(0, 15, $year, 0, 1, 'C');
        
        foreach ($yearPhotos as $photo) {
            // Fotoğrafı ekle
            $imagePath = 'uploads/' . $photo['filename'];
            if (file_exists($imagePath)) {
                $pdf->Ln(10);
                
                // Başlık ve tarih
                $pdf->SetFont('dejavusans', 'B', 14);
                $pdf->Cell(0, 10, htmlspecialchars($photo['title'] ?? 'İsimsiz'), 0, 1, 'L');
                
                $pdf->SetFont('dejavusans', '', 10);
                $date = date('d.m.Y', strtotime($photo['photo_date']));
                $age = '';
                if ($photo['age_years'] !== null) {
                    $age = ' - ' . $photo['age_years'] . ' yaş ' . ($photo['age_months'] % 12) . ' ay';
                }
                $pdf->Cell(0, 8, $date . $age, 0, 1, 'L');
                
                // Dönüm noktaları
                if (!empty($photo['milestones'])) {
                    $milestones = explode(', ', $photo['milestones']);
                    $pdf->SetFont('dejavusans', 'I', 10);
                    foreach ($milestones as $milestone) {
                        $pdf->Cell(0, 6, '★ ' . htmlspecialchars($milestone), 0, 1, 'L');
                    }
                }
                
                // Fotoğrafı ekle
                $imageWidth = 150;
                $imageHeight = 0;
                list($width, $height) = getimagesize($imagePath);
                if ($width > 0 && $height > 0) {
                    $imageHeight = $imageWidth * ($height / $width);
                }
                
                // Sayfa yetmiyorsa yeni sayfa ekle
                if ($pdf->GetY() + $imageHeight > $pdf->getPageHeight() - 30) {
                    $pdf->AddPage();
                }
                
                $pdf->Image($imagePath, '', '', $imageWidth, $imageHeight, '', '', 'T', false, 300, 'C', false, false, 1, true, false, false);
                $pdf->Ln(5);
                
                // Fotoğraf açıklaması
                if (!empty($photo['description'])) {
                    $pdf->SetFont('dejavusans', '', 10);
                    $pdf->Ln($imageHeight + 5);
                    $pdf->MultiCell(0, 5, htmlspecialchars($photo['description']), 0, 'L', false, 1, '', '', true, 0, false, true, 0, 'T', false);
                }
                
                // Anı notu
                if (!empty($photo['memory_note'])) {
                    // Sayfa yetmiyorsa yeni sayfa ekle
                    if ($pdf->GetY() > $pdf->getPageHeight() - 60) {
                        $pdf->AddPage();
                    }
                    
                    $pdf->SetFont('dejavusans', 'I', 10);
                    $pdf->Ln(5);
                    $pdf->SetFillColor(245, 245, 245);
                    $pdf->MultiCell(0, 5, '❝ ' . htmlspecialchars($photo['memory_note']) . ' ❞', 0, 'L', true, 1, '', '', true, 0, false, true, 0, 'T', false);
                }
                
                $pdf->Ln(15);
            }
        }
    }
    
    // Son sayfa - kapanış mesajı
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 15, 'Sevgili Kızıma', 0, 1, 'C');
    
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->Ln(10);
    $pdf->WriteHTML('<p>Bu albümü büyük bir sevgiyle hazırladım ve senin için sakladım. İçindeki her bir fotoğraf, senin hayatının birer parçası ve benim için çok değerli anılar.</p>');
    $pdf->Ln(10);
    $pdf->WriteHTML('<p>Umarım bu albümü incelemek sana geçmiş günleri hatırlatır ve gülümsemene vesile olur.</p>');
    $pdf->Ln(10);
    $pdf->WriteHTML('<p>Sevgiyle,<br>Annen</p>');
    
    // PDF'i kaydet ve indir
    $pdfFile = 'exports/hatira_albumu_' . date('Ymd_His') . '.pdf';
    $pdf->Output($pdfFile, 'F');
    
    // PDF indirme işlemi başlat
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="hatira_albumu.pdf"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($pdfFile));
    readfile($pdfFile);
    
    // İşlem tamamlandıktan sonra PDF'i sunucudan silme (isteğe bağlı)
    // unlink($pdfFile);
    exit;
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>
            <i class='fas fa-exclamation-circle'></i> PDF oluşturma sırasında bir hata oluştu: " . $e->getMessage() . "
          </div>";
    echo "<div class='text-center mt-4'>
            <a href='?page=export' class='btn btn-primary'>Geri Dön</a>
          </div>";
}
?>

<div class="container text-center">
    <div class="spinner-border text-primary" role="status">
        <span class="sr-only">PDF hazırlanıyor...</span>
    </div>
    <p class="mt-3">PDF hazırlanıyor, lütfen bekleyin...</p>
    <p class="small text-muted">Bu işlem fotoğraf sayısına bağlı olarak biraz zaman alabilir.</p>
    <p class="mt-4">
        <a href="?page=export" class="btn btn-outline-secondary">İşlemi iptal et</a>
    </p>
</div>

<style>
.spinner-border {
    width: 4rem;
    height: 4rem;
    margin-top: 2rem;
}
</style> 