# Auditoría de Promos/Descuentos — Fase 1 (no invasiva)

Este informe resume la estructura de BD, flujos actuales de ventas/tickets, puntos de totalización y lugares seguros para enganchar lógica de promociones sin alterar inventario ni romper reportes/cortes.


## Entidades y Relaciones (mínimo)

```
usuarios           sedes            catalogo_folios
    |                |                    |
    |                |                    |
    v                v                    v
ventas (id, usuario_id, corte_id, total, propinas, ...)
  |  \
  |   \--(1..n)--> venta_detalles (id, venta_id, producto_id, cantidad, precio_unitario, subtotal*)
  |                               ^
  |                               | (FK productos.id)
  |                               |
  +--(1..n)--> tickets (id, venta_id, folio, total_bruto=total, descuento, ...)
                     |
                     +--(1..n)--> ticket_detalles (id, ticket_id, producto_id, cantidad, precio_unitario, subtotal*)
                     |
                     +--(0..n)--> ticket_descuentos (id, ticket_id, tipo: cortesia|porcentaje|monto_fijo,
                                               venta_detalle_id?, porcentaje?, monto, motivo?, usuario_id, creado_en)

productos (id, nombre, precio, categoria_id, ...)

(*) subtotal es columna generada: cantidad * precio_unitario
```

- Claves foráneas relevantes:
  - `venta_detalles.venta_id -> ventas.id`, `venta_detalles.producto_id -> productos.id`
  - `tickets.venta_id -> ventas.id`, `tickets.usuario_id -> usuarios.id`, `tickets.sede_id -> sedes.id`
  - `ticket_detalles.ticket_id -> tickets.id`, `ticket_detalles.producto_id -> productos.id`
  - `ticket_descuentos.ticket_id -> tickets.id`, `ticket_descuentos.venta_detalle_id -> venta_detalles.id`


## Tablas y Campos Clave (ventas/ticket)

- `ventas`: `id`, `fecha`, `mesa_id`, `repartidor_id`, `tipo_entrega`, `usuario_id`, `total`, `estatus`, `corte_id`, `cajero_id`, `observacion`, `sede_id`, `propina_efectivo`, `propina_cheque`, `propina_tarjeta`.
- `venta_detalles`: `id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal` (GENERATED), `insumos_descargados`, `estado_producto`.
- `tickets`: `id`, `venta_id`, `folio`, `total` (bruto), `descuento`, `fecha`, `monto_recibido`, `tipo_pago`, `sede_id`, `mesa_nombre`, `meseroNombre`, datos negocio y pago.
- `ticket_detalles`: `id`, `ticket_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal` (GENERATED).
- `ticket_descuentos`: `id`, `ticket_id`, `tipo` (enum: `cortesia`|`porcentaje`|`monto_fijo`), `venta_detalle_id` (nullable), `porcentaje` (nullable), `monto`, `motivo` (nullable), `usuario_id`, `creado_en`.


## Vistas y Cálculo de Totales

- `vw_tickets_con_descuentos`:
  - `total_bruto = tickets.total`
  - `descuento_total = tickets.descuento`
  - `total_esperado = tickets.total - tickets.descuento`
  - Desglose por tipo: `descuento_cortesias`, `porcentaje_aplicado`, `descuento_porcentaje`, `descuento_monto_fijo`.
- `vista_productos_mas_vendidos` (KPI): suma `venta_detalles.cantidad` y `cantidad*precio_unitario` por producto (bruto; no descuenta promos).
- `vista_resumen_pagos`: por `tipo_pago`, usa `tickets.total` y propinas de `ventas`.
- `vista_ventas_diarias`: totales por día a partir de `tickets` + propinas.
- `vw_kpi_producto`, `vw_kpi_venta`: KPIs operativos (tiempos/servicio).

