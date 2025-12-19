<?php echo password_hash('admin123', PASSWORD_DEFAULT);
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { header('Location: '.url('login.php')); exit; }


?>