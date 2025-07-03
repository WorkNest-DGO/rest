-- Agrega campo de observaciones al corte
ALTER TABLE corte_caja
ADD COLUMN observaciones TEXT;

-- Relaciona ventas con cortes
ALTER TABLE ventas
ADD COLUMN corte_id INT DEFAULT NULL,
ADD CONSTRAINT fk_venta_corte FOREIGN KEY (corte_id) REFERENCES corte_caja(id);
