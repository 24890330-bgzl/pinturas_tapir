<?php
$rol_actual = $_SESSION['rol'] ?? 'Vendedor';
$modulo_actual = $modulo ?? 'inicio';

$permisos_menu = [
    'Vendedor'     => ['inicio', 'ventas'],
    'Vendedor Pro' => ['inicio', 'ventas', 'whatsapp', 'inventario', 'historial'],
    'Contabilidad' => ['inicio', 'historial', 'reportes'],
    'Admin'        => ['inicio', 'ventas', 'whatsapp', 'inventario', 'historial', 'reportes', 'configuracion'],
];

$permitidos_actual = $permisos_menu[$rol_actual] ?? ['inicio', 'ventas'];

$enlaces_menu = [
    'inicio'        => 'Inicio',
    'ventas'        => 'Iniciar Ventas',
    'whatsapp'      => 'Pedidos WhatsApp',
    'inventario'    => 'Inventario',
    'historial'     => 'Historial de Ventas',
    'reportes'      => 'Reportes Contabilidad',
    'configuracion' => 'Configuración',
];
?>
<div class="sidebar shadow-sm">
    <h6 class="fw-bold text-uppercase text-muted px-2 mb-3 small">Navegación</h6>
    <div class="nav flex-column">
        <?php foreach ($enlaces_menu as $clave_modulo => $etiqueta): ?>
            <?php $activo = ($modulo_actual === $clave_modulo) ? 'active' : ''; ?>
            <?php if (in_array($clave_modulo, $permitidos_actual, true)): ?>
                <a href="menuinicio.php?modulo=<?php echo urlencode($clave_modulo); ?>" class="nav-link <?php echo $activo; ?>">
                    <?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php else: ?>
                <a href="#" class="nav-link text-muted" onclick="alertaPermisos(<?php echo json_encode($etiqueta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>); return false;">
                    <?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <a href="../login.php" class="nav-link text-danger mt-4">Cerrar Sesión</a>
    </div>
</div>

<script>
function alertaPermisos(moduloNombre) {
    const rolActual = <?php echo json_encode($rol_actual, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    alert("Usuario: " + rolActual + "\nEl módulo \"" + moduloNombre + "\" requiere permisos adicionales para continuar.");
}
</script>