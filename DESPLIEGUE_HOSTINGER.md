# Guía de Despliegue en Hostinger — Tablero PPL

## Requisitos previos en Hostinger
- Plan de hosting compartido (PHP 8.1+ y MySQL 8 / MariaDB 10.4+)
- Acceso a hPanel

---

## Paso 1 — Crear base de datos MySQL en hPanel

1. Entra a **hPanel → Bases de datos → Bases de datos MySQL**
2. Crea una nueva base de datos, p.ej.: `u123456_tablero`
3. Crea un usuario, p.ej.: `u123456_ppl` con contraseña segura
4. Asigna **todos los privilegios** al usuario sobre la base de datos
5. Anota los datos: **host** (normalmente `localhost`), nombre DB, usuario, contraseña

---

## Paso 2 — Actualizar config/config.php

Edita el archivo `config/config.php` con los datos reales de Hostinger:

```php
define('DB_HOST',     'localhost');
define('DB_NAME',     'u123456_tablero');   // tu nombre real
define('DB_USER',     'u123456_ppl');       // tu usuario real
define('DB_PASS',     'TuContraseñaSegura');
define('DB_CHARSET',  'utf8mb4');
```

También actualiza la ruta de almacenamiento (fuera de `public_html`):

```php
define('STORAGE_PATH', '/home/u123456/storage/soportes');  // ajusta tu ruta
```

Y el dominio de tu aplicación para CSRF:

```php
define('APP_URL', 'https://tudominio.com');
```

---

## Paso 3 — Subir archivos

### Opción A — File Manager en hPanel
1. Ve a **hPanel → File Manager**
2. Navega a `public_html/` (o la carpeta de tu dominio)
3. Sube todos los archivos del proyecto **excepto**:
   - `storage/` (se crea aparte fuera de webroot)
   - `sql/generate_seed.php` (solo para uso local)

### Opción B — FTP (FileZilla)
1. Obtén credenciales FTP en **hPanel → Cuentas FTP**
2. Conecta con FileZilla
3. Sube la carpeta completa a `public_html/`

### Estructura final en el servidor
```
public_html/
├── .htaccess
├── index.php
├── config/
├── controllers/
├── core/
├── views/
├── public/
│   ├── css/app.css
│   └── js/app.js
└── sql/
    ├── schema.sql
    └── seed.sql

/home/u123456/storage/soportes/   ← FUERA de public_html
```

---

## Paso 4 — Crear carpeta storage fuera de webroot

En **hPanel → File Manager**, navega al directorio raíz `/home/u123456/` y crea la carpeta:
```
storage/soportes/
```

O por SSH (si Hostinger lo permite):
```bash
mkdir -p ~/storage/soportes
chmod 750 ~/storage/soportes
```

---

## Paso 5 — Importar el esquema SQL

1. En **hPanel → phpMyAdmin**, selecciona tu base de datos
2. Ve a la pestaña **Importar**
3. Sube y ejecuta `sql/schema.sql`

---

## Paso 6 — Generar y ejecutar el seed de usuarios

En tu máquina local (antes de subir):
```bash
cd files/TableroAtenciones-PHP
php sql/generate_seed.php > sql/seed.sql
```

Luego en phpMyAdmin, importa `sql/seed.sql`.

Esto crea los 4 usuarios iniciales:

| Usuario | Contraseña | Rol |
|---------|-----------|-----|
| `admin` | `Admin2026!` | Administrador |
| `facturador` | `Facturador2026!` | Facturador |
| `equipoppl` | `EquipoPPL2026!` | EquipoPPL |
| `estadistico` | `Estadistico2026!` | Estadístico |

> **⚠️ Cambia las contraseñas inmediatamente después del primer login.**

---

## Paso 7 — Verificar .htaccess

Si el hosting tiene `mod_rewrite` activo (la mayoría lo tiene), el archivo `.htaccess` ya está configurado.

En caso de error 500, en **hPanel → PHP Config** asegúrate de tener PHP 8.1+.

Si aparece error 404 en todas las rutas (excepto `/`), activa **AllowOverride All** — en Hostinger esto está habilitado por defecto.

---

## Paso 8 — Permisos de archivos

```
Carpetas: 755
Archivos PHP: 644
storage/soportes/: 750
```

En File Manager puedes cambiar permisos haciendo clic derecho → **Change Permissions**.

---

## Paso 9 — Verificar despliegue

1. Abre `https://tudominio.com` → debe redirigir a `/login`
2. Inicia sesión con `admin@ipsgoleman.com` / `Admin2026!`
3. Verifica las 4 secciones: Inicio, Pacientes, Atenciones, Soportes
4. Ve a Usuarios y cambia todas las contraseñas

---

## Configuración HTTPS (obligatorio)

En **hPanel → SSL → Instalar SSL gratuito (Let's Encrypt)**. Hostinger lo ofrece gratis.

El `.htaccess` ya incluye redirección HTTP→HTTPS:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Resolución de problemas comunes

| Error | Causa | Solución |
|-------|-------|----------|
| Pantalla en blanco | Error PHP sin display | Revisa **Error Logs** en hPanel |
| 500 en todas las páginas | .htaccess incompatible | Verifica la versión de PHP en hPanel |
| "Access denied" MySQL | Credenciales incorrectas | Revisa `config/config.php` |
| Soportes no se guardan | Carpeta storage con permisos incorrectos | `chmod 750 ~/storage/soportes` |
| Login no funciona | Sesiones sin permisos | Verifica `session.save_path` en php.ini |

---

## Variables de config.php para producción

```php
define('APP_ENV',     'production');  // cambia de 'development'
define('APP_DEBUG',   false);         // desactiva debug en producción
define('SESSION_SECURE', true);       // cookies HTTPS only
```
