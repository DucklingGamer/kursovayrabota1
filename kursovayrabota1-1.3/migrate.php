<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$stmts = [
    "CREATE TABLE IF NOT EXISTS user_role (
      role_id INT AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(20) NOT NULL UNIQUE,
      name VARCHAR(50) NOT NULL,
      level TINYINT NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS user_status (
      status_id INT AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(20) NOT NULL UNIQUE,
      name VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS user (
      user_id BIGINT AUTO_INCREMENT PRIMARY KEY,
      login VARCHAR(30) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      last_name VARCHAR(60) NOT NULL,
      first_name VARCHAR(60) NOT NULL,
      middle_name VARCHAR(60) NULL,
      fio VARCHAR(180) GENERATED ALWAYS AS (CONCAT_WS(' ', last_name, first_name, middle_name)) VIRTUAL,
      email VARCHAR(120) NOT NULL UNIQUE,
      phone_encrypted VARCHAR(255) NULL,
      role_id INT NOT NULL DEFAULT 1,
      status_id INT NOT NULL DEFAULT 1,
      totp_secret VARCHAR(64) NULL,
      totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
      failed_attempts TINYINT NOT NULL DEFAULT 0,
      locked_until DATETIME NULL,
      password_changed_at DATETIME NULL,
      last_login_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      deleted_at DATETIME NULL,
      CONSTRAINT chk_user_attempts CHECK (failed_attempts BETWEEN 0 AND 3),
      CONSTRAINT fk_user_role   FOREIGN KEY (role_id)   REFERENCES user_role(role_id),
      CONSTRAINT fk_user_status FOREIGN KEY (status_id) REFERENCES user_status(status_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS counterparty (
      cp_id BIGINT AUTO_INCREMENT PRIMARY KEY,
      type ENUM('physical','legal') NOT NULL,
      name VARCHAR(255) NOT NULL,
      inn VARCHAR(12) NULL,
      phone_encrypted VARCHAR(255) NULL,
      email VARCHAR(120) NULL,
      address TEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      deleted_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS car (
      car_id BIGINT AUTO_INCREMENT PRIMARY KEY,
      vin VARCHAR(17) NOT NULL UNIQUE,
      brand VARCHAR(60) NOT NULL,
      model VARCHAR(60) NOT NULL,
      year SMALLINT NOT NULL,
      color VARCHAR(40) NULL,
      power_hp SMALLINT NULL,
      price DECIMAL(12,2) NOT NULL DEFAULT 0,
      body_type VARCHAR(40) NULL,
      fuel VARCHAR(40) NULL,
      image_path VARCHAR(255) NULL,
      status ENUM('active','sold','service','archived') NOT NULL DEFAULT 'active',
      current_owner_id BIGINT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      deleted_at DATETIME NULL,
      CONSTRAINT fk_car_owner FOREIGN KEY (current_owner_id) REFERENCES counterparty(cp_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS purchase_request (
      request_id   BIGINT AUTO_INCREMENT PRIMARY KEY,
      car_id       BIGINT NOT NULL,
      client_name  VARCHAR(120) NOT NULL,
      client_phone VARCHAR(30)  NOT NULL,
      comment      TEXT NULL,
      user_id      BIGINT NULL,
      status       ENUM('new','in_progress','done','rejected') NOT NULL DEFAULT 'new',
      created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_req_car FOREIGN KEY (car_id) REFERENCES car(car_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS sale (
      sale_id BIGINT AUTO_INCREMENT PRIMARY KEY,
      car_id BIGINT NOT NULL,
      seller_id BIGINT NOT NULL,
      buyer_id BIGINT NOT NULL,
      price DECIMAL(12,2) NOT NULL,
      sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      contract_no VARCHAR(50) NOT NULL,
      created_by BIGINT NULL,
      CONSTRAINT fk_sale_car    FOREIGN KEY (car_id)    REFERENCES car(car_id),
      CONSTRAINT fk_sale_seller FOREIGN KEY (seller_id) REFERENCES counterparty(cp_id),
      CONSTRAINT fk_sale_buyer  FOREIGN KEY (buyer_id)  REFERENCES counterparty(cp_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS service_record (
      service_id BIGINT AUTO_INCREMENT PRIMARY KEY,
      car_id BIGINT NOT NULL,
      service_date DATE NOT NULL,
      work_type VARCHAR(120) NOT NULL,
      description TEXT NULL,
      cost DECIMAL(12,2) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_service_car FOREIGN KEY (car_id) REFERENCES car(car_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS insurance (
      insurance_id BIGINT AUTO_INCREMENT PRIMARY KEY,
      car_id BIGINT NOT NULL,
      owner_id BIGINT NOT NULL,
      policy_no VARCHAR(50) NOT NULL,
      company VARCHAR(120) NOT NULL,
      valid_from DATE NOT NULL,
      valid_to DATE NOT NULL,
      premium DECIMAL(12,2) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_ins_car   FOREIGN KEY (car_id)   REFERENCES car(car_id),
      CONSTRAINT fk_ins_owner FOREIGN KEY (owner_id) REFERENCES counterparty(cp_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS action_log (
      log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT NULL,
      login VARCHAR(30) NOT NULL,
      action VARCHAR(255) NOT NULL,
      ip VARCHAR(45) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_log_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS user_audit (
      audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT NULL,
      action VARCHAR(20) NOT NULL,
      field_name VARCHAR(60) NULL,
      old_value TEXT NULL,
      new_value TEXT NULL,
      changed_by BIGINT NULL,
      changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS doc_template (
      template_id INT AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(30) NOT NULL UNIQUE,
      name VARCHAR(120) NOT NULL,
      body TEXT NOT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      login VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS notes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      content VARCHAR(1000) NULL,
      id_user INT NOT NULL,
      created_at DATE NOT NULL,
      CONSTRAINT fk_notes_user FOREIGN KEY (id_user) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

try {
    foreach ($stmts as $sql) {
        db()->exec($sql);
    }
    echo "✅ Таблицы созданы\n";

    db()->exec("INSERT IGNORE INTO user_role (code,name,level) VALUES
        ('user','Пользователь',1),
        ('manager','Менеджер',5),
        ('admin','Администратор',9)");
        
    db()->exec("INSERT IGNORE INTO user_status (code,name) VALUES
        ('pending','Ожидает'),('active','Активен'),
        ('blocked','Заблокирован'),('deleted','Удалён')");
    echo "✅ Справочники заполнены\n";

    db()->exec("INSERT IGNORE INTO doc_template (code,name,body) VALUES
      ('contract','Договор купли-продажи',
       'ДОГОВОР № {contract_no}\n\nПродавец: {seller}\nПокупатель: {buyer}\nАвтомобиль: {brand} {model}, VIN {vin}, {year} г.\nЦена: {price} руб.\n\nДата: {date}')");
    echo "✅ Шаблоны добавлены\n";

    $cntUsers = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($cntUsers === 0) {
        db()->exec("INSERT INTO users (id, login) VALUES
            (3,'user03'), (7,'user07'), (12,'user12'), (25,'user25')");
    }

    $cntNotes = (int)db()->query("SELECT COUNT(*) FROM notes")->fetchColumn();
    if ($cntNotes === 0) {
        db()->exec("INSERT INTO notes (id, title, content, id_user, created_at) VALUES
            (1,'Конференция ИТ',   'Обсуждение новых технологий', 25,'2027-03-15'),
            (2,'Планёрка отдела',  'Задачи на неделю',            12,'2027-03-16'),
            (3,'Идеи для проекта', 'Список гипотез для MVP',      25,'2027-03-17'),
            (4,'Напоминание',      'Позвонить клиенту в 15:00',    7,'2027-03-18'),
            (5,'Отчёт за квартал', 'Черновик финансового отчёта',  3,'2027-03-19')");
    }
    echo "✅ Демо-данные notes/users добавлены\n";

    $exists = db()->query("SELECT 1 FROM user WHERE login='admin'")->fetch();
    if (!$exists) {
        $hash = password_hash('Admin123!', PASSWORD_BCRYPT);
        db()->prepare('INSERT INTO user (login,password_hash,last_name,first_name,middle_name,email,role_id,status_id,password_changed_at)
            VALUES (?,?,?,?,?,?,
              (SELECT role_id FROM user_role WHERE code="admin"),
              (SELECT status_id FROM user_status WHERE code="active"), NOW())')
            ->execute(['admin',$hash,'Админов','Иван','Петрович','admin@autocatalog.ru']);
        echo "✅ Админ: admin / Admin123!\n";
    }
    
    echo "🎉 Готово. Открой index.php\n";
} catch (PDOException $e) {
    http_response_code(500);
    echo "❌ " . $e->getMessage();
}