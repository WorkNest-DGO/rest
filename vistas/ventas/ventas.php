<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
// Base app dinámica y ruta relativa para validación
$__sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$__pos = strpos($__sn, '/vistas/');
$__app_base = $__pos !== false ? substr($__sn, 0, $__pos) : rtrim(dirname($__sn), '/');
$path_actual = preg_replace('#^' . preg_quote($__app_base, '#') . '#', '', ($__sn ?: $_SERVER['PHP_SELF']));
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

// Cargar todas las denominaciones disponibles para usarlas en el JS
$denominaciones = $conn->query("SELECT id, descripcion, valor FROM catalogo_denominaciones ORDER BY valor ASC")->fetch_all(MYSQLI_ASSOC);

$title = 'Ventas';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <h2>Modulo de Ventas</h2>
      </div>
      <div class="col-12">
        <a href="">Inicio</a>
        <a href="">Ventas</a>
      </div>
    </div>
  </div>
</div>
<!-- Page Header End -->
<div class="container mt-5 mb-5">
  <div class="section-header text-center">
    <p>Control de ventas</p>
    <h2>Registro de Venta</h2>
  </div>
<div class="container mt-5" hidden>
  <h2 class="section-header">Solicitudes de Ticket</h2>
  <div class="table-responsive">
    <table id="solicitudes" class="styled-table">
      <thead>
        <tr>
          <th style="color:#fff">Mesa</th>
          <th style="color:#fff">Imprimir</th>
        </tr>
      </thead>
      <tbody>
        <!-- Las solicitudes se insertarán aquí dinámicamente -->
      </tbody>
    </table>
  </div>
