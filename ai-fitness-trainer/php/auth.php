<?php
/**
 * auth.php – Handles signup, login, logout via POST requests.
 * Called by JS fetch() from the frontend.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

switch ($action) {

    // ── SIGNUP ─────────────────────────────────────
    case 'signup': {
        $name     = trim($body['name']  ?? '');
        $email    = trim($body['email'] ?? '');
        $password =      $body['password'] ?? '';

        if (!$name || !$email || !$password) {
            jsonResponse(['error' => 'All fields are required'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email address'], 422);
        }
        if (strlen($password) < 6) {
            jsonResponse(['error' => 'Password must be at least 6 characters'], 422);
        }

        $db   = getDB();
        $chk  = $db->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            jsonResponse(['error' => 'Email already registered'], 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins  = $db->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        $ins->execute([$name, $email, $hash]);

        $userId = $db->lastInsertId();
        $_SESSION['user_id']   = $userId;
        $_SESSION['user_name'] = $name;

        jsonResponse(['success' => true, 'name' => $name, 'id' => $userId]);
        break;
    }

    // ── LOGIN ──────────────────────────────────────
    case 'login': {
        $email    = trim($body['email']    ?? '');
        $password =      $body['password'] ?? '';

        if (!$email || !$password) {
            jsonResponse(['error' => 'Email and password are required'], 422);
        }

        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name, password, avatar FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];

        jsonResponse([
            'success' => true,
            'name'    => $user['name'],
            'avatar'  => $user['avatar'],
            'id'      => $user['id'],
        ]);
        break;
    }

    // ── LOGOUT ─────────────────────────────────────
    case 'logout': {
        session_destroy();
        jsonResponse(['success' => true]);
        break;
    }

    // ── CHECK SESSION ──────────────────────────────
    case 'check': {
        if (isLoggedIn()) {
            $user = currentUser();
            jsonResponse(['loggedIn' => true, 'user' => $user]);
        }
        jsonResponse(['loggedIn' => false]);
        break;
    }

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
