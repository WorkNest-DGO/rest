
#  Sistema Punto de Venta Tokio

Este sistema gestiona operaciones de venta en restaurante (mesas y reparto), inventario de insumos, recetas, cocina, caja, reportes y auditoría.

---

##  Arquitectura general

- **Frontend**: HTML + JS Vanilla
- **Backend**: PHP sin frameworks
- **Base de datos**: MySQL

---

##  Lógica delegada a la base de datos

Para mejorar la integridad y rendimiento del sistema, se delegaron varias operaciones directamente al motor de base de datos (MySQL). Estas incluyen:

###  TRIGGERS

- `trg_llama_descuento_insumos`  
  Al marcar un producto como "listo", se activa un trigger que descuenta automáticamente los insumos según la receta registrada.

###  STORED PROCEDURES

- `sp_descuento_insumos_por_detalle(detalle_id)`  
  Aplica receta y descuenta insumos asociados.

- `sp_cerrar_corte(usuario_id)`  
  Calcula el total de ventas cerradas desde el último corte de caja y actualiza `corte_caja`.

###  VISTAS

- `vw_ventas_detalladas`  
  Une ventas con usuarios, mesas y repartidores.

- `vw_consumo_insumos`  
  Resume cuántos insumos se han utilizado por venta, a partir de recetas.

- `vw_corte_resumen`  
  Muestra resumen de cortes de caja por usuario y fecha.

###  LOGS

- `logs_accion`  
  Tabla de auditoría que registra acciones importantes del sistema como cambios de estado o generación de cortes.

---

##  Archivos del sistema que deben usar esta lógica

| Archivo PHP | Reemplazo sugerido |
|-------------|--------------------|
| `crear_venta.php` | Delega descuento de insumos al trigger |
| `cambiar_estado_producto.php` | Ya no actualiza inventario, solo estado |
| `cerrar_corte.php` | Solo llama a `CALL sp_cerrar_corte(usuario_id)` |
| `listar_ventas.php` | Usa vista `vw_ventas_detalladas` |
| `listar_insumos_consumidos.php` | Usa vista `vw_consumo_insumos` |

---

##  Testing

Verifica que:

- Al cerrar venta con producto con receta, se descuenten insumos automáticamente
- Al cerrar corte, se actualice `corte_caja`
- Las vistas reflejen datos consistentes
- El log registre eventos

---

##  Archivo base

Consulta el archivo `bd.sql` para ver toda la lógica integrada en la base de datos.

---

##  Autor

Desarrollado por VioletaDev – Junio 2025
