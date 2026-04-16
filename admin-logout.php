<?php
require_once '../includes/config.php';
session_destroy();
header('Location: admin-login.php');
exit;
?>
