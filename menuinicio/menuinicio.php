<?php
session_start();
require_once("../conexion.php");

if (!isset($_SESSION['rol'])) {
    header("Location: ../login.php");
    exit();
}

$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['usuario_nombre'] ?? $rol;

$permisos_por_rol = [
    'Vendedor'     => ['inicio', 'ventas'],
    'Vendedor Pro' => ['inicio', 'ventas', 'whatsapp', 'inventario', 'historial'],
    'Contabilidad' => ['inicio', 'historial', 'reportes'],
    'Admin'        => ['inicio', 'ventas', 'whatsapp', 'inventario', 'historial', 'reportes', 'configuracion'],
];

$modulos_permitidos = $permisos_por_rol[$rol] ?? ['inicio', 'ventas'];
$modulo_solicitado = $_GET['modulo'] ?? 'inicio';
$modulo = in_array($modulo_solicitado, $modulos_permitidos, true) ? $modulo_solicitado : 'inicio';

// Leer los datos JSON enviados por fetch()
$input_raw = file_get_contents("php://input");
$datos_json = json_decode($input_raw, true);

// API para procesar venta y restar stock de forma segura
if (is_array($datos_json) && isset($datos_json['accion_procesar_venta']) && $datos_json['accion_procesar_venta'] === true) {
    header('Content-Type: application/json; charset=utf-8');

    if (!in_array($rol, ['Vendedor', 'Vendedor Pro', 'Admin'], true)) {
        http_response_code(403);
        echo json_encode(["exito" => false, "mensaje" => "Sin permisos para registrar ventas."]);
        exit();
    }

    if (!empty($datos_json['carrito']) && is_array($datos_json['carrito'])) {
        $conexion->begin_transaction();
        try {
            $stmt = $conexion->prepare(
                "UPDATE producto SET stock = stock - ? WHERE nom_produc LIKE ? AND stock >= ?"
            );

            foreach ($datos_json['carrito'] as $item) {
                $nombre_limpio = trim(strip_tags((string)($item['nombre'] ?? '')));
                $cantidad_vendida = (int)($item['cantidad'] ?? 0);

                if ($nombre_limpio === '' || $cantidad_vendida <= 0) {
                    throw new Exception("Datos de producto inválidos en el carrito.");
                }

                $patron_nombre = '%' . $nombre_limpio . '%';
                $stmt->bind_param("isi", $cantidad_vendida, $patron_nombre, $cantidad_vendida);
                $stmt->execute();

                if ($stmt->affected_rows === 0) {
                    throw new Exception("Stock insuficiente o producto no encontrado: " . $nombre_limpio);
                }
            }

            $stmt->close();
            $conexion->commit();
            echo json_encode(["exito" => true]);
        } catch (Throwable $e) {
            $conexion->rollback();
            echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["exito" => false, "mensaje" => "Carrito vacío"]);
    }
    exit();
}

// API de búsqueda flexible de productos (preparada y limitada para 500+ registros)
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
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
        <!-- Menú de navegación -->
        <?php include("menu.php"); ?>

        <!-- Contenido modular dinámico -->
        <div class="content-area">
            <?php
            switch ($modulo) {
                case 'ventas':
                    include("ventas.php");
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

    <!-- Script general -->
    <script src="script.js"></script>
</body>
</html>
