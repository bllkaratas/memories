<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        // Kullanıcıyı veritabanından kontrol et
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Giriş başarılı
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Session'ı kaydedelim
            session_write_close();
            
            // Tam URL ile yönlendirme - mutlak URL kullanıyoruz
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $redirectUrl = "$protocol://$host/?page=home";
            
            header("Location: $redirectUrl", true, 302);
            echo "<script>window.location.href='$redirectUrl';</script>";
            exit;
        } else {
            $error = 'Kullanıcı adı veya şifre hatalı!';
        }
    } catch(PDOException $e) {
        // Veritabanı bağlantısı yoksa basit kontrol
        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = $username;
            
            // Session'ı kaydedelim
            session_write_close();
            
            // Tam URL ile yönlendirme - mutlak URL kullanıyoruz
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $redirectUrl = "$protocol://$host/?page=home";
            
            header("Location: $redirectUrl", true, 302);
            echo "<script>window.location.href='$redirectUrl';</script>";
            exit;
        } else {
            $error = 'Giriş yapılamadı!';
        }
    }
}
?>

<style>
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden;
    min-height: 100vh;
    width: 100%;
}

.login-container {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    background: var(--gradient);
    margin: 0;
    padding: 0;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
}

.login-card {
    background: white;
    padding: 3rem;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    width: 100%;
    max-width: 400px;
}

.alert {
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 10px;
    text-align: center;
}

.alert-danger {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ef5350;
}
</style>

<div class="login-container">
    <div class="login-card fade-in">
        <div class="login-header">
            <h1><i class="fas fa-heart"></i> Hoş Geldiniz</h1>
            <p>Kızımın hatıra albümüne giriş yapın</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username" class="form-label">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Şifre</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-sign-in-alt"></i> Giriş Yap
            </button>
        </form>
    </div>
</div> 