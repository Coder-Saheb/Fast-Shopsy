<?php
require_once 'includes/config.php';
requireLogin();
$user = getCurrentUser();
$pdo = getDB();

$productId = intval($_GET['id'] ?? 0);
if (!$productId) { header('Location: shop.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) { header('Location: shop.php'); exit; }

$images = json_decode($product['images'], true) ?: [];

// Suggested products
$suggested = $pdo->prepare("SELECT * FROM products WHERE id != ? AND is_active = 1 AND category = ? LIMIT 4");
$suggested->execute([$productId, $product['category']]);
$suggestions = $suggested->fetchAll();
if (count($suggestions) < 4) {
    $suggested2 = $pdo->prepare("SELECT * FROM products WHERE id != ? AND is_active = 1 LIMIT 4");
    $suggested2->execute([$productId]);
    $suggestions = $suggested2->fetchAll();
}

// Check wishlist
$wStmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
$wStmt->execute([$user['id'], $productId]);
$inWishlist = (bool)$wStmt->fetch();

// Handle order placement
$orderSuccess = '';
$orderError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $state = sanitize($_POST['state'] ?? '');
    $postal = sanitize($_POST['postal_code'] ?? '');
    
    if ($fullName && $phone && $address && $city && $state && $postal) {
        $orderStmt = $pdo->prepare("INSERT INTO orders (user_id,product_id,full_name,phone,address,city,state,postal_code,payment_method,total_price) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $orderStmt->execute([$user['id'],$productId,$fullName,$phone,$address,$city,$state,$postal,'Cash On Delivery',$product['price']]);
        $orderSuccess = 'Order placed successfully! We will contact you soon.';
    } else {
        $orderError = 'Please fill in all delivery details.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title><?= htmlspecialchars($product['name']) ?> - Fast Shopsy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--primary: #2e4f41;--accent: #fcd34d;--bg: #cdcccf;--text: #111;--text-light: #666}
body{font-family:'DM Sans',sans-serif;min-height:100vh;width:100%;max-width:100vw;overflow-x:hidden;background:var(--bg);color:var(--text)}
header{
  position:sticky;top:0;z-index:100;
  background:#fff;backdrop-filter:blur(12px);
  border-bottom:1px solid rgba(46, 79, 65, 0.1);
  padding:12px 16px;display:flex;align-items:center;gap:12px;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.05);
}
.back-btn{
  background:rgba(46, 79, 65, 0.1);border:none;border-radius:50%;
  width:36px;height:36px;display:flex;align-items:center;justify-content:center;
  color:var(--text);cursor:none;text-decoration:none;transition:all .2s;
}
.back-btn:hover{background:rgba(46, 79, 65, 0.2)}
header h1{font-family:'Playfair Display',serif;font-size:1.1rem;
  background:linear-gradient(120deg,var(--primary),var(--accent));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.product-page{max-width:1140px;margin:0 auto;padding:0 16px;display:grid;gap:28px}
/* IMAGE SLIDER */
.slider{
  position:relative;
  overflow:hidden;
  background:#f5f5f5;
  width:100%;
  max-height: 640px;
  min-height: 320px;
}
.slider-track{
  display:flex;
  transition:transform .4s ease;
  height:100%;
}
.slide{
  min-width:100%;
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}
.slider-btn{
  position:absolute;top:50%;transform:translateY(-50%);
  background:rgba(46, 79, 65, 0.8);border:none;color:#fff;
  width:36px;height:36px;border-radius:50%;cursor:none;
  display:flex;align-items:center;justify-content:center;
  backdrop-filter:blur(6px);z-index:2;font-size:.9rem;
  transition:all .2s;
}
.slider-btn:hover{background:rgba(46, 79, 65, 1)}
.slider-btn.prev{left:10px}
.slider-btn.next{right:10px}
.dots{
  position:absolute;bottom:10px;left:50%;transform:translateX(-50%);
  display:flex;gap:6px;
}
.dot{
  width:6px;height:6px;border-radius:50%;
  background:rgba(255,255,255,0.4);cursor:pointer;transition:all .2s;
}
.dot.active{background:#fff;width:18px;border-radius:3px}
/* CONTENT */
.content{padding:20px 0 120px;margin:0}
.product-header{
  background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);
  border-radius:20px;padding:20px;margin-bottom:16px;
  backdrop-filter:blur(12px);
}
.product-title{
  font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--text);
  margin-bottom:12px;line-height:1.3;
}
.price-section{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.price-main{
  font-size:1.8rem;font-weight:700;
  background:linear-gradient(120deg,var(--primary),var(--accent));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.price-old{font-size:1rem;color:var(--text-light);text-decoration:line-through}
.discount-badge{
  background:linear-gradient(120deg,rgba(46, 79, 65, 0.1),rgba(252, 211, 77, 0.1));
  border:1px solid rgba(46, 79, 65, 0.2);
  color:var(--primary);font-size:.8rem;font-weight:700;
  padding:4px 10px;border-radius:20px;
}
.product-desc{color:var(--text-light);line-height:1.7;font-size:.95rem}
/* ACTIONS */
.action-bar{
  display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;
}
.btn{
  flex:1;padding:14px;border:none;border-radius:14px;
  font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:700;
  cursor:none;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-buy{
  background:linear-gradient(120deg,var(--primary),var(--accent));
  color:#fff;box-shadow:0 4px 20px rgba(46, 79, 65, 0.15);
}
.btn-buy:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(46, 79, 65, 0.2)}
.btn-wish{
  background:rgba(46, 79, 65, 0.05);border:1px solid rgba(46, 79, 65, 0.15);color:var(--text-light);
  flex:0 0 auto;padding:14px 18px;
}
.btn-wish.active,.btn-wish:hover{background:rgba(46, 79, 65, 0.1);border-color:rgba(46, 79, 65, 0.2);color:var(--primary)}
/* CHECKOUT MODAL */
.overlay{
  position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:300;
  display:flex;align-items:flex-end;justify-content:center;
  backdrop-filter:blur(4px);
  visibility:hidden;opacity:0;transition:all .3s;
}
.overlay.show{visibility:visible;opacity:1}
.sheet{
  background:#fff;border:1px solid rgba(46, 79, 65, 0.1);
  border-radius:24px 24px 0 0;padding:24px 20px 32px;
  width:100%;max-width:500px;max-height:90vh;overflow-y:auto;
  transform:translateY(100%);transition:transform .4s ease;
  box-shadow:0 10px 40px rgba(46, 79, 65, 0.15);
}
.overlay.show .sheet{transform:translateY(0)}
.sheet-handle{width:40px;height:4px;background:rgba(46, 79, 65, 0.2);border-radius:2px;margin:0 auto 20px}
.sheet h3{font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--text);margin-bottom:20px}
.field{margin-bottom:14px}
.field label{display:block;color:var(--text-light);font-size:.8rem;font-weight:600;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px}
.field input{
  width:100%;padding:12px 14px;
  background:#f5f5f5;border:1px solid rgba(46, 79, 65, 0.15);
  border-radius:10px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;
}
.field input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(46, 79, 65, 0.1);background:#fff}
.field input::placeholder{color:#999}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.pay-label{
  display:flex;align-items:center;gap:10px;
  background:rgba(46, 79, 65, 0.05);border:1px solid rgba(46, 79, 65, 0.15);
  border-radius:10px;padding:12px;cursor:none;color:var(--text);margin-top:4px;font-weight:600;
}
.pay-label input{accent-color:var(--primary);width:16px;height:16px}
.btn-order{
  width:100%;padding:15px;border:none;border-radius:14px;
  background:linear-gradient(120deg,var(--primary),var(--accent));
  color:#fff;font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;
  cursor:none;margin-top:16px;transition:all .3s;
  box-shadow:0 4px 20px rgba(46, 79, 65, 0.15);
}
.btn-order:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(46, 79, 65, 0.2)}
.alert{padding:12px;border-radius:10px;margin-bottom:12px;font-size:.9rem}
.alert-s{background:#e8ffe8;border:1px solid #99ff99;color:#006400}
.alert-e{background:#ffe8e8;border:1px solid #ff9999;color:#c41a1a}
/* SUGGESTIONS */
.sug-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--text);margin-bottom:14px}
.sug-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.sug-card{
  background:#fff;border:1px solid rgba(46, 79, 65, 0.1);
  border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;display:block;
  transition:transform .2s,box-shadow .2s;
  box-shadow:0 2px 10px rgba(46, 79, 65, 0.05);
}
.sug-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(46, 79, 65, 0.15)}
.sug-img{width:100%;aspect-ratio:1;object-fit:cover}
.sug-info{padding:8px 10px 10px}
.sug-name{font-size:.82rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px}
.sug-price{
  font-size:.88rem;font-weight:700;
  background:linear-gradient(120deg,var(--primary),var(--accent));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
@media (min-width: 1024px) {
  .product-page{grid-template-columns:1.05fr .95fr;align-items:start;gap:32px;}
  .slider{max-height:560px;min-height:400px;}
  .product-title{font-size:1.8rem;}
  .price-main{font-size:2.2rem;}
  .sug-grid{grid-template-columns:1fr 1fr;}
}
@media (max-width: 768px) {
  .slider{min-height:280px;max-height:520px;}
  .content{padding:18px 0 100px;}
  .action-bar{flex-direction:column;}
  .btn{width:100%;}
  .sug-grid{grid-template-columns:1fr 1fr;}
  .grid2{grid-template-columns:1fr;}
}
@media (max-width: 560px) {
  .product-page{padding:0 10px;}
  .slider{min-height:220px;max-height:420px;}
  .product-title{font-size:1.4rem;}
  .price-main{font-size:1.6rem;}
  .product-header{padding:20px;}
  .btn{padding:13px;}
  .sug-grid{grid-template-columns:1fr;}
  .sheet{border-radius:16px 16px 0 0;padding:20px 16px 28px;}
}
/* CUSTOM CURSOR */
.cursor-dot{
  position:fixed;width:8px;height:8px;background:#fcd34d;border-radius:50%;
  pointer-events:none;z-index:9999;transform:translate(-50%,-50%);
  transition:width .1s, height .1s;
}
.cursor-ring{
  position:fixed;width:34px;height:34px;border:2px solid #fcd34d;border-radius:50%;
  pointer-events:none;z-index:9999;transform:translate(-50%,-50%);
  transition:width .1s, height .1s;
}
</style>
</head>
<body>
<div class="cursor-dot"></div>
<div class="cursor-ring"></div>
<header>
  <a href="shop.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
  <h1>Product Details</h1>
</header>

<div class="product-page">

<!-- Image Slider -->
<div class="slider">
  <div class="slider-track" id="sliderTrack">
    <?php foreach ($images as $img): ?>
    <img class="slide" src="<?= htmlspecialchars($img) ?>" alt="product" loading="lazy" onerror="this.src='assets/images/placeholder.svg'; this.onerror=null;">
    <?php endforeach; ?>
    <?php if (empty($images)): ?>
    <img class="slide" src="assets/images/placeholder.svg" alt="product" loading="lazy">
    <?php endif; ?>
  </div>
  <?php if (count($images) > 1): ?>
  <button class="slider-btn prev" onclick="slide(-1)"><i class="fas fa-chevron-left"></i></button>
  <button class="slider-btn next" onclick="slide(1)"><i class="fas fa-chevron-right"></i></button>
  <div class="dots" id="dots">
    <?php foreach ($images as $i => $_): ?>
    <div class="dot <?= $i===0?'active':'' ?>" onclick="goSlide(<?= $i ?>)"></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="content">
  <div class="product-header">
    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
    <div class="price-section">
      <span class="price-main">₹<?= number_format($product['price']) ?></span>
      <?php if ($product['old_price']): 
        $disc = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100);
      ?>
        <span class="price-old">₹<?= number_format($product['old_price']) ?></span>
        <span class="discount-badge"><?= $disc ?>% OFF</span>
      <?php endif; ?>
    </div>
    <?php if ($product['description']): ?>
    <p class="product-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
    <?php endif; ?>
  </div>

  <div class="action-bar">
    <button class="btn btn-buy" onclick="openCheckout()">
      <i class="fas fa-bolt"></i> Buy Now
    </button>
    <button class="btn btn-wish <?= $inWishlist ? 'active' : '' ?>" onclick="toggleWish()" id="wishBtn">
      <i class="fa<?= $inWishlist ? 's' : 'r' ?> fa-heart"></i>
    </button>
  </div>

  <?php if (!empty($suggestions)): ?>
  <div>
    <div class="sug-title">You May Also Like</div>
    <div class="sug-grid">
      <?php foreach ($suggestions as $s):
        $sImgs = json_decode($s['images'], true);
        if (!is_array($sImgs)) {
          $raw = trim($s['images'] ?? '');
          $sImgs = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)));
        }
        $sImgs = array_values(array_filter($sImgs));
        $sImg = $sImgs[0] ?? 'assets/images/placeholder.svg';
        $sFallback = $sImgs[1] ?? 'assets/images/placeholder.svg';
      ?>
      <a href="product.php?id=<?= $s['id'] ?>" class="sug-card">
        <img class="sug-img" src="<?= htmlspecialchars($sImg) ?>" alt="<?= htmlspecialchars($s['name']) ?>" loading="lazy" data-fallback="<?= htmlspecialchars($sFallback) ?>" onerror="this.onerror=null;this.src=this.dataset.fallback||'<?= htmlspecialchars(APP_URL . '/assets/images/placeholder.svg') ?>'">
        <div class="sug-info">
          <div class="sug-name"><?= htmlspecialchars($s['name']) ?></div>
          <div class="sug-price">₹<?= number_format($s['price']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
</div>

<!-- Checkout Sheet -->
<div class="overlay" id="checkoutOverlay" onclick="closeCheckout(event)">
  <div class="sheet" onclick="event.stopPropagation()">
    <div class="sheet-handle"></div>
    <h3><i class="fas fa-shopping-cart"></i> Place Your Order</h3>

    <?php if ($orderSuccess): ?>
      <div class="alert alert-s"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($orderSuccess) ?></div>
    <?php endif; ?>
    <?php if ($orderError): ?>
      <div class="alert alert-e"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($orderError) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="place_order" value="1">
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="full_name" placeholder="Your full name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
      </div>
      <div class="field">
        <label>Phone Number</label>
        <input type="tel" name="phone" placeholder="10-digit mobile number" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Delivery Address</label>
        <input type="text" name="address" placeholder="House no, Street, Area" required>
      </div>
      <div class="grid2">
        <div class="field">
          <label>City</label>
          <input type="text" name="city" placeholder="City" required>
        </div>
        <div class="field">
          <label>State</label>
          <input type="text" name="state" placeholder="State" required>
        </div>
      </div>
      <div class="field">
        <label>Postal Code</label>
        <input type="text" name="postal_code" placeholder="6-digit PIN code" required>
      </div>
      <div class="field">
        <label>Payment Method</label>
        <label class="pay-label">
          <input type="radio" name="payment_method" value="Cash On Delivery" checked>
          <i class="fas fa-money-bill-wave"></i> Cash On Delivery
        </label>
      </div>
      <div style="background:rgba(46, 79, 65, 0.05);border-radius:10px;padding:12px;margin-top:12px;border:1px solid rgba(46, 79, 65, 0.1);">
        <div style="display:flex;justify-content:space-between;color:var(--text);font-size:.9rem;margin-bottom:4px;font-weight:500;">
          <span><?= htmlspecialchars($product['name']) ?></span>
          <span style="font-weight:700;">₹<?= number_format($product['price']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;color:var(--text-light);font-size:.85rem">
          <span>Delivery</span><span style="color:#008a40;font-weight:700;">FREE</span>
        </div>
        <hr style="border:none;border-top:1px solid rgba(46, 79, 65, 0.1);margin:10px 0">
        <div style="display:flex;justify-content:space-between;font-weight:700;color:var(--text);align-items:center;">
          <span>Total</span>
          <span style="color:var(--primary);font-size:1.2rem;font-weight:800;">
            ₹<?= number_format($product['price']) ?>
          </span>
        </div>
      </div>
      <button type="submit" class="btn-order">
        <i class="fas fa-check-circle"></i> Place Order
      </button>
    </form>
  </div>
</div>

<script>
// Slider
let cur=0;
const track=document.getElementById('sliderTrack');
const slides=document.querySelectorAll('.slide');
const dots=document.querySelectorAll('.dot');
function slide(dir){
  cur=Math.max(0,Math.min(cur+dir,slides.length-1));
  goSlide(cur);
}
function goSlide(i){
  cur=i;
  track.style.transform=`translateX(-${cur*100}%)`;
  dots.forEach((d,j)=>d.classList.toggle('active',j===i));
}

// Checkout
function openCheckout(){
  document.getElementById('checkoutOverlay').classList.add('show');
  document.body.style.overflow='hidden';
}
function closeCheckout(e){
  if(e.target===document.getElementById('checkoutOverlay')){
    document.getElementById('checkoutOverlay').classList.remove('show');
    document.body.style.overflow='';
  }
}
<?php if ($orderSuccess): ?>
openCheckout();
<?php endif; ?>

// Wishlist
function toggleWish(){
  fetch('api/wishlist.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({product_id:<?= $productId ?>})
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.success){
      const btn=document.getElementById('wishBtn');
      const added=data.action==='added';
      btn.className='btn btn-wish'+(added?' active':'');
      btn.querySelector('i').className=(added?'fas':'far')+' fa-heart';
    }
  });
}
</script>
<script>
  const cursorDot = document.querySelector('.cursor-dot');
  const cursorRing = document.querySelector('.cursor-ring');
  const isTouchDevice = () => window.innerWidth < 900;
  
  if (!isTouchDevice()) {
    document.addEventListener('mousemove', e => {
      cursorDot.style.left = e.clientX + 'px';
      cursorDot.style.top = e.clientY + 'px';
      cursorRing.style.left = e.clientX + 'px';
      cursorRing.style.top = e.clientY + 'px';
    });
    document.addEventListener('mousedown', () => {
      cursorDot.style.width = '4px';
      cursorDot.style.height = '4px';
      cursorRing.style.width = '52px';
      cursorRing.style.height = '52px';
    });
    document.addEventListener('mouseup', () => {
      cursorDot.style.width = '8px';
      cursorDot.style.height = '8px';
      cursorRing.style.width = '34px';
      cursorRing.style.height = '34px';
    });
  }
</script>
</body>
</html>
