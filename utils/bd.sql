CREATE DATABASE IF NOT EXISTS restaurante;
USE restaurante;

--  Usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    rol ENUM('cajero', 'mesero', 'admin') NOT NULL,
    activo TINYINT(1) DEFAULT 1
);

--  Mesas
CREATE TABLE mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    estado ENUM('libre', 'ocupada', 'reservada') DEFAULT 'libre',
    capacidad INT DEFAULT 4
);

--  Ventas
CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    mesa_id INT,
    usuario_id INT,
    total DECIMAL(10, 2) DEFAULT 0.00,
    estatus ENUM('activa', 'cerrada', 'cancelada') DEFAULT 'activa',
    FOREIGN KEY (mesa_id) REFERENCES mesas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

--  Productos
CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    descripcion TEXT,
    existencia INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1
);

--  Detalles de venta (platillos por venta)
CREATE TABLE venta_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
    FOREIGN KEY (venta_id) REFERENCES ventas(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

--  Corte de caja
CREATE TABLE corte_caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME,
    total DECIMAL(10,2),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

--agregar estatus a producto
ALTER TABLE venta_detalles
ADD COLUMN estatus_preparacion ENUM('pendiente', 'en preparación', 'listo', 'entregado') DEFAULT 'pendiente';

-- Agrega una columna para saber si una mesa está unida a otra
ALTER TABLE mesas ADD COLUMN mesa_principal_id INT DEFAULT NULL;

CREATE TABLE insumos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100),
  unidad VARCHAR(20),
  existencia DECIMAL(10,2)
);

CREATE TABLE recetas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  producto_id INT,
  insumo_id INT,
  cantidad DECIMAL(10,2),
  FOREIGN KEY (producto_id) REFERENCES productos(id),
  FOREIGN KEY (insumo_id) REFERENCES insumos(id)
);


--inserts de prueba 
INSERT INTO usuarios (nombre, usuario, contrasena, rol) VALUES
('Administrador', 'admin', 'admin123', 'admin'),
('Carlos Mesero', 'carlos', 'carlos123', 'mesero'),
('Laura Cajera', 'laura', 'laura123', 'cajero');

INSERT INTO mesas (nombre, estado, capacidad) VALUES
('Mesa 1', 'libre', 4),
('Mesa 2', 'libre', 4),
('Mesa 3', 'ocupada', 6);

INSERT INTO productos (nombre, precio, descripcion, existencia) VALUES
('Tacos al Pastor', 45.00, '3 piezas con piña', 50),
('Hamburguesa Especial', 85.00, 'Incluye papas y bebida', 30),
('Ensalada César', 60.00, 'Con pollo y aderezo', 20),
('Refresco 600ml', 20.00, 'Refresco embotellado', 100);

-- Venta simple con productos
INSERT INTO ventas (fecha, mesa_id, usuario_id, total, estatus)
VALUES (NOW(), 1, 2, 150.00, 'cerrada');

SET @venta_id = LAST_INSERT_ID();

INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario)
VALUES
(@venta_id, 1, 2, 45.00),
(@venta_id, 4, 3, 20.00);
