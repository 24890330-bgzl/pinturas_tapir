<?php
// Incluir la conexión a la base de datos
require_once __DIR__ . '/../../../conexion.php';

// Nombre del archivo a descargar con la fecha y hora actual
$nombre_archivo = 'respaldo_bd_' . date('Y-m-d_H-i-s') . '.sql';

// Configurar encabezados HTTP para forzar la descarga del archivo SQL
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

// Encabezado del archivo SQL
echo "-- Respaldo de Base de Datos\n";
echo "-- Generado el: " . date('Y-m-d H:i:s') . "\n\n";

// Obtener y validar todas las tablas reales de la base de datos
$tablas_permitidas = [];
$result = $conexion->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_row()) {
        $tablas_permitidas[] = $row[0];
    }
    $result->free();
}

// Recorrer cada tabla validada para extraer su estructura y sus datos
foreach ($tablas_permitidas as $tabla) {
    if (!in_array($tabla, $tablas_permitidas, true)) {
        continue;
    }

    $tabla_segura = '`' . str_replace('`', '``', $tabla) . '`';

    // 1. Estructura de la tabla (CREATE TABLE)
    $create_table = $conexion->query("SHOW CREATE TABLE {$tabla_segura}");
    if ($create_table) {
        $row_create = $create_table->fetch_row();
        $create_table->free();

        echo "\n\n-- Estructura de la tabla {$tabla_segura} --\n\n";
        echo "DROP TABLE IF EXISTS {$tabla_segura};\n";
        echo ($row_create[1] ?? '') . ";\n\n";
    }

    // 2. Datos de la tabla (INSERT INTO) usando lectura sin buffer para 500+ registros
    $datos = $conexion->query("SELECT * FROM {$tabla_segura}", MYSQLI_USE_RESULT);
    if ($datos) {
        $num_campos = $datos->field_count;

        while ($row = $datos->fetch_row()) {
            echo "INSERT INTO {$tabla_segura} VALUES(";
            for ($j = 0; $j < $num_campos; $j++) {
                if ($row[$j] !== null) {
                    $val = $conexion->real_escape_string((string) $row[$j]);
                    echo '"' . $val . '"';
                } else {
                    echo 'NULL';
                }
                if ($j < ($num_campos - 1)) {
                    echo ',';
                }
            }
            echo ");\n";
        }
        $datos->free();
    }
}

exit;