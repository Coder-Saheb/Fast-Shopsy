<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, name, description, price, old_price, images, category FROM products WHERE is_active = 1 ORDER BY created_at DESC");
    $products = [];

    while ($row = $stmt->fetch()) {
        $images = json_decode($row['images'], true);
        if (!is_array($images)) {
            $images = [];
        }
        $primaryImage = $images[0] ?? 'assets/images/placeholder.svg';
        $secondaryImage = $images[1] ?? $primaryImage;

        $products[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'price' => '₹' . number_format($row['price'], 0),
            'old' => $row['old_price'] ? '₹' . number_format($row['old_price'], 0) : '',
            'img' => $primaryImage,
            'pairImg' => $secondaryImage,
            'desc' => $row['description'] ?: $row['name'],
            'tags' => array_values(array_filter([$row['category'], 'Premium', 'New'])),
        ];
    }

    echo json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
