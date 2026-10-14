<?php
// ============================================================
//  api_notes.php — API GET /notes и GET /api/notes
//  КИМ 09.02.07-5-2027
// ============================================================

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// --- Только GET, иначе 400 ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный запрос'], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Проверка GET-параметров (если переданы) ---
foreach (['limit', 'id_user'] as $param) {
    if (isset($_GET[$param]) && $_GET[$param] !== '') {
        if (!ctype_digit((string)$_GET[$param]) || (int)$_GET[$param] <= 0) {
            http_response_code(400);
            echo json_encode(['error' => "Некорректный параметр: $param"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

try {
    // --- Основной запрос по правилам обработки ---
    $sql = "
        SELECT
            n.id                                  AS id,
            CONCAT(n.title, ' - ', u.login)       AS title_user,
            n.content                             AS content,
            DATE_FORMAT(n.created_at, '%d.%m.%Y') AS formatted_date
        FROM notes n
        JOIN users u ON u.id = n.id_user
    ";

    $params = [];
    if (!empty($_GET['id_user'])) {
        $sql .= " WHERE n.id_user = ?";
        $params[] = (int)$_GET['id_user'];
    }

    $sql .= " ORDER BY n.id";

    if (!empty($_GET['limit'])) {
        $sql .= " LIMIT " . (int)$_GET['limit'];
    }

    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // --- Данных нет = НЕ ошибка, отдаём [] с кодом 200 ---
    http_response_code(200);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (PDOException $e) {
    // --- Внутренняя ошибка сервера ---
    http_response_code(500);
    echo json_encode(
        ['error' => 'Ошибка подключения к базе данных'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}