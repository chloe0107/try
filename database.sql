CREATE DATABASE IF NOT EXISTS library_management_system 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_management_system;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS activity_logs; 
DROP TABLE IF EXISTS reservations; 
DROP TABLE IF EXISTS borrowings; 
DROP TABLE IF EXISTS books; 
DROP TABLE IF EXISTS categories; 
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS=1;
CREATE TABLE users(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
full_name VARCHAR(120) NOT NULL,
email VARCHAR(190) NOT NULL UNIQUE,
password_hash VARCHAR(255) NOT NULL,
role ENUM('admin','librarian','member') NOT NULL DEFAULT 'member',
email_verified TINYINT(1) NOT NULL DEFAULT 0,verification_token CHAR(64) NULL,
verification_expires DATETIME NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,
created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
INDEX idx_role(role),INDEX idx_token(verification_token)) ENGINE=InnoDB;

CREATE TABLE categories(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(100) NOT NULL UNIQUE,description TEXT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;

CREATE TABLE books(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
title VARCHAR(200) NOT NULL,
author VARCHAR(160) NOT NULL,
isbn VARCHAR(30) NULL UNIQUE,
publisher VARCHAR(160) NULL,
published_year SMALLINT UNSIGNED NULL,
category_id INT UNSIGNED NULL,
total_copies INT UNSIGNED NOT NULL DEFAULT 1,
available_copies INT UNSIGNED NOT NULL DEFAULT 1,
description TEXT NULL,
is_archived TINYINT(1) NOT NULL DEFAULT 0,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_books_category
 FOREIGN KEY(category_id)
  REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT chk_copies CHECK(available_copies<=total_copies),
  INDEX idx_title(title),INDEX idx_author(author),
  INDEX idx_archived(is_archived)) ENGINE=InnoDB;


CREATE TABLE borrowings(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id INT UNSIGNED NOT NULL,
book_id INT UNSIGNED NOT NULL,
status ENUM('pending','approved','borrowed','returned','rejected') NOT NULL DEFAULT 'pending',
requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
approved_by INT UNSIGNED NULL,
approved_at DATETIME NULL,
released_by INT UNSIGNED NULL,
released_at DATETIME NULL,
due_date DATE NULL,
returned_by INT UNSIGNED NULL,
returned_at DATETIME NULL,
CONSTRAINT fk_borrow_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
CONSTRAINT fk_borrow_book FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE RESTRICT,
CONSTRAINT fk_approved FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL,
CONSTRAINT fk_released FOREIGN KEY(released_by) REFERENCES users(id) ON DELETE SET NULL,
CONSTRAINT fk_returned FOREIGN KEY(returned_by) REFERENCES users(id) ON DELETE SET NULL,
INDEX idx_status(status),
INDEX idx_borrow_user(user_id),
INDEX idx_requested(requested_at),
INDEX idx_due(due_date)) ENGINE=InnoDB;

CREATE TABLE reservations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id INT UNSIGNED NOT NULL,
book_id INT UNSIGNED NOT NULL,
status ENUM('pending','approved','ready','fulfilled','cancelled','rejected','expired') NOT NULL DEFAULT 'pending',
requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
processed_by INT UNSIGNED NULL,
updated_at DATETIME NULL,
borrowing_id BIGINT UNSIGNED NULL,
CONSTRAINT fk_res_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
CONSTRAINT fk_res_book FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE RESTRICT,
CONSTRAINT fk_res_processor FOREIGN KEY(processed_by) REFERENCES users(id) ON DELETE SET NULL,
CONSTRAINT fk_res_borrowing FOREIGN KEY(borrowing_id) REFERENCES borrowings(id) ON DELETE SET NULL,
INDEX idx_res_status(status),
INDEX idx_res_book_queue(book_id,status,id),
INDEX idx_res_user(user_id),
INDEX idx_res_requested(requested_at)) ENGINE=InnoDB;

CREATE TABLE activity_logs(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id INT UNSIGNED NULL,
action VARCHAR(80) NOT NULL,details VARCHAR(500) NULL,
ip_address VARCHAR(45) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_log_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
INDEX idx_log_created(created_at),INDEX idx_log_action(action)) ENGINE=InnoDB;

INSERT INTO categories(name,description)
 VALUES('Computer Science','Programming, IT, and computing'),
 ('Fiction','Novels and short stories'),
 ('History','Historical works'),('Science','Science and research');

INSERT INTO books(title,author,isbn,publisher,published_year,category_id,total_copies,available_copies,description)
VALUES('Learning PHP and MySQL','Example Author','9780000000001','Demo Press',2024,1,4,4,'Sample record; replace with real catalog data.'),
('The Secret Garden','Frances Hodgson Burnett','9780000000002','Demo Press',1911,2,3,3,'Sample record; replace with real catalog data.'),
('A Brief History of Time','Stephen Hawking','9780000000003','Demo Press',1988,4,2,2,'Sample record; replace with real catalog data.');
