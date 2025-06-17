<?php
// Oturumu sonlandır
session_destroy();
 
// Ana sayfaya yönlendir
header('Location: ?page=login');
exit;
?> 