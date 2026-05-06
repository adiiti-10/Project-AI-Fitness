<?php
/**
 * config.php – Database & App Configuration
 * Place this file at the project root.
 * XAMPP default credentials shown below.
 */

// ── Database ──────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_USER',     'root');        // Change for production
define('DB_PASS',     '');            // Change for production
define('DB_NAME',     'ai_fitness_trainer');
define('DB_CHARSET',  'utf8mb4');

// ── App ───────────────────────────────────────────
define('APP_NAME',    'AI Fitness Trainer');
define('BASE_URL',    'http://localhost/ai-fitness-trainer');
define('FLASK_URL',   'http://127.0.0.1:5000');   // Python Flask API

// ── Session ───────────────────────────────────────
session_start();

// ── PDO Connection (singleton helper) ─────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Auth helpers ──────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function currentUser(): array {
    if (!isLoggedIn()) return [];
    $db  = getDB();
    $stmt = $db->prepare('SELECT id, name, email, avatar, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: [];
}

// ── JSON response helper ──────────────────────────
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