</div>
<br>
  <div class="row justify-content-center">
    <div class="col-md-10 bg-dark p-4 rounded">
           <div id="controlCaja" class="mb-3 text-white">
        <button id="btnCerrarCorte" type="button" class="btn custom-btn" title="" style="display:none">
          Cerrar corte
        </button>
      </div>
      <form id="formVenta">
        <div class="form-group">
          <label for="tipo_entrega" class="text-white">Tipo de venta:</label>
          <select id="tipo_entrega" name="tipo_entrega" class="form-control">
            <option value="" disabled selected>Seleccione</option>
            <option value="mesa">En restaurante</option>
            <option value="domicilio">A domicilio</option>
            <option value="rapido">Rápido</option>
          </select>
        </div>
        <div class="form-group">
          <label for="impresora_id" class="text-white">Impresora:</label>
          <select id="impresora_id" name="impresora_id" class="form-control">
            <option value="">Selecciona impresora</option>
          </select>
        </div>

        <div id="campoObservacion" class="form-group" style="display:none;">
          <label for="observacion" class="text-white">Observación:</label>
          <textarea id="observacion" name="observacion" class="form-control"></textarea>
        </div>

        <div id="campoMesa" class="form-group">
          <label for="mesa_id" class="text-white">Mesa:</label>
          <select id="mesa_id" name="mesa_id" class="form-control">
            <option value="">Seleccione</option>
            <option value="1">Mesa 1</option>
            <option value="2">Mesa 2</option>
            <option value="3">Mesa 3</option>
          </select>
        </div>

        <div id="campoRepartidor" class="form-group" style="display:none;">
          <label for="repartidor_id" class="text-white">Repartidor:</label>
          <select id="repartidor_id" name="repartidor_id" class="form-control"></select>
        </div>

        <div id="seccionClienteDomicilio" class="form-group" style="display:none;">
          <label class="text-white d-block">Cliente para entrega a domicilio:</label>
          <div class="d-flex flex-column flex-sm-row align-items-start gap-2 mb-2">
            <div class="selector-cliente position-relative flex-grow-1 w-100">
              <input
                type="text"
                id="buscarClienteDomicilio"
                class="form-control"
                placeholder="Escribe para buscar por nombre, teléfono o colonia"
              >
              <input type="hidden" id="cliente_id" name="cliente_id">
              <ul
                class="list-group list-group-flush position-absolute w-100"
                id="listaClientesDomicilio"
                style="z-index: 10;"
              ></ul>
            </div>
            <button type="button" class="btn btn-secondary" id="btnNuevoCliente">Nuevo</button>
          </div>
          <div  style="color:#fff"id="resumenCliente" class="p-2 rounded" style="background: #2c2c2c; color: #ffffff; display:none;">
            <div><strong>Teléfono:</strong> <span id="clienteTelefono">-</span></div>
            <div><strong>Dirección:</strong> <span id="clienteDireccion">-</span></div>
            <div class="d-flex flex-wrap gap-3 mt-2">
              <div><strong>Colonia:</strong> <span id="clienteColonia">-</span></div>
              <div><strong>Dist. a La Forestal:</strong> <span id="clienteDistancia">-</span></div>
            </div>
            <div id="clienteColoniaSelectWrap" class="mt-2" style="display:none;">
              <label for="clienteColoniaSelect" class="text-white">Selecciona colonia</label>
              <select id="clienteColoniaSelect" class="form-control"></select>
              <small class="text-muted">Solo se muestra cuando el cliente no tiene colonia asignada.</small>
            </div>
            <div class="row mt-3">
              <div class="col-md-6">
                <label for="costoForeInput" class="text-white mb-1">Costo de envío (costo_fore):</label>
                <input type="number" step="0.01" min="0" class="form-control" id="costoForeInput" placeholder="Captura el costo">
              </div>
              <div class="col-md-6 align-self-end">
                <small class="text-muted">Este monto se usará para el envío y se guardará en la colonia del cliente.</small>
              </div>
            </div>
          </div>
        </div>

        <div id="campoMesero" class="form-group">
          <label for="usuario_id" class="text-white">Mesero:</label>
          <select id="usuario_id" name="usuario_id" class="form-control"></select>
        </div>

        <!-- Sección que se muestra al elegir una mesa -->
        <div id="seccionProductos" style="display:none;" class="mt-4">
          <h3 class="text-white">Productos</h3>
          <table class="table table-bordered bg-white text-dark" id="productos">
            <thead>
              <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
              </tr>
            </thead>
            <tbody>
                <tr>
                  <td>
                    <!-- Nuevo selector de productos con buscador -->
                    <div class="selector-producto position-relative"><!-- INICIO nuevo selector -->
                      <input type="text" class="form-control buscador-producto" placeholder="Buscar producto...">
                      <select class="producto d-none" name="producto"></select>
                      <ul class="list-group lista-productos position-absolute w-100"></ul>
                    </div><!-- FIN nuevo selector -->
                  </td>
                  <td><input type="number" class="form-control cantidad"></td>
                  <td><input type="number" step="0.01" class="form-control precio" readonly></td>
                </tr>
              </tbody>
            </table>
          <div class="text-center mb-3">
            <button type="button" class="btn custom-btn" id="agregarProducto">Agregar Producto</button>
            <button type="button" class="btn custom-btn" id="registrarVenta">Registrar Venta</button>
          </div>
          
          <!-- Promociones aplicables a la venta (opcional, filtradas por tipo de entrega) -->
          <div id="campoPromocion" class="form-group" style="display:none;">
            <label class="text-white d-block">Promociones:</label>
            <div id="panelPromosVenta" class="promos-panel bg-light text-dark rounded p-3">
              <div class="promo-row d-flex flex-wrap gap-2 align-items-center">
                <select id="promocion_id" name="promocion_id" class="form-control promo-select flex-grow-1"></select>
                <button type="button" class="btn btn-secondary" id="btnAgregarPromoVenta">Agregar promoci&oacute;n</button>
              </div>
              <div id="promosVentaDinamicas"></div>
              <small class="text-muted d-block mt-2">Puedes activar m&aacute;s de una promoci&oacute;n; se validar&aacute;n contra los productos capturados.</small>
            </div>
            <div class="text-white mt-2">
              Promociones activas: <span id="lblPromosActivasVenta">0</span>
            </div>
          </div>

