
-- ========================================================
-- OPCIONES DE LÓGICA EN BASE DE DATOS PARA SISTEMA RESTAURANTE
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

-- =========================
-- 3. TRIGGERS
-- =========================

-- Trigger para descontar insumos al marcar platillo como 'listo'
DELIMITER //
CREATE TRIGGER trg_descuento_insumos
AFTER UPDATE ON venta_detalles
FOR EACH ROW
BEGIN
    IF NEW.estatus_preparacion = 'listo' AND OLD.estatus_preparacion <> 'listo' AND NEW.insumos_descargados = 0 THEN
        DECLARE done INT DEFAULT 0;
        DECLARE rid INT;
        DECLARE cant DECIMAL(10,2);
        DECLARE cur CURSOR FOR 
            SELECT insumo_id, cantidad * NEW.cantidad
            FROM recetas WHERE producto_id = NEW.producto_id;
        DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

        OPEN cur;
        insumo_loop: LOOP
            FETCH cur INTO rid, cant;
            IF done THEN
                LEAVE insumo_loop;
            END IF;
            UPDATE insumos SET existencia = existencia - cant WHERE id = rid;
        END LOOP;
        CLOSE cur;

        UPDATE venta_detalles SET insumos_descargados = 1 WHERE id = NEW.id;
    END IF;
END;
//
DELIMITER ;

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

-- Ejemplo de uso (desde un SP o trigger)
-- INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id)
-- VALUES (2, 'ventas', 'Producto marcado como listo', 123);
