<?php

class TapirComponentes
{
    // Permisos por rol 
    public function tienePermisoAccion(string $rol, string $accion): bool
    {
        $permisos = [
            'Admin' => ['dashboard', 'inventario', 'ventas', 'descuento_libre', 'reportes', 'configuracion'],
            'Vendedor Pro' => ['dashboard', 'ventas', 'descuento_libre'],
            'Vendedor' => ['dashboard', 'ventas'],
            'Contabilidad' => ['dashboard', 'reportes'],
        ];
        return in_array($accion, $permisos[$rol] ?? [], true);
    }

    // Para calcular la Utilidad / ganancia al registrar producto Precio venta - Precio compra)
    public function calcularUtilidad(float $precioCompra, float $precioVenta): float
    {
        return round($precioVenta - $precioCompra, 2);
    }

    // Para descuento a consideración solo Vendedor Pro o Admin 
    public function aplicarDescuentoConsideracion(string $rol, float $precio, float $porcentajeDescuento): float
    {
        if (!$this->tienePermisoAccion($rol, 'descuento_libre')) {
            return round($precio, 2);
        }
        $porcentajeDescuento = max(0.0, min(50.0, $porcentajeDescuento));
        return round($precio * (1 - ($porcentajeDescuento / 100)), 2);
    }

    // Precio final al cliente por defecto NO aplica IVA
    public function calcularPrecioFinal(float $precioBase, bool $aplicaIva = false, float $iva = 0.16): float
    {
        if ($precioBase <= 0) {
            return 0.0;
        }
        if (!$aplicaIva) {
            return round($precioBase, 2);
        }
        return round($precioBase * (1 + $iva), 2);
    }

    // Flujo neto del periodo Cobros + otros ingresos - Pagos/salidas
    public function calcularFlujoNeto(float $cobros, float $pagos, float $inversion = 0.0): float
    {
        return round(($cobros - $pagos) + $inversion, 2);
    }
}