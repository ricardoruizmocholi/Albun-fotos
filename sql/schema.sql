-- ============================================================
-- Galería de Fotos 3D — Schema MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS galeria_cumple
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE galeria_cumple;

CREATE TABLE IF NOT EXISTS albums (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120)  NOT NULL,
  emoji      VARCHAR(10)   NOT NULL DEFAULT '',
  color      VARCHAR(7)    NOT NULL DEFAULT '#0071e3',
  cover_url  VARCHAR(500)  NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS photos (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  album_id   INT UNSIGNED  NOT NULL,
  url        VARCHAR(500)  NOT NULL,
  title      VARCHAR(200)  NOT NULL DEFAULT '',
  tags       VARCHAR(500)  NOT NULL DEFAULT '',
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photos_album
    FOREIGN KEY (album_id) REFERENCES albums(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Datos de ejemplo
-- ============================================================

INSERT INTO albums (name, color) VALUES
  ('Cumpleaños 2024', '#ff6b6b'),
  ('Viaje a la playa', '#0071e3');

-- Fotos álbum 1 (id=1) — Unsplash
INSERT INTO photos (album_id, url, title, tags) VALUES
  (1, 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800', 'El pastel',  'pastel,cumple,2024'),
  (1, 'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=800', 'Los globos', 'globos,fiesta'),
  (1, 'https://images.unsplash.com/photo-1464349095431-e9a21285b5f3?w=800', 'Sorpresa',   'sorpresa,cumple');

-- Fotos álbum 2 (id=2) — Unsplash
INSERT INTO photos (album_id, url, title, tags) VALUES
  (2, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800', 'Atardecer', 'playa,atardecer,verano'),
  (2, 'https://images.unsplash.com/photo-1476673160081-cf065607f449?w=800', 'Palmeras',  'palmeras,tropical,playa'),
  (2, 'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=800', 'Olas',      'olas,mar,verano');
