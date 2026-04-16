<?php
require_once 'includes/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
requireLogin();
$user = getCurrentUser();
$supportNumber = getSupportNumber();
$pdo = getDB();

// Get search and category filters
$searchQuery = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

// Get product categories
try {
    $categories = $pdo->query("SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Category query error: " . $e->getMessage());
    $categories = [];
}

// Get products
try {
    $sql = "SELECT * FROM products WHERE is_active = 1";
    $params = [];
    if ($searchQuery !== '') {
        $sql .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ? OR CONCAT(name, ' ', description) LIKE ? )";
        $searchTerm = '%' . $searchQuery . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    if ($categoryFilter !== '') {
        $sql .= " AND category = ?";
        $params[] = $categoryFilter;
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    // Remove duplicate rows by product ID if the database contains duplicates
    if (!empty($products)) {
        $deduped = [];
        foreach ($products as $productRow) {
            $deduped[$productRow['id']] = $productRow;
        }
        $products = array_values($deduped);
    }
} catch (Exception $e) {
    error_log("Products query error: " . $e->getMessage());
    $products = [];
}

// Get wishlist IDs for current user
try {
    $wishlistStmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wishlistStmt->execute([$user['id']]);
    $wishlistIds = array_column($wishlistStmt->fetchAll(), 'product_id');
} catch (Exception $e) {
    error_log("Wishlist query error: " . $e->getMessage());
    $wishlistIds = [];
}

// Get unread broadcasts
try {
    $broadcastStmt = $pdo->prepare("
      SELECT b.* FROM broadcasts b 
      WHERE b.is_active = 1 
      AND b.id NOT IN (SELECT broadcast_id FROM broadcast_reads WHERE user_id = ?)
      ORDER BY b.created_at DESC LIMIT 1
    ");
    $broadcastStmt->execute([$user['id']]);
    $broadcast = $broadcastStmt->fetch();
} catch (Exception $e) {
    error_log("Broadcast query error: " . $e->getMessage());
    $broadcast = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Shop - Fast Shopsy</title>
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
}

body {
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  width: 100%;
  max-width: 100vw;
  overflow-x: hidden;
  background: var(--bg);
  color: var(--text);
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

/* HEADER */
header {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(205, 204, 207, 0.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(46, 79, 65, 0.1);
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.logo {
  font-family: 'Playfair Display', serif;
  background: linear-gradient(120deg, #000, #2e4f41, #fcd34d);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-size: 1.4rem;
  font-weight: 700;
  white-space: nowrap;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(46, 79, 65, 0.08);
  border: 1px solid rgba(46, 79, 65, 0.15);
  border-radius: 30px;
  padding: 6px 14px;
}

.user-info img {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--primary);
}

.user-info span {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text);
  max-width: 100px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.verified {
  color: #4fc3f7;
  font-size: 0.85rem;
}

.menu-btn {
  background: rgba(46, 79, 65, 0.08);
  border: 1px solid rgba(46, 79, 65, 0.15);
  border-radius: 50%;
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: none;
  color: var(--text);
  font-size: 1rem;
  position: relative;
  transition: all 0.2s;
}

.menu-btn:hover {
  background: rgba(46, 79, 65, 0.15);
}

/* DROPDOWN MENU */
.dropdown {
  position: absolute;
  top: 50px;
  right: 0;
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.15);
  border-radius: 16px;
  padding: 8px;
  min-width: 180px;
  backdrop-filter: blur(12px);
  box-shadow: 0 4px 20px rgba(46, 79, 65, 0.1);
  display: none;
  animation: dropIn 0.2s ease;
  z-index: 200;
}

@keyframes dropIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dropdown.show {
  display: block;
}

.dropdown a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 10px;
  color: var(--text);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 600;
  transition: all 0.2s;
}

.dropdown a:hover {
  background: rgba(46, 79, 65, 0.1);
  color: var(--primary);
}

.dropdown a.danger {
  color: #c41a1a;
}

.dropdown a.danger:hover {
  background: rgba(196, 26, 26, 0.1);
}

.dropdown hr {
  border: none;
  border-top: 1px solid rgba(46, 79, 65, 0.1);
  margin: 6px 0;
}

/* MAIN */
main {
  padding: 20px 14px 80px;
  max-width: 600px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.5rem;
  color: var(--text);
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-title span {
  font-size: 0.8rem;
  background: linear-gradient(120deg, var(--primary), var(--accent));
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
  padding: 3px 10px;
  border-radius: 20px;
  font-family: 'DM Sans', sans-serif;
  font-weight: 700;
}

.header-search {
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 30px;
  padding: 6px 8px;
  border: 1px solid rgba(46, 79, 65, 0.15);
  box-shadow: 0 4px 12px rgba(46, 79, 65, 0.05);
  transition: box-shadow 0.3s, border-color 0.3s;
  flex: 1;
  max-width: 480px;
  margin: 0 20px;
}

.header-search:hover {
  box-shadow: 0 6px 16px rgba(46, 79, 65, 0.1);
  border-color: rgba(46, 79, 65, 0.3);
}

.header-search select {
  background: transparent;
  border: none;
  padding: 6px 10px 6px 14px;
  font-size: 0.85rem;
  font-family: inherit;
  font-weight: 600;
  color: var(--text-light);
  outline: none;
  cursor: none;
  max-width: 140px;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
  background-size: 8px auto;
  padding-right: 24px;
}

.header-search input {
  flex: 1;
  border: none;
  padding: 6px 12px;
  background: transparent;
  outline: none;
  font-size: 0.95rem;
  font-family: inherit;
  color: var(--text);
  min-width: 0;
}

.header-search input::placeholder {
  color: #aaa;
}

.header-search button {
  background: linear-gradient(120deg, var(--primary), var(--accent));
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: none;
  transition: transform 0.2s, box-shadow 0.2s;
  flex-shrink: 0;
  font-size: 0.9rem;
}

.header-search button:hover {
  transform: scale(1.05) rotate(5deg);
  box-shadow: 0 4px 10px rgba(46, 79, 65, 0.2);
}

.filter-note {
  margin-bottom: 16px;
  color: var(--primary);
  font-size: 0.95rem;
  font-weight: 600;
}

/* PRODUCT GRID */
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.product-card {
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.1);
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.08);
  cursor: none;
  transition: transform 0.3s, box-shadow 0.3s;
  animation: cardIn 0.5s ease both;
  text-decoration: none;
  color: var(--text);
  display: block;
  position: relative;
}

.product-card:hover {
  transform: translateY(-6px) scale(1.02);
  box-shadow: 0 8px 24px rgba(46, 79, 65, 0.15);
}

@keyframes cardIn {
  from {
    opacity: 0;
    transform: translateY(24px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.product-img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  background: #f5f5f5;
  transition: transform 0.4s;
}

.product-card:hover .product-img {
  transform: scale(1.05);
}

.product-info {
  padding: 10px 12px 12px;
}

.product-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 6px;
}

.price-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.price-new {
  font-size: 1rem;
  font-weight: 700;
  color: var(--primary);
}

.price-old {
  font-size: 0.8rem;
  color: #999;
  text-decoration: line-through;
}

.heart-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  background: rgba(255, 255, 255, 0.8);
  border: none;
  border-radius: 50%;
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: none;
  transition: all 0.2s;
  backdrop-filter: blur(6px);
  color: var(--text-light);
  font-size: 1rem;
}

.heart-btn.active,
.heart-btn:hover {
  color: var(--primary);
  background: rgba(46, 79, 65, 0.1);
}

/* BROADCAST MODAL */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  backdrop-filter: blur(4px);
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal {
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.15);
  border-radius: 20px;
  padding: 24px;
  max-width: 380px;
  width: 100%;
  box-shadow: 0 8px 32px rgba(46, 79, 65, 0.15);
  animation: slideUp 0.4s ease;
  text-align: center;
}

@keyframes slideUp {
  from {
    transform: translateY(30px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  margin: 0 auto 16px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  color: #fff;
}

.modal h3 {
  font-family: 'Playfair Display', serif;
  font-size: 1.4rem;
  color: var(--text);
  margin-bottom: 12px;
}

.modal p {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 24px;
}

.modal .btn-close {
  background: linear-gradient(120deg, var(--primary), var(--accent));
  color: #fff;
  border: none;
  border-radius: 40px;
  padding: 12px 32px;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: none;
  transition: transform 0.2s;
}

.modal .btn-close:hover {
  transform: scale(1.05);
}

/* EMPTY STATE */
.empty {
  text-align: center;
  padding: 60px 20px;
  color: var(--text-light);
}

.empty i {
  font-size: 3rem;
  margin-bottom: 12px;
  display: block;
  color: var(--primary);
}

@media (max-width: 900px) {
  header {
    padding: 10px 14px;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: space-between;
  }
  .header-search {
    order: 3;
    width: 100%;
    margin: 6px 0 0;
    max-width: none;
  }
  .back-btn { width: 34px; height: 34px; }
  header h1 { font-size: 1rem; }
  .section-title { font-size: 1.3rem; }
  .user-info { flex-wrap: wrap; justify-content: center; gap: 6px; }
  .user-info span { max-width: 120px; }
  .menu-btn { width: 36px; height: 36px; }
  .grid { grid-template-columns: 1fr; gap: 16px; }
  main { max-width: 100%; padding: 16px; }
}

@media (max-width: 600px) {
  body { font-size: 15px; }
  header { padding: 10px 12px; }
  .section-title { font-size: 1.15rem; }
  .product-card { border-radius: 16px; }
  .product-info { padding: 10px; }
  .product-name { font-size: 0.95rem; }
  .price-new { font-size: 0.95rem; }
  .heart-btn { width: 32px; height: 32px; top: 8px; right: 8px; }
  .modal { padding: 20px; }
  .empty { padding: 40px 14px; }
}

.dropdown hr {
  border: none;
  border-top: 1px solid rgba(46, 79, 65, 0.1);
  margin: 6px 0;
}

.modal .btn-close:hover { transform: scale(1.05); }
/* EMPTY STATE */
.empty { text-align: center; padding: 60px 20px; color: var(--text-light); }
.empty i { font-size: 3rem; margin-bottom: 12px; display: block; }

@media (max-width: 380px) {
  .grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="cursor-dot"></div>
<div class="cursor-ring"></div>

<header>
  <div class="logo"><i class="fas fa-shopping-bag"></i> Fast Shopsy</div>

  <form method="GET" class="header-search" action="shop.php">
    <select name="category">
      <option value="">All Categories</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= htmlspecialchars($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
      <?php endforeach; ?>
    </select>
    <div style="width: 1px; height: 24px; background: rgba(0,0,0,0.1); margin: 0 4px;"></div>
    <input type="text" name="search" placeholder="Search for products..." value="<?= htmlspecialchars($searchQuery) ?>">
    <button type="submit" title="Search"><i class="fas fa-search"></i></button>
  </form>

  <div class="header-right">
    <div class="user-info">
      <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="avatar" onerror="this.src='assets/male.png'">
      <span><?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></span>
      <i class="fas fa-circle-check verified"></i>
    </div>
    <div class="menu-btn" onclick="toggleMenu(this)">
      <i class="fas fa-ellipsis-v"></i>
      <div class="dropdown" id="menuDropdown">
        <a href="settings.php"><i class="fas fa-gear"></i> Settings</a>
        <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
        <a href="wishlist.php"><i class="fas fa-heart"></i> Wish List</a>
        <a href="javascript:void(0)" onclick="contactSupport()"><i class="fab fa-whatsapp"></i> Support</a>
        <hr>
        <a href="logout.php" class="danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>
  </div>
</header>

<main>
  <?php if ($searchQuery || $categoryFilter): ?>
    <div class="section-title">
      Search Results <span><i class="fas fa-search"></i> Found <?= count($products) ?></span>
    </div>
    <div class="filter-note">
      Showing <?= count($products) ?> result<?= count($products) === 1 ? '' : 's' ?>
      <?= $searchQuery ? ' for "' . htmlspecialchars($searchQuery) . '"' : '' ?>
      <?= $categoryFilter ? ($searchQuery ? ' in "' : ' in "') . htmlspecialchars($categoryFilter) . '"' : '' ?>
    </div>
  <?php else: ?>
    <div class="section-title">
      New Arrivals <span>✨ Fresh</span>
    </div>
  <?php endif; ?>

  <?php if (empty($products)): ?>
    <div class="empty">
      <i class="fas fa-shirt"></i>
      <p><strong>No products available yet.</strong></p>
      <p style="font-size:0.9rem;margin-top:10px;color:#666">Admin: Please add products from the admin dashboard.</p>
      <p style="font-size:0.85rem;margin-top:5px;color:#999">If you imported the database, products should appear here.</p>
    </div>
  <?php else: ?>
  <div class="grid">
    <?php
    $placeholder = APP_URL . '/assets/images/placeholder.svg';
    foreach ($products as $i => $p):
      $images = json_decode($p['images'], true);
      if (!is_array($images)) {
          $rawImages = trim($p['images'] ?? '');
          $images = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $rawImages)));
      }
      $images = array_values(array_filter($images));
      $img = $images[0] ?? $placeholder;
      $fallbackImg = $images[1] ?? $placeholder;
      $inWishlist = in_array($p['id'], $wishlistIds);
    ?>
    <a href="product.php?id=<?= $p['id'] ?>" class="product-card" style="animation-delay:<?= $i * 0.07 ?>s">
      <img class="product-img" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" data-fallback="<?= htmlspecialchars($fallbackImg) ?>" onerror="this.onerror=null;this.src=this.dataset.fallback||'<?= htmlspecialchars($placeholder) ?>';">
      <button class="heart-btn <?= $inWishlist ? 'active' : '' ?>" 
        onclick="toggleWishlist(event, <?= $p['id'] ?>, this)"
        title="Add to wishlist">
        <i class="fa<?= $inWishlist ? 's' : 'r' ?> fa-heart"></i>
      </button>
      <div class="product-info">
        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="price-row">
          <span class="price-new">₹<?= number_format($p['price']) ?></span>
          <?php if ($p['old_price']): ?>
            <span class="price-old">₹<?= number_format($p['old_price']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<!-- Broadcast Modal -->
<?php if ($broadcast): ?>
<div class="modal-overlay" id="broadcastModal">
  <div class="modal">
    <div class="modal-icon"><i class="fas fa-bullhorn"></i></div>
    <h3><?= htmlspecialchars($broadcast['title']) ?></h3>
    <p><?= nl2br(htmlspecialchars($broadcast['message'])) ?></p>
    <button class="btn-close" onclick="closeBroadcast(<?= $broadcast['id'] ?>)">
      <i class="fas fa-check"></i> Got it!
    </button>
  </div>
</div>
<?php endif; ?>

<script>
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
  
  const interactiveElements = document.querySelectorAll('a, button, .product-card, .heart-btn, .menu-btn');
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

function toggleMenu(btn){
  const d=document.getElementById('menuDropdown');
  d.classList.toggle('show');
  document.addEventListener('click',function handler(e){
    if(!btn.contains(e.target)){d.classList.remove('show');document.removeEventListener('click',handler)}
  });
}

function toggleWishlist(e,productId,btn){
  e.preventDefault();e.stopPropagation();
  fetch('api/wishlist.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({product_id:productId})
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.success){
      btn.classList.toggle('active');
      const icon=btn.querySelector('i');
      icon.className=data.action==='added'?'fas fa-heart':'far fa-heart';
    }
  });
}

function handleProductImgError(img){
  const fallback = img.dataset.fallback || '<?= htmlspecialchars(APP_URL . '/assets/images/placeholder.svg') ?>';
  if (!img.dataset.errorTried) {
    img.dataset.errorTried = '1';
    img.src = fallback;
  } else {
    img.src = '<?= htmlspecialchars(APP_URL . '/assets/images/placeholder.svg') ?>';
  }
}

function closeBroadcast(broadcastId){
  fetch('api/broadcast_read.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({broadcast_id:broadcastId})
  });
  document.getElementById('broadcastModal').style.animation='fadeOut .3s ease forwards';
  setTimeout(()=>document.getElementById('broadcastModal').remove(),300);
}

function contactSupport(){
  window.open('https://wa.me/<?= $supportNumber ?>?text=Hi! I need help with my order.','_blank');
}

const s=document.createElement('style');
s.textContent='@keyframes fadeOut{to{opacity:0}}';
document.head.appendChild(s);
</script>
</body>
</html>
