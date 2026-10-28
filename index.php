<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

$reqUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (in_array(rtrim($reqUri, '/'), ['/notes', '/api/notes'], true)) {
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(400);
        echo json_encode(['error' => 'Некорректный запрос: разрешён только GET'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $allowedParams = ['limit', 'id_user'];
    foreach ($_GET as $key => $value) {
        if (!in_array($key, $allowedParams, true)) {
            http_response_code(400);
            echo json_encode(['error' => "Некорректный параметр: $key"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (is_array($value)) {
            http_response_code(400);
            echo json_encode(['error' => "Некорректный тип параметра: $key"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($value !== '' && (!ctype_digit((string)$value) || (int)$value <= 0)) {
            http_response_code(400);
            echo json_encode(['error' => "Некорректное значение параметра: $key"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    try {
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
            $sql .= ' WHERE n.id_user = ?';
            $params[] = (int)$_GET['id_user'];
        }
        $sql .= ' ORDER BY n.id';
        if (!empty($_GET['limit'])) {
            $sql .= ' LIMIT ' . (int)$_GET['limit'];
        }

        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подключения к базе данных'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (($_GET['page'] ?? '') === 'admin_notes_download') {
    $u = current_user();
    if (!$u || $u['role_code'] !== 'admin') {
        http_response_code(403);
        exit('Доступ запрещён');
    }

    try {
        $st = db()->query("
            SELECT
                n.id                                  AS id,
                CONCAT(n.title, ' - ', u.login)       AS title_user,
                n.content                             AS content,
                DATE_FORMAT(n.created_at, '%d.%m.%Y') AS formatted_date
            FROM notes n
            JOIN users u ON u.id = n.id_user
            ORDER BY n.id
        ");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $filename = 'api_notes_' . date('Y-m-d_His') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Ошибка подключения к базе данных'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    db()->exec('
        UPDATE user
        SET failed_attempts = 0,
            locked_until    = NULL
        WHERE locked_until IS NOT NULL
          AND locked_until <= NOW()
    ');
} catch (Throwable $e) {
}

$page   = get('page', '');
$action = post('action', get('action', ''));

if ($action === 'logout') {
    $u = current_user();
    if ($u) {
        log_action($u['login'], 'Выход из системы', (int)$u['user_id']);
    }
    session_destroy();
    redirect('index.php?page=login');
}

if ($action === 'car_request' && is_post()) {
    csrf_check();

    $carId = (int)post('car_id');
    $name  = trim(post('name'));
    $phone = trim(post('phone'));
    $cmt   = trim(post('comment'));

    $errors = [];
    if ($carId <= 0) {
        $errors[] = 'Выберите автомобиль';
    }
    if ($name === '') {
        $errors[] = 'Укажите имя';
    }
    if ($phone === '') {
        $errors[] = 'Укажите телефон';
    }

    if (!$errors) {
        $st = db()->prepare('SELECT 1 FROM car WHERE car_id = ? AND deleted_at IS NULL');
        $st->execute([$carId]);
        if (!$st->fetch()) {
            $errors[] = 'Автомобиль не найден';
        }
    }

    if ($errors) {
        $_SESSION['request_error'] = implode('. ', $errors);
        redirect('index.php?page=home');
    }

    try {
        db()->prepare('
            INSERT INTO purchase_request (car_id, client_name, client_phone, comment, user_id)
            VALUES (?, ?, ?, ?, ?)
        ')->execute([
            $carId,
            $name,
            $phone,
            $cmt ?: null,
            $_SESSION['user_id'] ?? null,
        ]);

        log_action($name, "Заявка на покупку: car_id=$carId, тел=$phone");
        $_SESSION['request_ok'] = 'Заявка отправлена! Наш менеджер свяжется с вами.';
    } catch (PDOException $e) {
        $_SESSION['request_error'] = 'Не удалось сохранить заявку. Попробуйте позже.';
    }

    redirect('index.php?page=home');
}

if ($action === 'login' && is_post()) {
    csrf_check();
    $login    = trim(post('login'));
    $password = post('password');
    $order    = json_decode(post('captcha_order'), true);

    if (!is_array($order) || $order !== [0, 1, 2, 3]) {
        $_SESSION['login_error'] = 'Соберите картинку правильно';
        unset($_SESSION['captcha_order']);
        redirect('index.php?page=login');
    }

    if ($login === '' || $password === '') {
        $_SESSION['login_error'] = 'Заполни логин и пароль';
        redirect('index.php?page=login');
    }

    $st = db()->prepare('
        SELECT u.user_id, u.password_hash,
               u.failed_attempts, u.locked_until,
               s.code AS status_code
        FROM user u
        JOIN user_status s ON s.status_id = u.status_id
        WHERE u.login = ?
    ');
    $st->execute([$login]);
    $u = $st->fetch();

    if (!$u) {
        $_SESSION['login_error'] = 'Неверный логин или пароль';
        redirect('index.php?page=login');
    }
    if ($u['status_code'] === 'deleted') {
        $_SESSION['login_error'] = 'Аккаунт удалён';
        redirect('index.php?page=login');
    }
    if ($u['status_code'] === 'blocked') {
        $_SESSION['login_error'] = 'Аккаунт заблокирован администратором';
        redirect('index.php?page=login');
    }

    if (!empty($u['locked_until'])) {
        $lockedUntil = strtotime($u['locked_until']);
        $now         = time();

        if ($lockedUntil > $now) {
            $leftMin = (int)ceil(($lockedUntil - $now) / 60);
            $_SESSION['login_error'] = "Слишком много неудачных попыток. Попробуйте через {$leftMin} мин.";
            redirect('index.php?page=login');
        } else {
            db()->prepare('UPDATE user SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?')
                ->execute([(int)$u['user_id']]);
            $u['failed_attempts'] = 0;
            $u['locked_until']    = null;
        }
    }

    if (!password_verify($password, $u['password_hash'])) {
        $attempts = (int)$u['failed_attempts'] + 1;

        if ($attempts >= 3) {
            db()->prepare('
                UPDATE user
                SET failed_attempts = 0,
                    locked_until    = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                WHERE user_id = ?
            ')->execute([(int)$u['user_id']]);

            log_action($login, 'Аккаунт заблокирован на 15 минут (3 неудачных попытки)', (int)$u['user_id']);
            $_SESSION['login_error'] = 'Слишком много неудачных попыток. Аккаунт заблокирован на 15 минут.';
            redirect('index.php?page=login');
        }

        db()->prepare('UPDATE user SET failed_attempts = ? WHERE user_id = ?')
            ->execute([$attempts, (int)$u['user_id']]);

        log_action($login, "Неудачная попытка входа ($attempts/3)", (int)$u['user_id']);

        $left = 3 - $attempts;
        $_SESSION['login_error'] = "Неверный логин или пароль. Осталось попыток: $left";
        redirect('index.php?page=login');
    }

    $_SESSION['user_id'] = (int)$u['user_id'];
    unset($_SESSION['captcha_order']);

    db()->prepare('
        UPDATE user
        SET failed_attempts = 0,
            locked_until    = NULL,
            last_login_at   = NOW()
        WHERE user_id = ?
    ')->execute([(int)$u['user_id']]);

    log_action($login, 'Успешный вход', (int)$u['user_id']);

    $role = db()->prepare('SELECT r.code FROM user u JOIN user_role r ON r.role_id = u.role_id WHERE u.user_id = ?');
    $role->execute([(int)$u['user_id']]);
    $roleCode = $role->fetchColumn();

    redirect($roleCode === 'admin' ? 'index.php?page=admin' : 'index.php?page=profile');
}

if ($action === 'register' && is_post()) {
    csrf_check();
    $fio    = trim(post('fio'));
    $login  = trim(post('login'));
    $pass   = post('password');
    $pass2  = post('confirm');
    $order  = json_decode(post('captcha_order'), true);

    $errors = [];
    if ($fio === '') {
        $errors['fio'] = 'Укажи ФИО';
    }
    if (!preg_match('/^[a-zA-Z0-9._]{3,30}$/', $login)) {
        $errors['login'] = 'Логин: 3–30 символов, латиница, цифры';
    }
    if (strlen($pass) < 6) {
        $errors['password'] = 'Минимум 6 символов';
    }
    if ($pass !== $pass2) {
        $errors['confirm'] = 'Пароли не совпадают';
    }

    if (!is_array($order) || $order !== [0, 1, 2, 3]) {
        $errors['general'] = 'Соберите картинку правильно';
        unset($_SESSION['captcha_order']);
    }

    if (!$errors) {
        $st = db()->prepare('SELECT 1 FROM user WHERE login = ?');
        $st->execute([$login]);
        if ($st->fetch()) {
            $errors['login'] = 'Такой логин уже занят';
        }
    }

    if ($errors) {
        $_SESSION['reg_errors'] = $errors;
        $_SESSION['reg_form']   = $_POST;
        redirect('index.php?page=register');
    }

    $parts  = preg_split('/\s+/', $fio);
    $last   = $parts[0] ?? '';
    $first  = $parts[1] ?? '';
    $middle = $parts[2] ?? null;

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    try {
        db()->prepare('
            INSERT INTO user (login, password_hash, last_name, first_name, middle_name,
                              email, role_id, status_id)
            VALUES (?, ?, ?, ?, ?, ?,
                    (SELECT role_id   FROM user_role   WHERE code="user"),
                    (SELECT status_id FROM user_status WHERE code="active"))
        ')->execute([$login, $hash, $last, $first, $middle, $login . '@autocatalog.ru']);
    } catch (PDOException $ex) {
        $_SESSION['reg_errors'] = ['general' => 'Ошибка БД: ' . $ex->getMessage()];
        $_SESSION['reg_form']   = $_POST;
        redirect('index.php?page=register');
    }
    log_action($login, 'Создана новая учётная запись');
    $_SESSION['reg_success'] = 'Аккаунт создан. Теперь войди.';
    redirect('index.php?page=login');
}

if ($action === 'profile_update' && is_post()) {
    csrf_check();
    $u = require_login();
    $last   = trim(post('last_name'));
    $first  = trim(post('first_name'));
    $middle = trim(post('middle_name'));
    $email  = trim(post('email'));

    $err = [];
    if ($last === '') {
        $err[] = 'Фамилия обязательна';
    }
    if ($first === '') {
        $err[] = 'Имя обязательно';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err[] = 'Некорректный email';
    }

    if (!$err) {
        $st = db()->prepare('SELECT 1 FROM user WHERE email = ? AND user_id != ?');
        $st->execute([$email, $u['user_id']]);
        if ($st->fetch()) {
            $err[] = 'Этот email уже занят';
        }
    }

    if ($err) {
        $_SESSION['profile_error'] = implode('. ', $err);
        redirect('index.php?page=profile');
    }

    db()->prepare('UPDATE user SET last_name=?, first_name=?, middle_name=?, email=? WHERE user_id=?')
        ->execute([$last, $first, $middle ?: null, $email, (int)$u['user_id']]);
    log_action($u['login'], 'Обновлён профиль', (int)$u['user_id']);
    $_SESSION['profile_ok'] = 'Профиль обновлён';
    redirect('index.php?page=profile');
}

if ($action === 'profile_password' && is_post()) {
    csrf_check();
    $u = require_login();
    $current = post('current_password');
    $new     = post('new_password');
    $new2    = post('new_password2');

    $err = [];
    if (!password_verify($current, $u['password_hash'])) {
        $err[] = 'Текущий пароль неверный';
    }
    if (strlen($new) < 6) {
        $err[] = 'Новый пароль — минимум 6 символов';
    }
    if ($new !== $new2) {
        $err[] = 'Новые пароли не совпадают';
    }

    if (empty($err) && password_verify($new, $u['password_hash'])) {
        $err[] = 'Новый пароль совпадает с текущим. Придумайте другой.';
    }

    if ($err) {
        $_SESSION['profile_error'] = implode('. ', $err);
        redirect('index.php?page=profile&tab=password');
    }

    $hash = password_hash($new, PASSWORD_BCRYPT);
    db()->prepare('UPDATE user SET password_hash=?, password_changed_at=NOW() WHERE user_id=?')
        ->execute([$hash, (int)$u['user_id']]);
    log_action($u['login'], 'Смена пароля', (int)$u['user_id']);
    $_SESSION['profile_ok'] = 'Пароль успешно изменён';
    redirect('index.php?page=profile&tab=password');
}

if ($action === 'admin_block' && is_post()) {
    csrf_check();
    require_admin();
    $uid = (int)post('user_id');
    if ($uid !== (int)$_SESSION['user_id']) {
        db()->prepare('UPDATE user SET status_id = (SELECT status_id FROM user_status WHERE code="blocked") WHERE user_id = ?')
            ->execute([$uid]);
        log_action('admin', "Блокировка user_id=$uid", (int)$_SESSION['user_id']);
    }
    redirect('index.php?page=admin');
}

if ($action === 'admin_unblock' && is_post()) {
    csrf_check();
    require_admin();
    $uid = (int)post('user_id');
    db()->prepare('UPDATE user SET status_id = (SELECT status_id FROM user_status WHERE code="active"), failed_attempts = 0, locked_until = NULL WHERE user_id = ?')
        ->execute([$uid]);
    log_action('admin', "Разблокировка user_id=$uid", (int)$_SESSION['user_id']);
    redirect('index.php?page=admin');
}

if ($action === 'admin_delete_user' && is_post()) {
    csrf_check();
    require_admin();
    $uid = (int)post('user_id');
    if ($uid !== (int)$_SESSION['user_id']) {
        db()->prepare('UPDATE user SET status_id = (SELECT status_id FROM user_status WHERE code="deleted"), deleted_at = NOW() WHERE user_id = ?')
            ->execute([$uid]);
        log_action('admin', "Удалён user_id=$uid", (int)$_SESSION['user_id']);
    }
    redirect('index.php?page=admin');
}

if ($action === 'admin_restore_user' && is_post()) {
    csrf_check();
    require_admin();
    $uid = (int)post('user_id');
    db()->prepare('UPDATE user SET status_id = (SELECT status_id FROM user_status WHERE code="active"), deleted_at = NULL WHERE user_id = ?')
        ->execute([$uid]);
    log_action('admin', "Восстановлен user_id=$uid", (int)$_SESSION['user_id']);
    redirect('index.php?page=admin');
}

if ($action === 'admin_grant_admin' && is_post()) {
    csrf_check();
    require_admin();
    $uid = (int)post('user_id');

    if ($uid === (int)$_SESSION['user_id']) {
        $_SESSION['admin_error'] = 'Вы уже администратор';
        redirect('index.php?page=admin');
    }

    $st = db()->prepare('SELECT login FROM user WHERE user_id = ? AND deleted_at IS NULL');
    $st->execute([$uid]);
    $target = $st->fetch();
    if (!$target) {
        $_SESSION['admin_error'] = 'Пользователь не найден';
        redirect('index.php?page=admin');
    }

    db()->prepare('UPDATE user SET role_id = (SELECT role_id FROM user_role WHERE code="admin") WHERE user_id = ?')
        ->execute([$uid]);
    log_action('admin', "Выдана роль администратора для {$target['login']} (id=$uid)", (int)$_SESSION['user_id']);
    $_SESSION['admin_ok'] = "Пользователь {$target['login']} теперь администратор";
    redirect('index.php?page=admin');
}

if ($action === 'admin_revoke_admin' && is_post()) {
    csrf_check();
    require_admin();
    $uid = (int)post('user_id');

    if ($uid === (int)$_SESSION['user_id']) {
        $_SESSION['admin_error'] = 'Нельзя забрать роль у самого себя';
        redirect('index.php?page=admin');
    }

    $st = db()->prepare('
        SELECT COUNT(*) FROM user u
        JOIN user_role r ON r.role_id = u.role_id
        WHERE r.code = "admin" AND u.deleted_at IS NULL
    ');
    $st->execute();
    $adminsCount = (int)$st->fetchColumn();

    if ($adminsCount <= 1) {
        $_SESSION['admin_error'] = 'Нельзя забрать роль у последнего администратора';
        redirect('index.php?page=admin');
    }

    $st = db()->prepare('SELECT login FROM user WHERE user_id = ?');
    $st->execute([$uid]);
    $target = $st->fetch();

    db()->prepare('UPDATE user SET role_id = (SELECT role_id FROM user_role WHERE code="user") WHERE user_id = ?')
        ->execute([$uid]);
    log_action('admin', 'Забрана роль администратора у ' . ($target['login'] ?? "id=$uid"), (int)$_SESSION['user_id']);
    $_SESSION['admin_ok'] = 'Роль администратора снята';
    redirect('index.php?page=admin');
}

if ($action === 'car_save' && is_post()) {
    csrf_check();
    require_admin();
    $id       = (int)post('car_id');
    $vin      = strtoupper(trim(post('vin')));
    $brand    = trim(post('brand'));
    $model    = trim(post('model'));
    $year     = (int)post('year');
    $color    = trim(post('color'));
    $power    = (int)post('power_hp');
    $price    = (float)post('price');
    $bodyType = trim(post('body_type'));
    $fuel     = trim(post('fuel'));

    $err = [];
    if ($vin === '') {
        $err[] = 'VIN обязателен';
    }
    if ($brand === '') {
        $err[] = 'Марка обязательна';
    }
    if ($model === '') {
        $err[] = 'Модель обязательна';
    }
    if ($year < 1900 || $year > 2100) {
        $err[] = 'Год некорректен';
    }

    if ($err) {
        $_SESSION['car_error'] = implode('. ', $err);
        redirect('index.php?page=admin_car_form' . ($id ? "&id=$id" : ''));
    }

    $newImagePath = null;
    $oldImagePath = null;

    if ($id > 0) {
        $st = db()->prepare('SELECT image_path FROM car WHERE car_id = ?');
        $st->execute([$id]);
        $oldImagePath = $st->fetchColumn() ?: null;
    }

    if (!empty($_FILES['image']['name'])) {
        $up = upload_car_image($_FILES['image']);
        if ($up['ok']) {
            $newImagePath = $up['path'];
        } elseif (empty($up['empty'])) {
            $_SESSION['car_error'] = $up['error'];
            redirect('index.php?page=admin_car_form' . ($id ? "&id=$id" : ''));
        }
    }

    $removeImage = post('remove_image') === '1';
    if ($removeImage && $oldImagePath && !$newImagePath) {
        delete_car_image($oldImagePath);
        db()->prepare('UPDATE car SET image_path = NULL WHERE car_id = ?')->execute([$id]);
        $oldImagePath = null;
    }

    try {
        if ($id > 0) {
            if ($newImagePath && $oldImagePath) {
                delete_car_image($oldImagePath);
            }

            if ($newImagePath) {
                db()->prepare('UPDATE car SET vin=?, brand=?, model=?, year=?, color=?, power_hp=?, price=?, body_type=?, fuel=?, image_path=? WHERE car_id=?')
                    ->execute([$vin, $brand, $model, $year, $color ?: null, $power ?: null, $price, $bodyType ?: null, $fuel ?: null, $newImagePath, $id]);
            } else {
                db()->prepare('UPDATE car SET vin=?, brand=?, model=?, year=?, color=?, power_hp=?, price=?, body_type=?, fuel=? WHERE car_id=?')
                    ->execute([$vin, $brand, $model, $year, $color ?: null, $power ?: null, $price, $bodyType ?: null, $fuel ?: null, $id]);
            }
            log_action('admin', "Изменён авто #$id ($brand $model)", (int)$_SESSION['user_id']);
        } else {
            db()->prepare('INSERT INTO car (vin, brand, model, year, color, power_hp, price, body_type, fuel, image_path) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$vin, $brand, $model, $year, $color ?: null, $power ?: null, $price, $bodyType ?: null, $fuel ?: null, $newImagePath]);
            log_action('admin', "Добавлен авто $brand $model", (int)$_SESSION['user_id']);
        }
    } catch (PDOException $e) {
        $_SESSION['car_error'] = 'Ошибка БД: ' . $e->getMessage();
        redirect('index.php?page=admin_car_form' . ($id ? "&id=$id" : ''));
    }
    redirect('index.php?page=admin_cars');
}

if ($action === 'car_delete' && is_post()) {
    csrf_check();
    require_admin();
    $id = (int)post('car_id');

    $st = db()->prepare('SELECT image_path FROM car WHERE car_id = ?');
    $st->execute([$id]);
    $img = $st->fetchColumn();
    if ($img) {
        delete_car_image($img);
    }

    db()->prepare('UPDATE car SET deleted_at = NOW() WHERE car_id = ?')->execute([$id]);
    log_action('admin', "Удалён авто #$id", (int)$_SESSION['user_id']);
    redirect('index.php?page=admin_cars');
}

if ($action === 'request_set_status' && is_post()) {
    csrf_check();
    require_admin();
    $rid    = (int)post('request_id');
    $status = post('status');

    $allowed = ['new', 'in_progress', 'done', 'rejected'];
    if (in_array($status, $allowed, true)) {
        db()->prepare('UPDATE purchase_request SET status = ? WHERE request_id = ?')
            ->execute([$status, $rid]);
        log_action('admin', "Заявка #$rid → статус $status", (int)$_SESSION['user_id']);
    }
    redirect('index.php?page=admin_requests');
}

$user = current_user();
$authPages   = ['login', 'register'];
$adminPages  = ['admin', 'admin_cars', 'admin_car_form', 'admin_logs', 'admin_users', 'admin_notes', 'admin_notes_download', 'admin_requests', 'admin_system'];
$userPages   = ['profile'];
$publicPages = ['home', 'cars', 'about', 'promo', 'contacts', 'dashboard'];

if ($page === '') {
    $page = $user ? 'dashboard' : 'home';
}
if (!$user && !in_array($page, $authPages) && !in_array($page, $publicPages)) {
    redirect('index.php?page=login');
}
if ($user && in_array($page, $authPages)) {
    redirect($user['role_code'] === 'admin' ? 'index.php?page=admin' : 'index.php?page=profile');
}
if (in_array($page, $adminPages) && (!$user || $user['role_code'] !== 'admin')) {
    redirect('index.php?page=profile');
}
if (in_array($page, $userPages) && !$user) {
    redirect('index.php?page=login');
}

$isAuth = in_array($page, $authPages);
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="<?= $isAuth ? 'auth-body' : 'app-body' ?>">

<?php if ($isAuth): ?>

<div class="auth-box">
    <div class="auth-card">

        <div class="auth-head">
            <div class="auth-logo">
                <span class="logo-mark">SC</span>
                <b>АВТОКАТАЛОГ</b>
            </div>
            <a href="index.php" class="auth-close">×</a>
        </div>

        <div class="auth-tabs">
            <a href="index.php?page=login"    class="auth-tab <?= $page === 'login' ? 'active' : '' ?>">ВХОД</a>
            <a href="index.php?page=register" class="auth-tab <?= $page === 'register' ? 'active' : '' ?>">РЕГИСТРАЦИЯ</a>
        </div>

<?php if ($page === 'login'):
    $err = $_SESSION['login_error'] ?? null;
    unset($_SESSION['login_error']);
    $ok  = $_SESSION['reg_success'] ?? null;
    unset($_SESSION['reg_success']);
?>
    <div class="demo-hint">Демо: <b>admin / Admin123!</b></div>
    <?php if ($ok): ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

    <form method="post" action="index.php" class="auth-form-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="captcha_order" value="">

        <div class="field">
            <label>Логин</label>
            <input type="text" name="login" placeholder="Введите логин" required autofocus>
        </div>
        <div class="field">
            <label>Пароль</label>
            <div class="input-with-icon">
                <input type="password" name="password" placeholder="Введите пароль" required>
                <button type="button" class="eye-btn" onclick="togglePass(this)">👁</button>
            </div>
        </div>

        <?= captcha_render() ?>

        <button class="btn primary block mt-3" type="submit">ВОЙТИ</button>
    </form>
    <p class="auth-switch">Нет аккаунта? <a href="index.php?page=register">Зарегистрируйтесь</a></p>

<?php elseif ($page === 'register'):
    $errors = $_SESSION['reg_errors'] ?? [];
    unset($_SESSION['reg_errors']);
    $f      = $_SESSION['reg_form']   ?? [];
    unset($_SESSION['reg_form']);
?>
    <?php if (!empty($errors['general'])): ?><div class="alert err"><?= e($errors['general']) ?></div><?php endif; ?>
    <form method="post" action="index.php" class="auth-form-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="captcha_order" value="">

        <div class="field"><label>ФИО</label><input type="text" name="fio" placeholder="Иван Иванов" required value="<?= e($f['fio'] ?? '') ?>">
            <?php if (!empty($errors['fio'])): ?><span class="err"><?= e($errors['fio']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label>Логин</label><input type="text" name="login" placeholder="Уникальный логин" required value="<?= e($f['login'] ?? '') ?>">
            <?php if (!empty($errors['login'])): ?><span class="err"><?= e($errors['login']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label>Пароль</label><input type="password" name="password" placeholder="Минимум 6 символов" required>
            <?php if (!empty($errors['password'])): ?><span class="err"><?= e($errors['password']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label>Подтвердите пароль</label><input type="password" name="confirm" placeholder="Повторите пароль" required>
            <?php if (!empty($errors['confirm'])): ?><span class="err"><?= e($errors['confirm']) ?></span><?php endif; ?>
        </div>

        <?= captcha_render() ?>

        <button class="btn primary block mt-3" type="submit">ЗАРЕГИСТРИРОВАТЬСЯ</button>
    </form>
    <p class="auth-switch">Уже есть аккаунт? <a href="index.php?page=login">Войти</a></p>

<?php endif; ?>

    </div>
</div>

<?php else: ?>

<?php
function admin_layout_start(string $active, array $user, string $title, string $subtitle = ''): void
{
    echo '<div class="admin-layout">';
    echo '<aside class="admin-side">';
    echo '<div class="admin-side-head">';
    echo '<div class="logo-mark">SC</div>';
    echo '<div><b>АДМИН-ПАНЕЛЬ</b><span>Автокаталог</span></div>';
    echo '</div>';
    echo '<nav class="admin-nav">';
    echo '<a href="index.php?page=admin" class="' . ($active === 'users' ? 'active' : '') . '">👥 Пользователи</a>';
    echo '<a href="index.php?page=admin_cars" class="' . ($active === 'cars' ? 'active' : '') . '">🚗 Автомобили</a>';
    echo '<a href="index.php?page=admin_requests" class="' . ($active === 'requests' ? 'active' : '') . '">📨 Заявки на покупку</a>';
    echo '<a href="index.php?page=admin_notes" class="' . ($active === 'notes' ? 'active' : '') . '">📝 Заметки (API)</a>';
    echo '<a href="index.php?page=admin_system" class="' . ($active === 'system' ? 'active' : '') . '">🖥️ Системная информация</a>';
    echo '<a href="index.php?page=admin_logs" class="' . ($active === 'logs' ? 'active' : '') . '">📋 Журнал действий</a>';
    echo '<div class="admin-nav-sep"></div>';
    echo '<a href="index.php?page=profile">👤 Мой профиль</a>';
    echo '<a href="index.php">🏠 На сайт</a>';
    echo '<a href="index.php?action=logout" class="logout">🚪 Выйти</a>';
    echo '</nav>';
    echo '<div class="admin-side-user">';
    echo '<div class="avatar">' . e(mb_substr($user['first_name'], 0, 1)) . '</div>';
    echo '<div><b>' . e($user['fio']) . '</b><span>' . e($user['role_name']) . '</span></div>';
    echo '</div>';
    echo '</aside>';
    echo '<main class="admin-main">';
    echo '<div class="admin-top">';
    echo '<div><h1>' . e($title) . '</h1>';
    if ($subtitle) {
        echo '<p class="text-dim mt-1">' . e($subtitle) . '</p>';
    }
    echo '</div>';
    echo '<div class="admin-top-actions">';
    echo '<a href="index.php?page=profile" class="btn ghost small">👤 Профиль</a>';
    echo '<a href="index.php?action=logout" class="btn outline small">Выйти</a>';
    echo '</div>';
    echo '</div>';
}

function admin_layout_end(): void
{
    echo '</main></div>';
}

if ($page === 'profile'):
    $isAdmin = ($user['role_code'] === 'admin');
    $tab     = get('tab', 'info');
    $perr    = $_SESSION['profile_error'] ?? null;
    unset($_SESSION['profile_error']);
    $pok     = $_SESSION['profile_ok']    ?? null;
    unset($_SESSION['profile_ok']);
?>
<div class="admin-layout">
    <aside class="admin-side">
        <div class="admin-side-head">
            <div class="logo-mark">SC</div>
            <div><b><?= $isAdmin ? 'АДМИН-ПАНЕЛЬ' : 'ЛИЧНЫЙ КАБИНЕТ' ?></b><span>Автокаталог</span></div>
        </div>
        <nav class="admin-nav">
            <?php if ($isAdmin): ?>
                <a href="index.php?page=admin">👥 Пользователи</a>
                <a href="index.php?page=admin_cars">🚗 Автомобили</a>
                <a href="index.php?page=admin_requests">📨 Заявки на покупку</a>
                <a href="index.php?page=admin_notes">📝 Заметки (API)</a>
                <a href="index.php?page=admin_system">🖥️ Системная информация</a>
                <a href="index.php?page=admin_logs">📋 Журнал действий</a>
                <div class="admin-nav-sep"></div>
            <?php else: ?>
                <a href="index.php?page=profile" class="active">👤 Мой профиль</a>
                <a href="index.php?page=cars">🚗 Каталог авто</a>
                <a href="index.php?page=contacts">📞 Связаться</a>
                <div class="admin-nav-sep"></div>
            <?php endif; ?>
            <a href="index.php?page=profile">👤 Профиль</a>
            <a href="index.php">🏠 На сайт</a>
            <a href="index.php?action=logout" class="logout">🚪 Выйти</a>
        </nav>
        <div class="admin-side-user">
            <div class="avatar"><?= e(mb_substr($user['first_name'], 0, 1)) ?></div>
            <div><b><?= e($user['fio']) ?></b><span><?= e($user['role_name']) ?></span></div>
        </div>
    </aside>

    <main class="admin-main">
        <div class="admin-top">
            <div>
                <h1>Мой профиль</h1>
                <p class="text-dim mt-1">Управление личными данными и безопасностью</p>
            </div>
            <div class="admin-top-actions">
                <a href="index.php" class="btn ghost small">🏠 На сайт</a>
                <a href="index.php?action=logout" class="btn outline small">Выйти</a>
            </div>
        </div>

        <?php if ($perr): ?><div class="alert err"><?= e($perr) ?></div><?php endif; ?>
        <?php if ($pok):  ?><div class="alert ok"><?= e($pok) ?></div><?php endif; ?>

        <div class="profile-head">
            <div class="profile-avatar"><?= e(mb_substr($user['first_name'], 0, 1)) ?></div>
            <div class="profile-info">
                <h2><?= e($user['fio']) ?></h2>
                <p class="text-dim">@<?= e($user['login']) ?> · <?= e($user['email']) ?></p>
                <div class="profile-badges">
                    <span class="badge <?= $isAdmin ? 'yellow' : 'gray' ?>"><?= e($user['role_name']) ?></span>
                    <span class="badge green"><?= e($user['status_name']) ?></span>
                </div>
            </div>
        </div>

        <div class="profile-tabs">
            <a href="index.php?page=profile&tab=info"
               class="profile-tab <?= $tab === 'info' ? 'active' : '' ?>">📝 Личные данные</a>
            <a href="index.php?page=profile&tab=password"
               class="profile-tab <?= $tab === 'password' ? 'active' : '' ?>">🔒 Смена пароля</a>
            <a href="index.php?page=profile&tab=security"
               class="profile-tab <?= $tab === 'security' ? 'active' : '' ?>">⚙️ Безопасность</a>
        </div>

        <?php if ($tab === 'info'): ?>
            <div class="admin-card admin-form-card">
                <h3>Личные данные</h3>
                <form method="post" action="index.php" class="admin-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile_update">
                    <div class="form-grid">
                        <div class="field"><label>Фамилия *</label><input type="text" name="last_name" required value="<?= e($user['last_name']) ?>"></div>
                        <div class="field"><label>Имя *</label><input type="text" name="first_name" required value="<?= e($user['first_name']) ?>"></div>
                        <div class="field"><label>Отчество</label><input type="text" name="middle_name" value="<?= e($user['middle_name'] ?? '') ?>"></div>
                        <div class="field"><label>Email *</label><input type="email" name="email" required value="<?= e($user['email']) ?>"></div>
                        <div class="field"><label>Логин</label><input type="text" value="<?= e($user['login']) ?>" disabled><span class="hint">Логин нельзя изменить</span></div>
                        <div class="field"><label>Роль</label><input type="text" value="<?= e($user['role_name']) ?>" disabled></div>
                    </div>
                    <div class="admin-form-actions">
                        <button class="btn primary" type="submit">💾 Сохранить изменения</button>
                    </div>
                </form>
            </div>

        <?php elseif ($tab === 'password'): ?>
            <div class="admin-card admin-form-card">
                <h3>Смена пароля</h3>
                <p class="text-dim mb-3">Пароль должен быть минимум 6 символов и отличаться от текущего.</p>
                <form method="post" action="index.php" class="admin-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile_password">
                    <div class="form-grid">
                        <div class="field" style="grid-column: 1 / -1">
                            <label>Текущий пароль *</label>
                            <div class="input-with-icon">
                                <input type="password" name="current_password" required>
                                <button type="button" class="eye-btn" onclick="togglePass(this)">👁</button>
                            </div>
                        </div>
                        <div class="field">
                            <label>Новый пароль *</label>
                            <div class="input-with-icon">
                                <input type="password" name="new_password" required minlength="6">
                                <button type="button" class="eye-btn" onclick="togglePass(this)">👁</button>
                            </div>
                        </div>
                        <div class="field">
                            <label>Повторите новый пароль *</label>
                            <div class="input-with-icon">
                                <input type="password" name="new_password2" required minlength="6">
                                <button type="button" class="eye-btn" onclick="togglePass(this)">👁</button>
                            </div>
                        </div>
                    </div>
                    <div class="admin-form-actions">
                        <button class="btn primary" type="submit">🔒 Сменить пароль</button>
                    </div>
                </form>
            </div>

        <?php elseif ($tab === 'security'): ?>
            <div class="admin-card admin-form-card">
                <h3>Информация о безопасности</h3>
                <table class="admin-table" style="margin-top:16px">
                    <tbody>
                        <tr><td><b>Логин</b></td><td><?= e($user['login']) ?></td></tr>
                        <tr><td><b>Email</b></td><td><?= e($user['email']) ?></td></tr>
                        <tr><td><b>Роль</b></td><td><span class="badge <?= $isAdmin ? 'yellow' : 'gray' ?>"><?= e($user['role_name']) ?></span></td></tr>
                        <tr><td><b>Статус</b></td><td><span class="badge green"><?= e($user['status_name']) ?></span></td></tr>
                        <tr><td><b>Дата регистрации</b></td><td><?= e($user['created_at']) ?></td></tr>
                        <tr><td><b>Последний вход</b></td><td><?= e($user['last_login_at'] ?? '—') ?></td></tr>
                        <tr><td><b>Пароль изменён</b></td><td><?= e($user['password_changed_at'] ?? '—') ?></td></tr>
                        <tr><td><b>Неудачных попыток</b></td><td><?= (int)$user['failed_attempts'] ?></td></tr>
                        <tr><td><b>Заблокирован до</b></td><td><?= e($user['locked_until'] ?? '—') ?></td></tr>
                    </tbody>
                </table>
                <div class="admin-form-actions">
                    <a href="index.php?page=profile&tab=password" class="btn primary">🔒 Сменить пароль</a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php elseif ($page === 'admin' || $page === 'admin_users'):
    $users = db()->query('
        SELECT u.user_id, u.login, u.fio, u.email,
               r.name AS role_name, r.code AS role_code,
               s.code AS status_code, s.name AS status_name,
               u.created_at, u.locked_until, u.failed_attempts
        FROM user u
        JOIN user_role   r ON r.role_id   = u.role_id
        JOIN user_status s ON s.status_id = u.status_id
        ORDER BY u.created_at DESC
    ')->fetchAll();

    $stats = db()->query('
        SELECT
          (SELECT COUNT(*) FROM user WHERE deleted_at IS NULL) AS users_cnt,
          (SELECT COUNT(*) FROM car  WHERE deleted_at IS NULL) AS cars_cnt,
          (SELECT COUNT(*) FROM action_log) AS logs_cnt
    ')->fetch();

    admin_layout_start('users', $user, 'Пользователи', 'Всего: ' . count($users));
?>

    <?php if (!empty($_SESSION['admin_ok'])): ?>
        <div class="alert ok"><?= e($_SESSION['admin_ok']) ?></div>
        <?php unset($_SESSION['admin_ok']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['admin_error'])): ?>
        <div class="alert err"><?= e($_SESSION['admin_error']) ?></div>
        <?php unset($_SESSION['admin_error']); ?>
    <?php endif; ?>

    <div class="admin-stats">
        <div class="admin-stat"><b><?= (int)$stats['users_cnt'] ?></b><span>Пользователей</span></div>
        <div class="admin-stat"><b><?= (int)$stats['cars_cnt'] ?></b><span>Автомобилей</span></div>
        <div class="admin-stat"><b><?= (int)$stats['logs_cnt'] ?></b><span>Записей в журнале</span></div>
    </div>

    <div class="admin-card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Email</th>
                    <th>Права</th>
                    <th>Статус</th>
                    <th>Регистрация</th>
                    <th style="text-align:right">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="<?= $u['status_code'] === 'deleted' ? 'row-deleted' : '' ?>">
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar"><?= e(mb_substr($u['fio'], 0, 1)) ?></div>
                            <div>
                                <b><?= e($u['fio']) ?></b>
                                <span class="text-dim">@<?= e($u['login']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <?php if ($u['role_code'] === 'admin'): ?>
                            <span class="badge yellow">👑 <?= e($u['role_name']) ?></span>
                        <?php else: ?>
                            <span class="badge gray"><?= e($u['role_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                            $cls = match ($u['status_code']) {
                                'active'  => 'green',
                                'blocked' => 'red',
                                'deleted' => 'gray',
                                default   => 'gray',
                            };

                            $tempLocked = !empty($u['locked_until']) && strtotime($u['locked_until']) > time();
                        ?>
                        <span class="badge <?= $cls ?>"><?= e($u['status_name']) ?></span>
                        <?php if ($tempLocked): ?>
                            <br><span class="badge red" style="margin-top:6px">⏱ до <?= e(date('H:i', strtotime($u['locked_until']))) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(date('d.m.Y', strtotime($u['created_at']))) ?></td>
                    <td class="row-actions">
                        <?php if ($u['user_id'] == $user['user_id']): ?>
                            <span class="text-dim" style="font-size:12px">— это вы —</span>
                        <?php else: ?>

                            <?php if ($u['role_code'] === 'admin'): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('Снять роль администратора?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="admin_revoke_admin">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                    <button class="btn ghost small">👑 Забрать</button>
                                </form>
                            <?php elseif ($u['status_code'] !== 'deleted'): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('Выдать роль администратора?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="admin_grant_admin">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                    <button class="btn primary small">👑 В админы</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($u['status_code'] === 'deleted'): ?>
                                <form method="post" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="admin_restore_user">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                    <button class="btn ghost small">↺ Восстановить</button>
                                </form>
                            <?php elseif ($u['status_code'] === 'blocked' || $tempLocked): ?>
                                <form method="post" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="admin_unblock">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                    <button class="btn outline small">🔓 Разблокировать</button>
                                </form>
                                <?php if ($u['status_code'] === 'blocked'): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('Удалить пользователя?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="admin_delete_user">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                    <button class="btn danger small">🗑 Удалить</button>
                                </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('Заблокировать?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="admin_block">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                    <button class="btn danger small">🔒 Блокировать</button>
                                </form>
                            <?php endif; ?>

                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php admin_layout_end(); ?>

<?php elseif ($page === 'admin_cars'):
    $q = trim(get('q', ''));
    $sql = 'SELECT * FROM car WHERE deleted_at IS NULL';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (brand LIKE ? OR model LIKE ? OR vin LIKE ?)';
        $params = ["%$q%", "%$q%", "%$q%"];
    }
    $sql .= ' ORDER BY created_at DESC';
    $st = db()->prepare($sql);
    $st->execute($params);
    $cars = $st->fetchAll();

    admin_layout_start('cars', $user, 'Автомобили', 'Всего: ' . count($cars));
?>

    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px">
        <form method="get" class="admin-search" style="flex:1; margin:0">
            <input type="hidden" name="page" value="admin_cars">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Поиск: марка, модель, VIN">
            <button class="btn ghost small">Найти</button>
            <?php if ($q): ?><a href="index.php?page=admin_cars" class="btn ghost small">Сбросить</a><?php endif; ?>
        </form>
        <a href="index.php?page=admin_car_form" class="btn primary small">+ Добавить автомобиль</a>
    </div>

    <div class="admin-card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Фото</th>
                    <th>VIN</th>
                    <th>Автомобиль</th>
                    <th>Год</th>
                    <th>Цена</th>
                    <th style="text-align:right">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cars as $c): ?>
                <tr>
                    <td>
                        <?php if (!empty($c['image_path'])): ?>
                            <img src="<?= e($c['image_path']) ?>" alt="" class="car-thumb">
                        <?php else: ?>
                            <div class="car-thumb car-thumb-empty">🚗</div>
                        <?php endif; ?>
                    </td>
                    <td><code class="vin"><?= e($c['vin']) ?></code></td>
                    <td>
                        <b><?= e($c['brand']) ?> <?= e($c['model']) ?></b>
                        <div class="text-dim" style="font-size:12px">
                            <?= e($c['color'] ?? '') ?>
                            <?= $c['body_type'] ? ' · ' . e($c['body_type']) : '' ?>
                        </div>
                    </td>
                    <td><?= (int)$c['year'] ?></td>
                    <td><b class="gold"><?= number_format((float)$c['price'], 0, ',', ' ') ?> ₽</b></td>
                    <td class="row-actions">
                        <a href="index.php?page=admin_car_form&id=<?= (int)$c['car_id'] ?>" class="btn ghost small">✎ Ред.</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Удалить авто?')"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="car_delete">
                            <input type="hidden" name="car_id" value="<?= (int)$c['car_id'] ?>">
                            <button class="btn danger small">🗑 Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$cars): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:40px">Нет автомобилей</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php admin_layout_end(); ?>

<?php elseif ($page === 'admin_car_form'):
    $id  = (int)get('id', 0);
    $car = null;
    if ($id > 0) {
        $st = db()->prepare('SELECT * FROM car WHERE car_id = ?');
        $st->execute([$id]);
        $car = $st->fetch();
    }
    $err = $_SESSION['car_error'] ?? null;
    unset($_SESSION['car_error']);

    admin_layout_start('cars', $user, $car ? 'Редактировать авто' : 'Новый автомобиль');
?>

    <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

    <div class="admin-card admin-form-card">
        <form method="post" action="index.php" class="admin-form" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="car_save">
            <input type="hidden" name="car_id" value="<?=($car['car_id'] ?? 0) ?>">

            <div class="form-grid">
                <div class="field"><label>VIN *</label><input type="text" name="vin" maxlength="17" required value="<?= e($car['vin'] ?? '') ?>"></div>
                <div class="field"><label>Марка *</label><input type="text" name="brand" required value="<?= e($car['brand'] ?? '') ?>"></div>
                <div class="field"><label>Модель *</label><input type="text" name="model" required value="<?= e($car['model'] ?? '') ?>"></div>
                <div class="field"><label>Год *</label><input type="number" name="year" min="1900" max="2100" required value="<?= e($car['year'] ?? date('Y')) ?>"></div>
                <div class="field"><label>Цвет</label><input type="text" name="color" value="<?= e($car['color'] ?? '') ?>"></div>
                <div class="field"><label>Мощность, л.с.</label><input type="number" name="power_hp" value="<?= e($car['power_hp'] ?? '') ?>"></div>
                <div class="field"><label>Цена, ₽ *</label><input type="number" name="price" step="0.01" required value="<?= e($car['price'] ?? '') ?>"></div>
                <div class="field"><label>Тип кузова</label>
                    <select name="body_type">
                        <?php
                            $bodies = ['Седан', 'Кроссовер', 'Внедорожник', 'Хэтчбек', 'Купе', 'Универсал'];
                            $cur = $car['body_type'] ?? '';
                            foreach ($bodies as $b) {
                                echo '<option ' . ($cur === $b ? 'selected' : '') . '>' . $b . '</option>';
                            }
                        ?>
                    </select>
                </div>
                <div class="field"><label>Топливо</label>
                    <select name="fuel">
                        <?php
                            $fuels = ['Бензин', 'Дизель', 'Гибрид', 'Электро'];
                            $cur = $car['fuel'] ?? '';
                            foreach ($fuels as $f) {
                                echo '<option ' . ($cur === $f ? 'selected' : '') . '>' . $f . '</option>';
                            }
                        ?>
                    </select>
                </div>

                <div class="field" style="grid-column: 1 / -1">
                    <label>Фотография автомобиля</label>

                    <?php if (!empty($car['image_path'])): ?>
                        <div class="car-img-preview">
                            <img src="<?= e($car['image_path']) ?>" alt="">
                            <label class="car-img-remove">
                                <input type="checkbox" name="remove_image" value="1">
                                <span>Удалить текущую картинку</span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="car-img-upload">
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" id="car-image-input">
                        <label for="car-image-input" class="car-img-drop">
                            <span>📷 Нажмите, чтобы выбрать фото</span>
                            <small>JPG, PNG, WEBP или GIF · до 5 МБ</small>
                        </label>
                    </div>
                </div>
            </div>

            <div class="admin-form-actions">
                <a href="index.php?page=admin_cars" class="btn ghost">Отмена</a>
                <button class="btn primary" type="submit">
                    <?= $car ? 'Сохранить изменения' : 'Создать автомобиль' ?>
                </button>
            </div>
        </form>
    </div>

<?php admin_layout_end(); ?>

<?php elseif ($page === 'admin_requests'):
    $requests = db()->query('
        SELECT r.*, c.brand, c.model, c.year, c.price
        FROM purchase_request r
        JOIN car c ON c.car_id = r.car_id
        ORDER BY r.created_at DESC
        LIMIT 200
    ')->fetchAll();

    admin_layout_start('requests', $user, 'Заявки на покупку', 'Всего: ' . count($requests));
?>

    <div class="admin-card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Автомобиль</th>
                    <th>Клиент</th>
                    <th>Телефон</th>
                    <th>Комментарий</th>
                    <th>Статус</th>
                    <th style="text-align:right">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><span class="text-dim"><?= e($r['created_at']) ?></span></td>
                    <td>
                        <b><?= e($r['brand']) ?> <?= e($r['model']) ?></b>
                        <div class="text-dim" style="font-size:12px">
                            <?= (int)$r['year'] ?> · <?= number_format((float)$r['price'], 0, ',', ' ') ?> ₽
                        </div>
                    </td>
                    <td><b><?= e($r['client_name']) ?></b></td>
                    <td><?= e($r['client_phone']) ?></td>
                    <td><?= e($r['comment'] ?? '—') ?></td>
                    <td>
                        <?php
                            $cls = match ($r['status']) {
                                'new'         => 'yellow',
                                'in_progress' => 'gray',
                                'done'        => 'green',
                                'rejected'    => 'red',
                                default       => 'gray',
                            };
                        ?>
                        <span class="badge <?= $cls ?>"><?= e($r['status']) ?></span>
                    </td>
                    <td class="row-actions">
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="request_set_status">
                            <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                            <select name="status" onchange="this.form.submit()"
                                    style="background:var(--bg-darker); border:1px solid var(--border); border-radius:4px; color:var(--text); padding:6px 10px; font-size:12px">
                                <option value="new"         <?= $r['status'] === 'new' ? 'selected' : '' ?>>Новая</option>
                                <option value="in_progress" <?= $r['status'] === 'in_progress' ? 'selected' : '' ?>>В работе</option>
                                <option value="done"        <?= $r['status'] === 'done' ? 'selected' : '' ?>>Выполнена</option>
                                <option value="rejected"    <?= $r['status'] === 'rejected' ? 'selected' : '' ?>>Отклонена</option>
                            </select>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:40px">Заявок пока нет</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php admin_layout_end(); ?>

<?php elseif ($page === 'admin_notes'):
    $notes = [];
    $dbError = null;
    try {
        $st = db()->query("
            SELECT
                n.id                                  AS id,
                CONCAT(n.title, ' - ', u.login)       AS title_user,
                n.content                             AS content,
                DATE_FORMAT(n.created_at, '%d.%m.%Y') AS formatted_date
            FROM notes n
            JOIN users u ON u.id = n.id_user
            ORDER BY n.id
        ");
        $notes = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $dbError = $e->getMessage();
    }

    admin_layout_start('notes', $user, 'Заметки (API)', 'Данные GET /notes');
?>

    <?php if ($dbError): ?>
        <div class="alert err">Ошибка БД: <?= e($dbError) ?></div>
    <?php endif; ?>

    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:20px">
        <a href="index.php?page=admin_notes" class="btn ghost small">↻ Обновить</a>
        <a href="index.php?page=admin_notes_download" class="btn primary small">⬇ Скачать JSON</a>
        <a href="/notes" target="_blank" class="btn outline small">🔗 Открыть API /notes</a>
    </div>

    <div class="admin-card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>title_user</th>
                    <th>content</th>
                    <th>formatted_date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($notes as $n): ?>
                <tr>
                    <td><code class="vin"><?= e($n['id']) ?></code></td>
                    <td><b><?= e($n['title_user']) ?></b></td>
                    <td><?= e($n['content']) ?></td>
                    <td><span class="badge green"><?= e($n['formatted_date']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$notes): ?>
                <tr><td colspan="4" class="text-center text-muted" style="padding:40px">
                    Заметок пока нет. Добавьте записи в таблицу <code>notes</code>.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php admin_layout_end(); ?>

<?php elseif ($page === 'admin_system'):
    $dbName = DB_NAME;
    
    $dbInfo = ['size' => 0, 'tables' => 0];
    try {
        $st = db()->prepare("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                COUNT(*) AS tables_count
            FROM information_schema.TABLES 
            WHERE table_schema = ?
        ");
        $st->execute([$dbName]);
        $res = $st->fetch();
        if ($res) {
            $dbInfo['size']   = (float)$res['size_mb'];
            $dbInfo['tables'] = (int)$res['tables_count'];
        }
    } catch (PDOException $e) {
    }

    $path = __DIR__;
    $freeSpace  = @disk_free_space($path);
    $totalSpace = @disk_total_space($path);
    
    $freeGb  = $freeSpace !== false ? round($freeSpace / 1024 / 1024 / 1024, 2) : 'н/д';
    $totalGb = $totalSpace !== false ? round($totalSpace / 1024 / 1024 / 1024, 2) : 'н/д';
    
    $usedGb = 0;
    $usedPercent = 0;
    if ($freeSpace !== false && $totalSpace !== false && $totalSpace > 0) {
        $usedBytes   = $totalSpace - $freeSpace;
        $usedGb      = round($usedBytes / 1024 / 1024 / 1024, 2);
        $usedPercent = round(($usedBytes / $totalSpace) * 100, 1);
    }

    admin_layout_start('system', $user, 'Системная информация', 'Состояние базы данных и жесткого диска');
?>

    <div class="admin-stats" style="grid-template-columns: repeat(2, 1fr); gap: 24px;">
        <div class="admin-card" style="padding: 32px;">
            <div class="about-card-icon" style="background: rgba(59,130,246,.15); color: #60a5fa; margin-bottom: 20px;">🗄️</div>
            <h3 style="font-size: 20px; font-weight: 900; margin-bottom: 16px;">База данных</h3>
            
            <table class="admin-table" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <td><b>Имя базы</b></td>
                        <td><code class="vin"><?= e(DB_NAME) ?></code></td>
                    </tr>
                    <tr>
                        <td><b>Размер БД</b></td>
                        <td><b class="gold" style="font-size: 18px;"><?= $dbInfo['size'] ?> МБ</b></td>
                    </tr>
                    <tr>
                        <td><b>Количество таблиц</b></td>
                        <td><b style="font-size: 18px;"><?= $dbInfo['tables'] ?></b></td>
                    </tr>
                </tbody>
            </table>
            
            <a href="index.php?page=admin_system" class="btn ghost small" style="width: 100%;">↻ Обновить данные</a>
        </div>

        <div class="admin-card" style="padding: 32px;">
            <div class="about-card-icon" style="background: rgba(34,197,94,.15); color: #4ade80; margin-bottom: 20px;">💾</div>
            <h3 style="font-size: 20px; font-weight: 900; margin-bottom: 16px;">Жесткий диск</h3>
            
            <table class="admin-table" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <td><b>Всего места</b></td>
                        <td><b style="font-size: 16px;"><?= $totalGb ?> ГБ</b></td>
                    </tr>
                    <tr>
                        <td><b>Занято</b></td>
                        <td><b style="font-size: 16px; color: #fca5a5;"><?= $usedGb ?> ГБ</b></td>
                    </tr>
                    <tr>
                        <td><b>Свободно</b></td>
                        <td><b class="gold" style="font-size: 18px;"><?= $freeGb ?> ГБ</b></td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">
                    <span>Заполнено на <?= $usedPercent ?>%</span>
                    <span><?= $freeGb ?> ГБ свободно</span>
                </div>
                <div style="width: 100%; height: 8px; background: var(--bg-darker); border-radius: 4px; overflow: hidden; border: 1px solid var(--border);">
                    <div style="width: <?= $usedPercent ?>%; height: 100%; background: <?= $usedPercent > 90 ? '#ef4444' : 'var(--gold)' ?>; transition: width 0.3s;"></div>
                </div>
            </div>

            <a href="index.php?page=admin_system" class="btn ghost small" style="width: 100%;">↻ Обновить данные</a>
        </div>
    </div>

<?php admin_layout_end(); ?>

<?php elseif ($page === 'admin_logs'):
    $logs = db()->query('
        SELECT l.*, u.fio
        FROM action_log l
        LEFT JOIN user u ON u.user_id = l.user_id
        ORDER BY l.created_at DESC
        LIMIT 200
    ')->fetchAll();

    admin_layout_start('logs', $user, 'Журнал действий', 'Последние 200 записей');
?>

    <div class="admin-card">
        <table class="admin-table">
            <thead><tr><th>Время</th><th>Пользователь</th><th>Действие</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><span class="text-dim"><?= e($l['created_at']) ?></span></td>
                    <td><b><?= e($l['fio'] ?? $l['login']) ?></b></td>
                    <td><?= e($l['action']) ?></td>
                    <td><span class="text-dim"><?= e($l['ip'] ?? '—') ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="4" class="text-center text-muted" style="padding:40px">Пока нет записей</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php admin_layout_end(); ?>

<?php else: ?>

<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="logo">
            <div class="logo-mark">SC</div>
            <div class="logo-text"><b>АВТОКАТАЛОГ</b></div>
        </a>
        <nav class="nav-main">
            <a href="index.php?page=about"    class="<?= $page === 'about' ? 'active' : '' ?>">О нас</a>
            <a href="index.php?page=promo"    class="<?= $page === 'promo' ? 'active' : '' ?>">Акции</a>
            <a href="index.php?page=cars"     class="<?= $page === 'cars' ? 'active' : '' ?>">Каталог</a>
            <a href="index.php?page=contacts" class="<?= $page === 'contacts' ? 'active' : '' ?>">Контакты</a>
        </nav>
        <div class="header-actions">
            <a href="tel:+78001234567" class="header-phone">📞 +7 (800) 123-45-67</a>
            <?php if ($user): ?>
                <a href="index.php?page=profile" class="btn ghost small">👤 <?= e(mb_substr($user['first_name'], 0, 1)) ?></a>
                <?php if ($user['role_code'] === 'admin'): ?>
                    <a href="index.php?page=admin" class="btn ghost small">Панель</a>
                <?php endif; ?>
                <a href="index.php?action=logout" class="btn outline small">Выйти</a>
            <?php else: ?>
                <a href="index.php?page=login" class="btn primary small">Войти</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main>
<?php if ($page === 'home' || $page === 'dashboard'):
    $cars = db()->query('SELECT * FROM car WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 8')->fetchAll();

    $requestCars = db()->query('
        SELECT car_id, brand, model, year, price
        FROM car WHERE deleted_at IS NULL
        ORDER BY brand, model
    ')->fetchAll();

    $reqErr = $_SESSION['request_error'] ?? null;
    unset($_SESSION['request_error']);
    $reqOk  = $_SESSION['request_ok']    ?? null;
    unset($_SESSION['request_ok']);
?>
<section class="hero-photo">
    <div class="hero-photo-bg" style="background-image:url('assets/hero.jpg')"></div>
    <div class="hero-photo-inner">
        <span class="kicker-gold">ПРЕМИУМ АВТОМОБИЛИ</span>
        <h1>НАЙДИТЕ <span class="gold">СВОЙ</span><br>АВТОМОБИЛЬ</h1>
        <p>Более 200 автомобилей премиум-класса. Прозрачные цены, гарантия качества и персональный менеджер для каждого клиента.</p>
        <div class="hero-actions">
            <a href="index.php?page=cars" class="btn primary">СМОТРЕТЬ КАТАЛОГ</a>
            <a href="index.php?page=contacts" class="btn outline">ТЕСТ-ДРАЙВ</a>
        </div>
    </div>
</section>

<section class="stats-strip">
    <div class="stat-item"><b>200+</b><span>Автомобилей</span></div>
    <div class="stat-item"><b>15 Лет</b><span>На рынке</span></div>
    <div class="stat-item"><b>4 800+</b><span>Довольных клиентов</span></div>
    <div class="stat-item"><b>98%</b><span>Положительных отзывов</span></div>
</section>

<section class="section about-block">
    <div class="section-inner">
        <div class="about-head">
            <div class="about-number">01</div>
            <div class="about-title-wrap">
                <span class="kicker-gold">О НАС</span>
                <h2>15 ЛЕТ ОПЫТА<br>В МИРЕ АВТОМОБИЛЕЙ</h2>
            </div>
            <div class="about-line"></div>
        </div>
        <div class="about-grid">
            <div class="about-text">
                <p class="about-lead">Мы гордимся тем, что предоставляем нашим клиентам непревзойденный уровень сервиса при выборе автомобилей премиум-сегмента.</p>
                <p>Каждый экземпляр в нашем салоне проходит строгий технический аудит. Доверие — главная ценность нашей компании.</p>
                <div class="about-features-list">
                    <div class="about-feature-line"><span class="num">01</span><span>Более 200 автомобилей в наличии</span></div>
                    <div class="about-feature-line"><span class="num">02</span><span>Гарантия юридической чистоты</span></div>
                    <div class="about-feature-line"><span class="num">03</span><span>Полное сопровождение сделки</span></div>
                </div>
            </div>
            <div class="about-image">
                <img src="assets/showroom.jpg" alt="">
                <div class="about-badge"><b>4 800+</b><span>Довольных клиентов</span></div>
            </div>
        </div>
        <div class="about-cards">
            <div class="about-card"><div class="about-card-num">01</div><div class="about-card-icon">🏆</div><b>Официальный дилер</b><span>Прямые поставки от производителей</span></div>
            <div class="about-card"><div class="about-card-num">02</div><div class="about-card-icon">🔧</div><b>Сервисный центр</b><span>Собственная техническая база</span></div>
            <div class="about-card"><div class="about-card-num">03</div><div class="about-card-icon">📜</div><b>Гарантия качества</b><span>Юридическая чистота сделки</span></div>
            <div class="about-card"><div class="about-card-num">04</div><div class="about-card-icon">💳</div><b>Кредит и Trade-In</b><span>Партнёрские программы банков</span></div>
        </div>
    </div>
</section>

<section class="section promo-block">
    <div class="section-inner">
        <span class="kicker-gold">СПЕЦИАЛЬНЫЕ ПРЕДЛОЖЕНИЯ</span>
        <h2>АКЦИИ И СКИДКИ</h2>
        <div class="promo-large">
            <div class="promo-text">
                <span class="promo-badge">ГОРЯЧЕЕ ПРЕДЛОЖЕНИЕ</span>
                <h3>Кредит <span class="gold">0%</span> на первые 12 месяцев</h3>
                <p>Уникальная финансовая программа совместно с банками-партнерами.</p>
                <a href="index.php?page=promo" class="btn primary">УЗНАТЬ ПОДРОБНЕЕ</a>
            </div>
            <div class="promo-image" style="background-image:url('assets/promo.jpg')"></div>
        </div>
    </div>
</section>

<section class="section catalog-block">
    <div class="section-inner">
        <span class="kicker-gold">НАШИ АВТОМОБИЛИ</span>
        <h2>КАТАЛОГ АВТОМОБИЛЕЙ</h2>

        <div class="filters">
            <a href="index.php?page=cars" class="filter-btn active">ВСЕ</a>
            <?php
                $bodiesList = ['Седан', 'Кроссовер', 'Внедорожник', 'Хэтчбек', 'Купе', 'Универсал'];
                foreach ($bodiesList as $b) {
                    echo '<a href="index.php?page=cars&body=' . urlencode($b) . '" class="filter-btn">' . e(mb_strtoupper($b)) . '</a>';
                }
            ?>
        </div>

        <div class="cars-grid compact">
            <?php foreach ($cars as $c): ?>
            <div class="car-card">
                <div class="car-image">
                    <?php if (!empty($c['image_path'])): ?>
                        <img src="<?= e($c['image_path']) ?>" alt="" class="car-image-img">
                    <?php endif; ?>
                    <span class="car-year-badge"><?= (int)$c['year'] ?></span>
                </div>
                <div class="car-body">
                    <h3><?= e($c['brand']) ?> <?= e($c['model']) ?></h3>
                    <div class="car-price"><?= number_format((float)$c['price'], 0, ',', ' ') ?> ₽</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="index.php?page=cars" class="btn outline">ПОКАЗАТЬ ВСЕ АВТОМОБИЛИ</a>
        </div>
    </div>
</section>

<section class="section testdrive-block">
    <div class="section-inner">
        <div class="td-grid">
            <div class="td-info">
                <span class="kicker-gold">ЗАПИСАТЬСЯ НА ТЕСТ-ДРАЙВ</span>
                <h2>ПОЧУВСТВУЙТЕ РАЗНИЦУ</h2>
                <p>Запишитесь на индивидуальный тест-драйв в любое удобное время.</p>
                <div class="td-contacts">
                    <div class="td-row"><span class="td-ico">📍</span><div><b>АДРЕС</b>г. Москва, ул. Автозаводская, 15</div></div>
                    <div class="td-row"><span class="td-ico">📞</span><div><b>ТЕЛЕФОН</b>+7 (800) 123-45-67</div></div>
                    <div class="td-row"><span class="td-ico">🕒</span><div><b>РЕЖИМ РАБОТЫ</b>Пн–Вс: 09:00 – 21:00</div></div>
                    <div class="td-row"><span class="td-ico">✉️</span><div><b>EMAIL</b>info@autocatalog.ru</div></div>
                </div>
            </div>

            <div>
                <?php if ($reqOk):  ?><div class="alert ok"><?= e($reqOk) ?></div><?php endif; ?>
                <?php if ($reqErr): ?><div class="alert err"><?= e($reqErr) ?></div><?php endif; ?>

                <form class="td-form" method="post" action="index.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="car_request">

                    <h3>Оставить заявку на покупку</h3>

                    <div class="field">
                        <label>Автомобиль *</label>
                        <select name="car_id" required>
                            <option value="">— выберите автомобиль —</option>
                            <?php foreach ($requestCars as $rc): ?>
                                <option value="<?= (int)$rc['car_id'] ?>">
                                    <?= e($rc['brand']) ?> <?= e($rc['model']) ?>, <?= (int)$rc['year'] ?>
                                    — <?= number_format((float)$rc['price'], 0, ',', ' ') ?> ₽
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label>Ваше имя *</label>
                        <input type="text" name="name" placeholder="Например, Александр" required>
                    </div>

                    <div class="field">
                        <label>Номер телефона *</label>
                        <input type="tel" name="phone" placeholder="+7 (___) ___-__-__" required>
                    </div>

                    <div class="field">
                        <label>Комментарий</label>
                        <textarea name="comment" placeholder="Укажите пожелания..."></textarea>
                    </div>

                    <button class="btn primary block" type="submit">ОТПРАВИТЬ ЗАЯВКУ</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php elseif ($page === 'cars'):
    $allowedBodies = ['Седан', 'Кроссовер', 'Внедорожник', 'Хэтчбек', 'Купе', 'Универсал'];
    $body = trim(get('body', ''));

    if ($body !== '' && in_array($body, $allowedBodies, true)) {
        $st = db()->prepare('SELECT * FROM car WHERE deleted_at IS NULL AND body_type = ? ORDER BY created_at DESC');
        $st->execute([$body]);
        $cars = $st->fetchAll();
    } else {
        $body = '';
        $cars = db()->query('SELECT * FROM car WHERE deleted_at IS NULL ORDER BY created_at DESC')->fetchAll();
    }
?>
<section class="section">
    <div class="section-inner">
        <span class="kicker-gold">НАШИ АВТОМОБИЛИ</span>
        <h2>КАТАЛОГ АВТОМОБИЛЕЙ</h2>

        <div class="filters">
            <a href="index.php?page=cars" class="filter-btn <?= $body === '' ? 'active' : '' ?>">ВСЕ</a>
            <?php foreach ($allowedBodies as $b): ?>
                <a href="index.php?page=cars&body=<?= urlencode($b) ?>"
                   class="filter-btn <?= $body === $b ? 'active' : '' ?>">
                    <?= e(mb_strtoupper($b)) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="cars-grid">
            <?php foreach ($cars as $c): ?>
            <div class="car-card">
                <div class="car-image">
                    <?php if (!empty($c['image_path'])): ?>
                        <img src="<?= e($c['image_path']) ?>" alt="" class="car-image-img">
                    <?php endif; ?>
                    <span class="car-year-badge"><?= (int)$c['year'] ?></span>
                </div>
                <div class="car-body">
                    <h3><?= e($c['brand']) ?> <?= e($c['model']) ?></h3>
                    <div class="car-meta">
                        <?php if ($c['color']): ?><span><?= e($c['color']) ?></span><?php endif; ?>
                        <?php if ($c['power_hp']): ?><span><?= (int)$c['power_hp'] ?> л.с.</span><?php endif; ?>
                        <?php if ($c['body_type']): ?><span><?= e($c['body_type']) ?></span><?php endif; ?>
                    </div>
                    <div class="car-price"><?= number_format((float)$c['price'], 0, ',', ' ') ?> ₽</div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$cars): ?>
                <div style="grid-column:1/-1; text-align:center; padding:40px; color:var(--text-dim)">
                    Автомобилей с таким типом кузова пока нет.
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php elseif ($page === 'about'): ?>
<section class="section about-block">
    <div class="section-inner">
        <div class="about-head">
            <div class="about-number"> </div>
            <div class="about-title-wrap">
                <span class="kicker-gold">О КОМПАНИИ</span>
                <h2>АВТОКАТАЛОГ<br>СЕГОДНЯ</h2>
            </div>
            <div class="about-line"></div>
        </div>
        <div class="about-grid">
            <div class="about-text">
                <p class="about-lead">«Автокаталог» — это команда профессионалов, влюбленных в автомобили. Мы помогаем нашим клиентам найти идеальный автомобиль, который будет радовать их каждый день.</p>
                <p>Наш автосалон работает с 2009 года. За это время мы успешно реализовали более 4800 автомобилей. Мы предлагаем только проверенные автомобили с прозрачной историей и гарантией юридической чистоты.</p>
                <p>Наши специалисты проходят регулярное обучение и знают всё о современных автомобилях, чтобы дать вам лучший совет при выборе.</p>
            </div>
            <div class="about-image">
                <img src="assets/showroom.jpg" alt="">
                <div class="about-badge"><b>15+</b><span>Лет на рынке</span></div>
            </div>
        </div>

        <div class="admin-stats" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 60px;">
            <div class="admin-stat"><b>200+</b><span>Автомобилей в наличии</span></div>
            <div class="admin-stat"><b>4 800+</b><span>Довольных клиентов</span></div>
            <div class="admin-stat"><b>98%</b><span>Положительных отзывов</span></div>
            <div class="admin-stat"><b>24/7</b><span>Поддержка клиентов</span></div>
        </div>

        <div class="about-cards">
            <div class="about-card"><div class="about-card-icon">🏆</div><b>Официальный дилер</b><span>Прямые поставки от производителей</span></div>
            <div class="about-card"><div class="about-card-icon">🔧</div><b>Сервисный центр</b><span>Собственная техническая база</span></div>
            <div class="about-card"><div class="about-card-icon">📜</div><b>Гарантия качества</b><span>Юридическая чистота сделки</span></div>
            <div class="about-card"><div class="about-card-icon">💳</div><b>Кредит и Trade-In</b><span>Партнёрские программы банков</span></div>
        </div>
    </div>
</section>

<?php elseif ($page === 'promo'): ?>
<section class="section promo-block">
    <div class="section-inner">
        <span class="kicker-gold">СПЕЦИАЛЬНЫЕ ПРЕДЛОЖЕНИЯ</span>
        <h2>АКЦИИ И СКИДКИ</h2>

        <div class="about-grid" style="margin-bottom: 60px;">
            <div class="about-text">
                <p class="about-lead">Покупайте автомобиль мечты на выгодных условиях. Мы подготовили специальные предложения для наших клиентов.</p>
                <p>Воспользуйтесь уникальными финансовыми программами, скидками на сервисное обслуживание и бонусами при сдаче старого автомобиля в трейд-ин.</p>
            </div>
            <div class="promo-large" style="margin: 0;">
                <div class="promo-text" style="padding: 36px;">
                    <span class="promo-badge">ХИТ ПРОДАЖ</span>
                    <h3 style="font-size: 24px;">Кредит <span class="gold">0%</span><br>на 12 месяцев</h3>
                    <p>Без переплат и скрытых комиссий. Решение по кредиту за 15 минут.</p>
                    <a href="index.php?page=contacts" class="btn primary">Оформить кредит</a>
                </div>
                <div class="promo-image" style="background-image:url('assets/promo.jpg'); min-height: 280px;"></div>
            </div>
        </div>

        <div class="about-cards" style="grid-template-columns: repeat(3, 1fr); gap: 24px;">
            <div class="about-card" style="padding: 40px 32px;">
                <div class="about-card-icon" style="background: rgba(34,197,94,.15); color: #4ade80;">💳</div>
                <b style="font-size: 18px; margin-bottom: 12px;">Трейд-ин с выгодой до 300 000 ₽</b>
                <span style="font-size: 14px; margin-bottom: 20px;">Сдайте свой старый автомобиль в зачёт нового и получите дополнительную скидку.</span>
                <a href="index.php?page=contacts" class="btn outline small" style="margin-top: auto; align-self: flex-start;">Узнать оценку</a>
            </div>
            <div class="about-card" style="padding: 40px 32px;">
                <div class="about-card-icon" style="background: rgba(212,175,55,.15); color: var(--gold);">🔧</div>
                <b style="font-size: 18px; margin-bottom: 12px;">Бесплатное ТО при покупке</b>
                <span style="font-size: 14px; margin-bottom: 20px;">При покупке любого автомобиля из наличия — первое техническое обслуживание в подарок.</span>
                <a href="index.php?page=cars" class="btn outline small" style="margin-top: auto; align-self: flex-start;">Выбрать авто</a>
            </div>
            <div class="about-card" style="padding: 40px 32px;">
                <div class="about-card-icon" style="background: rgba(59,130,246,.15); color: #60a5fa;">🛡️</div>
                <b style="font-size: 18px; margin-bottom: 12px;">КАСКО в подарок</b>
                <span style="font-size: 14px; margin-bottom: 20px;">Оформите автокредит у наших партнёров и получите полис КАСКО на первый год бесплатно.</span>
                <a href="index.php?page=contacts" class="btn outline small" style="margin-top: auto; align-self: flex-start;">Условия</a>
            </div>
        </div>

        <div class="admin-card" style="margin-top: 60px; padding: 40px; display: flex; align-items: center; justify-content: space-between; gap: 30px; flex-wrap: wrap;">
            <div>
                <h3 style="font-size: 24px; font-weight: 900; margin-bottom: 8px;">Не нашли подходящую акцию?</h3>
                <p class="text-dim">Оставьте заявку, и наш менеджер подберёт для вас индивидуальные условия.</p>
            </div>
            <a href="index.php?page=contacts" class="btn primary">Связаться с нами</a>
        </div>
    </div>
</section>

<?php elseif ($page === 'contacts'): ?>
<section class="section">
    <div class="section-inner">
        <span class="kicker-gold">СВЯЗАТЬСЯ С НАМИ</span>
        <h2>КОНТАКТЫ</h2>

        <div class="td-grid" style="margin-top: 40px;">
            <div class="td-info">
                <p>У вас есть вопросы по подбору автомобиля, условиям покупки или сервисному обслуживанию? Наши менеджеры всегда готовы помочь.</p>

                <div class="td-contacts">
                    <div class="td-row">
                        <span class="td-ico">📍</span>
                        <div><b>ЦЕНТРАЛЬНЫЙ ОФИС И ШОУРУМ</b>г. Москва, ул. Автозаводская, 15<br><span class="text-dim">Ежедневно с 09:00 до 21:00</span></div>
                    </div>
                    <div class="td-row">
                        <span class="td-ico">📞</span>
                        <div><b>ТЕЛЕФОН</b>+7 (800) 123-45-67<br><span class="text-dim">Бесплатно по России</span></div>
                    </div>
                    <div class="td-row">
                        <span class="td-ico">✉️</span>
                        <div><b>EMAIL</b>info@autocatalog.ru<br><span class="text-dim">Ответим в течение 15 минут</span></div>
                    </div>
                    <div class="td-row">
                        <span class="td-ico">🏢</span>
                        <div><b>РЕКВИЗИТЫ</b>ООО «Автокаталог»<br>ИНН: 7701234567<br>ОГРН: 1157746123456</div>
                    </div>
                </div>
            </div>

            <div class="td-form">
                <h3>Напишите нам</h3>
                <p class="text-dim mb-3">Заполните форму, и мы свяжемся с вами в ближайшее время.</p>
                <form method="post" action="index.php">
                    <?= csrf_field() ?>
                    <div class="field"><label>Ваше имя *</label><input type="text" placeholder="Как к вам обращаться?" required></div>
                    <div class="field"><label>Телефон или Email *</label><input type="text" placeholder="+7 (___) ___-__-__" required></div>
                    <div class="field"><label>Тема обращения</label>
                        <select>
                            <option>Вопрос по покупке</option>
                            <option>Запись на сервис</option>
                            <option>Трейд-ин</option>
                            <option>Сотрудничество</option>
                            <option>Другое</option>
                        </select>
                    </div>
                    <div class="field"><label>Сообщение</label><textarea placeholder="Ваш вопрос..."></textarea></div>
                    <button class="btn primary block mt-3" type="button">ОТПРАВИТЬ СООБЩЕНИЕ</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php else: ?>
    <section class="section">
        <div class="container text-center">
            <h2>Страница не найдена</h2>
            <a href="index.php" class="btn primary mt-3">На главную</a>
        </div>
    </section>
<?php endif; ?>
</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-col">
            <div class="logo">
                <div class="logo-mark">SC</div>
                <div class="logo-text"><b>АВТОКАТАЛОГ</b></div>
            </div>
            <p class="mt-2">Премиальный дилер автомобилей представительского класса в Москве.</p>
        </div>
        <div class="footer-col">
            <h5>НАВИГАЦИЯ</h5>
            <ul>
                <li><a href="index.php?page=about">О нас</a></li>
                <li><a href="index.php?page=cars">Каталог автомобилей</a></li>
                <li><a href="index.php?page=promo">Акции и скидки</a></li>
                <li><a href="index.php?page=contacts">Контакты</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h5>КОНТАКТЫ</h5>
            <ul>
                <li>📍 г. Москва, ул. Автозаводская, 15</li>
                <li>📞 +7 (800) 123-45-67</li>
                <li>🕒 Пн–Вс: 09:00 – 21:00</li>
                <li>✉️ info@autocatalog.ru</li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <span>© 2024 Автокаталог. Все права защищены.</span>
        <span>Политика конфиденциальности · Условия использования</span>
    </div>
</footer>

<?php endif; ?>

<?php endif; ?>

<script>
function togglePass(btn) {
    const inp = btn.previousElementSibling;
    if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
    else { inp.type = 'password'; btn.textContent = '👁'; }
}

function selectCell(el) {
    const grid = el.parentElement;
    const cur  = grid.querySelector('.puzzle-cell.selected');
    if (!cur) { el.classList.add('selected'); return; }
    if (cur === el) { cur.classList.remove('selected'); return; }

    const imgA = cur.querySelector('img').getAttribute('src');
    const imgB = el.querySelector('img').getAttribute('src');
    cur.querySelector('img').setAttribute('src', imgB);
    el.querySelector('img').setAttribute('src', imgA);

    const a = cur.dataset.piece;
    const b = el.dataset.piece;
    cur.dataset.piece = b;
    el.dataset.piece  = a;

    cur.classList.remove('selected');
    el.classList.remove('selected');
}

function shufflePuzzle() {
    document.querySelectorAll('[data-captcha-grid]').forEach(grid => {
        const cells  = Array.from(grid.querySelectorAll('.puzzle-cell'));
        const imgs   = cells.map(c => c.querySelector('img').getAttribute('src'));
        const pieces = cells.map(c => c.dataset.piece);
        let attempts = 0;
        do {
            for (let i = imgs.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [imgs[i], imgs[j]]     = [imgs[j], imgs[i]];
                [pieces[i], pieces[j]] = [pieces[j], pieces[i]];
            }
            attempts++;
        } while (pieces.join(',') === '0,1,2,3' && attempts < 10);
        cells.forEach((c, i) => {
            c.querySelector('img').setAttribute('src', imgs[i]);
            c.dataset.piece = pieces[i];
            c.classList.remove('selected');
        });
        const wrap = grid.closest('.captcha-wrap');
        const btn  = wrap.querySelector('.btn.primary');
        if (btn) { btn.textContent = 'ПРОВЕРИТЬ'; btn.style.background = ''; }
    });
}

function checkPuzzle(btn) {
    const grid  = btn.closest('.captcha-wrap').querySelector('.puzzle-grid');
    const cells = Array.from(grid.querySelectorAll('.puzzle-cell'));
    const order = cells.map(c => parseInt(c.dataset.piece, 10));
    const ok = order[0] === 0 && order[1] === 1 && order[2] === 2 && order[3] === 3;
    if (ok) {
        btn.textContent = '✓ СОБРАНО';
        btn.style.background = 'linear-gradient(135deg,#22c55e,#16a34a)';
        syncHiddenInputs(grid);
    } else {
        btn.textContent = '✗ НЕВЕРНО';
        btn.style.background = 'linear-gradient(135deg,#ef4444,#b91c1c)';
        setTimeout(() => { btn.textContent = 'ПРОВЕРИТЬ'; btn.style.background = ''; }, 1500);
    }
}

function syncHiddenInputs(grid) {
    const order = Array.from(grid.querySelectorAll('.puzzle-cell')).map(c => parseInt(c.dataset.piece, 10));
    const hidden = grid.closest('form').querySelector('input[name="captcha_order"]');
    if (hidden) hidden.value = JSON.stringify(order);
}

document.addEventListener('submit', function(ev) {
    ev.target.querySelectorAll('[data-captcha-grid]').forEach(g => syncHiddenInputs(g));
});
</script>
</body>
</html>