<?php
// JUTAR BAZAR BD admin: the app is admin/index.html; data goes through admin/api.php.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
readfile(__DIR__ . '/index.html');
