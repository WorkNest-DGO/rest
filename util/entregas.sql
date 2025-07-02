ALTER TABLE ventas ADD COLUMN estado_entrega ENUM('pendiente', 'en_camino', 'entregado') DEFAULT 'pendiente',
ADD COLUMN fecha_asignacion DATETIME DEFAULT NULL,
ADD COLUMN fecha_inicio DATETIME DEFAULT NULL,
ADD COLUMN fecha_entrega DATETIME DEFAULT NULL,
ADD COLUMN seudonimo_entrega VARCHAR(100) DEFAULT NULL,
ADD COLUMN foto_entrega VARCHAR(255) DEFAULT NULL;