<!-- Panel Envío (mostrar solo si: tipo_entrega=domicilio && repartidor=Repartidor casa) -->
          <div id="panelEnvioCasa" style="display:none; margin-top:12px;     color: #000000ff;">
            <div class="card">
              <div class="card-header" style="font-weight:600;">Envío</div>
              <div class="card-body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <div>
                  <label style="display:block; font-size:12px; opacity:.7;">Concepto</label>
                  <span id="envioNombre" style="font-weight:600;">ENVÍO – Repartidor casa</span>
                </div>
                <div hidden>
                  <label for="envioCantidad" style="display:block; font-size:12px; opacity:.7;">Cantidad</label>
                  <input id="envioCantidad" type="number" min="0" step="1" value="1" style="width:90px;">
                </div>
                <div>
                  <label for="envioPrecio" style="display:block; font-size:12px; opacity:.7;">Precio unitario</label>
                  <input id="envioPrecio" type="number" min="0" step="0.01" value="30.00" style="width:110px;">
                </div>
                <div>
                  <label style="display:block; font-size:12px; opacity:.7;">Subtotal</label>
                  <span id="envioSubtotal">0.00</span>
                </div>
                <button id="btnQuitarEnvio" type="button">Quitar envío</button>
              </div>
            </div>
          </div>

          <script>
            // Defaults si no están definidos en otro lado
            window.ENVIO_CASA_PRODUCT_ID = window.ENVIO_CASA_PRODUCT_ID || 9001;
            window.ENVIO_CASA_DEFAULT_PRECIO = window.ENVIO_CASA_DEFAULT_PRECIO || 30.00;
            window.ENVIO_CASA_NOMBRE = window.ENVIO_CASA_NOMBRE || 'ENVÍO – Repartidor casa';
          </script>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Botones de movimientos de caja -->
<div class="container mt-3 mb-3 text-center">
  <button id="btnDeposito" type="button" class="btn custom-btn" data-toggle="modal" data-target="#modalMovimientoCaja">Depósito a caja</button>
  <button id="btnRetiro" type="button" class="btn custom-btn" data-toggle="modal" data-target="#modalMovimientoCaja">Retiro de caja</button>
  <button id="btnDetalleMovs" type="button" class="btn custom-btn">Detalle</button>
</div>

<div style=" padding: 40px;">
  <h2 class="section-header">Historial de Ventas</h2>
  <div style="text-align: center;" id="paginacion" class="mt-2"></div> <br>
  <div class="mb-2 d-flex justify-content-between">
    <input type="search" id="buscadorVentas" class="form-control w-50" placeholder="Buscar...">
    <div class="d-flex align-items-center">
      <label for="recordsPerPage" class="me-2">Registros por página:</label>
      <select id="recordsPerPage" class="form-select w-auto">
        <option value="15">15</option>
        <option value="25">25</option>
        <option value="50">50</option>
      </select>
    </div>
  </div>
  <div class="table-responsive">
    <table id="historial" class="table">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Fecha</th>
          <th>Total</th>
          <th>Tipo</th>
          <th>Destino</th>
          <th>Observación</th>
          <th>Estatus</th>
          <th>Entregado</th>
          <th>Ver detalles</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <!-- Se insertan filas dinámicamente aquí -->
      </tbody>
    </table>
  </div>
  
</div>





