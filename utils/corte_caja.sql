ALTER TABLE corte_caja
ADD COLUMN observaciones TEXT;

ALTER TABLE ventas ADD COLUMN corte_id INT DEFAULT NULL;
ALTER TABLE ventas ADD CONSTRAINT fk_corte FOREIGN KEY (corte_id) REFERENCES corte_caja(id);
