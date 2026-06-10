-- ============================================================
-- Migration: Authentication, Storage Quota & User Seed
-- Run AFTER importing sql/schema.sql
-- Requires MySQL 8.0+
-- ============================================================

USE galeria_cumple;

-- ---- 1. Users table ----
CREATE TABLE IF NOT EXISTS users (
  id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  username         VARCHAR(60)   NOT NULL UNIQUE,
  password_hash    VARCHAR(255)  NOT NULL,
  role             ENUM('admin','user') NOT NULL DEFAULT 'user',
  storage_limit_mb INT UNSIGNED  NOT NULL DEFAULT 500,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- 2. Seed admin (warlock2484 / chaplin2484_?) ----
INSERT IGNORE INTO users (username, password_hash, role, storage_limit_mb)
VALUES ('warlock2484', '$2y$10$j0qjYQGdbf7V3ecDhz.Ug.imOCglnR3aSo8H0EpHoWzfbZXvZDXje', 'admin', 0);

-- ---- 3. Seed dayane (dayane / xicotetarosquilleta-4) ----
INSERT IGNORE INTO users (username, password_hash, role, storage_limit_mb)
VALUES ('dayane', '$2y$10$P6x0TcOPo9SaQ9t.bm4vPuAStf6e5Y/MaIH8jEJ/jTlYM3aRYTB2y', 'user', 500);

-- ---- 4. Add owner column to albums ----
ALTER TABLE albums
  ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

-- ---- 5. Assign ALL existing albums to dayane (they are her photos) ----
UPDATE albums
  SET user_id = (SELECT id FROM users WHERE username = 'dayane')
  WHERE 1;

-- ---- 6. Foreign key albums → users ----
ALTER TABLE albums
  ADD CONSTRAINT fk_albums_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- ---- 7. Add file_size to photos for quota tracking ----
ALTER TABLE photos
  ADD COLUMN IF NOT EXISTS file_size INT UNSIGNED NOT NULL DEFAULT 0 AFTER tags;