<!-- Modales -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nuevo cliente</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div  class="modal-body">
        <form id="formNuevoCliente">
          <div class="form-group">
            <label for="nuevoClienteNombre">Nombre</label>
            <input type="text" class="form-control" id="nuevoClienteNombre" required>
          </div>
          <div class="form-group">
            <label for="nuevoClienteTelefono">Teléfono</label>
            <input type="text" class="form-control" id="nuevoClienteTelefono">
          </div>
          <div class="form-group">
            <label for="nuevoClienteColonia">Colonia</label>
            <select id="nuevoClienteColonia" class="form-control" required></select>
          </div>
          <div class="form-group">
            <label for="nuevoClienteCalle">Calle</label>
            <input type="text" class="form-control" id="nuevoClienteCalle">
          </div>
          <div class="form-group">
            <label for="nuevoClienteNumero">Número exterior</label>
            <input type="text" class="form-control" id="nuevoClienteNumero">
          </div>
          <div class="form-group">
            <label for="nuevoClienteEntre1">Entre calle 1</label>
            <input type="text" class="form-control" id="nuevoClienteEntre1">
          </div>
          <div class="form-group">
            <label for="nuevoClienteEntre2">Entre calle 2</label>
            <input type="text" class="form-control" id="nuevoClienteEntre2">
          </div>
          <div class="form-group">
            <label for="nuevoClienteReferencias">Referencias</label>
            <textarea class="form-control" id="nuevoClienteReferencias"></textarea>
          </div>
          <div class="form-group">
            <label for="nuevoClienteCostoFore">Costo de envío (costo_fore)</label>
            <input type="number" step="0.01" min="0" class="form-control" id="nuevoClienteCostoFore" placeholder="Opcional">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn custom-btn" id="guardarNuevoCliente">Guardar</button>
      </div>
    </div>
  </div>
</div>
<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modal-detalles" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de venta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"><!-- contenido dinámico --></div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal: Propinas por usuario -->
<div class="modal fade" id="modalPropinasUsuarios" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Propinas por usuario</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="propinasUsuariosContenido">
          <div class="text-muted">Cargando...</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
 </div>
<!-- Modal: Totales de envios -->
<div class="modal fade" id="modalEnviosRepartidor" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Totales de envios</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="enviosRepartidorContenido">
          <div class="text-muted">Cargando...</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal: Información de envío/cliente -->
