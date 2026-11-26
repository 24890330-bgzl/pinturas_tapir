<?php
session_start();
require_once("../conexion.php");

if (!isset($_SESSION['rol'])) {
    header("Location: ../login.php");
    exit();
}

$rol_actual = $_SESSION['rol'];
$nombre_usuario = $_SESSION['usuario_nombre'] ?? $rol_actual;
$id_empleado_sesion = $_SESSION['id_empleado'] ?? ($rol_actual === 'Admin' ? 'ADMIN' : '');
$puede_ajustar_precio = in_array($rol_actual, ['Vendedor Pro', 'Admin'], true);

$permisos_por_rol = [
    'Vendedor'     => ['inicio', 'ventas'],
    'Vendedor Pro' => ['inicio', 'ventas', 'whatsapp', 'inventario', 'historial'],
    'Contabilidad' => ['inicio', 'historial', 'reportes'],
    'Admin'        => ['inicio', 'ventas', 'whatsapp', 'inventario', 'historial', 'reportes', 'configuracion'],
];

$modulos_permitidos = $permisos_por_rol[$rol_actual] ?? ['inicio', 'ventas'];
$modulo_solicitado = $_GET['modulo'] ?? 'inicio';
$modulo = in_array($modulo_solicitado, $modulos_permitidos, true) ? $modulo_solicitado : 'inicio';

// Leer los datos JSON enviados por JavaScript
$input_raw = file_get_contents("php://input");
$datos_json = json_decode($input_raw, true);

