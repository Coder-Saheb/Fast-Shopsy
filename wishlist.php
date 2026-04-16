<?php
require_once 'includes/config.php';
requireLogin();
$user = getCurrentUser();
$pdo = getDB();

$stmt = $pdo->prepare("
  SELECT p.*, w.id as wish_id
  FROM wishlist w
  JOIN products p ON w.product_id = p.id
  WHERE w.user_id = ?
  ORDER BY w.created_at DESC
");
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Wishlist - Fast Shopsy</title>
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

header {
  position: sticky;
  top: 0;
  z-index: 100;
  background: #fff;
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(46, 79, 65, 0.1);
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.05);
}

.back-btn {
  background: rgba(46, 79, 65, 0.1);
  border: none;
  border-radius: 50%;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text);
  text-decoration: none;
  cursor: none;
  transition: all 0.2s;
}

.back-btn:hover {
  background: rgba(46, 79, 65, 0.2);
}

header h1 {
  font-family: 'Playfair Display', serif;
  font-size: 1.1rem;
  background: linear-gradient(120deg, var(--primary), var(--accent));
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
}

main {
  padding: 20px 14px 60px;
  max-width: 600px;
  margin: 0 auto;
}

.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.wcard {
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.1);
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.05);
  animation: cardIn 0.4s ease both;
  position: relative;
  transition: transform 0.2s;
}

.wcard:hover {
  transform: translateY(-4px);
}

@keyframes cardIn {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.wcard-img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  display: block;
  text-decoration: none;
}

.wcard-info {
  padding: 10px 12px 12px;
}

.wcard-name {
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
  gap: 6px;
  justify-content: space-between;
}

.price-new {
  font-size: 0.95rem;
  font-weight: 700;
  background: linear-gradient(120deg, var(--primary), var(--accent));
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
}

.remove-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(196, 26, 26, 0.8);
  border: none;
  border-radius: 50%;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  cursor: none;
  backdrop-filter: blur(6px);
  font-size: 0.85rem;
  transition: all 0.2s;
}

.remove-btn:hover {
  background: rgba(196, 26, 26, 1);
}

.buy-btn {
  display: block;
  text-align: center;
  background: linear-gradient(120deg, var(--primary), var(--accent));
  color: #fff;
  text-decoration: none;
  padding: 8px;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 700;
  margin-top: 8px;
  transition: all 0.2s;
  cursor: none;
}

.buy-btn:hover {
  opacity: 0.9;
}

.empty {
  text-align: center;
  padding: 80px 20px;
  color: var(--text-light);
}

.empty i {
  font-size: 3rem;
  margin-bottom: 16px;
  display: block;
  color: rgba(46, 79, 65, 0.3);
}

.empty a {
  display: inline-block;
  margin-top: 16px;
  background: linear-gradient(120deg, var(--primary), var(--accent));
  color: #fff;
  padding: 12px 28px;
  border-radius: 12px;
  font-weight: 700;
  text-decoration: none;
  cursor: none;
}

@media (max-width: 380px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>
<div class="cursor-dot"></div>
<div class="cursor-ring"></div>
<header>
  <a href="shop.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
  <h1><i class="fas fa-heart"></i> My Wishlist</h1>
</header>
<main>
  <?php if (empty($items)): ?>
  <div class="empty">
    <i class="fas fa-heart-crack"></i>
    <p style="font-size:1.1rem;color:rgba(255,255,255,0.6);margin-bottom:8px">No items saved</p>
    <p>Tap the heart on any product to add it here.</p>
    <a href="shop.php" style="display:inline-block;margin-top:16px;background:linear-gradient(135deg,#ff6b9d,#c44dff);color:#fff;padding:12px 28px;border-radius:12px;font-weight:700">Browse Shop</a>
  </div>
  <?php else: ?>
  <div class="grid" id="wishGrid">
    <?php foreach ($items as $i => $item):
      $imgs = json_decode($item['images'], true);
      $img = $imgs[0] ?? '';
    ?>
    <div class="wcard" id="card-<?= $item['id'] ?>" style="animation-delay:<?= $i * 0.07 ?>s">
      <a href="product.php?id=<?= $item['id'] ?>">
        <img class="wcard-img" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
      </a>
      <button class="remove-btn" onclick="removeWish(<?= $item['id'] ?>)" title="Remove">
        <i class="fas fa-times"></i>
      </button>
      <div class="wcard-info">
        <div class="wcard-name"><?= htmlspecialchars($item['name']) ?></div>
        <div class="price-row">
          <span class="price-new">₹<?= number_format($item['price']) ?></span>
          <?php if ($item['old_price']): ?>
          <span style="font-size:.75rem;color:var(--text-light);text-decoration:line-through">₹<?= number_format($item['old_price']) ?></span>
          <?php endif; ?>
        </div>
        <a href="product.php?id=<?= $item['id'] ?>" class="buy-btn"><i class="fas fa-bolt"></i> Buy Now</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
<script>
function removeWish(productId){
  fetch('api/wishlist.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({product_id:productId})
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.success && data.action==='removed'){
      const card=document.getElementById('card-'+productId);
      card.style.transition='all .3s';
      card.style.opacity='0';card.style.transform='scale(0.8)';
      setTimeout(()=>{card.remove();
        if(!document.querySelectorAll('.wcard').length) location.reload();
      },300);
    }
  });
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
  
  const interactiveElements = document.querySelectorAll('a, button, .wcard');
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
