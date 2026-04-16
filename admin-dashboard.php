<?php
require_once '../includes/config.php';
requireAdminLogin();
$pdo = getDB();

// Handle POST actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_product') {
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $oldPrice = floatval($_POST['old_price'] ?? 0);
        $category = sanitize($_POST['category'] ?? '');
        $imagesRaw = $_POST['images'] ?? '';
        $imagesArr = array_filter(array_map('trim', explode("\n", $imagesRaw)));
        $imagesJson = json_encode(array_values($imagesArr));
        
        if (!$name || $price <= 0) {
            $msg = 'error:Product name and valid price are required.';
        } elseif ($oldPrice > 0 && $oldPrice <= $price) {
            $msg = 'error:Original price must be higher than selling price.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, old_price, category, images) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name, $desc, $price, $oldPrice ?: null, $category, $imagesJson]);
            $msg = 'success:Product listed successfully!';
        }
    }
    
    elseif ($action === 'update_order_status') {
        $orderId = intval($_POST['order_id'] ?? 0);
        $status = sanitize($_POST['status'] ?? '');
        $validStatuses = ['Order Placed','Packed','Shipped','Out For Delivery','Delivered'];
        if ($orderId && in_array($status, $validStatuses)) {
            $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status, $orderId]);
            $msg = 'success:Order status updated!';
        }
    }
    
    elseif ($action === 'send_broadcast') {
        $title = sanitize($_POST['title'] ?? '');
        $message = sanitize($_POST['message'] ?? '');
        if ($title && $message) {
            $pdo->prepare("INSERT INTO broadcasts (title, message) VALUES (?,?)")->execute([$title, $message]);
            $msg = 'success:Broadcast sent to all users!';
        }
    }
    
    elseif ($action === 'update_support') {
        $number = preg_replace('/[^0-9]/', '', $_POST['whatsapp_number'] ?? '');
        if ($number) {
            $stmt = $pdo->prepare("UPDATE support_settings SET whatsapp_number = ? LIMIT 1");
            $stmt->execute([$number]);
            $msg = 'success:Support number updated!';
        }
    }
    
    elseif ($action === 'delete_product') {
        $pid = intval($_POST['product_id'] ?? 0);
        if ($pid) {
            $pdo->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$pid]);
            $msg = 'success:Product removed.';
        }
    }
}

// Stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Recent orders
$orders = $pdo->query("
  SELECT o.*, u.full_name as customer, u.phone as cust_phone, p.name as product_name
  FROM orders o
  JOIN users u ON o.user_id = u.id
  JOIN products p ON o.product_id = p.id
  ORDER BY o.created_at DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Pass orders data to JavaScript for Modal popup
$ordersJson = json_encode($orders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// All products
$products = $pdo->query("SELECT * FROM products WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();

// All users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// Support settings
$support = $pdo->query("SELECT * FROM support_settings LIMIT 1")->fetch();

$msgType = '';
$msgText = '';
if ($msg) {
    [$msgType, $msgText] = explode(':', $msg, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Admin Dashboard - Fast Shopsy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  cursor: none;
}

:root {
  --primary: #2e4f41;
  --accent: #fcd34d;
  --bg: #cdcccf;
  --text: #111;
  --text-light: #666;
  --sidebar-w: 240px;
}

body {
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  width: 100%;
  max-width: 100vw;
  overflow-x: hidden;
  background: var(--bg);
  color: var(--text);
  display: flex;
}

.cursor-dot,
.cursor-ring {
  pointer-events: none;
  position: fixed;
  top: 0;
  left: 0;
  z-index: 9999;
  border-radius: 50%;
  opacity: 0;
}

.cursor-dot {
  width: 8px;
  height: 8px;
  background: var(--accent);
  box-shadow: 0 0 8px var(--accent);
}

.cursor-ring {
  width: 34px;
  height: 34px;
  border: 1.5px solid rgba(252, 211, 77, 0.7);
  background: rgba(252, 211, 77, 0.05);
  backdrop-filter: blur(2px);
  transition: width 0.2s, height 0.2s;
}

/* SIDEBAR */
.sidebar {
  width: var(--sidebar-w);
  min-height: 100vh;
  background: #fff;
  border-right: 1px solid rgba(46, 79, 65, 0.1);
  position: fixed;
  left: 0;
  top: 0;
  z-index: 200;
  transition: transform 0.3s ease;
  display: flex;
  flex-direction: column;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.05);
}

.sidebar.closed {
  transform: translateX(-100%);
}

.sidebar-logo {
  padding: 24px 20px 16px;
  font-family: 'Playfair Display', serif;
  font-size: 1.3rem;
  background: linear-gradient(120deg, #000, var(--primary), var(--accent));
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
  border-bottom: 1px solid rgba(46, 79, 65, 0.1);
}

.sidebar-logo p {
  font-family: 'DM Sans', sans-serif;
  font-size: 0.7rem;
  color: var(--text-light);
  -webkit-text-fill-color: var(--text-light);
  margin-top: 2px;
}

.sidebar-close {
  position: absolute;
  top: 16px;
  right: 14px;
  background: rgba(46, 79, 65, 0.1);
  border: none;
  color: var(--text);
  width: 28px;
  height: 28px;
  border-radius: 50%;
  cursor: none;
  display: none;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}

nav {
  flex: 1;
  padding: 12px 0;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  color: var(--text-light);
  cursor: none;
  transition: all 0.2s;
  font-size: 0.9rem;
  font-weight: 600;
  border-left: 3px solid transparent;
}

.nav-item:hover {
  color: var(--text);
  background: rgba(46, 79, 65, 0.05);
}

.nav-item.active {
  color: var(--primary);
  background: rgba(46, 79, 65, 0.1);
  border-left-color: var(--primary);
}

.nav-item i {
  width: 18px;
  text-align: center;
  font-size: 0.95rem;
}

.nav-item.danger {
  color: #c41a1a;
}

.nav-item.danger:hover {
  color: #a01616;
  background: rgba(196, 26, 26, 0.05);
}

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  z-index: 199;
  display: none;
  backdrop-filter: blur(4px);
}

/* MAIN */
.main {
  flex: 1;
  margin-left: var(--sidebar-w);
  min-height: 100vh;
  transition: margin 0.3s;
}

.main.expanded {
  margin-left: 0;
}

/* TOP BAR */
.topbar {
  background: #fff;
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(46, 79, 65, 0.1);
  padding: 14px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.05);
}

.menu-toggle {
  background: rgba(46, 79, 65, 0.1);
  border: none;
  color: var(--text);
  width: 38px;
  height: 38px;
  border-radius: 10px;
  cursor: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  transition: all 0.2s;
}

.menu-toggle:hover {
  background: rgba(46, 79, 65, 0.2);
}

.topbar-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.1rem;
  color: var(--text);
}

.topbar-admin {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-light);
  font-size: 0.85rem;
}

/* CONTENT */
.content {
  padding: 20px;
  max-width: 1100px;
  margin: 0 auto;
}

.section {
  display: none;
}

.section.active {
  display: block;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.section-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.4rem;
  color: var(--text);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

/* STATS */
.stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  margin-bottom: 24px;
}

.stat {
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.1);
  border-radius: 18px;
  padding: 20px;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.05);
  text-align: center;
  transition: transform 0.2s;
}

.stat:hover {
  transform: translateY(-4px);
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 14px;
  margin: 0 auto 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
}

.icon-pink {
  background: linear-gradient(135deg, rgba(46, 79, 65, 0.2), rgba(252, 211, 77, 0.2));
}

.icon-blue {
  background: linear-gradient(135deg, rgba(100, 150, 255, 0.2), rgba(102, 126, 234, 0.2));
}

.icon-green {
  background: linear-gradient(135deg, rgba(80, 200, 120, 0.2), rgba(46, 79, 65, 0.2));
}

.stat-num {
  font-size: 1.8rem;
  font-weight: 700;
  background: linear-gradient(120deg, var(--primary), var(--accent));
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
}

.stat-label {
  color: var(--text-light);
  font-size: 0.8rem;
  margin-top: 2px;
}

