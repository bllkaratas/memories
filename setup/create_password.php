<?php
// Bu dosya güvenli şifre hash'i oluşturmak için kullanılır
// Kullanım: php create_password.php

echo "Güvenli Şifre Hash Oluşturucu\n";
echo "==============================\n\n";

echo "Şifrenizi girin: ";
$password = trim(fgets(STDIN));

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "\nOluşturulan hash:\n";
echo $hash . "\n\n";

echo "Bu hash'i veritabanındaki users tablosuna ekleyin:\n";
echo "UPDATE users SET password = '$hash' WHERE username = 'admin';\n\n";

// Örnek admin123 için hash
$defaultHash = password_hash('admin123', PASSWORD_DEFAULT);
echo "Varsayılan admin123 şifresi için hash:\n";
echo $defaultHash . "\n";
?> 