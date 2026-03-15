-- ============================================
-- SISTEMA DE COMPRAS
-- Modelo: KIMI 2.5
-- Módulo: Creación de Tablas de Datos
-- Proyecto MalTir - Sistema de Gestión de Compras por Cotización
-- Fecha: 15 de marzo de 2026
-- Programador: Alfonso Orozco Aguilar
-- Licencia: GNU Lesser General Public License v2.1 (LGPL 2.1)
-- Este archivo forma parte del experimento de Vibe Coding "MalTir" (Prudencia),
-- diseñado para evaluar la coherencia lógica de Kimi 2.5 en entornos transaccionales.
-- La lógica de negocio está basada en el sistema original de 2006 (VB6 + SQL 2000).
-- * Repositorio Oficial (Versión más reciente): 
-- https://github.com/AlfonsoOrozcoAguilarnoNDA/maltir
-- * Más información, bitácoras y resultados del experimento en:
-- https://vibecodingmexico.com/?s=maltir
-- * "Los datos no mienten, las personas sí."
-- ============================================

-- Orden de creación respetado para evitar errores de Foreign Keys:
-- 1. cat_unidades_medida (tabla base, PK manual VARCHAR)
-- 2. cat_articulos (depende de unidades)
-- 3. cat_proveedores (independiente)
-- 4. cat_incoterms (tabla base, PK manual VARCHAR)
-- 5. kardex (depende de articulos)
-- 6. cotizaciones_maestro (independiente)
-- 7. cotizaciones_detalle (depende de maestro, articulos, unidades, incoterms)
-- 8. respuesta_cotizacion (depende de detalle, proveedores, incoterms, unidades)