<div class="modal fade" id="modalClienteEnvio" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Datos de envío</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="clienteEnvioContenido" class="text"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalDesglose" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Desglose de caja</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"><!-- contenido dinámico --></div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalCorteTemporal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Corte Temporal</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="corteTemporalDatos"></div>
        <!-- Vista formateada del corte temporal -->
        <div id="corteTemporalBonito" style="display:none;">
          <div class="tarjeta" style="margin-top:8px;">
            <div style="opacity:.8">Datos del corte</div>
            <div>Corte: <span id="lblTmpCorteId">-</span></div>
            <div>Inicio: <span id="lblTmpFechaInicio">-</span></div>
            <div>Folios: <span id="lblTmpFolios">-</span></div>
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div><strong>Totales de venta</strong></div>
            <div>Total bruto: <span id="lblTmpTotalBruto">0.00</span></div>
            <div>Descuentos: <span id="lblTmpTotalDescuentos">0.00</span></div>
            <div id="promocionesA" style="display:none"><strong>Promociones Aplicadas:</strong> <span id="lblTmpTotalPromociones">0.00</span></div>
            <div>Total esperado: <span id="lblTmpTotalEsperado">0.00</span></div>
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div><strong>Caja y movimientos</strong></div>
            <div>Fondo inicial: <span id="lblTmpFondo">0.00</span></div>
            <div>Depósitos: <span id="lblTmpDepositos">0.00</span></div>
            <div>Retiros: <span id="lblTmpRetiros">0.00</span></div>
            <div>Total propinas: <span id="lblTmpTotalPropinas">0.00</span></div>
            <div>Efectivo esperado en caja: <span id="lblTmpTotalFinalEfectivo">0.00</span></div>
            <div>Total final (todos los medios): <span id="lblTmpTotalFinalGeneral">0.00</span></div>
            <!-- <div>Total ingresado: <span id="lblTmpTotalIngresado">0.00</span></div> -->
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div style="opacity:.8">Totales por tipo de pago</div>
            <div>Efectivo: <span id="lblTmpTotalPagoEfectivo">0.00</span></div>
            <div>Boucher:  <span id="lblTmpTotalPagoBoucher">0.00</span></div>
            <div>Cheque:   <span id="lblTmpTotalPagoCheque">0.00</span></div>
            <div>Tarjeta:  <span id="lblTmpTotalPagoTarjeta">0.00</span></div>
            <div>Transferencia:  <span id="lblTmpTotalPagoTransfer">0.00</span></div>
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div style="opacity:.8">Esperado por tipo de pago</div>
            <div>Efectivo: <span id="lblTmpEsperadoEfectivo">0.00</span></div>
            <div>Boucher:  <span id="lblTmpEsperadoBoucher">0.00</span></div>
            <div>Cheque:   <span id="lblTmpEsperadoCheque">0.00</span></div>
            <div>Tarjeta:  <span id="lblTmpEsperadoTarjeta">0.00</span></div>
            <div>Transferencia:  <span id="lblTmpEsperadoTransfer">0.00</span></div>
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div><strong>Propinas por tipo de pago</strong></div>
            <div>Efectivo: <span id="lblTmpPropinaEfectivo">0.00</span></div>
            <div>Transferencia:  <span id="lblTmpPropinaTransfer">0.00</span></div>
            <div>Tarjeta:  <span id="lblTmpPropinaTarjeta">0.00</span></div>
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div style="opacity:.8">Cuentas por estatus</div>
            <div><strong>Cuentas abiertas:</strong> <span id="lblTmpCuentasActivas">0</span></div>
            <div><strong>Monto abiertas:</strong> <span id="lblTmpTotalActivas">0.00</span></div>
            <div><strong>Cuentas canceladas:</strong> <span id="lblTmpCuentasCanceladas">0</span></div>
            <div><strong>Monto canceladas:</strong> <span id="lblTmpTotalCanceladas">0.00</span></div>
          </div>

          

          <div class="tarjeta" style="margin-top:8px;">
            <div style="opacity:.8">Totales por mesero</div>
            <table id="tblTmpMeseros" class="tabla-compacta">
              <thead><tr><th>Mesero</th><th>Total</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div style="opacity:.8">Totales por repartidor</div>
            <table id="tblTmpRepartidores" class="tabla-compacta">
              <thead><tr><th>Repartidor</th><th>Total</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div style="opacity:.8">Totales por producto</div>
            <table id="tblTmpProductos" class="tabla-compacta">
              <thead><tr><th>Categoria</th><th>Total</th></tr></thead>
              <tbody>
                <tr><td>Alimentos</td><td id="lblTmpProdAlimentos">0.00</td></tr>
                <tr><td>Bebidas</td><td id="lblTmpProdBebidas">0.00</td></tr>
                <tr><td><strong>Total</strong></td><td id="lblTmpProdTotal"><strong>0.00</strong></td></tr>
              </tbody>
            </table>
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div style="opacity:.8">Totales por servicio</div>
            <table id="tblTmpServicio" class="tabla-compacta">
              <thead><tr><th>Servicio</th><th>Total</th></tr></thead>
              <tbody>
                <tr><td>Comedor</td><td id="lblTmpServComedor">0.00</td></tr>
                <tr><td>Domicilio</td><td id="lblTmpServDomicilio">0.00</td></tr>
                <tr><td>Rápido</td><td id="lblTmpServRapido">0.00</td></tr>
              </tbody>
            </table>
          </div>

          <div class="tarjeta" style="margin-top:8px;">
            <div style="opacity:.8">Totales por plataforma</div>
            <table id="tblTmpPlataformas" class="tabla-compacta">
              <thead><tr><th>Plataforma</th><th>Bruto</th><th>Descuento</th><th>Neto</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <label for="observacionesCorteTemp">Observaciones:</label>
        <textarea id="observacionesCorteTemp" class="form-control" rows="3"></textarea>
      </div>
      <div class="modal-footer">
        <button id="guardarCorteTemporal" class="btn btn-success">Guardar Corte Temporal</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalMovimientoCaja" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Movimiento de caja</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="formMovimientoCaja">
          <div class="mb-3">
            <label for="tipoMovimiento" class="form-label">Tipo de movimiento</label>
            <select id="tipoMovimiento" class="form-select">
              <option value="deposito">Depósito</option>
              <option value="retiro">Retiro</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="montoMovimiento" class="form-label">Monto</label>
            <input type="number" step="0.01" class="form-control" id="montoMovimiento" required>
          </div>
          <div class="mb-3">
            <label for="motivoMovimiento" class="form-label">Motivo</label>
            <textarea id="motivoMovimiento" class="form-control" required></textarea>
          </div>
          <div class="mb-3">
            <label for="fechaMovimiento" class="form-label">Fecha</label>
            <input type="text" class="form-control" id="fechaMovimiento" readonly>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn custom-btn" id="guardarMovimiento">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Detalle de movimientos de caja -->
