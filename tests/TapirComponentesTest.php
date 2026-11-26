<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/TapirComponentes.php';

class TapirComponentesTest extends TestCase
{
    private TapirComponentes $comp;

    protected function setUp(): void
    {
        $this->comp = new TapirComponentes();
    }

    public function testPermisosPorRol(): void
    {
        $this->assertTrue($this->comp->tienePermisoAccion('Vendedor Pro', 'descuento_libre'));
        $this->assertFalse($this->comp->tienePermisoAccion('Vendedor', 'descuento_libre'));
    }

    public function testCalcularUtilidad(): void
    {
        $this->assertSame(30.00, $this->comp->calcularUtilidad(40.00, 70.00));
    }

    public function testDescuentoConsideracionVendedorPro(): void
    {
        $this->assertSame(90.00, $this->comp->aplicarDescuentoConsideracion('Vendedor Pro', 100.00, 10));
        $this->assertSame(100.00, $this->comp->aplicarDescuentoConsideracion('Vendedor', 100.00, 10));
    }

    public function testPrecioFinalSinYConIva(): void
    {
        $this->assertSame(100.00, $this->comp->calcularPrecioFinal(100.00));
        $this->assertSame(116.00, $this->comp->calcularPrecioFinal(100.00, true));
    }

    public function testFlujoNetoEfectivo(): void
    {
        $this->assertSame(9900.00, $this->comp->calcularFlujoNeto(27800, 15900, -2000));
    }
}