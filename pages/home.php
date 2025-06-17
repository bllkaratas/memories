<?php
// Son eklenen fotoğrafları getir
$recentPhotosQuery = "SELECT p.*, 
    TIMESTAMPDIFF(YEAR, p.photo_date, CURDATE()) as years_ago,
    TIMESTAMPDIFF(MONTH, p.photo_date, CURDATE()) as months_ago
    FROM photos p 
    ORDER BY p.created_at DESC 
    LIMIT 6";

try {
    $recentPhotos = $db->query($recentPhotosQuery)->fetchAll();
    
    // İstatistikler
    $totalPhotos = $db->query("SELECT COUNT(*) FROM photos")->fetchColumn();
    $totalMemories = $db->query("SELECT COUNT(*) FROM photos WHERE memory_note IS NOT NULL")->fetchColumn();
    $totalMilestones = $db->query("SELECT COUNT(DISTINCT milestone_id) FROM photo_milestones")->fetchColumn();
} catch(PDOException $e) {
    $recentPhotos = [];
    $totalPhotos = 0;
    $totalMemories = 0;
    $totalMilestones = 0;
}
?>

<div class="container">
    <div class="welcome-section fade-in">
        <h1>Hoş Geldiniz, <?= isset($_SESSION['username']) ? $_SESSION['username'] : 'Ziyaretçi' ?>! 👋</h1>
        <p>Kızınızın güzel anılarını birlikte saklayalım</p>
    </div>
    
    <!-- İstatistik Kartları -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-camera"></i>
            </div>
            <div class="stat-info">
                <h3><?= $totalPhotos ?></h3>
                <p>Toplam Fotoğraf</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-heart"></i>
            </div>
            <div class="stat-info">
                <h3><?= $totalMemories ?></h3>
                <p>Anı Notu</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-info">
                <h3><?= $totalMilestones ?></h3>
                <p>Özel Gün</p>
            </div>
        </div>
    </div>
    
    <!-- Son Eklenen Fotoğraflar -->
    <div class="section-header">
        <h2>Son Eklenen Anılar</h2>
        <a href="?page=upload" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni Fotoğraf Ekle
        </a>
    </div>
    
    <?php if (empty($recentPhotos)): ?>
        <div class="empty-state">
            <i class="fas fa-camera-retro"></i>
            <h3>Henüz fotoğraf eklenmemiş</h3>
            <p>Kızınızın güzel anılarını saklamaya başlayın!</p>
            <a href="?page=upload" class="btn btn-primary">İlk Fotoğrafı Ekle</a>
        </div>
    <?php else: ?>
        <div class="photo-grid">
            <?php foreach($recentPhotos as $photo): ?>
                <div class="photo-card">
                    <img src="uploads/<?= htmlspecialchars($photo['filename']) ?>" 
                         alt="<?= htmlspecialchars($photo['title'] ?? 'Fotoğraf') ?>">
                    <div class="photo-info">
                        <h3 class="photo-title"><?= htmlspecialchars($photo['title'] ?? 'İsimsiz') ?></h3>
                        <?php if ($photo['age_years'] !== null): ?>
                            <span class="age-badge">
                                <?= $photo['age_years'] ?> yaş <?= $photo['age_months'] % 12 ?> ay
                            </span>
                        <?php endif; ?>
                        <div class="photo-meta">
                            <span><i class="fas fa-calendar"></i> <?= date('d.m.Y', strtotime($photo['photo_date'])) ?></span>
                            <?php if (!empty($photo['location'])): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($photo['location']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($photo['memory_note']): ?>
                            <p class="memory-preview"><?= htmlspecialchars(substr($photo['memory_note'], 0, 100)) ?>...</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.welcome-section {
    text-align: center;
    margin-bottom: 3rem;
}

.welcome-section h1 {
    color: var(--dark-color);
    margin-bottom: 0.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: var(--gradient);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-info h3 {
    font-size: 2rem;
    margin-bottom: 0.25rem;
    color: var(--dark-color);
}

.stat-info p {
    color: #666;
    margin: 0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.section-header h2 {
    color: var(--dark-color);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.empty-state i {
    font-size: 5rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: var(--dark-color);
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #666;
    margin-bottom: 1.5rem;
}

.memory-preview {
    margin-top: 0.5rem;
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}
</style> 