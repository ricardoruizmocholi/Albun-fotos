-- ============================================================
-- Galería de Fotos 3D — Schema MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS galeria_cumple
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE galeria_cumple;

CREATE TABLE IF NOT EXISTS albums (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120)  NOT NULL,
  emoji      VARCHAR(10)   NOT NULL DEFAULT '📷',
  color      VARCHAR(7)    NOT NULL DEFAULT '#0071e3',
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS photos (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id   INT UNSIGNED  NOT NULL,
  url        VARCHAR(500)  NOT NULL,
  title      VARCHAR(200)  NOT NULL DEFAULT '',
  pos_x      FLOAT         NOT NULL DEFAULT 0,
  pos_y      FLOAT         NOT NULL DEFAULT 0,
  pos_z      FLOAT         NOT NULL DEFAULT 0,
  rot_x      FLOAT         NOT NULL DEFAULT 0,
  rot_y      FLOAT         NOT NULL DEFAULT 0,
  rot_z      FLOAT         NOT NULL DEFAULT 0,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photos_album
    FOREIGN KEY (album_id) REFERENCES albums(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Datos de ejemplo
-- ============================================================

INSERT INTO albums (name, emoji, color) VALUES
  ('Cumpleaños 2024', '🎂', '#ff6b6b'),
  ('Viaje a la playa', '🏖️', '#0071e3');

-- Fotos álbum 1 (id=1) — Unsplash
INSERT INTO photos (album_id, url, title, pos_x, pos_y, pos_z, rot_x, rot_y, rot_z) VALUES
  (1, 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800', 'El pastel',       -320,  60, -200,  2, -8,  1),
  (1, 'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=800', 'Los globos',    120, -80,  100, -3,  6, -2),
  (1, 'https://images.unsplash.com/photo-1464349095431-e9a21285b5f3?w=800', 'Sorpresa',      -80, 140, -350,  4, 10,  3);

-- Fotos álbum 2 (id=2) — Unsplash
INSERT INTO photos (album_id, url, title, pos_x, pos_y, pos_z, rot_x, rot_y, rot_z) VALUES
  (2, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800', 'Atardecer',    -280, -40, -150, -2, -7,  2),
  (2, 'https://images.unsplash.com/photo-1476673160081-cf065607f449?w=800', 'Palmeras',      200,  90,  200,  3,  5, -1),
  (2, 'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=800', 'Olas',          -60, -120, -400,  1, -9,  4);
