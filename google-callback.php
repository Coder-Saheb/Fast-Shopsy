<?php
require_once '../includes/config.php';

// Google OAuth Callback Handler
// This file handles the redirect from Google after user authenticates

if (!isset($_GET['code'])) {
    header('Location: ../login.php?error=oauth_failed');
    exit;
}

// Verify state to prevent CSRF
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    header('Location: ../login.php?error=state_mismatch');
    exit;
}
unset($_SESSION['oauth_state']);

$code = $_GET['code'];

// Exchange authorization code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$tokenResponse = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    header('Location: ../login.php?error=token_fetch_failed');
    exit;
}

$tokenJson = json_decode($tokenResponse, true);

if (!isset($tokenJson['access_token'])) {
    header('Location: ../login.php?error=no_access_token');
    exit;
}

$accessToken = $tokenJson['access_token'];

// Fetch user info from Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$userInfoResponse = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($userInfoResponse, true);

if (!isset($googleUser['id']) || !isset($googleUser['email'])) {
    header('Location: ../login.php?error=user_info_failed');
    exit;
}

$pdo = getDB();

// Check if user already exists with this Google ID
$stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
$stmt->execute([$googleUser['id'], $googleUser['email']]);
$existingUser = $stmt->fetch();

if ($existingUser) {
    // Update Google ID if registered via email before
    if (empty($existingUser['google_id'])) {
        $stmt = $pdo->prepare("UPDATE users SET google_id = ?, auth_provider = 'google' WHERE id = ?");
        $stmt->execute([$googleUser['id'], $existingUser['id']]);
    }
    // Login existing user
    $_SESSION['user_id'] = $existingUser['id'];
    $_SESSION['user_name'] = $existingUser['full_name'];
    $_SESSION['user_email'] = $existingUser['email'];
    header('Location: ../shop.php');
    exit;
} else {
    // Register new user from Google
    $fullName = $googleUser['name'] ?? 'User';
    $email = $googleUser['email'];
    $googleId = $googleUser['id'];
    
    // Generate unique username from email
    $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
    $username = $baseUsername;
    $counter = 1;
    while (true) {
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if (!$checkStmt->fetch()) break;
        $username = $baseUsername . $counter;
        $counter++;
    }

    $avatar = 'assets/male.png'; // Default, can be updated in settings

    $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, google_id, auth_provider, avatar) VALUES (?, ?, ?, ?, 'google', ?)");
    $stmt->execute([$username, $fullName, $email, $googleId, $avatar]);
    
    $newUserId = $pdo->lastInsertId();
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['user_name'] = $fullName;
    $_SESSION['user_email'] = $email;
    
    header('Location: ../shop.php');
    exit;
}
?>