// API para procesar venta, registrar en BD, detalle y restar stock
if (is_array($datos_json) && isset($datos_json['accion_procesar_venta']) && $datos_json['accion_procesar_venta'] === true) {
    header('Content-Type: application/json; charset=utf-8');

    if (!in_array($rol_actual, ['Vendedor', 'Vendedor Pro', 'Admin'], true)) {
        http_response_code(403);
        echo json_encode(["exito" => false, "mensaje" => "Sin permisos para registrar ventas."]);
        exit();
    }

    // Recibir y validar Cliente, Empleado y Método de Pago
    $id_cliente  = !empty($datos_json['id_cliente']) ? trim((string)$datos_json['id_cliente']) : 'CLI-GEN';
    $id_empleado = !empty($datos_json['id_empleado']) ? strtoupper(trim((string)$datos_json['id_empleado'])) : '';
    $id_metodop  = isset($datos_json['id_metodop']) ? (int)$datos_json['id_metodop'] : 1;

    if ($rol_actual === 'Admin' && $id_empleado === '') {
        $id_empleado = 'ADMIN';
    }

    if ($id_empleado === '') {
        echo json_encode(["exito" => false, "mensaje" => "Por favor ingresa tu ID de Empleado antes de cerrar la venta."]);
        exit();
    }

    if (!empty($datos_json['carrito']) && is_array($datos_json['carrito'])) {
        $conexion->begin_transaction();
        try {
            $items_preparados = [];

            $stmt_por_id = $conexion->prepare("SELECT id_producto, nom_produc, precio, stock FROM producto WHERE id_producto = ? LIMIT 1");
            $stmt_por_nom = $conexion->prepare("SELECT id_producto, nom_produc, precio, stock FROM producto WHERE nom_produc LIKE ? LIMIT 1");

            foreach ($datos_json['carrito'] as $item) {
                $codigoProd = trim((string)($item['codigo'] ?? ($item['id'] ?? '')));
                $nombreProd = trim(strip_tags((string)($item['nombre'] ?? ($item['name'] ?? ''))));
                $cantidadVendida = (int)($item['cantidad'] ?? ($item['qty'] ?? 1));
                $precioEnviado = (float)($item['precioUnitario'] ?? ($item['precio'] ?? ($item['price'] ?? 0)));

                if ($cantidadVendida <= 0 || ($codigoProd === '' && $nombreProd === '')) {
                    throw new Exception("Datos de producto inválidos en el carrito.");
                }

                $filaProd = null;
                if ($codigoProd !== '') {
                    $stmt_por_id->bind_param("s", $codigoProd);
                    $stmt_por_id->execute();
                    $resId = $stmt_por_id->get_result();
                    if ($resId && $rowId = $resId->fetch_assoc()) {
                        $filaProd = $rowId;
                    }
                }

                if ($filaProd === null && $nombreProd !== '') {
                    $patronNom = '%' . $nombreProd . '%';
                    $stmt_por_nom->bind_param("s", $patronNom);
                    $stmt_por_nom->execute();
                    $resNom = $stmt_por_nom->get_result();
                    if ($resNom && $rowNom = $resNom->fetch_assoc()) {
                        $filaProd = $rowNom;
                    }
                }

                if ($filaProd === null) {
                    throw new Exception("Producto no encontrado en la BD: " . ($nombreProd !== '' ? $nombreProd : $codigoProd));
                }

                $precioOriginal = round((float)$filaProd['precio'], 2);

                // Solo Vendedor Pro y Admin pueden modificar o descontar el precio
                if ($puede_ajustar_precio && $precioEnviado >= 0 && abs($precioEnviado - $precioOriginal) > 0.0001) {
                    $precioCobrado   = round($precioEnviado, 2);
                    $precioDescuento = $precioCobrado; // Guarda el precio modificado/con descuento
                } else {
                    $precioCobrado   = $precioOriginal;
                    $precioDescuento = 0.00; // 0.00 indica que no hubo descuento
                }

                $subtotal = round($cantidadVendida * $precioCobrado, 2);
                $items_preparados[] = [
                    'id_producto' => $filaProd['id_producto'],
                    'nombre'      => $filaProd['nom_produc'],
                    'cantidad'    => $cantidadVendida,
                    'p_unitario'  => $precioOriginal,  // Siempre el precio original de catálogo
                    'p_descuento' => $precioDescuento, // El precio modificado si hubo cambio
                    'subtotal'    => $subtotal,
                ];
            }

            $stmt_por_id->close();
            $stmt_por_nom->close();

            $totalVenta = 0.0;
            foreach ($items_preparados as $ip) {
                $totalVenta += $ip['subtotal'];
            }
            $totalVenta = round($totalVenta, 2);

            // 1. Insertar cabecera en la tabla 'venta' con cliente, empleado y método de pago
            $stmt_venta = $conexion->prepare(
                "INSERT INTO venta (fecha, total, id_cliente, id_empleado, id_metodop) VALUES (NOW(), ?, ?, ?, ?)"
            );
            $stmt_venta->bind_param("dssi", $totalVenta, $id_cliente, $id_empleado, $id_metodop);
            if (!$stmt_venta->execute()) {
                throw new Exception("Error al registrar la venta principal: " . $stmt_venta->error);
            }
            $idVentaGenerada = (int)$conexion->insert_id;
            $stmt_venta->close();

            // 2. Insertar detalle_venta con precio original y descuento, y restar stock (mínimo 5 unidades)
            // 2. Insertar detalle_venta con precio original y descuento, y restar stock en AMBAS tablas
$stmt_detalle = $conexion->prepare(
    "INSERT INTO detalle_venta (id_venta, id_producto, cantidad, p_unitario, p_descuento, subtotal) VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt_stock = $conexion->prepare(
    "UPDATE producto SET stock = stock - ? WHERE id_producto = ? AND (stock - ?) >= 5"
);
// AGREGAR ESTA LÍNEA PARA INVENTARIO:
$stmt_stock_inv = $conexion->prepare(
    "UPDATE inventario SET cantidad_dispo = cantidad_dispo - ? WHERE id_producto = ?"
);

foreach ($items_preparados as $ip) {
    $stmt_detalle->bind_param(
        "isiddd",
        $idVentaGenerada,
        $ip['id_producto'],
        $ip['cantidad'],
        $ip['p_unitario'],
        $ip['p_descuento'],
        $ip['subtotal']
    );
    if (!$stmt_detalle->execute()) {
        throw new Exception("Error al guardar el detalle de venta: " . $stmt_detalle->error);
    }

    $stmt_stock->bind_param("isi", $ip['cantidad'], $ip['id_producto'], $ip['cantidad']);
    if (!$stmt_stock->execute() || $stmt_stock->affected_rows === 0) {
        throw new Exception(
            "Stock insuficiente o el stock no puede quedar por debajo de 5 unidades en: " . $ip['nombre']
        );
    }

    // AGREGAR ESTAS DOS LÍNEAS PARA RESTAR EN INVENTARIO:
    $stmt_stock_inv->bind_param("is", $ip['cantidad'], $ip['id_producto']);
    $stmt_stock_inv->execute();
}

$stmt_detalle->close();
$stmt_stock->close();
$stmt_stock_inv->close(); // Cerrar la consulta

            $conexion->commit();
            echo json_encode(["exito" => true, "id_venta" => $idVentaGenerada]);
        } catch (Throwable $e) {
            $conexion->rollback();
            echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["exito" => false, "mensaje" => "Carrito vacío"]);
    }
    exit();
}

