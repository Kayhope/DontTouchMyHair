-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS salon_db;
USE salon_db;

-- Owner-managed appointment times. One slot can be held by only one request.
CREATE TABLE IF NOT EXISTS availability_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slot_time (appointment_date, start_time, end_time),
    KEY idx_slots_date_status (appointment_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the appointments table
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
    bring_hairpieces ENUM('Yes', 'No') NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    slot_id INT NULL,
    feedback_token CHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Private website feedback and appointment reviews.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