/* TABLE */
.table-wrap {
  overflow-x: auto;
  border-radius: 16px;
  border: 1px solid rgba(46, 79, 65, 0.1);
  background: #fff;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 600px;
}

th {
  background: rgba(46, 79, 65, 0.05);
  padding: 12px 14px;
  text-align: left;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-light);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
  border-bottom: 1px solid rgba(46, 79, 65, 0.1);
}

td {
  padding: 12px 14px;
  border-top: 1px solid rgba(46, 79, 65, 0.05);
  font-size: 0.88rem;
  color: var(--text);
  vertical-align: middle;
}

tr:hover td {
  background: rgba(46, 79, 65, 0.02);
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
}

.badge-placed {
  background: rgba(46, 79, 65, 0.2);
  color: var(--primary);
}

.badge-packed {
  background: rgba(252, 211, 77, 0.2);
  color: #b8860b;
}

.badge-shipped {
  background: rgba(100, 200, 255, 0.2);
  color: #0066cc;
}

.badge-out {
  background: rgba(255, 150, 50, 0.2);
  color: #cc6600;
}

.badge-delivered {
  background: rgba(80, 200, 120, 0.2);
  color: #006400;
}

/* FORMS */
.form-card {
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.1);
  border-radius: 18px;
  padding: 24px;
  margin-bottom: 20px;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.05);
}

.form-card h3 {
  font-size: 1rem;
  color: var(--text);
  font-weight: 700;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.field {
  margin-bottom: 14px;
}

.field label {
  display: block;
  color: var(--text-light);
  font-size: 0.78rem;
  font-weight: 600;
  margin-bottom: 5px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.field input,
.field textarea,
.field select {
  width: 100%;
  padding: 11px 14px;
  background: #f5f5f5;
  border: 1px solid rgba(46, 79, 65, 0.15);
  border-radius: 10px;
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 0.9rem;
  outline: none;
  transition: all 0.3s;
}

.field input:focus,
.field textarea:focus,
.field select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(46, 79, 65, 0.1);
  background: #fff;
}

.field input::placeholder,
.field textarea::placeholder {
  color: #999;
}

.field textarea {
  resize: vertical;
  min-height: 80px;
}

.field select option {
  background: #fff;
  color: var(--text);
}

.grid2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.btn-action {
  padding: 11px 22px;
  border: none;
  border-radius: 40px;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: none;
  transition: all 0.3s;
  background: linear-gradient(120deg, var(--primary), var(--accent));
  color: #fff;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.15);
}

.btn-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(46, 79, 65, 0.2);
}

.btn-sm {
  padding: 6px 14px;
  font-size: 0.78rem;
  border-radius: 8px;
  border: none;
  cursor: none;
  background: linear-gradient(120deg, var(--primary), var(--accent));
  color: #000000;
  font-family: 'DM Sans', sans-serif;
  font-weight: 700;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.btn-sm:hover {
  opacity: 0.9;
}

.alert {
  padding: 12px 16px;
  border-radius: 10px;
  margin-bottom: 16px;
  font-size: 0.88rem;
  border: 1px solid;
}

.alert-s {
  background: #e8ffe8;
  border-color: #99ff99;
  color: #006400;
}

.alert-e {
  background: #ffe8e8;
  border-color: #ff9999;
  color: #c41a1a;
}

.status-select {
  background: #f5f5f5;
  border: 1px solid rgba(46, 79, 65, 0.15);
  color: var(--text);
  border-radius: 8px;
  padding: 6px 10px;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.82rem;
  outline: none;
  cursor: none;
}

.status-select option {
  background: #fff;
  color: var(--text);
}

/* MODAL STYLES */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  z-index: 999;
  display: none;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
  padding: 20px;
}

.modal {
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.1);
  border-radius: 16px;
  width: 100%;
  max-width: 500px;
  padding: 24px;
  box-shadow: 0 10px 40px rgba(46, 79, 65, 0.15);
  position: relative;
  animation: fadeIn 0.3s ease;
}

.modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  background: none;
  border: none;
  color: var(--text-light);
  font-size: 1.2rem;
  cursor: none;
  transition: 0.2s;
}

.modal-close:hover {
  color: var(--primary);
  transform: scale(1.1);
}

