SET @ARCHIVE_MODE = 1;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_SAFE_UPDATES  = 0;

DELETE FROM factura_tickets;
DELETE FROM factura_detalles;
DELETE FROM facturas;

DELETE FROM venta_detalles_log;
DELETE FROM venta_detalles_cancelados;
DELETE FROM ticket_descuentos;
DELETE FROM ticket_detalles;

DELETE FROM log_mesas;
DELETE FROM log_cancelaciones;

DELETE FROM movimientos_caja;
DELETE FROM desglose_corte;
DELETE FROM corte_caja_historial;

DELETE FROM tickets;
DELETE FROM venta_detalles;
DELETE FROM ventas;
DELETE FROM corte_caja;

SET FOREIGN_KEY_CHECKS = 1;
SET @ARCHIVE_MODE = NULL;
