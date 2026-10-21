<?php

declare(strict_types=1);

function e(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post(string $k, mixed $d = ''): mixed
{
    return $_POST[$k] ?? $d;
}

function get(string $k, mixed $d = ''): mixed
{
    return $_GET[$k] ?? $d;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    try {
        $st = db()->prepare('
            SELECT u.*, r.code AS role_code, r.name AS role_name,
                   s.code AS status_code, s.name AS status_name
            FROM user u
            JOIN user_role   r ON r.role_id   = u.role_id
            JOIN user_status s ON s.status_id = u.status_id
            WHERE u.user_id = ?
        ');
        $st->execute([$_SESSION['user_id']]);
        $u = $st->fetch();

        return $u ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('index.php?page=login');
    }
    if ($u['status_code'] === 'blocked') {
        session_destroy();
        redirect('index.php?page=login&blocked=1');
    }
    if ($u['status_code'] === 'deleted') {
        session_destroy();
        redirect('index.php?page=login');
    }

    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role_code'] !== 'admin') {
        redirect('index.php?page=profile');
    }

    return $u;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        die('CSRF-токен недействителен.');
    }
}

function log_action(string $login, string $action, ?int $userId = null): void
{
    try {
        db()->prepare('INSERT INTO action_log (user_id, login, action, ip) VALUES (?,?,?,?)')
            ->execute([$userId, $login, $action, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (PDOException $e) {
    }
}

function captcha_init(): array
{
    if (empty($_SESSION['captcha_order'])) {
        $order = [0, 1, 2, 3];
        shuffle($order);
        if ($order === [0, 1, 2, 3]) {
            shuffle($order);
        }
        $_SESSION['captcha_order'] = $order;
    }

    return $_SESSION['captcha_order'];
}

function captcha_render(): string
{
    $order = captcha_init();
    ob_start();
    ?>
    <div class="captcha-wrap">
        <p class="captcha-title">СОБЕРИТЕ КАРТИНКУ — КЛИКНИТЕ ПО ОДНОМУ КВАДРАТУ, ПОТОМ ПО ДРУГОМУ, ЧТОБЫ ПОМЕНЯТЬ ИХ МЕСТАМИ</p>

        <div class="captcha-sample">
            <span class="captcha-sample-label">Образец:</span>
            <div class="captcha-sample-img">
                <img src="assets/puzzle_0.png" alt="">
                <img src="assets/puzzle_1.png" alt="">
                <img src="assets/puzzle_2.png" alt="">
                <img src="assets/puzzle_3.png" alt="">
            </div>
        </div>

        <div class="puzzle-grid" data-captcha-grid>
            <?php foreach ($order as $piece): ?>
                <div class="puzzle-cell" data-piece="<?= (int)$piece ?>" onclick="selectCell(this)">
                    <img src="assets/puzzle_<?= (int)$piece ?>.png" alt="">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="captcha-actions">
            <button type="button" class="btn primary" onclick="checkPuzzle(this)">ПРОВЕРИТЬ</button>
            <button type="button" class="btn ghost" onclick="shufflePuzzle()">ПЕРЕМЕШАТЬ</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function upload_car_image(array $file): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Файл не выбран', 'empty' => true];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Ошибка загрузки файла (код ' . $file['error'] . ')'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Файл больше 5 МБ'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Разрешены только JPG, PNG, WEBP, GIF'];
    }

    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'Не удалось создать папку uploads/'];
        }
    }

    $ext      = $allowed[$mime];
    $filename = 'car_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target   = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'Не удалось сохранить файл'];
    }

    return ['ok' => true, 'path' => 'uploads/' . $filename];
}

function delete_car_image(?string $path): void
{
    if (!$path) {
        return;
    }
    if (strpos($path, 'uploads/') !== 0) {
        return;
    }
    $full = __DIR__ . '/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}