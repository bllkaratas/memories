<?php
// Sadece anı notu olan fotoğrafları getir
try {
    $query = "SELECT p.*, 
              GROUP_CONCAT(m.title SEPARATOR ', ') as milestones
              FROM photos p
              LEFT JOIN photo_milestones pm ON p.id = pm.photo_id
              LEFT JOIN milestones m ON pm.milestone_id = m.id
              WHERE p.memory_note IS NOT NULL AND p.memory_note != ''
              GROUP BY p.id
              ORDER BY p.photo_date DESC";
    
    $memories = $db->query($query)->fetchAll();
} catch(PDOException $e) {
    $memories = [];
}
?>

<div class="container">
    <div class="page-header">
        <h1>Özel Anılar 💝</h1>
        <p>Kızınıza yazdığınız tüm özel notlar</p>
    </div>
    
    <?php if (empty($memories)): ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <h3>Henüz özel anı notu eklenmemiş</h3>
            <p>Fotoğraflara anı notu ekleyerek kızınıza özel mesajlar bırakabilirsiniz!</p>
            <a href="?page=upload" class="btn btn-primary">Anı Ekle</a>
        </div>
    <?php else: ?>
        <div class="memories-grid">
            <?php foreach ($memories as $memory): ?>
                <div class="memory-card fade-in">
                    <div class="memory-image">
                        <img src="uploads/<?= htmlspecialchars($memory['filename']) ?>" 
                             alt="<?= htmlspecialchars($memory['title'] ?? 'Anı') ?>">
                        <div class="memory-date">
                            <i class="fas fa-calendar"></i>
                            <?= strftime('%d %B %Y', strtotime($memory['photo_date'])) ?>
                        </div>
                    </div>
                    
                    <div class="memory-content">
                        <h3><?= htmlspecialchars($memory['title'] ?? 'İsimsiz Anı') ?></h3>
                        
                        <div class="memory-meta">
                            <?php if ($memory['age_years'] !== null): ?>
                                <span class="age-badge">
                                    <?= $memory['age_years'] ?> yaş <?= $memory['age_months'] % 12 ?> ay
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($memory['location'])): ?>
                                <span class="location-badge">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($memory['location']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="memory-letter">
                            <div class="letter-header">
                                <i class="fas fa-envelope-open-text"></i>
                                Sevgili Kızım,
                            </div>
                            <div class="letter-body">
                                <?= nl2br(htmlspecialchars($memory['memory_note'])) ?>
                            </div>
                            <div class="letter-footer">
                                <i class="fas fa-heart"></i> Seni çok seviyoruz
                            </div>
                        </div>
                        
                        <?php if ($memory['milestones']): ?>
                            <div class="memory-milestones">
                                <?php foreach (explode(', ', $memory['milestones']) as $milestone): ?>
                                    <span class="milestone-badge">
                                        <i class="fas fa-star"></i>
                                        <?= htmlspecialchars($milestone) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.memories-grid {
    display: grid;
    gap: 3rem;
}

.memory-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    display: grid;
    grid-template-columns: 400px 1fr;
    transition: transform 0.3s;
}

.memory-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.15);
}

.memory-image {
    position: relative;
    height: 300px;
}

.memory-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.memory-date {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
    color: white;
    padding: 1rem;
    font-size: 0.9rem;
}

.memory-content {
    padding: 2.5rem;
}

.memory-content h3 {
    color: var(--dark-color);
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.memory-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.location-badge {
    background: var(--light-color);
    color: var(--secondary-color);
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.memory-letter {
    background: #fff9f9;
    border: 2px dashed var(--primary-color);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    font-family: 'Georgia', serif;
}

.letter-header {
    font-size: 1.1rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
    font-style: italic;
}

.letter-header i {
    margin-right: 0.5rem;
}

.letter-body {
    color: #444;
    line-height: 1.8;
    margin-bottom: 1rem;
    font-size: 1.05rem;
}

.letter-footer {
    text-align: right;
    color: var(--primary-color);
    font-style: italic;
    font-size: 0.95rem;
}

.letter-footer i {
    margin-right: 0.5rem;
}

.memory-milestones {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .memory-card {
        grid-template-columns: 1fr;
    }
    
    .memory-image {
        height: 200px;
    }
    
    .memory-content {
        padding: 1.5rem;
    }
    
    .memory-letter {
        padding: 1.5rem;
    }
}

/* Özel animasyon */
@keyframes letterFloat {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}

.memory-card:hover .letter-header i {
    animation: letterFloat 2s ease-in-out infinite;
}
</style> 