<?php
/**
 * Concert Programma Planner API
 *
 * Endpoints:
 * GET  ?action=load&session=<id>           - Load session state
 * POST ?action=save&session=<id>           - Save session state
 * GET  ?action=poll&session=<id>&since=<t> - Poll for updates since timestamp
 */

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$sessionId = $_GET['session'] ?? '';

if (empty($sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Session ID required']);
    exit;
}

// Sanitize session ID (alphanumeric and hyphens only)
if (!preg_match('/^[a-zA-Z0-9\-]+$/', $sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid session ID']);
    exit;
}

$pdo = getDatabase();

switch ($action) {
    case 'load':
        loadSession($pdo, $sessionId);
        break;

    case 'save':
        saveSession($pdo, $sessionId);
        break;

    case 'poll':
        $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;
        pollSession($pdo, $sessionId, $since);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Load session state from database
 */
function loadSession($pdo, $sessionId) {
    $stmt = $pdo->prepare("SELECT performers, programme_items, concert_info, updated_at FROM sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'success' => true,
            'performers' => json_decode($row['performers'] ?: '[]'),
            'programmeItems' => json_decode($row['programme_items'] ?: '[]'),
            'concertInfo' => json_decode($row['concert_info'] ?: '{}'),
            'updatedAt' => (int)$row['updated_at']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'performers' => [],
            'programmeItems' => [],
            'concertInfo' => new stdClass(),
            'updatedAt' => 0
        ]);
    }
}

/**
 * Save session state to database
 */
function saveSession($pdo, $sessionId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    $performers = json_encode($input['performers'] ?? []);
    $programmeItems = json_encode($input['programmeItems'] ?? []);
    $concertInfo = json_encode($input['concertInfo'] ?? new stdClass());
    $updatedAt = round(microtime(true) * 1000); // milliseconds

    $stmt = $pdo->prepare("
        INSERT INTO sessions (session_id, performers, programme_items, concert_info, updated_at)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            performers = VALUES(performers),
            programme_items = VALUES(programme_items),
            concert_info = VALUES(concert_info),
            updated_at = VALUES(updated_at)
    ");

    $stmt->execute([$sessionId, $performers, $programmeItems, $concertInfo, $updatedAt]);

    echo json_encode([
        'success' => true,
        'updatedAt' => $updatedAt
    ]);
}

/**
 * Poll for updates since a given timestamp
 */
function pollSession($pdo, $sessionId, $since) {
    $stmt = $pdo->prepare("SELECT performers, programme_items, concert_info, updated_at FROM sessions WHERE session_id = ? AND updated_at > ?");
    $stmt->execute([$sessionId, $since]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'hasUpdate' => true,
            'performers' => json_decode($row['performers'] ?: '[]'),
            'programmeItems' => json_decode($row['programme_items'] ?: '[]'),
            'concertInfo' => json_decode($row['concert_info'] ?: '{}'),
            'updatedAt' => (int)$row['updated_at']
        ]);
    } else {
        echo json_encode([
            'hasUpdate' => false
        ]);
    }
}
