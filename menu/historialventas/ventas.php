<?php
$rol_sesion = $_SESSION['rol'] ?? 'Vendedor';
$usuario_sesion = $_SESSION['usuario_nombre'] ?? $rol_sesion;
$id_empleado_sesion = $_SESSION['id_empleado'] ?? '';
$puede_ajustar_precio = in_array($rol_sesion, ['Vendedor Pro', 'Admin'], true);
$total_columnas_carrito = $puede_ajustar_precio ? 5 : 4;

// Consultar clientes ordenando primero al Público General ('CLI-GEN')
// Cambia esto (línea 9 de ventas.php):
$query_clientes = mysqli_query($conexion, "SELECT id_cliente, nombre, tipo_cliente FROM cliente ORDER BY (id_cliente = 'CLI-GEN') DESC, nombre ASC");
?>
<div class="card p-4 shadow-sm pos-card bg-white">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 flex-wrap gap-2">
        <h5 class="fw-bold text-dark m-0">Pinturas TAPIR - Sucursal Centro</h5>

        <!-- Condicional según rol: etiqueta fija para Admin, casilla de ID para Vendedor / Vendedor Pro -->
        <?php if ($rol_sesion === 'Admin' || $rol_sesion === 'Administrador'): ?>
            <span class="badge badge-cajero text-white fw-bold">
                Usuario: <?php echo htmlspecialchars($usuario_sesion, ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($rol_sesion, ENT_QUOTES, 'UTF-8'); ?>)
            </span>
            <input type="hidden" id="id_empleado_venta" value="ADMIN">
        <?php else: ?>
            <div class="d-flex align-items-center gap-2 bg-secondary text-white px-3 py-1 rounded">
                <label for="id_empleado_venta" class="small fw-bold m-0">ID Vendedor:</label>
                <input
                    type="text"
                    id="id_empleado_venta"
                    class="form-control form-control-sm fw-bold text-uppercase"
                    style="width: 90px;"
                    placeholder="Ej. A01"
                    value="<?php echo htmlspecialchars($id_empleado_sesion, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>
        <?php endif; ?>
    </div>

    <!-- Selector de Cliente, Buscador y Agregar al Carrito -->
    <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
            <label for="select_cliente" class="form-label small fw-bold text-muted mb-1">Cliente / Tipo de Tarifa:</label>
            <select id="select_cliente" name="select_cliente" class="form-select form-select-sm">
                <?php
                if ($query_clientes) {
                    while ($cli = mysqli_fetch_assoc($query_clientes)) {
                        $id_c = htmlspecialchars($cli['id_cliente'], ENT_QUOTES, 'UTF-8');
                        $nom_c = htmlspecialchars($cli['nombre'] . " (" . $cli['tipo_cliente'] . ")", ENT_QUOTES, 'UTF-8');
                        $selected = ($cli['id_cliente'] === 'CLI-GEN') ? 'selected' : '';
                        echo "<option value='{$id_c}' {$selected}>{$nom_c}</option>";
                    }
                } else {
                    echo "<option value='CLI-GEN'>Público General (General)</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="codigo_prod" class="form-label small fw-bold text-muted mb-1">Buscar Código o Nombre de Producto:</label>
            <div class="input-group input-group-sm">
                <input
                    type="text"
                    id="codigo_prod"
                    name="codigo_prod"
                    class="form-control"
                    placeholder="Ej. PIN-01, CAL, Thiner"
                    onkeydown="if(event.key === 'Enter'){ event.preventDefault(); buscarProductoBD(); }"
                >
                <button class="btn btn-primary" type="button" onclick="buscarProductoBD()">Buscar</button>
            </div>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-success btn-sm fw-bold w-100 py-2" onclick="agregarAlCarrito()">Agregar Al Carrito</button>
        </div>
    </div>

    <div id="info_producto_encontrado" class="alert alert-info border small py-2 mb-3 d-none">
        <strong>Producto Encontrado:</strong> <span id="lbl_nombre">-</span> |
        <strong>Precio:</strong> $<span id="lbl_precio">0.00</span> |
        <strong>Stock:</strong> <span id="lbl_stock">0</span>
    </div>

    <!-- Catálogo Rápido con scroll vertical -->
    <div class="card bg-light border-0 p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-bold text-secondary">Productos en Existencia (Catálogo Rápido):</span>
            <span class="badge bg-secondary">Use el buscador para 500+ productos o clic en + Añadir</span>
        </div>
        <div class="table-responsive" style="max-height: 165px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px;">
            <table class="table table-sm table-bordered bg-white align-middle small mb-0">
                <thead class="table-secondary sticky-top">
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Descripción del Producto</th>
                        <th scope="col">Precio Unitario</th>
                        <th scope="col">Stock</th>
                        <th scope="col" class="text-center" style="width: 100px;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt_cat = $conexion->prepare(
                        "SELECT id_producto, nom_produc, precio, stock FROM producto ORDER BY nom_produc ASC LIMIT 100"
                    );
                    if ($stmt_cat && $stmt_cat->execute()) {
                        $res_cat = $stmt_cat->get_result();
                        while ($p = $res_cat->fetch_assoc()) {
                            $cod_raw = (string)$p['id_producto'];
                            $nom_raw = (string)$p['nom_produc'];
                            $cod = htmlspecialchars($cod_raw, ENT_QUOTES, 'UTF-8');
                            $nom = htmlspecialchars($nom_raw, ENT_QUOTES, 'UTF-8');
                            $precio = (float)$p['precio'];
                            $stock = (int)($p['stock'] ?? 0);
                            $cod_js = json_encode($cod_raw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                            $nom_js = json_encode($nom_raw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                            echo "<tr>";
                            echo "<td><code class='text-primary fw-bold'>{$cod}</code></td>";
                            echo "<td>{$nom}</td>";
                            echo "<td>$" . number_format($precio, 2) . "</td>";
                            echo "<td><span class='badge bg-info text-dark'>{$stock} unids</span></td>";
                            echo "<td class='text-center'><button type='button' class='btn btn-outline-primary btn-sm py-0 px-2 fw-bold' onclick='seleccionarRapido({$nom_js}, {$precio}, {$cod_js})'>+ Añadir</button></td>";
                            echo "</tr>";
                        }
                        $stmt_cat->close();
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabla del Carrito -->
    <div class="table-responsive mb-3">
        <table class="table table-bordered align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 80px;" class="text-center">Cant.</th>
                    <th scope="col">Concepto / Descripción</th>
                    <th scope="col" style="width: 130px;">P. Unitario</th>
                    <th scope="col" style="width: 130px;">Importe</th>
                    <?php if ($puede_ajustar_precio): ?>
                        <th scope="col" style="width: 150px;" class="text-center">Modificar / Desc.</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tabla_carrito">
                <tr>
                    <td colspan="<?php echo $total_columnas_carrito; ?>" class="text-center text-muted py-4">
                        No hay productos en el carrito
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pie de Cobro con Botón Limpiar Carrito -->
    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded border flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <label for="metodo_pago" class="small fw-bold text-dark m-0">Método de pago:</label>
            <select id="metodo_pago" name="metodo_pago" class="form-select form-select-sm w-auto">
                <option value="1">Efectivo</option>
                <option value="2">Tarjeta crédito</option>
                <option value="3">Tarjeta débito</option>
                <option value="4">Transferencia</option>
            </select>
            <button type="button" class="btn btn-outline-danger btn-sm fw-bold ms-2" onclick="limpiarCarrito()">
                Limpiar Carrito
            </button>
        </div>
        <h3 class="fw-bold text-danger m-0">Total: $<span id="total_venta">0.00</span></h3>
        <button type="button" class="btn btn-success btn-lg fw-bold px-4" onclick="procesarVenta()">Continuar venta</button>
    </div>
</div>

<!-- MODAL DE CONFIRMACIÓN DE VENTA -->
<div id="modal_confirmacion_venta" class="modal" tabindex="-1" style="background: rgba(0,0,0,0.6); display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content shadow-lg border-0 rounded-0">
            <div class="modal-header text-white py-2" style="background-color: #dc3545; border-radius: 0;">
                <h5 class="modal-title fw-bold fs-6 m-0">Confirmación de venta</h5>
                <button type="button" class="btn-close btn-close-white" aria-label="Cerrar" onclick="cancelarVenta()"></button>
            </div>
            <div class="modal-body bg-white p-4">
                <div class="border p-2 mb-3 bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($rol_sesion, ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong class="text-dark ms-1" id="modal_lbl_vendedor"><?php echo htmlspecialchars($usuario_sesion, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <span class="small text-muted fw-bold" id="modal_lbl_cliente">Público General</span>
                </div>

                <div class="mb-2 row align-items-center">
                    <label for="modal_lbl_tarifa" class="col-4 small fw-bold text-muted">Condición de precio:</label>
                    <div class="col-8">
                        <input type="text" id="modal_lbl_tarifa" class="form-control form-control-sm bg-white" readonly value="Precio estándar / ajustable">
                    </div>
                </div>

                <div class="mb-2 row align-items-center">
                    <div class="col-4 small fw-bold text-muted">Monto total:</div>
                    <div class="col-8 fw-bold fs-5 text-dark">$<span id="modal_lbl_total">0.00</span></div>
                </div>

                <div class="mb-3 row align-items-center">
                    <div class="col-4 small fw-bold text-muted">Pago con:</div>
                    <div class="col-8 fw-bold text-secondary" id="modal_lbl_metodo">Efectivo</div>
                </div>

                <div class="text-center mb-3">
                    <span class="small fw-bold text-dark d-block mb-2">Comprobante de transacción</span>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" id="btn_modo_ticket" class="btn btn-success btn-sm px-4 rounded-pill fw-bold" onclick="seleccionarComprobante('ticket')">Ticket</button>
                        <button type="button" id="btn_modo_factura" class="btn btn-outline-secondary btn-sm px-4 rounded-pill fw-bold" onclick="seleccionarComprobante('factura')">Factura</button>
                    </div>
                </div>

                <button type="button" class="btn btn-success w-100 fw-bold py-2 shadow-sm" onclick="confirmarYDescontarStock()">Confirmar venta</button>
            </div>
        </div>
    </div>
</div>