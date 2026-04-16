<?php
require_once 'includes/config.php';
requireLogin();
$user = getCurrentUser();
$pdo = getDB();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $full_name = sanitize($_POST['full_name'] ?? '');
    $gender = sanitize($_POST['gender'] ?? 'male');
    $dob = sanitize($_POST['dob'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    if (empty($username) || empty($full_name) || empty($email)) {
        $error = 'Username, full name and email are required.';
    } else {
        // Check uniqueness excluding current user
        $check = $pdo->prepare("SELECT id FROM users WHERE (email=? OR username=?) AND id != ?");
        $check->execute([$email, $username, $user['id']]);
        if ($check->fetch()) {
            $error = 'Email or username is already taken.';
        } else {
            $avatar = ($gender === 'female') ? 'assets/female.png' : 'assets/male.png';
            $stmt = $pdo->prepare("UPDATE users SET username=?,full_name=?,gender=?,dob=?,email=?,phone=?,avatar=? WHERE id=?");
            $stmt->execute([$username, $full_name, $gender, $dob ?: null, $email, $phone, $avatar, $user['id']]);
            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_email'] = $email;
            $user = getCurrentUser(); // Refresh
            $success = 'Profile updated successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Settings - Fast Shopsy</title>
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
  cursor: none;
  text-decoration: none;
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
  max-width: 500px;
  margin: 0 auto;
}

.avatar-section {
  text-align: center;
  margin-bottom: 28px;
}

.avatar-wrap {
  width: 90px;
  height: 90px;
  border-radius: 50%;
  margin: 0 auto 12px;
  border: 3px solid;
  border-image: linear-gradient(120deg, var(--primary), var(--accent)) 1;
  box-shadow: 0 0 24px rgba(46, 79, 65, 0.15);
  overflow: hidden;
  position: relative;
}

.avatar-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-name {
  font-family: 'Playfair Display', serif;
  font-size: 1.3rem;
  color: var(--text);
  font-weight: 700;
}

.avatar-email {
  color: var(--text-light);
  font-size: 0.85rem;
  margin-top: 2px;
}

.card {
  background: #fff;
  border: 1px solid rgba(46, 79, 65, 0.1);
  border-radius: 20px;
  padding: 22px;
  margin-bottom: 16px;
  box-shadow: 0 2px 10px rgba(46, 79, 65, 0.05);
}

.card-title {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-light);
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 16px;
}

.field {
  margin-bottom: 14px;
}

.field label {
  display: block;
  color: var(--text-light);
  font-size: 0.8rem;
  font-weight: 600;
  margin-bottom: 5px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.field input,
.field select {
  width: 100%;
  padding: 12px 14px;
  background: #f5f5f5;
  border: 1px solid rgba(46, 79, 65, 0.15);
  border-radius: 10px;
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 0.95rem;
  outline: none;
  transition: all 0.3s;
}

.field input:focus,
.field select:focus {
  border-color: var(--primary);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(46, 79, 65, 0.1);
}

.field input::placeholder {
  color: #999;
}

.field select option {
  background: #fff;
  color: var(--text);
}

.gender-preview {
  display: flex;
  gap: 12px;
  margin-top: 10px;
}

.gender-opt {
  flex: 1;
  padding: 14px;
  border-radius: 12px;
  background: rgba(46, 79, 65, 0.05);
  border: 2px solid transparent;
  cursor: none;
  text-align: center;
  transition: all 0.3s;
  color: var(--text-light);
}

.gender-opt.active {
  background: linear-gradient(120deg, rgba(46, 79, 65, 0.15), rgba(252, 211, 77, 0.15));
  border-color: var(--primary);
  color: var(--text);
  font-weight: 600;
}

.gender-opt i {
  font-size: 1.5rem;
  display: block;
  margin-bottom: 4px;
}

.gender-opt span {
  font-size: 0.85rem;
  font-weight: 700;
}

.btn-save {
  width: 100%;
  padding: 15px;
  border: none;
  border-radius: 14px;
  background: linear-gradient(120deg, var(--primary), var(--accent));
  color: #fff;
  font-family: 'DM Sans', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  cursor: none;
  transition: all 0.3s;
  margin-top: 8px;
  box-shadow: 0 4px 20px rgba(46, 79, 65, 0.15);
}

.btn-save:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(46, 79, 65, 0.2);
}

.alert {
  padding: 12px 16px;
  border-radius: 10px;
  margin-bottom: 14px;
  font-size: 0.9rem;
}

.alert-s {
  background: #e8ffe8;
  border: 1px solid #99ff99;
  color: #006400;
}

.alert-e {
  background: #ffe8e8;
  border: 1px solid #ff9999;
  color: #c41a1a;
}

.row2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 768px) {
  main { padding: 18px 14px 60px; }
  .avatar-wrap { width: 80px; height: 80px; }
  .avatar-name { font-size: 1.2rem; }
  .avatar-email { font-size: 0.82rem; }
  .row2 { grid-template-columns: 1fr; }
  .card { padding: 18px; }
  .field input, .field select { font-size: 0.92rem; }
}

@media (max-width: 400px) {
  main { padding: 16px 12px 50px; }
  .avatar-wrap { width: 70px; height: 70px; }
  .avatar-name { font-size: 1.1rem; }
  .card { padding: 16px; }
  .btn-save { padding: 14px; }
  .field label { font-size: 0.75rem; }
}
</style>
</head>
<body>
<div class="cursor-dot"></div>
<div class="cursor-ring"></div>

<header>
  <a href="shop.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
  <h1><i class="fas fa-gear"></i> Settings</h1>
</header>
<main>
  <div class="avatar-section">
    <div class="avatar-wrap" style="border-radius:50%;border:3px solid transparent;background:linear-gradient(#1a0533,#1a0533) padding-box,linear-gradient(135deg,#ff6b9d,#c44dff) border-box">
      <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" id="avatarImg" onerror="this.src='assets/male.png'">
    </div>
    <div class="avatar-name"><?= htmlspecialchars($user['full_name']) ?></div>
    <div class="avatar-email"><?= htmlspecialchars($user['email']) ?></div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-s"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-e"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="card">
      <div class="card-title"><i class="fas fa-user"></i> Personal Info</div>
      <div class="row2">
        <div class="field">
          <label>Username</label>
          <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
        </div>
      </div>
      <div class="row2">
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        <div class="field">
          <label>Phone</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+91...">
        </div>
      </div>
      <div class="field">
        <label>Date of Birth</label>
        <input type="date" name="dob" value="<?= htmlspecialchars($user['dob'] ?? '') ?>">
      </div>
    </div>

    <div class="card">
      <div class="card-title"><i class="fas fa-venus-mars"></i> Gender & Avatar</div>
      <input type="hidden" name="gender" id="genderInput" value="<?= htmlspecialchars($user['gender']) ?>">
      <div class="gender-preview">
        <div class="gender-opt <?= $user['gender'] === 'male' ? 'active' : '' ?>" onclick="selectGender('male')">
          <i class="fas fa-mars"></i><span>Male</span>
        </div>
        <div class="gender-opt <?= $user['gender'] === 'female' ? 'active' : '' ?>" onclick="selectGender('female')">
          <i class="fas fa-venus"></i><span>Female</span>
        </div>
      </div>
      <p style="margin-top:10px;color:rgba(255,255,255,0.4);font-size:.8rem">Avatar will update automatically based on gender.</p>
    </div>

    <button type="submit" class="btn-save">
      <i class="fas fa-save"></i> Save Changes
    </button>
  </form>
</main>
<script>
function selectGender(g){
  document.getElementById('genderInput').value=g;
  document.querySelectorAll('.gender-opt').forEach((el,i)=>el.classList.toggle('active',i===(g==='male'?0:1)));
  document.getElementById('avatarImg').src='assets/'+(g)+'.png';
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
  
  const interactiveElements = document.querySelectorAll('a, button, input, select, .gender-opt');
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
