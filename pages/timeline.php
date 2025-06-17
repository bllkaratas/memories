<?php
// Yıl bazında fotoğrafları grupla
try {
    $query = "SELECT p.*, 
              GROUP_CONCAT(m.title SEPARATOR ', ') as milestones,
              GROUP_CONCAT(m.icon SEPARATOR ', ') as milestone_icons
              FROM photos p
              LEFT JOIN photo_milestones pm ON p.id = pm.photo_id
              LEFT JOIN milestones m ON pm.milestone_id = m.id
              GROUP BY p.id
              ORDER BY p.photo_date DESC";
    
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
    $photosByYear = [];
}
?>

<div class="container">
    <div class="page-header">
        <h1>Zaman Çizelgesi</h1>
        <p>Kızınızın büyüme yolculuğu</p>
    </div>
    
    <?php if (empty($photosByYear)): ?>
        <div class="empty-state">
            <i class="fas fa-clock"></i>
            <h3>Henüz zaman çizelgesinde fotoğraf yok</h3>
            <p>Fotoğraf ekledikçe burada güzel bir zaman çizelgesi oluşacak!</p>
            <a href="?page=upload" class="btn btn-primary">İlk Fotoğrafı Ekle</a>
        </div>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($photosByYear as $year => $yearPhotos): ?>
                <div class="timeline-year">
                    <h2 class="year-title"><?= $year ?></h2>
                    <div class="year-photos">
                        <?php foreach ($yearPhotos as $index => $photo): ?>
                            <div class="timeline-item fade-in" style="animation-delay: <?= $index * 0.1 ?>s">
                                <div class="timeline-marker">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-photo" onclick="openPhotoModal('uploads/<?= htmlspecialchars($photo['filename']) ?>', '<?= htmlspecialchars($photo['title'] ?? 'Fotoğraf') ?>')">
                                        <img src="uploads/<?= htmlspecialchars($photo['filename']) ?>" 
                                             alt="<?= htmlspecialchars($photo['title'] ?? 'Fotoğraf') ?>"
                                             loading="lazy">
                                        <div class="photo-overlay">
                                            <i class="fas fa-search-plus"></i>
                                        </div>
                                    </div>
                                    <div class="timeline-info">
                                        <h3><?= htmlspecialchars($photo['title'] ?? 'İsimsiz') ?></h3>
                                        <div class="timeline-meta">
                                            <span class="date">
                                                <i class="fas fa-calendar"></i> 
                                                <?= strftime('%d %B %Y', strtotime($photo['photo_date'])) ?>
                                            </span>
                                            <?php if ($photo['age_years'] !== null): ?>
                                                <span class="age-badge">
                                                    <?= $photo['age_years'] ?> yaş <?= $photo['age_months'] % 12 ?> ay
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($photo['location'])): ?>
                                                <span class="location">
                                                    <i class="fas fa-map-marker-alt"></i> 
                                                    <?= htmlspecialchars($photo['location']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($photo['milestones']): ?>
                                            <div class="timeline-milestones">
                                                <?php 
                                                $milestones = explode(', ', $photo['milestones']);
                                                $icons = explode(', ', $photo['milestone_icons']);
                                                foreach ($milestones as $i => $milestone): 
                                                ?>
                                                    <span class="milestone-badge">
                                                        <i class="fas fa-<?= $icons[$i] ?? 'star' ?>"></i>
                                                        <?= htmlspecialchars($milestone) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($photo['description']): ?>
                                            <p class="timeline-description">
                                                <?= htmlspecialchars($photo['description']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($photo['memory_note']): ?>
                                            <div class="memory-note">
                                                <i class="fas fa-quote-left"></i>
                                                <p><?= nl2br(htmlspecialchars($photo['memory_note'])) ?></p>
                                                <i class="fas fa-quote-right"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Fotoğraf Modal -->
        <div id="photoModal" class="photo-modal">
            <span class="close-modal" onclick="closePhotoModal()">&times;</span>
            <img id="modalImage" class="modal-content" src="" alt="">
            <div id="modalCaption" class="modal-caption"></div>
        </div>
    <?php endif; ?>
</div>

<style>
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 15px;
}

.page-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.page-header h1 {
    color: var(--primary-color);
    margin-bottom: 0.5rem;
    font-size: 2.2rem;
}

.page-header p {
    color: #666;
}

.timeline {
    position: relative;
    padding: 1rem 0;
    margin: 0 auto;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 60px;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
    border-radius: 2px;
}

.timeline-year {
    margin-bottom: 3rem;
    position: relative;
}

.year-title {
    font-size: 2.8rem;
    color: var(--primary-color);
    margin-bottom: 2rem;
    position: relative;
    padding-left: 100px;
    display: inline-block;
}

.year-title::before {
    content: '';
    position: absolute;
    left: 60px;
    top: 50%;
    transform: translateY(-50%) translateX(-15px);
    width: 30px;
    height: 30px;
    background: var(--primary-color);
    border-radius: 50%;
    border: 4px solid white;
    box-shadow: 0 0 0 3px var(--primary-color);
    z-index: 1;
}

.timeline-item {
    position: relative;
    margin-bottom: 3rem;
    padding-left: 120px;
    margin-left: 30px;
}

.timeline-marker {
    position: absolute;
    left: 60px;
    top: 30px;
    width: 20px;
    height: 20px;
    background: white;
    border: 3px solid var(--secondary-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: translateX(-10px);
    z-index: 2;
    box-shadow: 0 0 8px rgba(0,0,0,0.2);
}

.timeline-marker i {
    font-size: 10px;
    color: var(--secondary-color);
}

.timeline-content {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    overflow: hidden;
    display: flex;
    transition: all 0.3s ease;
    position: relative;
    width: 100%;
    transform: translateZ(0); /* Mobilde daha iyi performans için */
    will-change: transform; /* Animasyon performansını iyileştir */
}

.timeline-content:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.timeline-content::before {
    content: '';
    position: absolute;
    left: -15px;
    top: 30px;
    border-top: 12px solid transparent;
    border-bottom: 12px solid transparent;
    border-right: 15px solid white;
}

.timeline-photo {
    width: 350px;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.timeline-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.timeline-content:hover .timeline-photo img {
    transform: scale(1.05);
}

.timeline-info {
    padding: 2rem;
    flex-grow: 1;
}

.timeline-info h3 {
    color: var(--dark-color);
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.timeline-meta {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    margin-bottom: 1.5rem;
    color: #666;
    font-size: 1rem;
    flex-wrap: wrap;
}

.timeline-meta .date {
    display: flex;
    align-items: center;
    gap: 8px;
}

.timeline-meta .date i {
    color: var(--primary-color);
}

.location {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
}

.location i {
    color: var(--secondary-color);
}

.age-badge {
    background: var(--gradient);
    color: white;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 0.95rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

.timeline-milestones {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

.milestone-badge {
    background: var(--accent-color);
    color: var(--dark-color);
    padding: 7px 15px;
    border-radius: 20px;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.milestone-badge i {
    color: var(--primary-color);
}

.timeline-description {
    color: #666;
    margin-bottom: 1.5rem;
    line-height: 1.7;
    font-size: 1rem;
}

.memory-note {
    background: #fff5f5;
    padding: 2rem;
    border-radius: 12px;
    margin-top: 1.5rem;
    position: relative;
    font-style: italic;
    color: #555;
    border: 1px dashed var(--primary-color);
}

.memory-note i:first-child {
    position: absolute;
    top: 15px;
    left: 15px;
    color: var(--primary-color);
    opacity: 0.3;
    font-size: 1.4rem;
}

.memory-note i:last-child {
    position: absolute;
    bottom: 15px;
    right: 15px;
    color: var(--primary-color);
    opacity: 0.3;
    font-size: 1.4rem;
}

.memory-note p {
    margin: 0;
    padding: 0 2rem;
    font-size: 1.05rem;
    line-height: 1.8;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.empty-state i {
    font-size: 4rem;
    color: var(--primary-color);
    margin-bottom: 1.5rem;
}

.empty-state h3 {
    color: var(--dark-color);
    margin-bottom: 1rem;
}

.empty-state p {
    color: #666;
    margin-bottom: 1.5rem;
}

/* Mobil düzen için responsive ayarlar */
@media (max-width: 992px) {
    .timeline-content {
        flex-direction: column;
    }
    
    .timeline-photo {
        width: 100%;
        height: 300px;
    }
    
    .container {
        padding: 0 10px;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 0 5px;
    }
    
    .timeline::before {
        left: 20px;
    }
    
    .year-title {
        padding-left: 50px;
        font-size: 2rem;
        margin-bottom: 1.5rem;
    }
    
    .year-title::before {
        left: 20px;
        width: 20px;
        height: 20px;
        transform: translateY(-50%) translateX(-10px);
    }
    
    .timeline-item {
        padding-left: 40px;
        margin-left: 0;
        margin-bottom: 2rem;
    }
    
    .timeline-marker {
        left: 20px;
        width: 14px;
        height: 14px;
        transform: translateX(-7px);
        top: 15px;
    }
    
    .timeline-marker i {
        font-size: 8px;
    }
    
    .timeline-content::before {
        left: -10px;
        top: 15px;
        border-top: 8px solid transparent;
        border-bottom: 8px solid transparent;
        border-right: 10px solid white;
    }
    
    .timeline-photo {
        height: 200px;
    }
    
    .timeline-info {
        padding: 1rem;
    }
    
    .timeline-info h3 {
        font-size: 1.3rem;
        margin-bottom: 0.8rem;
    }
    
    .timeline-meta {
        gap: 0.8rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }
    
    .age-badge {
        padding: 5px 10px;
        font-size: 0.85rem;
    }
    
    .timeline-milestones {
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .milestone-badge {
        padding: 5px 10px;
        font-size: 0.85rem;
    }
    
    .timeline-description {
        font-size: 0.95rem;
        margin-bottom: 1rem;
    }
    
    .memory-note {
        padding: 1.2rem;
        margin-top: 1rem;
    }
    
    .memory-note p {
        padding: 0 1rem;
        font-size: 0.95rem;
    }
    
    .memory-note i:first-child {
        top: 8px;
        left: 8px;
        font-size: 1.1rem;
    }
    
    .memory-note i:last-child {
        bottom: 8px;
        right: 8px;
        font-size: 1.1rem;
    }
}

/* Daha küçük mobil ekranlar için ek düzenlemeler */
@media (max-width: 480px) {
    .page-header h1 {
        font-size: 1.8rem;
    }
    
    .year-title {
        font-size: 1.7rem;
        padding-left: 40px;
    }
    
    .timeline-item {
        padding-left: 35px;
    }
    
    .timeline-photo {
        height: 180px;
    }
    
    .timeline-info h3 {
        font-size: 1.2rem;
    }
    
    .timeline-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .timeline-meta span {
        margin-bottom: 0.3rem;
    }
    
    .timeline-milestones {
        margin-top: 0.5rem;
    }
    
    .photo-overlay i {
        font-size: 1.5rem;
        width: 40px;
        height: 40px;
    }
    
    .modal-content {
        max-width: 95%;
    }
    
    .close-modal {
        top: 10px;
        right: 15px;
    }
    
    .modal-caption {
        font-size: 1rem;
        padding: 5px 0;
    }
}

/* Türkçe ay isimleri için */
<?php
setlocale(LC_TIME, 'tr_TR.UTF-8', 'turkish');
?>

/* Fotoğraf overlay */
.photo-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.photo-overlay i {
    color: white;
    font-size: 2rem;
    background: rgba(0,0,0,0.5);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.timeline-photo:hover .photo-overlay {
    opacity: 1;
}

/* Fotoğraf Modal */
.photo-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    padding-top: 50px;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.9);
    transition: opacity 0.3s ease;
}

.modal-content {
    margin: auto;
    display: block;
    max-width: 90%;
    max-height: 80vh;
    object-fit: contain;
}

.modal-caption {
    margin: auto;
    display: block;
    width: 80%;
    max-width: 700px;
    text-align: center;
    color: white;
    padding: 10px 0;
    font-size: 1.2rem;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 25px;
    color: #f1f1f1;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 10000;
}

@keyframes zoomIn {
    from {transform: scale(0.1); opacity: 0;}
    to {transform: scale(1); opacity: 1;}
}

.modal-content, .modal-caption {
    animation-name: zoomIn;
    animation-duration: 0.5s;
}
</style>

<script>
// Fotoğraf modalı için fonksiyonlar
function openPhotoModal(imgSrc, caption) {
    const modal = document.getElementById('photoModal');
    const modalImg = document.getElementById('modalImage');
    const captionText = document.getElementById('modalCaption');
    
    modal.style.display = "block";
    modalImg.src = imgSrc;
    captionText.innerHTML = caption;
    
    // Mobil cihazlarda scroll'u engelle
    document.body.style.overflow = 'hidden';
}

function closePhotoModal() {
    document.getElementById('photoModal').style.display = "none";
    
    // Scroll'u tekrar etkinleştir
    document.body.style.overflow = 'auto';
}

// Arka plana tıklayınca da kapat
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('photoModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closePhotoModal();
            }
        });
    }
});
</script> 