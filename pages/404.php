<div class="container">
    <div class="error-page">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1>404</h1>
        <h2>Sayfa Bulunamadı</h2>
        <p>Aradığınız sayfa bulunamadı veya taşınmış olabilir.</p>
        <a href="?page=home" class="btn btn-primary">
            <i class="fas fa-home"></i> Ana Sayfaya Dön
        </a>
    </div>
</div>

<style>
.error-page {
    text-align: center;
    padding: 4rem 2rem;
    min-height: 60vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.error-icon {
    font-size: 5rem;
    color: var(--primary-color);
    margin-bottom: 2rem;
}

.error-page h1 {
    font-size: 6rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
    font-weight: bold;
}

.error-page h2 {
    color: var(--dark-color);
    margin-bottom: 1rem;
}

.error-page p {
    color: #666;
    margin-bottom: 2rem;
    font-size: 1.1rem;
}
</style> 