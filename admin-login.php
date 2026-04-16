<?php
require_once '../includes/config.php';

if (isAdminLoggedIn()) {
    header('Location: admin-dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: admin-dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Admin Login - Fast Shopsy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  cursor: none;
}

body {
  background: #cdcccf;
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  overflow-x: hidden;
  color: #111;
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
  background: #fcd34d;
  box-shadow: 0 0 8px #fcd34d;
}

.cursor-ring {
  width: 34px;
  height: 34px;
  border: 1.5px solid rgba(252, 211, 77, 0.7);
  background: rgba(252, 211, 77, 0.05);
  backdrop-filter: blur(2px);
  transition: width 0.2s, height 0.2s;
}

a, button, .field input {
  cursor: none;
}

.wrap {
  width: 100%;
  max-width: 400px;
  z-index: 1;
}

.logo {
  text-align: center;
  margin-bottom: 32px;
}

.logo h1 {
  font-family: 'Playfair Display', serif;
  font-size: 2rem;
  font-weight: 700;
  background: linear-gradient(120deg, #000, #2e4f41, #fcd34d);
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
}

.logo p {
  color: #666;
  font-size: 0.85rem;
  margin-top: 4px;
}

.card {
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.1);
  border-radius: 24px;
  padding: 32px 28px;
  box-shadow: 0 4px 20px rgba(46, 79, 65, 0.1);
}

h2 {
  font-family: 'Playfair Display', serif;
  color: #111;
  font-size: 1.4rem;
  margin-bottom: 8px;
  text-align: center;
}

.sub {
  text-align: center;
  color: #666;
  font-size: 0.85rem;
  margin-bottom: 24px;
}

.field {
  margin-bottom: 16px;
  position: relative;
}

.field label {
  display: block;
  color: #666;
  font-size: 0.8rem;
  font-weight: 600;
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.field input {
  width: 100%;
  padding: 13px 16px 13px 44px;
  background: #f5f5f5;
  border: 1px solid rgba(46, 79, 65, 0.15);
  border-radius: 12px;
  color: #111;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.95rem;
  outline: none;
  transition: all 0.3s;
}

.field input:focus {
  border-color: #2e4f41;
  box-shadow: 0 0 0 3px rgba(46, 79, 65, 0.1);
  background: #f9f9f9;
}

.field input::placeholder {
  color: #999;
}

.field-icon {
  position: absolute;
  left: 14px;
  top: 38px;
  color: #999;
  font-size: 0.9rem;
}

.btn {
  width: 100%;
  padding: 14px;
  border: none;
  border-radius: 40px;
  background: linear-gradient(120deg, #2e4f41, #fcd34d);
  color: #fff;
  font-family: 'DM Sans', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  cursor: none;
  transition: all 0.3s;
  box-shadow: 0 4px 12px rgba(46, 79, 65, 0.2);
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(46, 79, 65, 0.25);
}

.alert {
  background: #ffe8e8;
  border: 1px solid #ff9999;
  color: #c41a1a;
  padding: 12px 16px;
  border-radius: 10px;
  margin-bottom: 14px;
  font-size: 0.9rem;
}

.back {
  text-align: center;
  margin-top: 16px;
  color: #666;
  font-size: 0.85rem;
}

.back a {
  color: #2e4f41;
  text-decoration: none;
  font-weight: 600;
  cursor: none;
}

.back a:hover {
  color: #fcd34d;
}
</style>
</head>
<body>
<div class="cursor-dot"></div>
<div class="cursor-ring"></div>

<div class="wrap">
  <div class="logo">
    <h1><i class="fas fa-shield-halved"></i> Admin</h1>
    <p>fast Shop Administration</p>
  </div>
  <div class="card">
    <h2>Admin Login</h2>
    <p class="sub">Enter your admin credentials</p>
    <?php if ($error): ?>
    <div class="alert"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="field">
        <label>Username</label>
        <i class="fas fa-user field-icon"></i>
        <input type="text" name="username" placeholder="Admin username" required>
      </div>
      <div class="field">
        <label>Password</label>
        <i class="fas fa-lock field-icon"></i>
        <input type="password" name="password" placeholder="Admin password" required>
      </div>
      <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Login</button>
    </form>
    <div class="back"><a href="../index.html"><i class="fas fa-arrow-left"></i> Back to Shop</a></div>
  </div>
</div>

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
  
  const interactiveElements = document.querySelectorAll('a, button, .field input');
  interactiveElements.forEach(el => {
    el.addEventListener('mouseenter', () => {
      ring.style.width = '52px';
      ring.style.height = '52px';
      ring.style.border = '2px solid #fcd34d';
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
