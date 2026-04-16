<?php
require_once 'includes/config.php';

$pdo = getDB();

// Check what's currently in the database
echo "<h3>Current Database Value:</h3>";
$current = $pdo->query("SELECT * FROM support_settings")->fetchAll();
foreach ($current as $row) {
    echo "ID: " . $row['id'] . "<br>";
    echo "WhatsApp Number: " . $row['whatsapp_number'] . "<br>";
    echo "Updated At: " . $row['updated_at'] . "<br><br>";
}

// Update all rows to be safe
echo "<h3>Updating all rows...</h3>";
$stmt = $pdo->prepare("UPDATE support_settings SET whatsapp_number = '917718570357'");
$result = $stmt->execute();

if ($result) {
    echo "✅ Updated successfully!<br><br>";
    
    // Verify
    echo "<h3>After Update:</h3>";
    $verify = $pdo->query("SELECT * FROM support_settings")->fetchAll();
    foreach ($verify as $row) {
        echo "ID: " . $row['id'] . "<br>";
        echo "WhatsApp Number: " . $row['whatsapp_number'] . "<br>";
    }
} else {
    echo "❌ Update failed";
}
?>
