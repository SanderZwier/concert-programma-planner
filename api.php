<?php
/**
 * Concert Programma Planner API
 *
 * Endpoints:
 * GET  ?action=list                          - List all concerts
 * GET  ?action=load&concert=<slug>           - Load concert data
 * POST ?action=save&concert=<slug>           - Save concert data
 * POST ?action=create                        - Create new concert
 * POST ?action=delete&concert=<slug>         - Delete concert
 * GET  ?action=poll&concert=<slug>&since=<t> - Poll for updates
 */

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$concertSlug = $_GET['concert'] ?? '';

$pdo = getDatabase();

switch ($action) {
    case 'list':
        listConcerts($pdo);
        break;

    case 'load':
        if (empty($concertSlug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Concert slug required']);
            exit;
        }
        if (!isValidSlug($concertSlug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid concert slug']);
            exit;
        }
        loadConcert($pdo, $concertSlug);
        break;

    case 'save':
        if (empty($concertSlug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Concert slug required']);
            exit;
        }
        if (!isValidSlug($concertSlug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid concert slug']);
            exit;
        }
        saveConcert($pdo, $concertSlug);
        break;

    case 'create':
        createConcert($pdo);
        break;

    case 'delete':
        if (empty($concertSlug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Concert slug required']);
            exit;
        }
        deleteConcert($pdo, $concertSlug);
        break;

    case 'poll':
        if (empty($concertSlug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Concert slug required']);
            exit;
        }
        $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;
        pollConcert($pdo, $concertSlug, $since);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Validate slug format (lowercase alphanumeric and hyphens)
 */
function isValidSlug($slug) {
    return preg_match('/^[a-z0-9\-]+$/', $slug);
}

/**
 * Generate slug from title
 */
function generateSlug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
    $slug = preg_replace('/[\s]+/', '-', $slug);
    $slug = preg_replace('/\-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug ?: 'concert-' . time();
}

/**
 * List all concerts
 */
function listConcerts($pdo) {
    $stmt = $pdo->query("SELECT slug, title, concert_date, created_at FROM concerts ORDER BY created_at DESC");
    $concerts = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'concerts' => array_map(function($c) {
            return [
                'slug' => $c['slug'],
                'title' => $c['title'],
                'date' => $c['concert_date'],
                'createdAt' => (int)$c['created_at']
            ];
        }, $concerts)
    ]);
}

/**
 * Load concert data
 */
function loadConcert($pdo, $slug) {
    $stmt = $pdo->prepare("SELECT * FROM concerts WHERE slug = ?");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'slug' => $row['slug'],
            'title' => $row['title'],
            'date' => $row['concert_date'],
            'performers' => json_decode($row['performers'] ?: '[]'),
            'programmeItems' => json_decode($row['programme_items'] ?: '[]'),
            'updatedAt' => (int)$row['updated_at']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false
        ]);
    }
}

/**
 * Save concert data
 */
function saveConcert($pdo, $slug) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    $title = $input['title'] ?? 'Untitled Concert';
    $concertDate = $input['date'] ?? null;
    $performers = json_encode($input['performers'] ?? []);
    $programmeItems = json_encode($input['programmeItems'] ?? []);
    $updatedAt = round(microtime(true) * 1000);

    $stmt = $pdo->prepare("
        INSERT INTO concerts (slug, title, concert_date, performers, programme_items, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            concert_date = VALUES(concert_date),
            performers = VALUES(performers),
            programme_items = VALUES(programme_items),
            updated_at = VALUES(updated_at)
    ");

    $stmt->execute([$slug, $title, $concertDate, $performers, $programmeItems, $updatedAt, $updatedAt]);

    echo json_encode([
        'success' => true,
        'updatedAt' => $updatedAt
    ]);
}

/**
 * Create a new concert
 */
function createConcert($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Title required']);
        return;
    }

    $title = trim($input['title']);
    $slug = generateSlug($title);
    $concertDate = $input['date'] ?? null;
    $now = round(microtime(true) * 1000);

    // Check if slug already exists, append number if needed
    $baseSlug = $slug;
    $counter = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM concerts WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() == 0) break;
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }

    $stmt = $pdo->prepare("
        INSERT INTO concerts (slug, title, concert_date, performers, programme_items, created_at, updated_at)
        VALUES (?, ?, ?, '[]', '[]', ?, ?)
    ");

    $stmt->execute([$slug, $title, $concertDate, $now, $now]);

    echo json_encode([
        'success' => true,
        'slug' => $slug,
        'title' => $title
    ]);
}

/**
 * Delete a concert
 */
function deleteConcert($pdo, $slug) {
    $stmt = $pdo->prepare("DELETE FROM concerts WHERE slug = ?");
    $stmt->execute([$slug]);

    echo json_encode([
        'success' => true
    ]);
}

/**
 * Poll for updates since a given timestamp
 */
function pollConcert($pdo, $slug, $since) {
    $stmt = $pdo->prepare("SELECT * FROM concerts WHERE slug = ? AND updated_at > ?");
    $stmt->execute([$slug, $since]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'hasUpdate' => true,
            'title' => $row['title'],
            'date' => $row['concert_date'],
            'performers' => json_decode($row['performers'] ?: '[]'),
            'programmeItems' => json_decode($row['programme_items'] ?: '[]'),
            'updatedAt' => (int)$row['updated_at']
        ]);
    } else {
        echo json_encode([
            'hasUpdate' => false
        ]);
    }
}
