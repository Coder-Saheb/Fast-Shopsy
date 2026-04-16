<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: shop.php');
    exit;
}

$error = '';
$success = '';

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['auth_provider'] === 'google' && empty($user['password'])) {
                $error = 'This account uses Google login. Please use "Continue with Google".';
            } elseif ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                header('Location: shop.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    } elseif ($_POST['action'] === 'register') {
        $username = sanitize($_POST['username'] ?? '');
        $full_name = sanitize($_POST['full_name'] ?? '');
        $gender = sanitize($_POST['gender'] ?? 'male');
        $dob = sanitize($_POST['dob'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($full_name) || empty($email) || empty($password)) {
            $error = 'reg:Please fill in all required fields.';
        } elseif (strlen($password) < 6) {
            $error = 'reg:Password must be at least 6 characters.';
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                $error = 'reg:Email or username already exists.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $avatar = ($gender === 'female') ? 'assets/female.png' : 'assets/male.png';
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, gender, dob, email, password, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $full_name, $gender, $dob ?: null, $email, $hashedPassword, $avatar]);
                $success = 'Account created! Please login.';
            }
        }
    }
}

// Google OAuth redirect
if (isset($_GET['google'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $params = http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account'
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

$showRegister = str_starts_with($error, 'reg:') || !empty($success);
if (str_starts_with($error, 'reg:')) $error = substr($error, 4);
$urlError = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Login - Fast Shopsy</title>
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
  overflow-x: hidden;
  color: #111;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
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

a, button, .card, .field input {
  cursor: none;
}

.wrap {
  width: 100%;
  max-width: 460px;
  position: relative;
  z-index: 1;
}

.logo {
  text-align: center;
  margin-bottom: 32px;
}

.logo h1 {
  font-family: 'Playfair Display', serif;
  font-size: 2.4rem;
  font-weight: 700;
  background: linear-gradient(120deg, #000, #2e4f41, #fcd34d);
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
  letter-spacing: 1px;
}

.logo p {
  color: #666;
  font-size: 0.95rem;
  margin-top: 4px;
}

.card {
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.1);
  border-radius: 24px;
  padding: 36px 32px;
  box-shadow: 0 4px 20px rgba(46, 79, 65, 0.08);
}

.form-toggle {
  display: none;
}

.form-panel {
  animation: fadeIn 0.4s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

h2 {
  font-family: 'Playfair Display', serif;
  color: #111;
  font-size: 1.6rem;
  font-weight: 700;
  margin-bottom: 24px;
  text-align: center;
}

.field {
  margin-bottom: 16px;
  position: relative;
}

.field label {
  display: block;
  color: #666;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.field input,
.field select {
  width: 100%;
  padding: 13px 16px 13px 44px;
  background: #f5f5f5;
  border: 1px solid rgba(46, 79, 65, 0.15);
  border-radius: 12px;
  color: #111;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.95rem;
  transition: all 0.3s;
  outline: none;
}

.field input::placeholder {
  color: #999;
}

.field input:focus,
.field select:focus {
  border-color: #2e4f41;
  background: #f9f9f9;
  box-shadow: 0 0 0 3px rgba(46, 79, 65, 0.1);
}

.field select option {
  background: #fff;
  color: #111;
}

.field-icon {
  position: absolute;
  left: 14px;
  top: 38px;
  color: #999;
  font-size: 0.95rem;
  pointer-events: none;
}

.row2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.btn {
  width: 100%;
  padding: 14px;
  border: 1px solid #2e4f41;
  border-radius: 40px;
  font-family: 'DM Sans', sans-serif;
  font-size: 1rem;
  font-weight: 600;
  cursor: none;
  transition: all 0.3s;
  letter-spacing: 0.5px;
  background: transparent;
  color: #111;
}

.btn-primary {
  background: transparent;
  color: #111;
  margin-top: 8px;
  box-shadow: 0 4px 12px rgba(46, 79, 65, 0.2);
}

.btn-primary:hover {
  transform: scale(1.02);
  box-shadow: 0 0 15px #fcd34d;
  background: #2e4f41;
  color: rgb(240, 240, 240);
}

.btn-primary:active {
  transform: scale(0.98);
}

.btn-google {
  background: #f5f5f5;
  border: 1px solid rgba(46, 79, 65, 0.15);
  color: #111;
  margin-top: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  box-shadow: 0 4px 12px rgba(46, 79, 65, 0.08);
}

.btn-google:hover {
  background: #efefef;
  transform: scale(1.02);
}

.btn-google img {
  width: 20px;
  height: 20px;
}

.divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 20px 0;
}

.divider span {
  flex: 1;
  height: 1px;
  background: rgba(46, 79, 65, 0.1);
}

.divider p {
  color: #999;
  font-size: 0.85rem;
  white-space: nowrap;
}

.switch-link {
  text-align: center;
  margin-top: 20px;
  color: #666;
  font-size: 0.9rem;
}

.switch-link a {
  color: #2e4f41;
  text-decoration: none;
  font-weight: 700;
  cursor: none;
}

.switch-link a:hover {
  color: #fcd34d;
}

.alert {
  padding: 12px 16px;
  border-radius: 10px;
  margin-bottom: 16px;
  font-size: 0.9rem;
  border: 1px solid;
}

.alert-error {
  background: #ffe8e8;
  border-color: #ff9999;
  color: #c41a1a;
}

.alert-success {
  background: #e8ffe8;
  border-color: #99ff99;
  color: #006400;
}

@media (max-width: 480px) {
  .card {
    padding: 28px 20px;
  }
  .row2 {
    grid-template-columns: 1fr;
  }
  .logo h1 {
    font-size: 2rem;
  }
}
</style>
</head>
<body>
<div class="cursor-dot"></div>
<div class="cursor-ring"></div>

<div class="wrap">
  <div class="logo">
    <h1><i class="fas fa-shopping-bag"></i> Fast Shopsy</h1>
    <p>Your fashion destination</p>
  </div>

  <div class="card">
    <!-- LOGIN PANEL -->
    <div class="form-panel" id="loginPanel" style="<?= $showRegister ? 'display:none' : '' ?>">
      <h2>Welcome Back</h2>
      <?php if ($error && !$showRegister): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($urlError): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Google login failed. Please try again.</div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="field">
          <label>Email Address</label>
          <i class="fas fa-envelope field-icon"></i>
          <input type="email" name="email" placeholder="you@example.com" required>
        </div>
        <div class="field">
          <label>Password</label>
          <i class="fas fa-lock field-icon"></i>
          <input type="password" name="password" placeholder="Your password" required>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-sign-in-alt"></i> Login
        </button>
      </form>
      <div class="divider"><span></span><p>or</p><span></span></div>
      <a href="?google=1" class="btn btn-google">
        <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
        Continue with Google
      </a>
      <div class="switch-link">
        Don't have an account? <a onclick="showRegister()">Register</a>
      </div>
    </div>

    <!-- REGISTER PANEL -->
    <div class="form-panel" id="registerPanel" style="<?= $showRegister ? '' : 'display:none' ?>">
      <h2>Create Account</h2>
      <?php if ($error && $showRegister): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="register">
        <div class="row2">
          <div class="field">
            <label>Username</label>
            <i class="fas fa-at field-icon"></i>
            <input type="text" name="username" placeholder="username" required>
          </div>
          <div class="field">
            <label>Full Name</label>
            <i class="fas fa-user field-icon"></i>
            <input type="text" name="full_name" placeholder="Your Name" required>
          </div>
        </div>
        <div class="row2">
          <div class="field">
            <label>Gender</label>
            <i class="fas fa-venus-mars field-icon"></i>
            <select name="gender">
              <option value="male">Male</option>
              <option value="female">Female</option>
            </select>
          </div>
          <div class="field">
            <label>Date of Birth</label>
            <i class="fas fa-calendar field-icon"></i>
            <input type="date" name="dob">
          </div>
        </div>
        <div class="field">
          <label>Email Address</label>
          <i class="fas fa-envelope field-icon"></i>
          <input type="email" name="email" placeholder="you@example.com" required>
        </div>
        <div class="field">
          <label>Password</label>
          <i class="fas fa-lock field-icon"></i>
          <input type="password" name="password" placeholder="Min 6 characters" required>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-user-plus"></i> Create Account
        </button>
      </form>
      <div class="switch-link" style="margin-top:16px">
        Already have an account? <a onclick="showLogin()">Login</a>
      </div>
    </div>
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
  
  const interactiveElements = document.querySelectorAll('a, button, .field input, .field select');
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

function showRegister(){
  const lp=document.getElementById('loginPanel');
  const rp=document.getElementById('registerPanel');
  lp.style.animation='fadeOut .3s ease forwards';
  setTimeout(()=>{lp.style.display='none';rp.style.display='block';rp.style.animation='fadeIn .4s ease'},280);
}

function showLogin(){
  const lp=document.getElementById('loginPanel');
  const rp=document.getElementById('registerPanel');
  rp.style.animation='fadeOut .3s ease forwards';
  setTimeout(()=>{rp.style.display='none';lp.style.display='block';lp.style.animation='fadeIn .4s ease'},280);
}

const s=document.createElement('style');
s.textContent='@keyframes fadeOut{from{opacity:1;transform:translateY(0)}to{opacity:0;transform:translateY(-16px)}}';
document.head.appendChild(s);
</script>
</body>
</html>
