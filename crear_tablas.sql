-- ============================================================
-- Proyecto MalTir - Sistema de Gestión de Compras
-- Benchmark KIMI 2.5
-- Versión 2 - Agrega tabla usuarios y usuario inicial
-- Generado con Claude Sonnet 4.6
-- ============================================================

SET sql_mode = '';
SET NAMES utf8mb4;

-- ============================================================
-- TABLA: cat_unidades_medida
-- PK es VARCHAR(7) - clave corta tipo PZA, KG, LT
-- ============================================================
CREATE TABLE IF NOT EXISTS cat_unidades_medida (
  idUnidadMedida       VARCHAR(7)    NOT NULL PRIMARY KEY,
  clave_interna        VARCHAR(30)   NOT NULL,
  clave_sat            VARCHAR(30),
  descripcion          VARCHAR(200)  NOT NULL,
  comentario           VARCHAR(500),
  activo               VARCHAR(3)    DEFAULT 'si',
  fecha_alta           DATETIME,
  ultima_actualizacion DATETIME,
  usuario_alta         VARCHAR(100),
  usuario_modifica     VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: cat_articulos
-- es_servicio='si' → no maneja inventario físico
-- es_fisico='no' + maneja_inventario='si' → licencias/intangibles con stock
-- maneja_inventario controla si aplica kardex
-- imagen BLOB ignorada en este ejercicio
-- ============================================================
CREATE TABLE IF NOT EXISTS cat_articulos (
  idArticulo           INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre               VARCHAR(200)  NOT NULL,
  clave_interna        VARCHAR(50),
  descripcion_sat      VARCHAR(500),
  comentario           TEXT,
  imagen               BLOB          DEFAULT NULL,
  es_servicio          VARCHAR(3)    DEFAULT 'no',
  es_fisico            VARCHAR(3)    DEFAULT 'si',
  maneja_inventario    VARCHAR(3)    DEFAULT 'si',
  tasa_iva             DECIMAL(14,6) DEFAULT 0,
  existencia           DECIMAL(14,6) DEFAULT 0,
  min_stock            DECIMAL(14,6) DEFAULT 0,
  ultima_compra        DATETIME      DEFAULT NULL,
  para_venta           VARCHAR(3)    DEFAULT 'no',
  comentario_corto     VARCHAR(200),
  idUnidadMedida       VARCHAR(7),
  activo               VARCHAR(3)    DEFAULT 'si',
  fecha_alta           DATETIME,
  ultima_actualizacion DATETIME,
  usuario_alta         VARCHAR(100),
  usuario_modifica     VARCHAR(100),
  FOREIGN KEY (idUnidadMedida) REFERENCES cat_unidades_medida(idUnidadMedida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: cat_proveedores
-- fecha_baja se graba al desactivar
-- ============================================================
CREATE TABLE IF NOT EXISTS cat_proveedores (
  idProveedor          INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre               VARCHAR(200)  NOT NULL,
  descripcion          VARCHAR(500),
  razon_social         VARCHAR(300),
  rfc                  VARCHAR(20),
  persona_contacto     VARCHAR(200),
  comentario           TEXT,
  activo               VARCHAR(3)    DEFAULT 'si',
  fecha_alta           DATETIME,
  fecha_baja           DATETIME      DEFAULT NULL,
  ultima_actualizacion DATETIME,
  usuario_alta         VARCHAR(100),
  usuario_modifica     VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: cat_incoterms
-- Basado en Incoterms 2020 (DAT fue reemplazado por DPU)
-- ============================================================
CREATE TABLE IF NOT EXISTS cat_incoterms (
  idIncoterm           VARCHAR(10)   NOT NULL PRIMARY KEY,
  descripcion          VARCHAR(200)  NOT NULL,
  comentario           VARCHAR(500),
  activo               VARCHAR(3)    DEFAULT 'si',
  fecha_alta           DATETIME,
  ultima_actualizacion DATETIME,
  usuario_alta         VARCHAR(100),
  usuario_modifica     VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales Incoterms 2020
INSERT IGNORE INTO cat_incoterms (idIncoterm, descripcion, comentario, activo, fecha_alta, usuario_alta) VALUES
('EXW',  'Ex Works',                        'Compras locales, recolección por comprador',             'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('FCA',  'Free Carrier',                    'Multimodal, contenedores',                               'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('CPT',  'Carriage Paid To',               'Transporte terrestre/aéreo internacional',               'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('CIP',  'Carriage and Insurance Paid To', 'Transporte terrestre/aéreo con seguro',                  'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('DAP',  'Delivered At Place',             'Entrega en almacén del comprador sin descarga',           'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('DPU',  'Delivered at Place Unloaded',    'Entrega en terminal con descarga incluida (antes DAT)',   'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('DDP',  'Delivered Duty Paid',            'Importaciones puerta a puerta, vendedor todo incluido',  'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('FAS',  'Free Alongside Ship',            'Granel, carga pesada puerto',                            'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('FOB',  'Free On Board',                  'El más usado en importaciones marítimas desde Asia',      'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('CFR',  'Cost and Freight',               'Exportaciones marítimas, vendedor controla flete',       'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA'),
('CIF',  'Cost Insurance and Freight',     'Importaciones marítimas con seguro incluido',            'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'SISTEMA');

-- ============================================================
-- TABLA: usuarios
-- Sin niveles de acceso por ahora - todos ven todo
-- password en BCrypt
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
  idUsuario            INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario              VARCHAR(50)   NOT NULL UNIQUE,
  password             VARCHAR(255)  NOT NULL,
  nombre_completo      VARCHAR(200),
  email                VARCHAR(200),
  activo               VARCHAR(3)    DEFAULT 'si',
  fecha_alta           DATETIME,
  fecha_baja           DATETIME      DEFAULT NULL,
  ultima_actualizacion DATETIME,
  usuario_alta         VARCHAR(100),
  usuario_modifica     VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario inicial: admin / admin123
-- Hash BCrypt generado con password_hash('admin123', PASSWORD_BCRYPT)
INSERT IGNORE INTO usuarios (usuario, password, nombre_completo, email, activo, fecha_alta, usuario_alta)
VALUES (
  'admin',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'Administrador del Sistema',
  'admin@empresa.com',
  'si',
  CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
  'SISTEMA'
);
-- IMPORTANTE: Cambiar password inmediatamente después del primer acceso
-- Para generar nuevo hash: echo password_hash('nueva_clave', PASSWORD_BCRYPT);

-- ============================================================
-- TABLA: kardex
-- Movimientos de inventario: entradas, salidas, ajustes
-- Historial de tiempos de entrega se calcula desde aquí
-- ============================================================
CREATE TABLE IF NOT EXISTS kardex (
  idKardex             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idArticulo           INT           NOT NULL,
  tipo_movimiento      VARCHAR(20)   NOT NULL,
  cantidad             DECIMAL(14,6) NOT NULL,
  referencia           VARCHAR(100),
  comentario           VARCHAR(500),
  fecha_movimiento     DATETIME,
  usuario_alta         VARCHAR(100),
  FOREIGN KEY (idArticulo) REFERENCES cat_articulos(idArticulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: cotizaciones_maestro
-- cerrada='si' bloquea agregar partidas y recibir mercancía
-- ============================================================
CREATE TABLE IF NOT EXISTS cotizaciones_maestro (
  idCotizacion         INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fecha_cotizacion     DATETIME,
  comentario           TEXT,
  cerrada              VARCHAR(3)    DEFAULT 'no',
  fecha_cierre         DATETIME      DEFAULT NULL,
  motivo_cierre        VARCHAR(500),
  hubo_incidencia      VARCHAR(3)    DEFAULT 'no',
  descripcion_incidencia VARCHAR(500),
  activo               VARCHAR(3)    DEFAULT 'si',
  fecha_alta           DATETIME,
  ultima_actualizacion DATETIME,
  usuario_alta         VARCHAR(100),
  usuario_modifica     VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: cotizaciones_detalle
-- Una partida = un artículo dentro de una cotización
-- Pendiente: cantidad_recibida < cantidad_solicitada
-- Incoterms OBLIGATORIO por partida
-- comprar='si' indica partida autorizada
-- ============================================================
CREATE TABLE IF NOT EXISTS cotizaciones_detalle (
  idDetalle            INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idCotizacion         INT           NOT NULL,
  idArticulo           INT           NOT NULL,
  idUnidadMedida       VARCHAR(7)    NOT NULL,
  idIncoterm           VARCHAR(10)   NOT NULL,
  cantidad_solicitada  DECIMAL(14,6) NOT NULL,
  cantidad_recibida    DECIMAL(14,6) DEFAULT 0,
  fecha_requerida      DATETIME,
  fecha_comprometida   DATETIME      DEFAULT NULL,
  fecha_recibida       DATETIME      DEFAULT NULL,
  comprar              VARCHAR(3)    DEFAULT 'no',
  cantidad_autorizada  DECIMAL(14,6) DEFAULT 0,
  activo               VARCHAR(3)    DEFAULT 'si',
  fecha_alta           DATETIME,
  ultima_actualizacion DATETIME,
  usuario_alta         VARCHAR(100),
  usuario_modifica     VARCHAR(100),
  FOREIGN KEY (idCotizacion)   REFERENCES cotizaciones_maestro(idCotizacion),
  FOREIGN KEY (idArticulo)     REFERENCES cat_articulos(idArticulo),
  FOREIGN KEY (idUnidadMedida) REFERENCES cat_unidades_medida(idUnidadMedida),
  FOREIGN KEY (idIncoterm)     REFERENCES cat_incoterms(idIncoterm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: respuesta_cotizacion
-- Una respuesta por proveedor por partida
-- precio_total: IVA incluido, SIN aduanas
-- cantidad_cotizada: en NUESTRA presentación, no la del proveedor
-- ajuste: UNA SOLA VEZ al cerrar, irreversible
--   +valor = recibimos de más (peligro)
--   -valor = descontinuado
-- autorizado: solo cambia no->si, nunca reversible
-- cantidad_autorizada: solo puede incrementarse, nunca bajar
-- cantidad_recibida: agregado para dashboard de pendientes
-- ============================================================
CREATE TABLE IF NOT EXISTS respuesta_cotizacion (
  idRespuesta          INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idDetalle            INT           NOT NULL,
  idProveedor          INT           NOT NULL,
  idIncoterm           VARCHAR(10)   NOT NULL,
  precio_total         DECIMAL(14,6) NOT NULL,
  fecha_comprometida   DATETIME      NOT NULL,
  idUnidadMedida       VARCHAR(7)    NOT NULL,
  cantidad_cotizada    DECIMAL(14,6) NOT NULL,
  cantidad_recibida    DECIMAL(14,6) DEFAULT 0,
  ajuste               DECIMAL(14,6) DEFAULT 0,
  autorizado           VARCHAR(3)    DEFAULT 'no',
  cantidad_autorizada  DECIMAL(14,6) DEFAULT 0,
  fecha_autorizacion   DATETIME      DEFAULT NULL,
  usuario_autoriza     VARCHAR(100),
  activo               VARCHAR(3)    DEFAULT 'si',
  fecha_alta           DATETIME,
  ultima_actualizacion DATETIME,
  usuario_alta         VARCHAR(100),
  usuario_modifica     VARCHAR(100),
  FOREIGN KEY (idDetalle)      REFERENCES cotizaciones_detalle(idDetalle),
  FOREIGN KEY (idProveedor)    REFERENCES cat_proveedores(idProveedor),
  FOREIGN KEY (idIncoterm)     REFERENCES cat_incoterms(idIncoterm),
  FOREIGN KEY (idUnidadMedida) REFERENCES cat_unidades_medida(idUnidadMedida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- FIN DEL SCRIPT
-- Nota sobre timezones:
-- Si CONVERT_TZ devuelve NULL, instalar tablas de zona horaria:
-- mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql
-- ============================================================
