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

-- INSERTS DE PRUEBA
INSERT INTO usuarios (nombre, usuario, contrasena, rol) VALUES
('Administrador', 'admin', 'admin123', 'admin'),
('Carlos Mesero', 'carlos', 'carlos123', 'mesero'),
('Laura Cajera', 'laura', 'laura123', 'cajero');

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