<div class="modal fade" id="modalMovimientos" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Movimientos de caja (corte actual)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-striped table-sm" id="tablaMovimientos">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Monto</th>
                <th>Motivo</th>
                <th>Usuario</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
 </div>

<!-- MODAL NORMALIZED 2025-08-14 -->
<div class="modal fade" id="modalCortePreview" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Previsualización – Corte / Cierre de caja</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <pre id="corteTicketText" class="ticket-mono"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnImprimirCorte" class="btn custom-btn">Imprimir</button>
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de confirmación de cancelación -->
<div class="modal fade" id="cancelVentaModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">¿Deseas cancelar la venta?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="confirmCancelVenta">Cancelar venta</button>
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal global de mensajes -->
<div class="modal fade" id="appMsgModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Mensaje</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

  <?php require_once __DIR__ . '/../footer.php'; ?>
  
<script>
    // ID de usuario proveniente de la sesión para operaciones en JS
    window.usuarioId = <?php echo json_encode($_SESSION['usuario_id']); ?>;
    // ID de la venta actualmente consultada en detalle
    window.ventaIdActual = null;
    // ID de corte actual si existe en la sesión
    window.corteId = <?php echo json_encode($_SESSION['corte_id'] ?? null); ?>;
    // Catálogo de denominaciones cargado desde la base de datos
    const catalogoDenominaciones = <?php echo json_encode($denominaciones); ?>;
    // Último ID de detalle enviado a cocina almacenado
    window.ultimoDetalleCocina = parseInt(localStorage.getItem('ultimoDetalleCocina') || '0', 10);

    // Config ENVÍO automático (Repartidor casa)
    window.ENVIO_CASA_PRODUCT_ID = 9001;
    window.ENVIO_CASA_DEFAULT_PRECIO = 30.00;
</script>
<!-- Modal de error de promoción -->
<div class="modal fade" id="modalPromoError" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Promoci&oacute;n no aplicable</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="promoErrorMsg"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Corte enviado -->
<div class="modal fade" id="modalCorteEnviado" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Corte enviado</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        Corte enviado, que tengas un buen día.
      </div>
      <div class="modal-footer">
        <button type="button" id="btnContinuarCorte" class="btn custom-btn">Continuar</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal: pendientes para corte -->
<div class="modal fade" id="modalPendientesCorte" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">No se puede cerrar el corte</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Existen pendientes que debes liberar antes de cerrar el corte.</p>
        <ul class="mb-0">
          <li>Ventas activas: <span id="lblPendVentas">0</span></li>
          <li>Mesas ocupadas: <span id="lblPendMesas">0</span></li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn custom-btn" data-dismiss="modal">Entendido</button>
      </div>
    </div>
  </div>
</div>

<script src="../../utils/js/buscador.js"></script>
<script src="ventas.js"></script>
  </body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
