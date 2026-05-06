<?php
/**
 * workouts.php – CRUD for workout sessions.
 * Endpoints:
 *   POST  action=save   → save a completed session
 *   POST  action=list   → get user's workout history
 *   POST  action=stats  → aggregated stats for dashboard
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$userId = $_SESSION['user_id'];
$db     = getDB();

switch ($action) {

    // ── SAVE SESSION ───────────────────────────────
    case 'save': {
        $exercise    = $body['exercise_type'] ?? '';
        $reps        = (int)   ($body['reps']         ?? 0);
        $accuracy    = (float) ($body['accuracy']      ?? 0.0);
        $duration    = (int)   ($body['duration_sec']  ?? 0);
        // Rough calorie estimate: squats ≈ 0.32 kcal/rep, pushups ≈ 0.29 kcal/rep
        $calPerRep   = ($exercise === 'squat') ? 0.32 : 0.29;
        $calories    = round($reps * $calPerRep * ($accuracy / 100 + 0.5), 2);

        if (!in_array($exercise, ['squat', 'pushup'], true)) {
            jsonResponse(['error' => 'Invalid exercise type'], 422);
        }

        $stmt = $db->prepare('
            INSERT INTO workouts (user_id, exercise_type, reps, accuracy, duration_sec, calories)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$userId, $exercise, $reps, $accuracy, $duration, $calories]);

        jsonResponse(['success' => true, 'workout_id' => $db->lastInsertId(), 'calories' => $calories]);
        break;
    }

    // ── LIST HISTORY ───────────────────────────────
    case 'list': {
        $limit  = min((int)($body['limit']  ?? 20), 100);
        $offset = (int)($body['offset'] ?? 0);

        $stmt = $db->prepare('
            SELECT id, exercise_type, reps, accuracy, duration_sec, calories,
                   DATE_FORMAT(date, "%d %b %Y, %H:%i") AS formatted_date
            FROM workouts
            WHERE user_id = ?
            ORDER BY date DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$userId, $limit, $offset]);
        $rows = $stmt->fetchAll();

        // total count for pagination
        $cnt  = $db->prepare('SELECT COUNT(*) FROM workouts WHERE user_id = ?');
        $cnt->execute([$userId]);
        $total = (int) $cnt->fetchColumn();

        jsonResponse(['success' => true, 'workouts' => $rows, 'total' => $total]);
        break;
    }

    // ── AGGREGATED STATS ───────────────────────────
    case 'stats': {
        // Overall totals
        $tot = $db->prepare('
            SELECT
                COUNT(*)              AS total_sessions,
                COALESCE(SUM(reps),0) AS total_reps,
                COALESCE(ROUND(AVG(accuracy),1), 0) AS avg_accuracy,
                COALESCE(SUM(calories),0) AS total_calories
            FROM workouts WHERE user_id = ?
        ');
        $tot->execute([$userId]);
        $totals = $tot->fetch();

        // Last 7 days – reps per day (for chart)
        $chart = $db->prepare('
            SELECT DATE(date) AS day, SUM(reps) AS reps
            FROM workouts
            WHERE user_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(date)
            ORDER BY day ASC
        ');
        $chart->execute([$userId]);
        $chartData = $chart->fetchAll();

        // Per-exercise breakdown
        $byEx = $db->prepare('
            SELECT exercise_type,
                   COUNT(*) AS sessions,
                   SUM(reps) AS total_reps,
                   ROUND(AVG(accuracy),1) AS avg_accuracy
            FROM workouts WHERE user_id = ?
            GROUP BY exercise_type
        ');
        $byEx->execute([$userId]);
        $byExercise = $byEx->fetchAll();

        jsonResponse([
            'success'     => true,
            'totals'      => $totals,
            'chart'       => $chartData,
            'by_exercise' => $byExercise,
        ]);
        break;
    }

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
