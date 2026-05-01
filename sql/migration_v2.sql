-- ============================================================
-- Migración v2 — ejecutar sobre base de datos existente
-- ============================================================

USE galeria_cumple;

-- Mejora 2: eliminar campos de posición 3D de photos
--   (las posiciones ahora son efímeras y se calculan en el cliente)
ALTER TABLE photos DROP COLUMN IF EXISTS pos_x;
ALTER TABLE photos DROP COLUMN IF EXISTS pos_y;
ALTER TABLE photos DROP COLUMN IF EXISTS pos_z;
ALTER TABLE photos DROP COLUMN IF EXISTS rot_x;
ALTER TABLE photos DROP COLUMN IF EXISTS rot_y;
ALTER TABLE photos DROP COLUMN IF EXISTS rot_z;

-- Mejora 3/4: añadir color y cover_url a albums si no existen
ALTER TABLE albums
  MODIFY COLUMN emoji VARCHAR(10) NOT NULL DEFAULT '';

ALTER TABLE albums
  ADD COLUMN IF NOT EXISTS color     VARCHAR(7)   NOT NULL DEFAULT '#0071e3';

ALTER TABLE albums
  ADD COLUMN IF NOT EXISTS cover_url VARCHAR(500) NULL;
