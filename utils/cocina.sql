ALTER TABLE venta_detalles
ADD COLUMN estado_producto ENUM('pendiente','en_preparacion','listo','entregado') DEFAULT 'pendiente',
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN observaciones TEXT;
