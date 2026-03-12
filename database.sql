-- =============================================
-- Tutor Tracking System - Database Schema
-- =============================================



-- Admin accounts
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tutor accounts (created by admin)
CREATE TABLE tutors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    nickname VARCHAR(50) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Schools (managed by admin)
CREATE TABLE schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Teaching records (submitted by tutors)
CREATE TABLE teaching_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    school_id INT NOT NULL,
    teaching_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    teaching_summary TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    teaching_fee DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutors(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Teaching photos (multiple per record)
CREATE TABLE teaching_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL,
    photo_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (record_id) REFERENCES teaching_records(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Index for faster monthly queries
CREATE INDEX idx_teaching_date ON teaching_records(teaching_date);
CREATE INDEX idx_tutor_date ON teaching_records(tutor_id, teaching_date);
CREATE INDEX idx_photo_record ON teaching_photos(record_id);

-- Default admin account (password: 123456)
INSERT INTO admins (username, password, name) VALUES
('admin', '$2y$10$mgPl9z.M520LMW8s8RkUC.usBj4EWAv9kjkzykIPZeTzKX7bNNwEm', 'ผู้ดูแลระบบ');
