-- TK Imperio · estructura (sin datos reales). MySQL 8, InnoDB, utf8mb4.
CREATE DATABASE IF NOT EXISTS tk_imperio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tk_imperio;

CREATE TABLE IF NOT EXISTS tk_usuarios (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(120) NOT NULL,
    usuario        VARCHAR(60)  NOT NULL,
    password_hash  VARCHAR(255) NOT NULL DEFAULT '',
    origen         ENUM('local','sas') NOT NULL DEFAULT 'local',
    sas_usuario_id INT UNSIGNED NULL,
    rol            ENUM('admin','supervisor','usuario') NOT NULL DEFAULT 'usuario',
    color          CHAR(7) NOT NULL DEFAULT '#0d6efd',
    activo         TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login   DATETIME NULL,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario (usuario),
    KEY idx_sas (sas_usuario_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tk_tickets (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo               ENUM('ticket','tarea') NOT NULL DEFAULT 'ticket',
    dni                VARCHAR(20)  NULL,
    cliente            VARCHAR(200) NULL,
    sas_cliente_id     INT UNSIGNED NULL,
    titulo             VARCHAR(200) NULL,
    problematica       TEXT NULL,
    prioridad          ENUM('normal','alta') NOT NULL DEFAULT 'normal',
    estado             ENUM('pendiente','en_proceso','resuelto','anulado') NOT NULL DEFAULT 'pendiente',
    asignado_a         INT UNSIGNED NULL,
    creado_por         INT UNSIGNED NOT NULL,
    fecha_tarea        DATE NULL,
    compromiso_fecha   DATETIME NULL,
    compromiso_detalle VARCHAR(255) NULL,
    nota_resolucion    TEXT NULL,
    creado_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tomado_en          DATETIME NULL,
    resuelto_en        DATETIME NULL,
    resuelto_por       INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_pantalla (estado, asignado_a, creado_en),
    KEY idx_dni (dni),
    CONSTRAINT fk_t_asignado FOREIGN KEY (asignado_a)  REFERENCES tk_usuarios (id),
    CONSTRAINT fk_t_creador  FOREIGN KEY (creado_por)  REFERENCES tk_usuarios (id),
    CONSTRAINT fk_t_resolvio FOREIGN KEY (resuelto_por) REFERENCES tk_usuarios (id),
    CONSTRAINT ck_ticket_datos CHECK (
        (tipo = 'ticket' AND dni IS NOT NULL AND dni <> '' AND cliente IS NOT NULL AND cliente <> '')
        OR (tipo = 'tarea' AND titulo IS NOT NULL AND titulo <> '')
    )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tk_ticket_imagenes (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id INT UNSIGNED NOT NULL,
    archivo   VARCHAR(64) NOT NULL,
    mime      VARCHAR(50) NOT NULL,
    tamano    INT UNSIGNED NOT NULL,
    orden     TINYINT UNSIGNED NOT NULL,
    subido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ticket_orden (ticket_id, orden),
    CONSTRAINT fk_i_ticket FOREIGN KEY (ticket_id) REFERENCES tk_tickets (id) ON DELETE CASCADE,
    CONSTRAINT ck_orden CHECK (orden BETWEEN 1 AND 3)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tk_ticket_eventos (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id  INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    accion     ENUM('creado','tomado','reasignado','resuelto','anulado','comentario') NOT NULL,
    detalle    TEXT NULL,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket (ticket_id, creado_en),
    CONSTRAINT fk_e_ticket  FOREIGN KEY (ticket_id)  REFERENCES tk_tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_e_usuario FOREIGN KEY (usuario_id) REFERENCES tk_usuarios (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tk_config (
    clave VARCHAR(60) NOT NULL,
    valor TEXT NOT NULL,
    PRIMARY KEY (clave)
) ENGINE=InnoDB;

INSERT IGNORE INTO tk_config (clave, valor) VALUES
    ('pantalla_intervalo', '300'),
    ('empresa', 'Imperio Comercial');
