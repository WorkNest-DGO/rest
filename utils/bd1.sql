-- SCRIPT DE BASE DE DATOS COMPLETO PARA SISTEMA DE PUNTO DE VENTA - RESTAURANTE
-- Incluye: usuarios, mesas, ventas, productos, insumos, recetas, proveedores, corte de caja, entregas, etc.

CREATE DATABASE IF NOT EXISTS restaurante;
USE restaurante;

-- Tabla de usuarios del sistema
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    rol ENUM('cajero', 'mesero', 'admin') NOT NULL,
    activo TINYINT(1) DEFAULT 1
);

-- Tabla de mesas del restaurante
CREATE TABLE IF NOT EXISTS mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    estado ENUM('libre','ocupada','reservada') DEFAULT 'libre',
    capacidad INT DEFAULT 4,
    mesa_principal_id INT DEFAULT NULL
);

-- Tabla de repartidores para ventas a domicilio
CREATE TABLE IF NOT EXISTS repartidores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    telefono VARCHAR(20)
);

-- Tabla de productos (platillos)
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    descripcion TEXT,
    existencia INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1
);

-- Tabla de ventas
CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    mesa_id INT,
    repartidor_id INT DEFAULT NULL,
    tipo_entrega ENUM('mesa','domicilio') DEFAULT 'mesa',
    usuario_id INT,
    total DECIMAL(10,2) DEFAULT 0.00,
    estatus ENUM('activa','cerrada','cancelada') DEFAULT 'activa',
    entregado TINYINT(1) DEFAULT 0,
    FOREIGN KEY (mesa_id) REFERENCES mesas(id),
    FOREIGN KEY (repartidor_id) REFERENCES repartidores(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Detalles de cada venta
CREATE TABLE IF NOT EXISTS venta_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
    estatus_preparacion ENUM('pendiente','en preparación','listo','entregado') DEFAULT 'pendiente',
    insumos_descargados TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- Tabla de corte de caja por usuario
CREATE TABLE IF NOT EXISTS corte_caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME,
    total DECIMAL(10,2),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de insumos (ingredientes)
CREATE TABLE IF NOT EXISTS insumos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    unidad VARCHAR(20),
    existencia DECIMAL(10,2),
    tipo_control ENUM('por_receta','unidad_completa','uso_general','no_controlado','desempaquetado') DEFAULT 'por_receta'
);

-- Recetas: relación entre productos e insumos
CREATE TABLE IF NOT EXISTS recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    insumo_id INT,
    cantidad DECIMAL(10,2),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (insumo_id) REFERENCES insumos(id)
);

-- Proveedores de insumos
CREATE TABLE IF NOT EXISTS proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    telefono VARCHAR(20),
    direccion TEXT
);

-- Entradas de insumo (compra)
CREATE TABLE IF NOT EXISTS entradas_insumo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2),
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
);

-- Detalle de entradas de insumo
CREATE TABLE IF NOT EXISTS entradas_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrada_id INT,
    producto_id INT,
    cantidad INT,
    precio_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
    FOREIGN KEY (entrada_id) REFERENCES entradas_insumo(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- Catálogo de folios
CREATE TABLE IF NOT EXISTS catalogo_folios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(100),
    folio_actual INT DEFAULT 0
);

-- Tabla de tickets divididos por subcuenta
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    folio INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    propina DECIMAL(10,2) DEFAULT 0,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    usuario_id INT,
    FOREIGN KEY (venta_id) REFERENCES ventas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Detalle de productos en cada subticket
CREATE TABLE IF NOT EXISTS ticket_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- INSERTS DE PRUEBA
INSERT INTO usuarios (nombre, usuario, contrasena, rol) VALUES
('Administrador', 'admin', 'admin123', 'admin'),
('Carlos Mesero', 'carlos', 'carlos123', 'mesero'),
('Laura Cajera', 'laura', 'laura123', 'cajero'),
('Juan Mesero', 'juan', 'juan123', 'mesero'),
('Luisa Mesera', 'luisa', 'luisa123', 'mesero');

-- SERIES
INSERT INTO catalogo_folios (descripcion, folio_actual) VALUES
('Serie Restaurante', 1000),
('Serie Domicilio', 2000);
-- REPARTIDORES
INSERT INTO repartidores (nombre, telefono) VALUES
('Pedro Repartidor', '555-000-1111'),
('Ana Repartidora', '555-999-2222');

