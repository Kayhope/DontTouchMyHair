<?php
/**
 * Database configuration for Don't Touch My Hair.
 *
 * Uses SQLite so the site works immediately with zero extra setup —
 * no MySQL server to install or configure. The database lives in a
 * single file (data/salon.sqlite) inside this folder.
 *
 * If you later move to a host that requires MySQL, you only need to
 * change this one file: swap the PDO DSN below for
 * "mysql:host=localhost;dbname=salon_db" and update the credentials
 * (Database.sql already has the matching MySQL schema).
 */

function get_db(): PDO {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $dbPath = $dataDir . '/salon.sqlite';
    $isNew = !file_exists($dbPath);

    $conn = new PDO('sqlite:' . $dbPath);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec('PRAGMA foreign_keys = ON;');

    // Always safe to run — CREATE TABLE IF NOT EXISTS is a no-op once the
    // tables already exist, so every request just re-confirms the schema.
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
            status TEXT NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending','Confirmed','Completed','Cancelled')),
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

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
        );
    ");

    return $conn;
}
