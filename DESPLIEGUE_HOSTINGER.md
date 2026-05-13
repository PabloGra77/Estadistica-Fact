# Guía de Despliegue en Hostinger

## Requisitos previos

- Plan de Hostinger con **PHP 8.1+** y **MySQL 8.0+**
- Acceso a hPanel (panel de control de Hostinger)
- FileZilla o cliente SFTP

---

## 1. Crear la base de datos

1. En hPanel → **Bases de datos** → **Gestionar** → **Crear nueva base de datos**
2. Pon un nombre descriptivo, p.ej.: `u123456_ppl`
3. Crea un usuario, p.ej.: `u123456_ppl` con contraseña segura
4. Asigna **todos los privilegios** al usuario sobre esa base de datos
5. Anota los datos: **host** (normalmente `localhost`), nombre DB, usuario, contraseña

---

## 2. Subir los archivos

1. Conecta por SFTP con FileZilla a tu hosting
2. Sube **todo el contenido** de `TableroAtenciones-PHP/` dentro de `public_html/`
   - La carpeta `public/` debe quedar en `public_html/public/`
   - Si Hostinger sirve directamente desde `public_html/`, el `index.php` de `public/` debe estar en la raíz
3. Crea manualmente (NO subir desde git) el archivo `config/config.php` con tus credenciales reales:

```php
<?php
// =============================================
// CONFIGURACIÓN LOCAL — NO SUBIR AL REPOSITORIO
// =============================================

// Base de datos (usar tus datos de Hostinger)
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'u123456_ppl');        // tu nombre de DB en Hostinger
define('DB_USER', 'u123456_ppl');        // tu usuario de DB en Hostinger
define('DB_PASS', 'TU_CONTRASEÑA_AQUI'); // contraseña segura

// Rutas de almacenamiento
define('BASE_PATH',    dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage/soportes');
define('INBOX_PATH',   BASE_PATH . '/storage/inbox');

// Límite de subida vía HTTP (en MB) — para ZIPs grandes usar la bandeja del servidor
define('MAX_UPLOAD_MB', 200);

// Dominio de correo institucional
define('EMAIL_DOMAIN', '@ipsgoleman.com.co');

// Roles
define('ROL_ADMINISTRADOR', 0);
define('ROL_FACTURADOR',    1);
define('ROL_EQUIPO_PPL',    2);
define('ROL_ESTADISTICO',   3);

// Servicios y pesos ponderados (suma = 100)
const TIPOS_SERVICIO  = ['VALPS','VALPQ','PS','PQ','TF','TS','TZ','PYP','MED','REG'];
const PESOS_SERVICIO  = [0=>10, 1=>10, 2=>20, 3=>20, 4=>5, 5=>10, 6=>10, 7=>5, 8=>5, 9=>5];

// Precios por paquete (en COP)
const PRECIO_PAQUETE  = [1 => 780000, 2 => 950000];

// Porcentaje mínimo de cumplimiento
const PCT_CUMPLIMIENTO = 80;
```

---

## 3. Importar el esquema de la base de datos

1. En hPanel → **phpMyAdmin** → selecciona tu base de datos
2. Ve a **Importar** y sube el archivo `sql/schema.sql`

---

## 4. Generar e importar el seed de usuarios

En tu equipo local (con PHP instalado):

```bash
cd /ruta/al/proyecto
php sql/generate_seed.php > sql/seed.sql
# El script pedirá una contraseña para cada usuario:
#   - soporte.360  (Administrador)
#   - facturador   (Facturador)
#   - equipoppl    (Equipo PPL)
#   - estadistico  (Estadístico)
```

Luego importa `sql/seed.sql` en phpMyAdmin (mismos pasos que el schema).

> **⚠️ El archivo `sql/seed.sql` contiene hashes de contraseñas. Elimínalo del servidor después de importarlo y no lo subas al repositorio (ya está en `.gitignore`).**

Esto crea los 4 usuarios iniciales:

| Usuario | Contraseña | Rol |
|---------|-----------|-----|
| `soporte.360` | *(la que ingresaste al ejecutar generate_seed.php)* | Administrador |
| `facturador` | *(la que ingresaste al ejecutar generate_seed.php)* | Facturador |
| `equipoppl` | *(la que ingresaste al ejecutar generate_seed.php)* | EquipoPPL |
| `estadistico` | *(la que ingresaste al ejecutar generate_seed.php)* | Estadístico |

> **⚠️ Cambia las contraseñas inmediatamente después del primer login.**

---

## 5. Configurar permisos de carpetas

En hPanel → **Administrador de archivos** o por SSH:

```bash
chmod 755 storage/
chmod 755 storage/soportes/
chmod 755 storage/inbox/
chmod 755 logs/
```

---

## 6. Subir ZIPs grandes (>200 MB)

Para ZIPs de soportes mayores a 200 MB:

1. Súbelos por **SFTP/FileZilla** directamente a `storage/inbox/` en el servidor
2. En la aplicación → **Soportes** → **Importar ZIP** → pestaña **"Bandeja del servidor"**
3. Selecciona el ZIP y haz clic en **Procesar**

El sistema procesa los PDFs en streaming (sin extraer el ZIP completo) para no agotar la memoria.

---

## 7. Configurar `.user.ini` (límites de subida)

El archivo `public/.user.ini` ya está incluido con:

```ini
upload_max_filesize=200M
post_max_size=210M
max_execution_time=300
max_input_time=300
```

Hostinger respeta este archivo automáticamente para PHP-FPM/CGI.

---

## 8. Verificación post-despliegue

1. Abre la URL de tu sitio
2. Inicia sesión con `soporte.360` y la contraseña que generaste
3. Ve a **Pacientes** y verifica que carga correctamente
4. Ve a **Usuarios** y cambia todas las contraseñas
5. Prueba subir un ZIP pequeño de prueba

---

## Notas de seguridad

- `config/config.php` está en `.gitignore` — **NUNCA** se sube al repositorio
- `sql/seed.sql` está en `.gitignore` — elimínalo del servidor tras importarlo
- `storage/inbox/` solo contiene `.gitkeep` — los ZIPs no se versionar
- Los ZIPs en `storage/inbox/` deben ser eliminados manualmente después de procesarlos