-- MESAS
INSERT INTO mesas (nombre, estado, capacidad) VALUES
('Mesa 1', 'libre', 4),
('Mesa 2', 'ocupada', 4),
('Mesa 3', 'reservada', 6);

-- PRODUCTOS
INSERT INTO productos (nombre, precio, descripcion, existencia) VALUES
('Tacos al Pastor', 45.00, '3 piezas con piña', 50),
('Hamburguesa Especial', 85.00, 'Incluye papas y bebida', 30),
('Ensalada César', 60.00, 'Con pollo y aderezo', 20),
('Refresco 600ml', 20.00, 'Refresco embotellado', 100);

-- PROVEEDORES
INSERT INTO proveedores (nombre, telefono, direccion) VALUES
('Suministros Sushi MX', '555-123-4567', 'Calle Soya #123, CDMX'),
('Pescados del Pacífico', '555-987-6543', 'Av. Mar #456, CDMX');

-- INSUMOS
INSERT INTO insumos (nombre, unidad, existencia, tipo_control) VALUES
('Arroz para sushi', 'gramos', 10000, 'por_receta'),
('Alga Nori', 'piezas', 200, 'por_receta'),
('Salmón fresco', 'gramos', 5000, 'por_receta'),
('Refresco en lata', 'piezas', 24, 'unidad_completa'),
('Salsa Soya', 'ml', 5000, 'uso_general');

-- PRODUCTO ROLLO CALIFORNIA
INSERT INTO productos (nombre, precio, descripcion, existencia) VALUES
('Rollo California', 120.00, 'Salmón, arroz, alga nori', 10);

SET @producto_id = LAST_INSERT_ID();

-- RECETA DEL ROLLO CALIFORNIA
INSERT INTO recetas (producto_id, insumo_id, cantidad) VALUES
(@producto_id, 1, 300),  -- Arroz
(@producto_id, 2, 2),    -- Alga
(@producto_id, 3, 100);  -- Salmón

-- ENTRADA DE INSUMOS (compras)
INSERT INTO entradas_insumo (proveedor_id, total) VALUES (1, 500);
SET @entrada_id = LAST_INSERT_ID();

INSERT INTO entradas_detalle (entrada_id, producto_id, cantidad, precio_unitario) VALUES
(@entrada_id, 1, 5, 100.00);

-- VENTA CON ROLLO CALIFORNIA
INSERT INTO ventas (fecha, mesa_id, usuario_id, total, estatus)
VALUES (NOW(), 1, 2, 120.00, 'cerrada');

SET @venta_california = LAST_INSERT_ID();

INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario, estatus_preparacion, insumos_descargados) VALUES
(@venta_california, @producto_id, 1, 120.00, 'entregado', 1);

-- CORTE DE CAJA SIMULADO
INSERT INTO corte_caja (usuario_id, fecha_inicio, fecha_fin, total)
VALUES (2, NOW() - INTERVAL 3 HOUR, NOW(), 270.00);





-- ========================================================
-- OPCIONES DE LÓGICA EN BASE DE DATOS 
-- VISTAS, STORED PROCEDURES, TRIGGERS Y LOGS
-- ========================================================

-- =========================
-- 1. VISTAS (VIEWS)
-- =========================

-- Vista de ventas detalladas con usuario, mesa y total
CREATE OR REPLACE VIEW vw_ventas_detalladas AS
SELECT
    v.id AS venta_id,
    v.fecha,
    v.total,
    v.estatus,
    u.nombre AS usuario,
    m.nombre AS mesa,
    r.nombre AS repartidor
FROM ventas v
LEFT JOIN usuarios u ON v.usuario_id = u.id
LEFT JOIN mesas m ON v.mesa_id = m.id
LEFT JOIN repartidores r ON v.repartidor_id = r.id;

-- Vista de consumo estimado de insumos por venta
CREATE OR REPLACE VIEW vw_consumo_insumos AS
SELECT
    vd.venta_id,
    r.insumo_id,
    i.nombre AS insumo,
    i.unidad,
    SUM(r.cantidad * vd.cantidad) AS total_consumido
FROM venta_detalles vd
JOIN recetas r ON vd.producto_id = r.producto_id
JOIN insumos i ON r.insumo_id = i.id
GROUP BY vd.venta_id, r.insumo_id;

