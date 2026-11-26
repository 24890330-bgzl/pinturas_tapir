<?php
if (!isset($conexion)) {
    require_once(__DIR__ . "/../../conexion.php");
}

$mensaje_estado = '';
$tipo_alerta = 'info';

// Detectar si la tabla producto tiene columna de IVA (porcentaje_iva o iva)
$columna_iva_prod = null;
$res_cols = $conexion->query("SHOW COLUMNS FROM producto");
if ($res_cols) {
    while ($col = $res_cols->fetch_assoc()) {
        if ($col['Field'] === 'porcentaje_iva') {
            $columna_iva_prod = 'porcentaje_iva';
            break;
        }
        if ($col['Field'] === 'iva') {
            $columna_iva_prod = 'iva';
        }
    }
    $res_cols->free();
}

// Eliminar registro de inventario vía POST seguro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_eliminar_inv'])) {
    $id_eliminar = (int)($_POST['id_inv_eliminar'] ?? 0);
    if ($id_eliminar > 0) {
        $stmt_del = $conexion->prepare("DELETE FROM inventario WHERE id_inv = ?");
        if ($stmt_del) {
            $stmt_del->bind_param("i", $id_eliminar);
            if ($stmt_del->execute()) {
                $mensaje_estado = "Registro de inventario #{$id_eliminar} eliminado correctamente.";
                $tipo_alerta = "success";
            } else {
                $mensaje_estado = "No se pudo eliminar el registro: " . $stmt_del->error;
                $tipo_alerta = "danger";
            }
            $stmt_del->close();
        }
    }
}