// API de búsqueda flexible de productos (preparada para 500+ registros)
if (isset($_GET['api_buscar_producto'])) {
    header('Content-Type: application/json; charset=utf-8');
    $busqueda = trim((string)$_GET['api_buscar_producto']);

    if ($busqueda === '') {
        echo json_encode(["encontrado" => false]);
        exit();
    }

    $patron = '%' . $busqueda . '%';
    $stmt = $conexion->prepare(
        "SELECT id_producto, nom_produc, precio, stock
         FROM producto
         WHERE id_producto LIKE ? OR nom_produc LIKE ?
         ORDER BY nom_produc ASC
         LIMIT 1"
    );
    $stmt->bind_param("ss", $patron, $patron);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $row = $res->fetch_assoc()) {
        echo json_encode([
            "encontrado" => true,
            "codigo"     => $row['id_producto'],
            "nombre"     => $row['nom_produc'],
            "precio"     => (float)$row['precio'],
            "stock"      => (int)$row['stock']
        ]);
    } else {
        echo json_encode(["encontrado" => false]);
    }
    $stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISTEMA DE CONTROL Y REGISTRO - PINTURAS TAPIR</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
        .header-bar { background-color: #1a365d; color: #ffffff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0d6efd; }
        .main-layout { display: flex; flex: 1; }
        .sidebar { width: 230px; background-color: #ffffff; padding: 20px 12px; border-right: 1px solid #e0e0e0; flex-shrink: 0; }
        .sidebar .nav-link { color: #495057; font-weight: 600; padding: 10px 14px; border-radius: 6px; margin-bottom: 6px; text-decoration: none; display: block; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #1a365d; color: #ffffff !important; }
        .content-area { flex: 1; padding: 25px; }
        .footer-bar { background-color: #1a365d; color: #ffffff; text-align: center; padding: 10px; font-size: 0.85rem; margin-top: auto; }
        .pos-card { border-radius: 8px; border: 1px solid #dee2e6; }
        .badge-cajero { background-color: #6c757d; font-size: 0.85rem; padding: 6px 12px; border-radius: 4px; }
    </style>
</head>
<body>

    <!-- Encabezado -->
    <?php include("encabezado.php"); ?>

    <div class="main-layout">
        <!-- Menú de navegación dinámico según rol -->
        <?php include("menu.php"); ?>

        <!-- Contenido modular dinámico -->
        <div class="content-area">
            <?php
            switch ($modulo) {
                case 'ventas':
                    include("historialventas/ventas.php");
                    break;
                case 'whatsapp':
                    include("conexiónWhatsApp/WhatsApp.html");
                    break;
                case 'inventario':
                    include("inventario/inventario.php");
                    break;
                case 'historial':
                    include("historialventas/historialventa.php");
                    break;
                case 'reportes':
                    include("reporteconta/reporte.php");
                    break;
                case 'configuracion':
                    include("configuracion/configuraciones.php");
                    break;
                case 'inicio':
                default:
                    include("pantalla_inicio/inicio.php");
                    break;
            }
            ?>
        </div>
    </div>

    <!-- Pie de página -->
    <div class="footer-bar">
        © 2026 - Sistema de Información | WEBE -- Instituto Tecnológico de Tizimín
    </div>

    <div
        id="app-config"
        data-rol="<?php echo htmlspecialchars($rol_actual, ENT_QUOTES, 'UTF-8'); ?>"
        data-usuario="<?php echo htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8'); ?>"
        data-id-empleado="<?php echo htmlspecialchars($id_empleado_sesion, ENT_QUOTES, 'UTF-8'); ?>"
        data-puede-ajustar="<?php echo $puede_ajustar_precio ? '1' : '0'; ?>"
        class="d-none"
    ></div>

    <!-- Script general -->
    <!-- Cambia esto al final de menu/menuinicio.php -->
<script src="../js/script.js?v=2"></script>
</body>
</html>