CREATE DATABASE IF NOT EXISTS snu_student_support CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE snu_student_support;

CREATE TABLE IF NOT EXISTS admins (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
 username VARCHAR(60) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT one_admin_only CHECK (id = 1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS students (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 student_number VARCHAR(40) NOT NULL UNIQUE,
 student_name VARCHAR(150) NOT NULL,
 phone_number VARCHAR(35) NOT NULL,
 department VARCHAR(100) NOT NULL,
 class_semester VARCHAR(100) NOT NULL,
 deleted_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_students_department (department),
 INDEX idx_students_name (student_name)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS laptops (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 student_id BIGINT UNSIGNED NOT NULL UNIQUE,
 brand VARCHAR(80) NOT NULL,
 accessories_notes TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_laptop_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
 INDEX idx_laptop_brand (brand)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS laptop_accessories (
 laptop_id BIGINT UNSIGNED NOT NULL,
 accessory VARCHAR(50) NOT NULL,
 PRIMARY KEY (laptop_id, accessory),
 CONSTRAINT fk_accessory_laptop FOREIGN KEY (laptop_id) REFERENCES laptops(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS support_records (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 student_id BIGINT UNSIGNED NOT NULL,
 laptop_id BIGINT UNSIGNED NOT NULL,
 support_date DATE NOT NULL,
 problem_type ENUM('Hardware Only','Software Only','Both Hardware and Software') NOT NULL,
 problem_categories TEXT NULL,
 problem_description TEXT NOT NULL,
 work_status ENUM('Pending','In Progress','Done') NOT NULL DEFAULT 'Pending',
 laptop_status ENUM('In Store','Collected') NOT NULL DEFAULT 'In Store',
 completion_date DATE NULL,
 deleted_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_support_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
 CONSTRAINT fk_support_laptop FOREIGN KEY (laptop_id) REFERENCES laptops(id) ON DELETE RESTRICT,
 INDEX idx_support_student_date (student_id, support_date),
 INDEX idx_support_status (work_status),
 INDEX idx_support_type (problem_type)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS backup_records (
 support_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 drive_c ENUM('Yes','No','Not Needed') NOT NULL DEFAULT 'Not Needed',
 drive_d ENUM('Yes','No','Not Needed') NOT NULL DEFAULT 'Not Needed',
 desktop ENUM('Yes','No','Not Needed') NOT NULL DEFAULT 'Not Needed',
 documents ENUM('Yes','No','Not Needed') NOT NULL DEFAULT 'Not Needed',
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_backup_support FOREIGN KEY (support_id) REFERENCES support_records(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
