 <?php
// ============================================================
//  install.php — визуальный мастер установки
//  Открыть один раз: http://localhost:8000/install.php
// ============================================================
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

$messages = [];

if (is_post()) {
    csrf_check();
    try {
        // 1. Таблицы
        $stmts = [
"CREATE TABLE IF NOT EXISTS user_rank (
  rank_id INT NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL, name VARCHAR(50) NOT NULL,
  level TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (rank_id), UNIQUE KEY uq_rank_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS user_status (
  status_id INT NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL, name VARCHAR(50) NOT NULL,
  PRIMARY KEY (status_id), UNIQUE KEY uq_status_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS user (
  user_id BIGINT NOT NULL AUTO_INCREMENT,
  login VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  first_name VARCHAR(60) NOT NULL,
  middle_name VARCHAR(60) NULL,
  fio VARCHAR(180) GENERATED ALWAYS AS (CONCAT_WS(' ', last_name, first_name, middle_name)) VIRTUAL,
  email VARCHAR(120) NOT NULL,
  phone VARCHAR(25) NULL,
  rank_id INT NOT NULL DEFAULT 1,
  status_id INT NOT NULL DEFAULT 1,
  group_code VARCHAR(20) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT NULL,
  deleted_at DATETIME NULL,
  last_login_at DATETIME NULL,
  failed_attempts TINYINT NOT NULL DEFAULT 0,
  password_changed_at DATETIME NULL,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_user_login (login),
  UNIQUE KEY uq_user_email (email),
  CONSTRAINT chk_user_attempts CHECK (failed_attempts BETWEEN 0 AND 3),
  CONSTRAINT fk_user_rank FOREIGN KEY (rank_id) REFERENCES user_rank(rank_id),
  CONSTRAINT fk_user_status FOREIGN KEY (status_id) REFERENCES user_status(status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS user_audit (
  audit_id BIGINT NOT NULL AUTO_INCREMENT,
  user_id BIGINT NULL, action VARCHAR(20) NOT NULL,
  field_name VARCHAR(60) NULL, old_value TEXT NULL, new_value TEXT NULL,
  changed_by BIGINT NULL, changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS action_log (
  log_id BIGINT NOT NULL AUTO_INCREMENT,
  user_id BIGINT NULL, login VARCHAR(30) NOT NULL,
  action VARCHAR(255) NOT NULL, ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($stmts as $sql) db()->exec($sql);
        $messages[] = ['ok', 'Таблицы созданы'];

        // 2. Справочники
        db()->exec("INSERT IGNORE INTO user_rank (code,name,level) VALUES
            ('student','Студент',1),('curator','Куратор группы',5),('admin','Администратор',9)");
        db()->exec("INSERT IGNORE INTO user_status (code,name) VALUES
            ('pending','Ожидает'),('active','Активен'),('blocked','Заблокирован'),('deleted','Удалён')");
        $messages[] = ['ok', 'Справочники заполнены'];

        // 3. Админ
        $lastName  = trim(post('admin_last'));
        $firstName = trim(post('admin_first'));
        $middle    = trim(post('admin_middle'));
        $login     = trim(post('admin_login'));
        $email     = trim(post('admin_email'));
        $pass      = post('admin_pass');

        if ($login === '' || $pass === '') {
            $messages[] = ['err', 'Логин и пароль админа обязательны'];
        } else {
            $exists = db()->prepare("SELECT 1 FROM user WHERE login = ?");
            $exists->execute([$login]);
            if ($exists->fetch()) {
                $messages[] = ['info', "Админ '$login' уже существует"];
            } else {
                db()->prepare('
                    INSERT INTO user (login, password_hash, last_name, first_name, middle_name,
                                      email, rank_id, status_id)
                    VALUES (?, ?, ?, ?, ?, ?,
                            (SELECT rank_id   FROM user_rank   WHERE code="admin"),
                            (SELECT status_id FROM user_status WHERE code="active"))
                ')->execute([
                    $login, password_hash($pass, PASSWORD_BCRYPT),
                    $lastName ?: 'Админ', $firstName ?: 'Главный', $middle ?: null,
                    $email ?: ($login . '@edu.ru')
                ]);
                $messages[] = ['ok', "Админ '$login' создан"];
            }
        }

        $messages[] = ['ok', 'Установка завершена. Можно переходить на index.php'];
    } catch (PDOException $e) {
        $messages[] = ['err', 'Ошибка: ' . $e->getMessage()];
    }
}

// Диагностика
$checks = [];
try {
    $checks['Подключение к MariaDB']    = true;
    $checks['База данных ' . DB_NAME]   = true;
    $checks['Кодировка utf8mb4']        = true;
} catch (Throwable $e) {
    $checks['Подключение'] = false;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Установка — <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
<div class="auth-wrap" style="grid-template-columns: 1fr; max-width: 640px">
  <div class="auth-card" style="max-width: 640px">
    <div class="brand-logo" style="margin-bottom:16px">ЭК</div>
    <h2>Мастер установки</h2>
    <p class="muted">Настройка базы данных и создание первого администратора.</p>

    <?php foreach ($messages as [$type, $text]): ?>
      <div class="alert <?= $type === 'ok' ? 'ok' : ($type === 'err' ? 'err' : '') ?>">
        <?= e($text) ?>
      </div>
    <?php endforeach; ?>

    <h3 style="margin-top:24px">Диагностика</h3>
    <ul class="list">
      <li>Хост <span><?= e(DB_HOST) ?>:<?= e(DB_PORT) ?></span></li>
      <li>База данных <span><?= e(DB_NAME) ?></span></li>
      <li>Пользователь <span><?= e(DB_USER) ?></span></li>
    </ul>

    <hr>

    <h3>Первый администратор</h3>
    <form method="post" action="install.php">
      <?= csrf_field() ?>
      <label>Фамилия <input type="text" name="admin_last" value="Иванов"></label>
      <label>Имя <input type="text" name="admin_first" value="Михаил"></label>
      <label>Отчество <input type="text" name="admin_middle" value="Петрович"></label>
      <label>Логин <input type="text" name="admin_login" value="admin" required></label>
      <label>Email <input type="email" name="admin_email" value="admin@edu.ru"></label>
      <label>Пароль <input type="text" name="admin_pass" value="admin123" required></label>
      <button class="btn primary" type="submit">Установить</button>
    </form>

    <hr>
    <p class="muted center">
      <a class="link" href="index.php">Перейти на сайт →</a>
    </p>
  </div>
</div>
</body>
</html>