Puntos de totalización actuales:
- Servidor (`api/tickets/guardar_ticket.php`):
  - Recalcula `total_bruto` a partir de `venta_detalles` de la venta.
  - Calcula `cortesias_total` (sumando subtotales por `venta_detalle_id` marcados).
  - Calcula `desc_pct_monto` (porcentaje sobre base sin cortesías) y suma `monto_fijo` por subcuenta.
  - Persiste: `tickets.total = total_bruto_sub`, `tickets.descuento = descuento_total_sub`, filas en `ticket_descuentos` por cada concepto.
  - Derivado: `total_esperado = total_bruto_sub - descuento_total_sub` (usado para cobro/validación de recibido en tarjeta/cheque).
- SQL (vista `vw_tickets_con_descuentos`) reexpone totales calculados para reportes.
- Impresión (`api/tickets/imprime_ticket.php`) vuelve a totalizar para mostrar; imprime bloque “DESCUENTOS APLICADOS”.


## Flujo actual y Call Graph (ventas → APIs → SQL)

- UI `vistas/ventas/ventas.php` + `vistas/ventas/ventas.js`:
  - Historial: GET `api/ventas/listar_ventas.php?pagina=&limite=&orden=&busqueda=`
    - SQL: `vw_ventas_detalladas` JOIN `ventas` LEFT JOIN `tickets`, agrupa por venta; `total = SUM(t.total) OR v.total`.
  - Registrar venta: POST `api/ventas/crear_venta.php`
    - Inserta/actualiza `ventas`; INSERT `venta_detalles` (cada producto). Recalcula `ventas.total` con `SUM(cantidad*precio_unitario)`.
    - Envío “casa” es producto 9001 (no afecta recetas/insumos).
  - Ver detalles: POST `api/ventas/detalle_venta.php` → SELECT de `venta_detalles` JOIN `productos`.
  - Agregar producto: POST `api/mesas/agregar_producto_venta.php` → INSERT `venta_detalles` y `ventas.total += subtotal`.
  - Eliminar producto: POST `api/mesas/eliminar_producto_venta.php` → DELETE `venta_detalles` y `ventas.total -= subtotal`.
  - Imprimir ticket: navega a `vistas/ventas/ticket.php` (UI de división/subcuentas). Luego:
    - Guardar tickets: POST `api/tickets/guardar_ticket.php` (payload: `venta_id`, `sede_id?`, `descuento_porcentaje?`, `cortesias?`, `subcuentas[]` con `productos[]`, `detalle_ids[]`, `cortesias[]`, `descuento_porcentaje?`, `descuento_monto_fijo?`, datos de pago/serie).
      - SQL: INSERT `tickets`, UPDATE `tickets.descuento`, INSERT `ticket_detalles`, INSERT `ticket_descuentos` por tipo, UPDATE `catalogo_folios`, UPDATE `ventas.estatus='cerrada'`.
    - Imprimir físico: GET `api/tickets/imprime_ticket.php?venta_id=...` → SELECT `tickets` + `ticket_detalles` + `ticket_descuentos` y emite ESC/POS.
    - Guardar propinas (si aplica): POST `api/ventas/guardar_propina_venta.php` → UPDATE `ventas.propina_*`.


## Impresión de tickets

- Archivo: `api/tickets/imprime_ticket.php` ya imprime bloque “DESCUENTOS APLICADOS” con detalle por:
  - Cortesía (incluye nombre y cantidad del producto original).
  - Descuento porcentaje (muestra % y monto).
  - Descuento de monto fijo.
- Reimpresión (`api/tickets/reimprime_ticket.php`) imprime totales pero no lista el bloque de descuentos (no bloqueante para la fase actual).


## Inventario y KPIs

- Inventario: promociones/desc. no generan líneas negativas ni afectan existencias. Los descuentos viven en `ticket_descuentos`; el consumo de insumos sigue atado a `venta_detalles`/`recetas`. No hay “productos negativos”.
- KPI “productos más vendidos”: `vista_productos_mas_vendidos` usa `venta_detalles` (unidades e ingresos brutos). Las cortesías no quitan unidades ni ingresos en esa vista.
  - Si se requiere KPI neto, se puede proponer una vista alternativa que reste `ticket_descuentos` de tipo `cortesia` del ingreso y/o unidades; no es necesario para habilitar promos.