-- Vista de resumen de corte de caja
CREATE OR REPLACE VIEW vw_corte_resumen AS
SELECT
    c.id AS corte_id,
    u.nombre AS cajero,
    c.fecha_inicio,
    c.fecha_fin,
    c.total
FROM corte_caja c
JOIN usuarios u ON c.usuario_id = u.id;

-- =========================
-- 2. STORED PROCEDURES
-- =========================

-- Procedimiento para cerrar corte de caja
DELIMITER //
CREATE PROCEDURE sp_cerrar_corte(IN p_usuario_id INT)
BEGIN
    DECLARE total_ventas DECIMAL(10,2);
    SELECT SUM(total) INTO total_ventas FROM ventas
    WHERE usuario_id = p_usuario_id AND estatus = 'cerrada'
    AND fecha >= (SELECT MAX(fecha_inicio) FROM corte_caja WHERE usuario_id = p_usuario_id);

    UPDATE corte_caja
    SET fecha_fin = NOW(), total = total_ventas
    WHERE usuario_id = p_usuario_id AND fecha_fin IS NULL;
END;
//
DELIMITER ;
-- sp_descuento_insumos_por_detalle y trigger trg_llama_descuento_insumos eliminados; la lógica de inventario se maneja en PHP.


-- =========================
-- 4. LOGS / AUDITORÍA
-- =========================

-- Tabla de logs del sistema
CREATE TABLE IF NOT EXISTS logs_accion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    modulo VARCHAR(50),
    accion VARCHAR(100),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    referencia_id INT
);


-- correccion de insumos
ALTER TABLE entradas_detalle DROP FOREIGN KEY entradas_detalle_ibfk_2;
ALTER TABLE entradas_detalle CHANGE producto_id insumo_id INT NOT NULL;
ALTER TABLE entradas_detalle
ADD CONSTRAINT fk_entrada_detalle_insumo FOREIGN KEY (insumo_id) REFERENCES insumos(id);

ALTER TABLE entradas_insumo
ADD COLUMN usuario_id INT AFTER proveedor_id,
ADD CONSTRAINT fk_entrada_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id);

 -- trigger pa recalcular con el sp de recetas cada q se cambia uno

DELIMITER //

CREATE TRIGGER trg_update_insumo_existencia
AFTER UPDATE ON insumos
FOR EACH ROW
BEGIN
    IF NEW.existencia != OLD.existencia THEN
        CALL sp_recalcular_productos_por_insumo(NEW.id);
    END IF;
END;
//

DELIMITER ;


-- el sp de recetas q recalcula alv 
DELIMITER //

CREATE PROCEDURE sp_recalcular_productos_por_insumo(IN p_insumo_id INT)
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_producto_id INT;
    DECLARE cur CURSOR FOR
        SELECT DISTINCT r.producto_id
        FROM recetas r
        WHERE r.insumo_id = p_insumo_id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_producto_id;
        IF done THEN
            LEAVE read_loop;
        END IF;

        UPDATE productos p
        SET existencia = (
            SELECT IFNULL(MIN(FLOOR(i.existencia / r.cantidad)), 0)
            FROM recetas r
            JOIN insumos i ON r.insumo_id = i.id
            WHERE r.producto_id = v_producto_id
        )
        WHERE p.id = v_producto_id;

    END LOOP;
    CLOSE cur;
END;
//

DELIMITER ;

-- metimos esta wea hasta aca pero ejecutenla antes xd

CREATE TABLE catalogo_areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL
);
INSERT INTO catalogo_areas (nombre) VALUES 
('Ala izquierda'), 
('ala derecha'), 
('terraza');

ALTER TABLE mesas
ADD COLUMN area_id INT DEFAULT NULL,
ADD COLUMN usuario_id INT DEFAULT NULL,
ADD COLUMN estado_reserva ENUM('ninguna', 'reservada') DEFAULT 'ninguna',
ADD COLUMN nombre_reserva VARCHAR(100) DEFAULT NULL,
ADD COLUMN fecha_reserva DATETIME DEFAULT NULL,
ADD COLUMN tiempo_ocupacion_inicio DATETIME DEFAULT NULL;

ALTER TABLE mesas

ADD CONSTRAINT fk_mesa_area FOREIGN KEY (area_id) REFERENCES catalogo_areas(id),
ADD CONSTRAINT fk_mesa_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id);
