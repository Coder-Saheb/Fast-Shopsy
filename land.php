<?php
require_once 'includes/config.php';

$pdo = getDB();
$newpass = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE username = 'admin'");
if ($stmt->execute([$newpass])) {
    echo "Password updated successfully!";
} else {
    echo "Failed!";
}
?>