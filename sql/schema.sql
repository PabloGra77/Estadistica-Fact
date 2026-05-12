-- ============================================================
-- Esquema PPL · Tablero de Atenciones
-- Motor: MySQL 8 / MariaDB 10.5+  (Hostinger)
-- Ejecutar UNA VEZ antes de desplegar la aplicación.
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── Usuarios ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Usuarios (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre_usuario          VARCHAR(100)    NOT NULL,
    password_hash           VARCHAR(255)    NOT NULL,
    rol                     TINYINT UNSIGNED NOT NULL DEFAULT 3
                                COMMENT '0=Administrador 1=Facturador 2=EquipoPPL 3=Estadistico',
    activo                  TINYINT(1)      NOT NULL DEFAULT 1,
    cambio_password_req     TINYINT(1)      NOT NULL DEFAULT 0,
    intentos_fallidos       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueo_hasta           DATETIME        NULL,
    fecha_creacion          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nombre_usuario (nombre_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pacientes ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Pacientes (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    documento       VARCHAR(20)     NOT NULL,
    nombre          VARCHAR(200)    NOT NULL,
    paquete         TINYINT UNSIGNED NOT NULL DEFAULT 1
                        COMMENT '1=Paquete1 2=Paquete2',
    nui             VARCHAR(30)     NULL DEFAULT NULL,
    fecha_nacimiento DATE            NULL DEFAULT NULL,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    fecha_creacion  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documento (documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Atenciones ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Atenciones (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    paciente_id     INT UNSIGNED    NOT NULL,
    servicio        TINYINT UNSIGNED NOT NULL
                        COMMENT '0=VALPS 1=VALPQ 2=PS 3=PQ 4=TF 5=TS',
    anio_atencion   SMALLINT UNSIGNED NOT NULL,
    mes_atencion    TINYINT UNSIGNED  NOT NULL,
    fecha_carga     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cargado_por     VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_atencion (paciente_id, servicio, anio_atencion, mes_atencion),
    CONSTRAINT fk_aten_paciente FOREIGN KEY (paciente_id)
        REFERENCES Pacientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Soportes ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Soportes (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    atencion_id             INT UNSIGNED    NOT NULL,
    nombre_original         VARCHAR(500)    NOT NULL,
    nombre_fisico           VARCHAR(100)    NOT NULL,
    hash_sha256             CHAR(64)        NOT NULL,
    estado                  TINYINT UNSIGNED NOT NULL DEFAULT 0
                                COMMENT '0=Pendiente 1=AprobadoAuto 2=AprobadoManual 3=Rechazado 4=Inconsistente',
    texto_extraido          MEDIUMTEXT      NOT NULL,
    requiere_ocr            TINYINT(1)      NOT NULL DEFAULT 0,
    fecha_carga             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cargado_por             VARCHAR(100)    NOT NULL,
    auditado_por            VARCHAR(100)    NULL,
    fecha_auditoria         DATETIME        NULL,
    observacion_auditoria   TEXT            NULL,
    PRIMARY KEY (id),
    KEY idx_hash (hash_sha256),
    KEY idx_estado (estado),
    CONSTRAINT fk_sop_atencion FOREIGN KEY (atencion_id)
        REFERENCES Atenciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Auditoría ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS AuditoriasAccion (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_usuario  VARCHAR(100)    NOT NULL,
    accion          VARCHAR(200)    NOT NULL,
    detalle         TEXT            NOT NULL,
    fecha           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip              VARCHAR(45)     NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_fecha (fecha),
    KEY idx_usuario (nombre_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ============================================================
-- DATOS INICIALES (contraseñas hasheadas con bcrypt cost=12)
-- ============================================================
-- Ejecutar seed.sql por separado.