## Dónde enganchar “Aplicar promoción” (hooks seguros)

- Hook de sugerencia (no persistente, opcional): tras agregar/eliminar detalle en UI, consultar un endpoint nuevo p.ej. `api/promos/sugerir.php?venta_id=...` que devuelva recomendaciones (`cortesias[]`, `%`, `monto_fijo`) según reglas; la UI pre‑rellena controles en `ticket.js`.
  - Puntos UI: después de `agregar_producto_venta` y `eliminar_producto_venta` y/o al abrir `verDetalles(ventaId)`.
- Hook de aplicación (canónico, persistente): en `api/tickets/guardar_ticket.php`, justo antes de calcular `descuento_total_sub`, invocar motor/reglas para:
  - Marcar `venta_detalle_id` como `cortesia` cuando aplique 2x1, N‑por‑M, etc.
  - Calcular `%` o `monto_fijo` por subcuenta/boleta.
  - Persistir en `ticket_descuentos` y actualizar `tickets.descuento` (ya soportado).

Este enfoque evita tocar inventario y mantiene reportes/cortes intactos.


## Recomendación de mínimo cambio

- Opción existente (recomendada): reutilizar `ticket_descuentos` + lógica actual en `guardar_ticket.php` y `ticket.js` para registrar promos como:
  - Cortesía por renglón (2x1, 3x2 → marcar ítem(‑es) más baratos como `cortesia`).
  - Descuento global (%) o monto fijo por ticket/subcuenta.
  - Si se requiere trazabilidad de la regla aplicada, agregar de forma incremental un catálogo liviano `promociones` y un campo opcional `ticket_descuentos.promocion_id` (nullable) sin cambiar flujos.

- Opción A (sin nuevo catálogo): “botones de promoción” en `productos` que sólo disparen lógica y no creen líneas. Requiere cambios de UI y distinguir productos “lógicos”; no aporta ventajas frente a `ticket_descuentos` ya disponible. No recomendada.

- Opción B (catálogo liviano): agregar tabla `promociones` (id, nombre, tipo, alcance, parámetros JSON, activo) y (opcional) `ticket_descuentos.promocion_id` para auditar qué promo aplicó cada descuento. Cambios acotados y compatibles con reportes.


## Referencias de código

- Vistas:
  - `vistas/ventas/ventas.php`
  - `vistas/ventas/ventas.js`
  - `vistas/ventas/ticket.php`
  - `vistas/ventas/ticket.js`
- APIs ventas/tickets:
  - `api/ventas/crear_venta.php`
  - `api/ventas/detalle_venta.php`
  - `api/ventas/listar_ventas.php`
  - `api/mesas/agregar_producto_venta.php`
  - `api/mesas/eliminar_producto_venta.php`
  - `api/ventas/guardar_propina_venta.php`
  - `api/tickets/guardar_ticket.php`
  - `api/tickets/imprime_ticket.php`
  - `api/tickets/reimprime_ticket.php`
- Vistas SQL relevantes en `utils/restaurante(13).sql`:
  - `vw_tickets_con_descuentos`, `vista_productos_mas_vendidos`, `vista_resumen_pagos`, `vista_ventas_diarias`, `vw_kpi_producto`, `vw_kpi_venta`.


## Resumen

- Ya existe infraestructura para descuentos: `ticket_descuentos`, campo `tickets.descuento` y vista `vw_tickets_con_descuentos`.
- Totalización se hace en servidor al guardar ticket y está respaldada por vistas SQL.
- Impresión ya muestra el bloque de “Descuentos aplicados”.
- Inventario y KPIs no requieren cambios para habilitar promos. Si se requiere KPI neto, se puede agregar una vista alternativa sin tocar procesos centrales.
- Siguiente paso (fase 2): definir reglas de negocio de promociones y, si se quiere trazabilidad, introducir `promociones` + `ticket_descuentos.promocion_id` (opcional), más endpoint `api/promos/sugerir.php` para pre‑visualización.

