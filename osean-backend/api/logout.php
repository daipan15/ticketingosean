<?php
// =============================================
// OSEAN - logout.php
// =============================================
require_once __DIR__ . '/../config.php';

session_destroy();
setcookie(session_name(), '', time() - 3600, '/');

send_success([], 'Logout berhasil. Sampai jumpa!');
