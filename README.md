# 💝 Kızımın Hatıra Albümü

Küçük kızınızın tüm güzel anılarını dijital ortamda saklayabileceğiniz, büyüdüğünde ona hediye edebileceğiniz özel bir hatıra albümü projesi.

## 🌟 Özellikler

- **📸 Fotoğraf Yükleme**: Sürükle-bırak desteği ile kolay fotoğraf yükleme
- **📅 Zaman Çizelgesi**: Kronolojik sırada anıları görüntüleme
- **💌 Özel Anı Notları**: Her fotoğrafa kızınıza özel mesajlar ekleyebilme
- **🎉 Özel Günler**: İlk adım, ilk kelime gibi özel anları işaretleme
- **🔒 Güvenli Giriş**: Sadece ebeveynlerin erişebileceği güvenli sistem
- **📱 Responsive Tasarım**: Mobil uyumlu modern arayüz

## 🚀 Kurulum

### Gereksinimler
- PHP 7.4 veya üstü
- MySQL veritabanı
- Apache/Nginx web sunucusu

### Adım Adım Kurulum

1. **Dosyaları Yükleyin**
   ```bash
   # Projeyi web sunucunuza yükleyin
   ```

2. **Veritabanı Kurulumu**
   - phpMyAdmin veya MySQL konsolunu açın
   - `database/schema.sql` dosyasını içe aktarın
   - Veya SQL komutlarını manuel olarak çalıştırın

3. **Veritabanı Ayarları**
   - `config/database.php` dosyasını açın
   - Veritabanı bilgilerinizi güncelleyin:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'memories_db');
   define('DB_USER', 'kullanici_adi');
   define('DB_PASS', 'sifre');
   ```

4. **Klasör İzinleri**
   ```bash
   chmod 777 uploads/
   ```

5. **İlk Giriş**
   - Tarayıcınızdan projeyi açın
   - Varsayılan kullanıcı: `admin`
   - Varsayılan şifre: `admin123`
   - ⚠️ **ÖNEMLİ**: İlk girişten sonra şifrenizi değiştirin!

## 📝 Kullanım

### Fotoğraf Ekleme
1. "Fotoğraf Yükle" menüsüne tıklayın
2. Fotoğrafı sürükleyip bırakın veya seçin
3. Fotoğraf bilgilerini doldurun:
   - Başlık (örn: "İlk doğum günü")
   - Tarih
   - Konum
   - Açıklama
4. Özel anı notu ekleyin (kızınız büyüdüğünde okuyacağı mesaj)
5. Varsa özel günleri işaretleyin
6. "Anıyı Kaydet" butonuna tıklayın

### Zaman Çizelgesi
- Tüm fotoğraflar yıllara göre gruplandırılmış şekilde görüntülenir
- Her fotoğrafın yaş bilgisi otomatik hesaplanır
- Anı notları bu sayfada da görüntülenir

### Özel Anılar
- Sadece anı notu eklenmiş fotoğraflar bu bölümde görüntülenir
- Mektup formatında güzel bir sunum sağlar

## 🎨 Özelleştirme

### Renk Teması
`assets/css/style.css` dosyasında renkleri değiştirebilirsiniz:
```css
:root {
    --primary-color: #ff6b6b;     /* Ana renk */
    --secondary-color: #4ecdc4;    /* İkincil renk */
    --accent-color: #ffe66d;       /* Vurgu rengi */
}
```

### Doğum Tarihi Ayarı
`pages/upload.php` dosyasında kızınızın doğum tarihini güncelleyin:
```php
$birthDate = '2020-01-01'; // Kızınızın doğum tarihini buraya girin
```

### Özel Günler
Veritabanına yeni özel günler ekleyebilirsiniz:
```sql
INSERT INTO milestones (title, icon) VALUES 
('İlk Kar Tanesi', 'snowflake'),
('İlk Bisiklet', 'bicycle');
```

## 🔒 Güvenlik

- Tüm kullanıcı girişleri filtrelenir
- Dosya yüklemeleri kontrol edilir
- SQL injection koruması vardır
- XSS koruması uygulanmıştır

### Yeni Kullanıcı Ekleme
```php
// Şifreyi hashleyin
$hashedPassword = password_hash('yeni_sifre', PASSWORD_DEFAULT);

// Veritabanına ekleyin
INSERT INTO users (username, password) VALUES ('kullanici_adi', '$hashedPassword');
```

## 📱 Mobil Kullanım

Proje tamamen responsive tasarıma sahiptir. Telefonunuzdan da rahatlıkla:
- Fotoğraf yükleyebilir
- Anıları görüntüleyebilir
- Anı notları ekleyebilirsiniz

## 🎁 Hediye Olarak Sunma

Kızınız büyüdüğünde bu albümü ona hediye etmek için:

1. **Dijital Sunum**: Projeyi online tutun ve erişim bilgilerini verin
2. **Offline Sunum**: Tüm veriyi dışa aktarıp USB/DVD'ye kaydedin
3. **Kitap Haline Getirme**: Fotoğrafları ve notları basılı albüm yapın

## 🤝 Katkıda Bulunma

Bu proje açık kaynak değildir ancak önerilerinizi dinlemekten mutluluk duyarız.

## 📞 Destek

Kurulum veya kullanım sırasında sorun yaşarsanız:
- Veritabanı bağlantısını kontrol edin
- PHP error loglarını inceleyin
- Klasör izinlerini kontrol edin

## 💕 Son Söz

Bu proje, kızınızla aranızdaki bağı güçlendirmek ve ona ileride çok değerli olacak bir hediye bırakmak için tasarlandı. Her fotoğraf, her not, ona ne kadar değerli olduğunu gösterecek.

**Mutlu anılar biriktirmeniz dileğiyle! 🌸** 