# Pre-análisis para conciliación de pagos desde CSV

Objetivo: a partir de un CSV con folio externo, producto(s) y montos cobrados, generar en lote una venta (`ventas`), sus partidas (`venta_detalles`) y su ticket (`tickets` + `ticket_detalles`) con el tipo de pago correcto, dejando todo cerrado y listo para cortes. Se usará un folio fijo de conciliación en tickets y el folio CSV quedará en observación para trazabilidad.

## Tablas y campos clave (según restaurante(18).sql)
- `ventas`: id, fecha, tipo_entrega (mesa/domicilio/rapido), mesa_id/repartidor_id/usuario_id, total, estatus, entregado, corte_id, cajero_id, observacion, sede_id, propinas (efectivo/cheque/tarjeta), promoción (promocion_id/promocion_descuento).
- `venta_detalles`: id, venta_id, producto_id, cantidad, precio_unitario, subtotal (generado), estado_producto.
- `tickets`: id, venta_id, folio, serie_id, total, descuento, fecha, usuario_id, monto_recibido, tipo_pago (efectivo, boucher, cheque, plataforma, varios), sede_id, mesa_nombre, mesero_nombre, fechas inicio/fin, nombre/dirección/rfc/telefono negocio, tipo_entrega, datos de tarjeta/boucher/cheque.
- `ticket_detalles`: id, ticket_id, producto_id, cantidad, precio_unitario, subtotal (generado).
- Catálogos implicados: `productos` (id, nombre, precio, existencia), `usuarios` (mesero/cajero), `repartidores`, `mesas`, `clientes` (para domicilio), `serie_tickets` (si aplica), cat. bancos/tarjeta si se requieren.

## Flujo sugerido de importación/conciliación
1) **Leer CSV** y agrupar por folio externo (cada folio = una venta + ticket). El folio interno a grabar será “conciliacion” (y `serie_id` dedicada si existe); el folio externo se guarda en `ventas.observacion` y/o campo libre.
2) **Resolver catálogos**:
   - Producto: mapear por nombre → `producto_id`; si hay múltiples coincidencias decidir regla (exacta, case-insensitive); opcional: permitir id directo.
   - Usuario/mesero, repartidor, cliente: intentar match por nombre; si no existe, permitir insertar la venta con los campos nulos (sin relación). Defaults para usuario_id/cajero_id/sede si no vienen.
   - Tipo de entrega: derivar de CSV (mesa/domicilio/rapido) para poblar `ventas.tipo_entrega` y `tickets.tipo_entrega`; si es mesa, opcional `mesa_id`; si es domicilio, opcional `repartidor_id`/cliente.
   - Tipo de pago: mapear a enum de `tickets.tipo_pago` (efectivo/boucher/cheque/plataforma/varios); si es tarjeta, poblar marca/banco/boucher/cheque según disponibilidad.
3) **Crear venta (`ventas`)**:
   - fecha = del CSV o `current_timestamp()`.
   - total = suma de partidas o monto explícito del CSV; estatus siempre 'cerrada'; entregado según flujo (probablemente 0); propinas iniciales en 0 salvo que se proporcionen.
   - set `cajero_id`/`usuario_id`/`sede_id` según contexto; observacion con referencia al folio CSV y notas de conciliación.
4) **Crear detalles (`venta_detalles`)** por cada línea de producto:
   - producto_id, cantidad, precio_unitario (desde CSV o precio de catálogo), estado_producto='entregado' si ya surtido.
5) **Crear ticket (`tickets`)**:
   - `venta_id` recién creado.
   - `folio`: literal “conciliacion” (y `serie_id` de conciliación si aplica).
   - `total` = total venta; `monto_recibido` = monto cobrado (puede incluir propina si así se maneja); `tipo_pago` mapeado; `descuento` si aplica.
   - Poblar `sede_id`, `mesa_nombre`, `mesero_nombre`, `tipo_entrega`; rellenar negocio (nombre/dirección/RFC/teléfono) desde config actual (se cargan en `ticket.php`).
6) **Crear `ticket_detalles`** replicando los `venta_detalles` (producto_id, cantidad, precio_unitario).
7) **Validaciones**:
   - Suma de `venta_detalles` = total de venta = total de ticket; si hay discrepancia, marcar en log y no ajustar silencioso.
   - Campos obligatorios por tipo de pago (boucher/cheque requieren número/banco si se usan).
   - Evitar duplicados: si ya existe ticket con folio “conciliacion” para ese folio externo, decidir política (omit/actualizar) y registrar en log.
8) **Conciliación/reportes**:
   - Registrar log en BD por lote con procesados/no procesados y motivo de error por folio/línea.
   - Opcional: actualizar `corte_id` o generar reporte de diferencias contra CSV original.

## Formato CSV sugerido (mínimo)
Columnas orientativas (una línea por producto; agrupar por `folio_ext`):
- `folio_ext` (string/num): folio del ticket externo.
- `fecha` (YYYY-MM-DD HH:MM, opcional): fecha de operación.
- `tipo_entrega` (mesa/domicilio/rapido, opcional, default mesa).
- `mesa` o `repartidor`/`cliente` (opcionales según tipo_entrega).
- `producto` (nombre o id), `cantidad`, `precio_unitario` (si falta, usar precio catálogo).
- `total_linea` (opcional, para validar).
- `tipo_pago` (efectivo/boucher/cheque/plataforma/varios), `monto_cobrado` (si falta, se usa suma por folio).
- Líneas de propina: `concepto=propina` + `tipo_pago_propina` + `monto_propina` (se suman a propinas y/o ticket).
- Extras opcionales: `sede_id`, `usuario_id` (cajero/mesero), `comentario` (se guarda en observacion), `boucher/cheque_numero`, `tarjeta_marca_id`, `tarjeta_banco_id`.

## Decisiones aplicadas
- Folio: `tickets.folio` = “conciliacion” (serie dedicada si aplica); folio externo del CSV en observación para trazabilidad.
- Catálogos: match por nombre; si no hay coincidencia, se permite dejar cliente/mesero/repartidor en null.
- Propinas/promos: el CSV debe traer líneas de propina con tipo de pago; promociones, si se usan, también en línea dedicada.
- Discrepancias: registrar log en BD por lote/folio con procesados/no procesados y detalle; sin ajustes silenciosos.
- Estados: todas las ventas se generan cerradas con ticket emitido, íntegras para entrar a cortes.
