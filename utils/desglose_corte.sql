ALTER TABLE corte_caja ADD COLUMN fondo_inicial DECIMAL(10,2) DEFAULT 0;

CREATE TABLE desglose_corte (
  id INT AUTO_INCREMENT PRIMARY KEY,
  corte_id INT NOT NULL,
  denominacion DECIMAL(10,2) NOT NULL,
  cantidad INT NOT NULL,
  tipo_pago ENUM('efectivo','boucher','cheque') DEFAULT 'efectivo',
  FOREIGN KEY (corte_id) REFERENCES corte_caja(id)
);

CREATE TABLE fondo (
  usuario_id INT PRIMARY KEY,
  monto DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