// Guardar o actualizar stock, precio e IVA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_guardar_inv'])) {
    $id_inv = (int)($_POST['inv_id'] ?? 0);
    $id_producto = trim((string)($_POST['inv_producto'] ?? ''));
    $cant_disp = (int)($_POST['inv_dispo'] ?? -1);
    $cant_min = (int)($_POST['inv_min'] ?? -1);
    $ubicacion = trim((string)($_POST['inv_ubicacion'] ?? ''));
    $id_empleado = trim((string)($_POST['inv_empleado'] ?? ''));
    $precio_unit = (float)($_POST['inv_precio'] ?? -1);
    $aplica_iva = (($_POST['aplica_iva'] ?? '1') === '1');
    $porcentaje_iva = $aplica_iva ? (float)($_POST['porcentaje_iva'] ?? 16) : 0.0;

    if ($id_inv <= 0 || $id_producto === '' || $cant_disp < 0 || $cant_min < 0 || $ubicacion === '' || $id_empleado === '' || $precio_unit < 0) {
        $mensaje_estado = "Por favor complete todos los campos obligatorios con valores válidos.";
        $tipo_alerta = "warning";
    } elseif ($porcentaje_iva < 0 || $porcentaje_iva > 100) {
        $mensaje_estado = "El porcentaje de IVA debe estar entre 0% y 100%.";
        $tipo_alerta = "warning";
    } else {
        $conexion->begin_transaction();
        try {
            $stmt_chk_prod = $conexion->prepare("SELECT id_producto FROM producto WHERE id_producto = ? LIMIT 1");
            $stmt_chk_prod->bind_param("s", $id_producto);
            $stmt_chk_prod->execute();
            $res_chk_prod = $stmt_chk_prod->get_result();
            $existe_prod = ($res_chk_prod && $res_chk_prod->num_rows > 0);
            $stmt_chk_prod->close();

            if (!$existe_prod) {
                throw new Exception("El ID de producto '{$id_producto}' no existe en el catálogo.");
            }

            if ($columna_iva_prod !== null) {
                $sql_upd_prod = "UPDATE producto SET precio = ?, stock = ?, `{$columna_iva_prod}` = ? WHERE id_producto = ?";
                $stmt_upd_prod = $conexion->prepare($sql_upd_prod);
                $stmt_upd_prod->bind_param("dids", $precio_unit, $cant_disp, $porcentaje_iva, $id_producto);
            } else {
                $sql_upd_prod = "UPDATE producto SET precio = ?, stock = ? WHERE id_producto = ?";
                $stmt_upd_prod = $conexion->prepare($sql_upd_prod);
                $stmt_upd_prod->bind_param("dis", $precio_unit, $cant_disp, $id_producto);
            }

            if (!$stmt_upd_prod->execute()) {
                throw new Exception("Error al actualizar el producto: " . $stmt_upd_prod->error);
            }
            $stmt_upd_prod->close();

            $stmt_chk_inv = $conexion->prepare("SELECT id_inv FROM inventario WHERE id_inv = ? LIMIT 1");
            $stmt_chk_inv->bind_param("i", $id_inv);
            $stmt_chk_inv->execute();
            $res_chk_inv = $stmt_chk_inv->get_result();
            $existe_inv = ($res_chk_inv && $res_chk_inv->num_rows > 0);
            $stmt_chk_inv->close();

            if ($existe_inv) {
                $stmt_inv = $conexion->prepare(
                    "UPDATE inventario
                     SET id_producto = ?, cantidad_dispo = ?, cantidad_min = ?, ubicacion = ?, id_empleado = ?
                     WHERE id_inv = ?"
                );
                $stmt_inv->bind_param("siissi", $id_producto, $cant_disp, $cant_min, $ubicacion, $id_empleado, $id_inv);
            } else {
                $stmt_inv = $conexion->prepare(
                    "INSERT INTO inventario (id_inv, id_producto, cantidad_dispo, cantidad_min, ubicacion, id_empleado)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt_inv->bind_param("isiiss", $id_inv, $id_producto, $cant_disp, $cant_min, $ubicacion, $id_empleado);
            }

            if (!$stmt_inv->execute()) {
                throw new Exception("Error al guardar en inventario: " . $stmt_inv->error);
            }
            $stmt_inv->close();

            $conexion->commit();
            $mensaje_estado = "Inventario y precio actualizados correctamente (IVA: " . ($aplica_iva ? number_format($porcentaje_iva, 2) . "%" : "No aplica (0%)") . ").";
            $tipo_alerta = "success";
        } catch (Throwable $e) {
            $conexion->rollback();
            $mensaje_estado = $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

$siguiente_id_inv = 1;
$res_max = $conexion->query("SELECT COALESCE(MAX(id_inv), 0) + 1 AS sig_id FROM inventario");
if ($res_max && $fila_max = $res_max->fetch_assoc()) {
    $siguiente_id_inv = (int)$fila_max['sig_id'];
    $res_max->free();
}

// Catálogo de productos para la lista desplegable buscable (soporta 500+ registros)
$catalogo_productos = [];
$stmt_prod = $conexion->prepare("SELECT id_producto, nom_produc, precio FROM producto ORDER BY nom_produc ASC LIMIT 1000");
if ($stmt_prod && $stmt_prod->execute()) {
    $res_prod = $stmt_prod->get_result();
    while ($prod_row = $res_prod->fetch_assoc()) {
        $catalogo_productos[] = [
            'id'     => (string)$prod_row['id_producto'],
            'nombre' => (string)$prod_row['nom_produc'],
            'precio' => (float)($prod_row['precio'] ?? 0),
        ];
    }
    $stmt_prod->close();
}

// Consulta completa sin paginación para lista corrediza
$buscar_inv = trim((string)($_GET['buscar_inv'] ?? ''));

if ($buscar_inv !== '') {
    $patron_busq = '%' . $buscar_inv . '%';
    $stmt_list = $conexion->prepare(
        "SELECT i.id_inv, i.id_producto, i.cantidad_dispo, i.cantidad_min, i.ubicacion, i.id_empleado,
                p.nom_produc, p.precio
         FROM inventario i
         LEFT JOIN producto p ON i.id_producto = p.id_producto
         WHERE i.id_producto LIKE ? OR i.ubicacion LIKE ? OR i.id_empleado LIKE ? OR p.nom_produc LIKE ?
         ORDER BY i.id_inv ASC"
    );
    $stmt_list->bind_param("ssss", $patron_busq, $patron_busq, $patron_busq, $patron_busq);
} else {
    $stmt_list = $conexion->prepare(
        "SELECT i.id_inv, i.id_producto, i.cantidad_dispo, i.cantidad_min, i.ubicacion, i.id_empleado,
                p.nom_produc, p.precio
         FROM inventario i
         LEFT JOIN producto p ON i.id_producto = p.id_producto
         ORDER BY i.id_inv ASC"
    );
}

$filas_inventario = [];
if ($stmt_list && $stmt_list->execute()) {
    $res_list = $stmt_list->get_result();
    while ($fila = $res_list->fetch_assoc()) {
        $filas_inventario[] = $fila;
    }
    $stmt_list->close();
}

$total_registros = count($filas_inventario);
?>

<div class="card p-4 shadow-sm bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 flex-wrap gap-2">
        <div>
            <h5 class="fw-bold text-dark m-0">Gestión de Inventario, Precio e IVA</h5>
            <small class="text-muted">Total de registros en inventario: <strong><?php echo $total_registros; ?></strong> (deslice para ver más)</small>
        </div>
        <form method="GET" action="menuinicio.php" class="d-flex gap-2">
            <input type="hidden" name="modulo" value="inventario">
            <label for="buscar_inv" class="visually-hidden">Buscar en inventario</label>
            <input
                type="text"
                id="buscar_inv"
                name="buscar_inv"
                class="form-control form-control-sm"
                placeholder="Buscar código, nombre, ubicación..."
                value="<?php echo htmlspecialchars($buscar_inv, ENT_QUOTES, 'UTF-8'); ?>"
            >
            <button type="submit" class="btn btn-sm btn-primary">Buscar</button>
        </form>
    </div>

    <?php if ($mensaje_estado !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($tipo_alerta, ENT_QUOTES, 'UTF-8'); ?> py-2 small" role="alert">
            <?php echo htmlspecialchars($mensaje_estado, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- Tabla con altura fija corrediza y encabezados fijos -->
    <div class="table-responsive" style="max-height: 360px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px;">
        <table class="table table-bordered table-hover align-middle small mb-0">
            <thead class="table-light sticky-top" style="z-index: 1;">
                <tr>
                    <th scope="col">ID Inv</th>
                    <th scope="col">ID Producto</th>
                    <th scope="col">Producto</th>
                    <th scope="col">Precio Unit.</th>
                    <th scope="col">Disponible</th>
                    <th scope="col">Mínimo</th>
                    <th scope="col">Ubicación</th>
                    <th scope="col">Empleado</th>
                    <th scope="col" class="text-center">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filas_inventario)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">No se encontraron registros de inventario.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($filas_inventario as $row): ?>
                        <?php
                        $id_i = (int)$row['id_inv'];
                        $id_p = htmlspecialchars((string)$row['id_producto'], ENT_QUOTES, 'UTF-8');
                        $nom_p = htmlspecialchars((string)($row['nom_produc'] ?? '-'), ENT_QUOTES, 'UTF-8');
                        $prec_p = (float)($row['precio'] ?? 0);
                        $disp_i = (int)$row['cantidad_dispo'];
                        $min_i = (int)$row['cantidad_min'];
                        $ub_i = htmlspecialchars((string)$row['ubicacion'], ENT_QUOTES, 'UTF-8');
                        $emp_i = htmlspecialchars((string)$row['id_empleado'], ENT_QUOTES, 'UTF-8');
                        $stock_bajo = ($disp_i <= $min_i);
                        ?>
                        <tr class="<?php echo $stock_bajo ? 'table-warning' : ''; ?>">
                            <td class="fw-bold"><?php echo $id_i; ?></td>
                            <td><code><?php echo $id_p; ?></code></td>
                            <td><?php echo $nom_p; ?></td>
                            <td>$<?php echo number_format($prec_p, 2); ?></td>
                            <td>
                                <?php echo $disp_i; ?>
                                <?php if ($stock_bajo): ?>
                                    <span class="badge bg-danger ms-1">Stock bajo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $min_i; ?></td>
                            <td><?php echo $ub_i; ?></td>
                            <td><?php echo $emp_i; ?></td>
                            <td class="text-center">
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Desea eliminar el registro #<?php echo $id_i; ?>?');">
                                    <input type="hidden" name="id_inv_eliminar" value="<?php echo $id_i; ?>">
                                    <button type="submit" name="accion_eliminar_inv" value="1" class="btn btn-danger btn-sm py-0 px-2">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-4 shadow-sm bg-light border">
    <h5 class="fw-bold text-dark mb-1">Registrar / Actualizar Stock</h5>
    <p class="text-muted small mb-3">Complete los datos correspondientes para agregar un nuevo producto o actualizar existente</p>

    <form method="POST" id="formInventario">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="inv_id" class="form-label fw-bold small">ID Inv: *</label>
                <input type="number" id="inv_id" name="inv_id" class="form-control" value="<?php echo $siguiente_id_inv; ?>" min="1" required>
            </div>

            <div class="col-md-6">
                <label for="inv_producto" class="form-label fw-bold small">Producto (ID o Nombre): *</label>
                <input
                    type="text"
                    id="inv_producto"
                    name="inv_producto"
                    list="lista_productos_inv"
                    class="form-control"
                    placeholder="Escriba o elija de la lista (Ej. PIN-01)"
                    autocomplete="off"
                    required
                >
                <datalist id="lista_productos_inv">
                    <?php foreach ($catalogo_productos as $cp): ?>
                        <?php
                        $id_attr = htmlspecialchars($cp['id'], ENT_QUOTES, 'UTF-8');
                        $nom_attr = htmlspecialchars($cp['nombre'], ENT_QUOTES, 'UTF-8');
                        $prec_attr = htmlspecialchars(number_format($cp['precio'], 2, '.', ''), ENT_QUOTES, 'UTF-8');
                        ?>
                        <option value="<?php echo $id_attr; ?>" data-precio="<?php echo $prec_attr; ?>">
                            <?php echo $nom_attr; ?> ($<?php echo number_format($cp['precio'], 2); ?>)
                        </option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-md-6">
                <label for="inv_dispo" class="form-label fw-bold small">Cantidad Disponible: *</label>
                <input type="number" id="inv_dispo" name="inv_dispo" class="form-control" placeholder="Ej. 30" min="0" required>
            </div>

            <div class="col-md-6">
                <label for="inv_min" class="form-label fw-bold small">Cantidad Mínima: *</label>
                <input type="number" id="inv_min" name="inv_min" class="form-control" placeholder="Ej. 5" min="0" required>
            </div>

            <div class="col-md-6">
                <label for="inv_ubicacion" class="form-label fw-bold small">Ubicación: *</label>
                <input type="text" id="inv_ubicacion" name="inv_ubicacion" class="form-control" placeholder="Ej. Pasillo A - Estante 1" required>
            </div>

            <div class="col-md-6">
                <label for="inv_empleado" class="form-label fw-bold small">ID Empleado: *</label>
                <input type="text" id="inv_empleado" name="inv_empleado" class="form-control" placeholder="Ej. EA2" required>
            </div>

            <div class="col-md-4">
                <label for="inv_precio" class="form-label fw-bold small">Precio Unitario ($): *</label>
                <input type="number" step="0.01" min="0" id="inv_precio" name="inv_precio" class="form-control" placeholder="Ej. 245.00" required>
            </div>

            <div class="col-md-4">
                <label for="aplica_iva" class="form-label fw-bold small">¿Aplica IVA a este producto? *</label>
                <select id="aplica_iva" name="aplica_iva" class="form-select" onchange="togglePorcentajeIvaInv()">
                    <option value="1" selected>Sí</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="col-md-4" id="grupo_porcentaje_iva_inv">
                <label for="porcentaje_iva" class="form-label fw-bold small">% de IVA:</label>
                <input type="number" step="0.01" min="0" max="100" id="porcentaje_iva" name="porcentaje_iva" class="form-control" value="16">
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" name="accion_guardar_inv" value="1" class="btn btn-success fw-bold px-4">Guardar en Inventario</button>
            <button type="reset" class="btn btn-warning fw-bold px-4" onclick="setTimeout(togglePorcentajeIvaInv, 10);">Restablecer</button>
        </div>
    </form>
</div>

<script>
function togglePorcentajeIvaInv() {
    const selIva = document.getElementById("aplica_iva");
    const grupoIva = document.getElementById("grupo_porcentaje_iva_inv");
    const inputIva = document.getElementById("porcentaje_iva");
    if (!selIva || !grupoIva || !inputIva) return;

    if (selIva.value === "1") {
        grupoIva.style.display = "block";
        const val = Number.parseFloat(inputIva.value);
        if (Number.isNaN(val) || val <= 0) {
            inputIva.value = "16";
        }
    } else {
        inputIva.value = "0";
        grupoIva.style.display = "none";
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const inputProd = document.getElementById("inv_producto");
    const inputPrecio = document.getElementById("inv_precio");
    const datalistProd = document.getElementById("lista_productos_inv");

    if (!inputProd || !inputPrecio || !datalistProd) return;

    inputProd.addEventListener("change", function () {
        const valorIngresado = inputProd.value.trim().toLowerCase();
        if (!valorIngresado) return;

        for (const opt of datalistProd.options) {
            const valOpt = (opt.value || "").trim().toLowerCase();
            const textoOpt = (opt.textContent || "").trim().toLowerCase();
            if (valOpt === valorIngresado || textoOpt.includes(valorIngresado)) {
                inputProd.value = opt.value;
                const precioSugerido = Number.parseFloat(opt.dataset.precio || "");
                if (!Number.isNaN(precioSugerido) && inputPrecio.value === "") {
                    inputPrecio.value = precioSugerido.toFixed(2);
                }
                break;
            }
        }
    });
});
</script>