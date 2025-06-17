<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-heart"></i>
        <h3>Hatıra Albümüm</h3>
    </div>
    
    <nav class="sidebar-nav">
        <a href="?page=home" class="nav-item <?= $page == 'home' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> <span>Ana Sayfa</span>
        </a>
        
        <a href="?page=upload" class="nav-item <?= $page == 'upload' ? 'active' : '' ?>">
            <i class="fas fa-cloud-upload-alt"></i> <span>Fotoğraf Yükle</span>
        </a>
        
        <a href="?page=timeline" class="nav-item <?= $page == 'timeline' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> <span>Zaman Çizelgesi</span>
        </a>
        
        <a href="?page=memories" class="nav-item <?= $page == 'memories' ? 'active' : '' ?>">
            <i class="fas fa-book-open"></i> <span>Anılar</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="?page=export" class="nav-item <?= $page == 'export' ? 'active' : '' ?>">
            <i class="fas fa-file-export"></i> <span>Dışa Aktar</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="?page=logout" class="nav-item logout">
            <i class="fas fa-sign-out-alt"></i> <span>Çıkış</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?= $_SESSION['username'] ?? 'Kullanıcı' ?></span>
        </div>
    </div>
</div> 