.modal-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.3rem;
  margin-bottom: 16px;
  border-bottom: 1px solid rgba(46, 79, 65, 0.1);
  padding-bottom: 10px;
  color: var(--text);
}

.detail-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
  font-size: 0.95rem;
  border-bottom: 1px dashed rgba(46, 79, 65, 0.1);
  padding-bottom: 8px;
}

.detail-label {
  color: var(--text-light);
  font-weight: 600;
  min-width: 100px;
}

.detail-val {
  color: var(--text);
  text-align: right;
  word-break: break-word;
  font-weight: 500;
}

/* MOBILE */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
  }
  .sidebar.open {
    transform: translateX(0);
  }
  .sidebar-close {
    display: flex;
  }
  .main {
    margin-left: 0;
  }
  .stats {
    grid-template-columns: 1fr 1fr;
  }
  .grid2 {
    grid-template-columns: 1fr;
  }
  .content {
    padding: 14px;
  }
  .detail-row {
    flex-direction: column;
    text-align: left;
  }
  .detail-val {
    text-align: left;
    margin-top: 4px;
  }
}

@media (max-width: 400px) {
  .stats {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>

<div class="cursor-dot"></div>
<div class="cursor-ring"></div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <i class="fas fa-shopping-bag"></i> Fast Shop
    <p>Admin Panel</p>
  </div>
  <button class="sidebar-close" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
  <nav>
    <div class="nav-item active" onclick="showSection('dashboard',this)"><i class="fas fa-chart-pie"></i> Dashboard</div>
    <div class="nav-item" onclick="showSection('products',this)"><i class="fas fa-shirt"></i> Products</div>
    <div class="nav-item" onclick="showSection('orders',this)"><i class="fas fa-box"></i> Orders</div>
    <div class="nav-item" onclick="showSection('users',this)"><i class="fas fa-users"></i> Users</div>
    <div class="nav-item" onclick="showSection('broadcast',this)"><i class="fas fa-bullhorn"></i> Broadcast</div>
    <div class="nav-item" onclick="showSection('support',this)"><i class="fab fa-whatsapp"></i> Support Settings</div>
    <div class="nav-item danger" onclick="adminLogout()"><i class="fas fa-sign-out-alt"></i> Logout</div>
  </nav>
</aside>

<!-- Main -->
<div class="main" id="mainArea">
  <div class="topbar">
    <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="topbar-title" id="pageTitle">Dashboard</div>
    <div class="topbar-admin"><i class="fas fa-shield-halved"></i> <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></div>
  </div>

  <div class="content">
    <?php if ($msgText): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 's' : 'e' ?>">
      <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
      <?= htmlspecialchars($msgText) ?>
    </div>
    <?php endif; ?>

    <!-- DASHBOARD -->
    <div class="section active" id="sec-dashboard">
      <div class="section-title"><i class="fas fa-chart-pie"></i> Dashboard</div>
      <div class="stats">
        <div class="stat">
          <div class="stat-icon icon-pink"><i class="fas fa-users" style="color:var(--pink)"></i></div>
          <div class="stat-num"><?= $totalUsers ?></div>
          <div class="stat-label">Total Users</div>
        </div>
        <div class="stat">
          <div class="stat-icon icon-blue"><i class="fas fa-shirt" style="color:var(--indigo)"></i></div>
          <div class="stat-num"><?= $totalProducts ?></div>
          <div class="stat-label">Products</div>
        </div>
        <div class="stat">
          <div class="stat-icon icon-green"><i class="fas fa-box" style="color:#80ffb0"></i></div>
          <div class="stat-num"><?= $totalOrders ?></div>
          <div class="stat-label">Orders</div>
        </div>
      </div>

      <div class="section-title" style="font-size:1.1rem;margin-bottom:14px"><i class="fas fa-clock"></i> Recent Orders</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Customer</th><th>Product</th><th>Total</th><th>Status</th><th>Date</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($orders, 0, 8) as $o):
              $bc = match($o['status']) {
                'Order Placed' => 'badge-placed','Packed' => 'badge-packed','Shipped' => 'badge-shipped',
                'Out For Delivery' => 'badge-out','Delivered' => 'badge-delivered',default => 'badge-placed'
              };
            ?>
            <tr>
              <td><?= htmlspecialchars($o['customer']) ?></td>
              <td><?= htmlspecialchars(substr($o['product_name'],0,20)) ?>...</td>
              <td>₹<?= number_format($o['total_price']) ?></td>
              <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($o['status']) ?></span></td>
              <td style="color:#111;font-size:.8rem"><?= date('d M', strtotime($o['created_at'])) ?></td>
              <td>
                <button type="button" class="btn-sm" style="background:var(--indigo);color:#000000;" onclick="viewOrder(<?= $o['id'] ?>)">
                  <i class="fas fa-eye"></i> View
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PRODUCTS -->
    <div class="section" id="sec-products">
      <div class="section-title"><i class="fas fa-shirt"></i> Products</div>
      
      <div class="form-card">
        <h3><i class="fas fa-plus-circle"></i> Add New Product</h3>
        <form method="POST">
          <input type="hidden" name="action" value="add_product">
          <div class="grid2">
            <div class="field"><label>Product Name *</label>
              <input type="text" name="name" placeholder="e.g. Floral Maxi Dress" required></div>
            <div class="field"><label>Category</label>
              <input type="text" name="category" placeholder="e.g. Dresses"></div>
          </div>
          <div class="field"><label>Description</label>
            <textarea name="description" placeholder="Product description..."></textarea></div>
          <div class="grid2">
            <div class="field"><label>Price (₹) *</label>
              <input type="number" name="price" placeholder="999" step="0.01" required></div>
            <div class="field"><label>Old Price (₹)</label>
              <input type="number" name="old_price" placeholder="1999" step="0.01"></div>
          </div>
          <div class="field"><label>Image URLs (one per line)</label>
            <textarea name="images" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg" style="min-height:90px"></textarea>
          </div>
          <button type="submit" class="btn-action"><i class="fas fa-plus"></i> List Product</button>
        </form>
      </div>

      <div class="section-title" style="font-size:1.1rem;margin-bottom:14px"><i class="fas fa-list"></i> All Products (<?= count($products) ?>)</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Price</th><th>Old Price</th><th>Category</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td>₹<?= number_format($p['price']) ?></td>
              <td><?= !empty($p['old_price']) ? '₹'.number_format($p['old_price']) : '-' ?></td>
              <td><?= htmlspecialchars($p['category']) ?></td>
              <td>
                <form method="POST" style="display:inline" onsubmit="return confirm('Remove this product?')">
                  <input type="hidden" name="action" value="delete_product">
                  <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                  <button class="btn-sm" style="background:rgba(255,80,80,0.3);color:#ff9999"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ORDERS -->
    <div class="section" id="sec-orders">
      <div class="section-title"><i class="fas fa-box"></i> Orders (<?= count($orders) ?>)</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Customer</th><th>Product</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o):
              $bc = match($o['status']) {
                'Order Placed' => 'badge-placed','Packed' => 'badge-packed','Shipped' => 'badge-shipped',
                'Out For Delivery' => 'badge-out','Delivered' => 'badge-delivered',default => 'badge-placed'
              };
            ?>
            <tr>
              <td>#<?= $o['id'] ?></td>
              <td>
                <?= htmlspecialchars($o['customer']) ?><br>
                <small style="color:rgba(255,255,255,0.4)"><i class="fas fa-phone"></i> <?= htmlspecialchars($o['cust_phone']) ?></small>
              </td>
              <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= htmlspecialchars($o['product_name']) ?>
              </td>
              <td>₹<?= number_format($o['total_price']) ?></td>
              <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($o['status']) ?></span></td>
              <td style="display:flex;gap:8px;align-items:center;">
                <!-- Full View Button -->
                <button type="button" class="btn-sm" style="background:var(--indigo);" onclick="viewOrder(<?= $o['id'] ?>)">
                  <i class="fas fa-eye"></i> Details
                </button>

                <!-- Update Status Form -->
                <form method="POST" style="display:flex;gap:6px;align-items:center;margin:0;">
                  <input type="hidden" name="action" value="update_order_status">
                  <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                  <select name="status" class="status-select">
                    <?php foreach (['Order Placed','Packed','Shipped','Out For Delivery','Delivered'] as $st): ?>
                    <option <?= $o['status']===$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn-sm" style="padding:6px 10px;" title="Update Status"><i class="fas fa-check"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- USERS -->
    <div class="section" id="sec-users">
      <div class="section-title"><i class="fas fa-users"></i> Users (<?= count($users) ?>)</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Gender</th><th>Provider</th><th>Joined</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['full_name']) ?></td>
              <td style="color:#111;font-weight:600">@<?= htmlspecialchars($u['username']) ?></td>
              <td style="color:#111;font-size:.85rem"><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <span class="badge" style="background:rgba(46,79,65,0.07);color:#111">
                  <i class="fas fa-<?= $u['gender']==='female'?'venus':'mars' ?>"></i> <?= ucfirst($u['gender']) ?>
                </span>
              </td>
              <td>
                <?php if ($u['auth_provider']==='google'): ?>
                <span class="badge" style="background:rgba(234,67,53,0.12);color:#a00"><i class="fab fa-google"></i> Google</span>
                <?php else: ?>
                <span class="badge" style="background:rgba(102,126,234,0.12);color:#2255cc"><i class="fas fa-key"></i> Email</span>
                <?php endif; ?>
              </td>
              <td style="color:#111;font-size:.8rem"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- BROADCAST -->
    <div class="section" id="sec-broadcast">
      <div class="section-title"><i class="fas fa-bullhorn"></i> Broadcast</div>
      <div class="form-card">
        <h3><i class="fas fa-paper-plane"></i> Send Message to All Users</h3>
        <form method="POST">
          <input type="hidden" name="action" value="send_broadcast">
          <div class="field"><label>Broadcast Title *</label>
            <input type="text" name="title" placeholder="e.g. 🔥 Mega Sale Alert!" required></div>
          <div class="field"><label>Message *</label>
            <textarea name="message" placeholder="Write your broadcast message here..." style="min-height:100px" required></textarea></div>
          <button type="submit" class="btn-action"><i class="fas fa-paper-plane"></i> Send Broadcast</button>
        </form>
      </div>
      <p style="color:rgba(255,255,255,0.4);font-size:.85rem;margin-top:8px">
        <i class="fas fa-info-circle"></i> Users will see a popup dialog the next time they visit the shop. Each user will see it only once.
      </p>
    </div>

    <!-- SUPPORT -->
    <div class="section" id="sec-support">
      <div class="section-title"><i class="fab fa-whatsapp"></i> Support Settings</div>
      <div class="form-card">
        <h3><i class="fas fa-headset"></i> WhatsApp Support Number</h3>
        <form method="POST">
          <input type="hidden" name="action" value="update_support">
          <div class="field"><label>WhatsApp Number (with country code)</label>
            <input type="text" name="whatsapp_number" 
              value="<?= htmlspecialchars($support['whatsapp_number'] ?? '917718570357') ?>"
              placeholder="917718570357 (91 = India)">
          </div>
          <p style="color:rgba(255,255,255,0.4);font-size:.82rem;margin-bottom:14px">
            Include country code without + sign. Example: 917718570357 for India (+91)
          </p>
          <button type="submit" class="btn-action"><i class="fas fa-save"></i> Save Number</button>
        </form>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ORDER DETAILS MODAL -->
