# Galería de Fotos 3D

Galería de fotos con navegación 3D, estilo Apple/glassmorphism. PHP 8+, MySQL, sin dependencias externas.

## Instalación en 5 pasos

**1. Importar la base de datos**
```bash
mysql -u root -p < sql/schema.sql
```
O desde phpMyAdmin: importar el archivo `sql/schema.sql`.

**2. Configurar credenciales**

Editar `config/db.php` y ajustar:
```php
const DB_HOST = 'localhost';
const DB_NAME = 'galeria_cumple';
const DB_USER = 'root';
const DB_PASS = '';       // tu contraseña MySQL
```

**3. Permisos de uploads (Linux/Mac)**
```bash
chmod 755 uploads/
```
En Windows con XAMPP los permisos son automáticos.

**4. Iniciar el servidor**

**Con XAMPP** — colocar la carpeta en `htdocs/` y abrir:
```
http://localhost/web%20galeria%20cumple/
```

**Con PHP built-in server:**
```bash
cd "web galeria cumple"
php -S localhost:8000
# Abrir http://localhost:8000
```

**5. ¡Listo!** Abre el navegador, crea álbumes y sube fotos.

---

## Estructura de archivos

```
├── index.php          # Vista principal (álbumes)
├── gallery.php        # Galería 3D de un álbum
├── config/db.php      # Conexión PDO MySQL
├── api/
│   ├── albums.php     # CRUD álbumes (JSON)
│   └── photos.php     # CRUD fotos + upload (JSON)
├── css/style.css      # Estilos globales
├── js/
│   ├── albums.js      # Lógica de álbumes
│   └── gallery3d.js   # Motor 3D y galería
├── uploads/           # Imágenes subidas
└── sql/schema.sql     # Tablas + datos de ejemplo
```

## Controles de la galería 3D

| Acción | Resultado |
|--------|-----------|
| Arrastrar (ratón/dedo) | Mover la escena |
| Rueda del ratón | Zoom in/out |
| Pellizco táctil | Zoom in/out |
| Clic en foto | Ampliar en lightbox |
| ESC | Cerrar lightbox |
| Icono 🗑 (hover) | Eliminar foto |

## Requisitos

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Extensión PDO + pdo_mysql habilitada