-- ============================================
-- 1. cat_unidades_medida
-- PK es VARCHAR(7) capturada manualmente, NO autoincremental
-- Ejemplos: PZA, KG, LT, CAJA, METRO
-- ============================================
CREATE TABLE cat_unidades_medida (
    idUnidadMedida VARCHAR(7) NOT NULL PRIMARY KEY,
    clave_interna VARCHAR(30) NOT NULL,
    clave_sat VARCHAR(30),
    descripcion VARCHAR(200) NOT NULL,
    comentario VARCHAR(500),
    activo VARCHAR(3) DEFAULT 'si',
    fecha_alta DATETIME,
    ultima_actualizacion DATETIME,
    usuario_alta VARCHAR(100),
    usuario_modifica VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. cat_articulos
-- Lógica de negocio especial:
-- - es_servicio='si' → NO maneja inventario (no entra al kardex)
-- - es_fisico='no' + maneja_inventario='si' → licencias/intangibles con stock limitado
-- - Campo imagen: BLOB, ignorado en este ejercicio (no se usa en formularios)
-- - Campos que NO se modifican después de creación: idArticulo (PK autoincremental)
-- ============================================
CREATE TABLE cat_articulos (
    idArticulo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    clave_interna VARCHAR(50),
    descripcion_sat VARCHAR(500),
    comentario TEXT,
    imagen BLOB DEFAULT NULL, -- ignorar en este ejercicio
    es_servicio VARCHAR(3) DEFAULT 'no', -- 'si'=no maneja inventario
    es_fisico VARCHAR(3) DEFAULT 'si', -- 'no' para licencias/intangibles
    maneja_inventario VARCHAR(3) DEFAULT 'si', -- controla si aplica kardex
    tasa_iva DECIMAL(14,6) DEFAULT 0,
    existencia DECIMAL(14,6) DEFAULT 0,
    min_stock DECIMAL(14,6) DEFAULT 0,
    ultima_compra DATETIME DEFAULT NULL,
    para_venta VARCHAR(3) DEFAULT 'no',
    comentario_corto VARCHAR(200),
    idUnidadMedida VARCHAR(7),
    activo VARCHAR(3) DEFAULT 'si',
    fecha_alta DATETIME,
    ultima_actualizacion DATETIME,
    usuario_alta VARCHAR(100),
    usuario_modifica VARCHAR(100),
    FOREIGN KEY (idUnidadMedida) REFERENCES cat_unidades_medida(idUnidadMedida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. cat_proveedores
-- Al desactivar: grabar fecha_baja con timestamp actual
-- ============================================
CREATE TABLE cat_proveedores (
    idProveedor INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    descripcion VARCHAR(500),
    razon_social VARCHAR(300),
    rfc VARCHAR(20),
    persona_contacto VARCHAR(200),
    comentario TEXT,
    activo VARCHAR(3) DEFAULT 'si',
    fecha_alta DATETIME,
    fecha_baja DATETIME DEFAULT NULL,
    ultima_actualizacion DATETIME,
    usuario_alta VARCHAR(100),
    usuario_modifica VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. cat_incoterms
-- PK es VARCHAR(10) capturada manualmente (EXW, FOB, CIF, etc.)
-- ============================================
CREATE TABLE cat_incoterms (
    idIncoterm VARCHAR(10) NOT NULL PRIMARY KEY,
    descripcion VARCHAR(200) NOT NULL,
    comentario VARCHAR(500),
    activo VARCHAR(3) DEFAULT 'si',
    fecha_alta DATETIME,
    ultima_actualizacion DATETIME,
    usuario_alta VARCHAR(100),
    usuario_modifica VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. kardex
-- Movimientos de inventario: entradas, salidas, ajustes
-- HISTORIAL DE TIEMPOS: Se calcula desde esta tabla consultando fechas de 
-- entrada por proveedor+artículo. NO existe tabla separada para historial.
-- Campos clave para análisis: fecha_movimiento, referencia (número de remisión/cotización)
-- ============================================
CREATE TABLE kardex (
    idKardex INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    idArticulo INT NOT NULL,
    tipo_movimiento VARCHAR(20) NOT NULL, -- 'entrada','salida','ajuste'
    cantidad DECIMAL(14,6) NOT NULL,
    referencia VARCHAR(100), -- número de remisión o cotización
    comentario VARCHAR(500),
    fecha_movimiento DATETIME,
    usuario_alta VARCHAR(100),
    FOREIGN KEY (idArticulo) REFERENCES cat_articulos(idArticulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. cotizaciones_maestro
-- Estados: cerrada='no' (editable) / cerrada='si' (bloqueada permanentemente)
-- Campos que NO se modifican después de cerrar: TODOS (la cotización se congela)
-- ============================================
CREATE TABLE cotizaciones_maestro (
    idCotizacion INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fecha_cotizacion DATETIME,
    comentario TEXT,
    cerrada VARCHAR(3) DEFAULT 'no',
    fecha_cierre DATETIME DEFAULT NULL,
    motivo_cierre VARCHAR(500), -- obligatorio al cerrar
    hubo_incidencia VARCHAR(3) DEFAULT 'no',
    descripcion_incidencia VARCHAR(500),
    activo VARCHAR(3) DEFAULT 'si',
    fecha_alta DATETIME,
    ultima_actualizacion DATETIME,
    usuario_alta VARCHAR(100),
    usuario_modifica VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. cotizaciones_detalle
-- Partidas de cotización (una partida = un artículo)
-- CÁLCULO DE PENDIENTE: cantidad_recibida < cantidad_solicitada
-- REGLA CRÍTICA: idIncoterm es OBLIGATORIO por partida (no puede ser NULL)
-- Campos que NO se modifican después de confirmar: cantidad_solicitada, idArticulo, idUnidadMedida
-- ============================================
CREATE TABLE cotizaciones_detalle (
    idDetalle INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    idCotizacion INT NOT NULL,
    idArticulo INT NOT NULL,
    idUnidadMedida VARCHAR(7) NOT NULL,
    idIncoterm VARCHAR(10) NOT NULL, -- OBLIGATORIO
    cantidad_solicitada DECIMAL(14,6) NOT NULL,
    cantidad_recibida DECIMAL(14,6) DEFAULT 0,
    fecha_requerida DATETIME,
    fecha_comprometida DATETIME DEFAULT NULL, -- prometida por proveedor
    fecha_recibida DATETIME DEFAULT NULL, -- cuando llegó físicamente
    comprar VARCHAR(3) DEFAULT 'no', -- 'si' = partida autorizada
    cantidad_autorizada DECIMAL(14,6) DEFAULT 0,
    activo VARCHAR(3) DEFAULT 'si',
    fecha_alta DATETIME,
    ultima_actualizacion DATETIME,
    usuario_alta VARCHAR(100),
    usuario_modifica VARCHAR(100),
    FOREIGN KEY (idCotizacion) REFERENCES cotizaciones_maestro(idCotizacion),
    FOREIGN KEY (idArticulo) REFERENCES cat_articulos(idArticulo),
    FOREIGN KEY (idUnidadMedida) REFERENCES cat_unidades_medida(idUnidadMedida),
    FOREIGN KEY (idIncoterm) REFERENCES cat_incoterms(idIncoterm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. respuesta_cotizacion
-- Respuesta de proveedor por partida
-- REGLAS CRÍTICAS:
-- - precio_total: IVA incluido, SIN aduanas
-- - idUnidadMedida: NUESTRA presentación, NO la del proveedor
-- - cantidad_cotizada: en NUESTRA presentación (el capturista debe convertir)
-- - ajuste: UNA SOLA VEZ, irreversible (+excedente / -descontinuado)
-- - autorizado: solo cambia 'no'->'si', NUNCA reversible
-- - cantidad_autorizada: puede subir, NUNCA bajar
-- ============================================
CREATE TABLE respuesta_cotizacion (
    idRespuesta INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    idDetalle INT NOT NULL,
    idProveedor INT NOT NULL,
    idIncoterm VARCHAR(10) NOT NULL,
    precio_total DECIMAL(14,6) NOT NULL, -- IVA incluido, SIN aduanas
    fecha_comprometida DATETIME NOT NULL,
    idUnidadMedida VARCHAR(7) NOT NULL, -- NUESTRA presentación
    cantidad_cotizada DECIMAL(14,6) NOT NULL, -- en NUESTRA presentación
    ajuste DECIMAL(14,6) DEFAULT 0, -- UNA SOLA VEZ, irreversible
    -- +50 = recibimos de más (peligro)
    -- -50 = descontinuado
    autorizado VARCHAR(3) DEFAULT 'no', -- solo cambia no->si, nunca reversible
    cantidad_autorizada DECIMAL(14,6) DEFAULT 0, -- puede subir, NUNCA bajar
    fecha_autorizacion DATETIME DEFAULT NULL,
    usuario_autoriza VARCHAR(100),
    activo VARCHAR(3) DEFAULT 'si',
    fecha_alta DATETIME,
    ultima_actualizacion DATETIME,
    usuario_alta VARCHAR(100),
    usuario_modifica VARCHAR(100),
    FOREIGN KEY (idDetalle) REFERENCES cotizaciones_detalle(idDetalle),
    FOREIGN KEY (idProveedor) REFERENCES cat_proveedores(idProveedor),
    FOREIGN KEY (idIncoterm) REFERENCES cat_incoterms(idIncoterm),
    FOREIGN KEY (idUnidadMedida) REFERENCES cat_unidades_medida(idUnidadMedida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INSERT INCOTERMS 2020 - MÁS HABITUALES
-- Modelo: KIMI 2.5
-- ============================================

INSERT INTO cat_incoterms (
    idIncoterm, 
    descripcion, 
    comentario, 
    activo, 
    fecha_alta, 
    ultima_actualizacion, 
    usuario_alta, 
    usuario_modifica
) VALUES 
-- INCOTERMS PARA CUALQUIER MODO DE TRANSPORTE
('EXW', 'Ex Works / En Fábrica', 'El vendedor pone la mercancía a disposición del comprador en sus instalaciones. El comprador asume todos los costos y riesgos desde ese punto.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

('FCA', 'Free Carrier / Franco Transportista', 'El vendedor entrega la mercancía al transportista designado por el comprador en el lugar acordado. El vendedor se encarga de la exportación.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

('CPT', 'Carriage Paid To / Transporte Pagado Hasta', 'El vendedor paga el transporte hasta el destino acordado, pero el riesgo se transfiere al comprador cuando entrega al primer transportista.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

('CIP', 'Carriage and Insurance Paid To / Transporte y Seguro Pagado Hasta', 'Igual que CPT pero el vendedor contrata además un seguro de transporte para el comprador.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

('DAP', 'Delivered at Place / Entregado en Lugar', 'El vendedor entrega la mercancía en el lugar de destino acordado, lista para descargar. No incluye descarga ni despacho aduanero de importación.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

('DPU', 'Delivered at Place Unloaded / Entregado en Lugar Descargado', 'El vendedor entrega y descarga la mercancía en el lugar de destino acordado. Reemplaza al antiguo DAT.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

('DDP', 'Delivered Duty Paid / Entregado con Derechos Pagados', 'El vendedor asume todos los costos y riesgos hasta entregar en destino, incluyendo aranceles e impuestos de importación. Máxima obligación para el vendedor.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

-- INCOTERMS ESPECÍFICOS PARA TRANSPORTE MARÍTIMO Y VÍAS NAVEGABLES
('FAS', 'Free Alongside Ship / Franco al Costado del Buque', 'El vendedor entrega la mercancía al costado del buque en el puerto de embarque designado. El comprador asume desde ahí.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

('FOB', 'Free On Board / Franco a Bordo', 'El vendedor carga la mercancía a bordo del buque designado por el comprador en el puerto de embarque. Riesgo se transfiere al cruzar la borda.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

('CFR', 'Cost and Freight / Costo y Flete', 'El vendedor paga costos y flete hasta puerto de destino, pero el riesgo se transfiere cuando la mercancía pasa la borda en origen.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO'),

('CIF', 'Cost, Insurance and Freight / Costo, Seguro y Flete', 'Igual que CFR pero el vendedor contrata seguro marítimo mínimo para el comprador durante el transporte.', 'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 'YO', 'YO');

-- ============================================
-- FIN DEL SCRIPT
-- Ejecutar en MariaDB con: SOURCE crear_tablas.sql;
-- o copiar y pegar en phpMyAdmin / línea de comandos
-- ============================================