<div class="modal-overlay" id="orderModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title"><i class="fas fa-receipt"></i> Order Details #<span id="m-id"></span></div>
    
    <div class="detail-row">
      <span class="detail-label">Customer Name:</span>
      <span class="detail-val" id="m-customer"></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Phone No:</span>
      <span class="detail-val" id="m-phone"></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Full Address:</span>
      <span class="detail-val" id="m-address"></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Product Name:</span>
      <span class="detail-val" id="m-product"></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Total Amount:</span>
      <span class="detail-val" id="m-price" style="color:var(--pink); font-size:1.1rem; font-weight:700;"></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Current Status:</span>
      <span class="detail-val" id="m-status"></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Order Date:</span>
      <span class="detail-val" id="m-date"></span>
    </div>
  </div>
</div>

<script>
// JSON string of all orders safely passed from PHP
const ordersData = <?= $ordersJson ?>;

function viewOrder(id) {
  const order = ordersData.find(o => o.id == id);
  if(!order) return;
  
  document.getElementById('m-id').innerText = order.id;
  document.getElementById('m-customer').innerText = order.customer;
  document.getElementById('m-phone').innerText = order.cust_phone || 'N/A';
  
  // Format Address (handle null values smoothly)
  let fullAddr = [order.address, order.city, order.pincode].filter(Boolean).join(', ');
  document.getElementById('m-address').innerText = fullAddr || 'Address not provided';
  
  document.getElementById('m-product').innerText = order.product_name;
  document.getElementById('m-price').innerText = '₹' + parseFloat(order.total_price).toLocaleString('en-IN');
  document.getElementById('m-status').innerText = order.status;
  
  // Format date readable
  let dateObj = new Date(order.created_at);
  document.getElementById('m-date').innerText = dateObj.toLocaleDateString('en-IN', {day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'});

  document.getElementById('orderModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('orderModal').style.display = 'none';
}

// Close Modal when clicked outside the box
document.getElementById('orderModal').addEventListener('click', function(e) {
  if(e.target === this) {
    closeModal();
  }
});


function showSection(id, el){
  document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  document.getElementById('sec-'+id).classList.add('active');
  if(el) el.classList.add('active');
  const titles={dashboard:'Dashboard',products:'Products',orders:'Orders',users:'Users',broadcast:'Broadcast',support:'Support Settings'};
  document.getElementById('pageTitle').textContent=titles[id]||id;
  if(window.innerWidth<768) closeSidebar();
}

function toggleSidebar(){
  const s=document.getElementById('sidebar');
  const o=document.getElementById('sidebarOverlay');
  if(window.innerWidth<768){
    s.classList.toggle('open');
    o.style.display=s.classList.contains('open')?'block':'none';
  } else {
    s.classList.toggle('closed');
    document.getElementById('mainArea').classList.toggle('expanded');
  }
}

function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').style.display='none';
}

function adminLogout(){
  if(confirm('Logout from admin panel?')) location.href='admin-logout.php';
}

// Show section from URL hash (Bug fixed with proper quotes in selector)
const hash = location.hash.replace('#','');
if(hash){
  const el = document.querySelector(`[onclick*='${hash}']`);
  if(el) showSection(hash, el);
}

// Custom Cursor
if (window.innerWidth > 900) {
  const dot = document.querySelector('.cursor-dot');
  const ring = document.querySelector('.cursor-ring');
  let mouseX = 0, mouseY = 0;
  let ringX = 0, ringY = 0;
  
  document.addEventListener('mousemove', (e) => {
    mouseX = e.clientX;
    mouseY = e.clientY;
    dot.style.transform = `translate3d(${mouseX - 4}px, ${mouseY - 4}px, 0)`;
  });
  
  function animateRing() {
    ringX += (mouseX - ringX) * 0.14;
    ringY += (mouseY - ringY) * 0.14;
    ring.style.transform = `translate3d(${ringX - 17}px, ${ringY - 17}px, 0)`;
    requestAnimationFrame(animateRing);
  }
  
  animateRing();
  
  const interactiveElements = document.querySelectorAll('a, button, .nav-item, .stat, input, textarea, select, .btn-sm, .status-select');
  interactiveElements.forEach(el => {
    el.addEventListener('mouseenter', () => {
      ring.style.width = '52px';
      ring.style.height = '52px';
      ring.style.border = '2px solid var(--accent)';
    });
    el.addEventListener('mouseleave', () => {
      ring.style.width = '34px';
      ring.style.height = '34px';
      ring.style.border = '1.5px solid rgba(252, 211, 77, 0.7)';
    });
  });
  
  dot.style.opacity = '1';
  ring.style.opacity = '1';
  document.body.style.cursor = 'none';
} else {
  document.querySelectorAll('.cursor-dot, .cursor-ring').forEach(el => el.remove());
  document.body.style.cursor = 'auto';
}
</script>
</body>
</html>