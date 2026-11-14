<?php

function app_setting(string $name, string $default = ''): string {
    static $privateSettings = null;
    if ($privateSettings === null) {
        $settingsFile = __DIR__ . '/private_config.php';
        $privateSettings = is_file($settingsFile) ? require $settingsFile : [];
        if (!is_array($privateSettings)) {
            throw new RuntimeException('The private hosting configuration must return an array.');
        }
    }

    $environmentValue = getenv($name);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    $value = $privateSettings[$name] ?? $default;
    if (!is_string($value)) {
        throw new RuntimeException("The private hosting setting $name must be a string.");
    }
    return $value;
}

date_default_timezone_set(app_setting('APP_TIMEZONE', 'Africa/Johannesburg'));

function get_db(): PDO {
    static $conn = null;
    if ($conn instanceof PDO) {
        return $conn;
    }

    $dsn = app_setting('DB_DSN');
    $user = app_setting('DB_USER') ?: null;
    $password = app_setting('DB_PASSWORD') ?: null;

    if (!$dsn) {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
            throw new RuntimeException('Could not create the local database directory.');
        }
        $dsn = 'sqlite:' . $dataDir . '/salon.sqlite';
    }

    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $conn->exec('PRAGMA foreign_keys = ON');
    }

    initialize_schema($conn);
    return $conn;
}

function initialize_schema(PDO $conn): void {
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS availability_slots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                appointment_date TEXT NOT NULL,
                start_time TEXT NOT NULL,
                end_time TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'Available'
                    CHECK (status IN ('Available', 'Held', 'Booked', 'Closed')),
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE (appointment_date, start_time, end_time)
            )
        ");
        $conn->exec("
            CREATE TABLE IF NOT EXISTS appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL,
                service TEXT NOT NULL,
                appointment_date TEXT NOT NULL,
                appointment_time TEXT NOT NULL,
                message TEXT,
                style_image TEXT,
                bring_hairpieces TEXT NOT NULL CHECK (bring_hairpieces IN ('Yes','No')),
                status TEXT NOT NULL DEFAULT 'Pending'
                    CHECK (status IN ('Pending','Confirmed','Completed','Cancelled')),
                slot_id INTEGER REFERENCES availability_slots(id),
                feedback_token TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        ensure_sqlite_column($conn, 'appointments', 'slot_id', 'INTEGER REFERENCES availability_slots(id)');
        ensure_sqlite_column($conn, 'appointments', 'feedback_token', 'TEXT');
        $conn->exec("
            CREATE TABLE IF NOT EXISTS customer_feedback (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                appointment_id INTEGER REFERENCES appointments(id) ON DELETE SET NULL,
                name TEXT,
                email TEXT,
                rating INTEGER CHECK (rating IS NULL OR rating BETWEEN 1 AND 5),
                message TEXT NOT NULL,
                feedback_type TEXT NOT NULL DEFAULT 'Website',
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $conn->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_feedback_appointment ON customer_feedback(appointment_id)');
        $conn->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL,
                quantity INTEGER NOT NULL,
                collection_method TEXT NOT NULL,
                address TEXT,
                paxi_location TEXT,
                email_sent INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $conn->exec('CREATE INDEX IF NOT EXISTS idx_slots_date_status ON availability_slots(appointment_date, status)');
        $conn->exec('CREATE INDEX IF NOT EXISTS idx_appointments_slot_status ON appointments(slot_id, status)');
        return;
    }

    if ($driver !== 'mysql') {
        throw new RuntimeException('The configured database must use SQLite or MySQL.');
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS availability_slots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'Available',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_slot_time (appointment_date, start_time, end_time),
            KEY idx_slots_date_status (appointment_date, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            service VARCHAR(100) NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            message TEXT,
            style_image VARCHAR(255),
            bring_hairpieces ENUM('Yes','No') NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Pending',
            slot_id INT NULL,
            feedback_token CHAR(64) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_appointments_slot_status (slot_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ensure_mysql_column($conn, 'appointments', 'status', "VARCHAR(20) NOT NULL DEFAULT 'Pending'");
    ensure_mysql_column($conn, 'appointments', 'slot_id', 'INT NULL');
    ensure_mysql_column($conn, 'appointments', 'feedback_token', 'CHAR(64) NULL');
    $conn->exec("
        CREATE TABLE IF NOT EXISTS customer_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NULL,
            name VARCHAR(100) NULL,
            email VARCHAR(100) NULL,
            rating TINYINT NULL,
            message TEXT NOT NULL,
            feedback_type VARCHAR(20) NOT NULL DEFAULT 'Website',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_feedback_created (created_at),
            UNIQUE KEY uq_feedback_appointment (appointment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            quantity INT NOT NULL,
            collection_method VARCHAR(50) NOT NULL,
            address TEXT,
            paxi_location TEXT,
            email_sent TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensure_sqlite_column(PDO $conn, string $table, string $column, string $definition): void {
    $columns = $conn->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $existing) {
        if ($existing['name'] === $column) {
            return;
        }
    }
    $conn->exec("ALTER TABLE $table ADD COLUMN $column $definition");
}

function ensure_mysql_column(PDO $conn, string $table, string $column, string $definition): void {
    $statement = $conn->query('SHOW COLUMNS FROM ' . $table);
    foreach ($statement->fetchAll() as $existing) {
        if ($existing['Field'] === $column) {
            return;
        }
    }
    $conn->exec("ALTER TABLE $table ADD COLUMN $column $definition");
}
