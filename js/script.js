let productoActual = null;
let carrito = [];
let tipoComprobanteActual = "ticket";

function escaparHTML(texto) {
    return String(texto ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

function obtenerConfigApp() {
    const cfg = document.getElementById("app-config");
    const rol = cfg ? (cfg.dataset.rol || "Vendedor") : "Vendedor";
    const usuario = cfg ? (cfg.dataset.usuario || rol) : rol;
    const puedeAjustar = cfg
        ? (cfg.dataset.puedeAjustar === "1" && (rol === "Vendedor Pro" || rol === "Admin"))
        : false;
    return { rol, usuario, puedeAjustar };
}

// Búsqueda de productos en la base de datos
function buscarProductoBD() {
    const inputBusqueda = document.getElementById("codigo_prod").value.trim();
    if (!inputBusqueda) {
        alert("Ingrese un código o nombre de producto.");
        return;
    }

    fetch(`menuinicio.php?api_buscar_producto=${encodeURIComponent(inputBusqueda)}`)
        .then(response => response.json())
        .then(data => {
            if (data.encontrado) {
                const precioNumerico = Number.parseFloat(data.precio);
                const stockNumerico = Number.parseInt(data.stock, 10);

                productoActual = {
                    codigo: data.codigo || "",
                    nombre: data.nombre,
                    precio: Number.isNaN(precioNumerico) ? 0 : precioNumerico,
                    stock: Number.isNaN(stockNumerico) ? 0 : stockNumerico
                };

                document.getElementById("lbl_nombre").innerText = productoActual.nombre;
                document.getElementById("lbl_precio").innerText = productoActual.precio.toFixed(2);
                document.getElementById("lbl_stock").innerText = productoActual.stock;
                document.getElementById("info_producto_encontrado").classList.remove("d-none");
            } else {
                alert("Producto no encontrado en la base de datos.");
                document.getElementById("info_producto_encontrado").classList.add("d-none");
                productoActual = null;
            }
        })
        .catch(err => console.error("Error al buscar:", err));
}

function seleccionarRapido(nombre, precio, codigo = "") {
    const precioConvertido = Number.parseFloat(precio);
    productoActual = {
        codigo: codigo || "",
        nombre: nombre,
        precio: Number.isNaN(precioConvertido) ? 0 : precioConvertido
    };
    agregarAlCarrito();
}

function agregarAlCarrito() {
    if (!productoActual) {
        alert("Primero busque y seleccione un producto.");
        return;
    }

    const precioBase = Number.parseFloat(productoActual.precio);
    const precioValido = Number.isNaN(precioBase) ? 0 : precioBase;
    const index = carrito.findIndex(p => p.nombre === productoActual.nombre);

    if (index !== -1) {
        carrito[index].cantidad += 1;
    } else {
        carrito.push({
            codigo: productoActual.codigo || "",
            nombre: productoActual.nombre,
            precioOriginal: precioValido,
            precioUnitario: precioValido,
            cantidad: 1
        });
    }

    actualizarTablaCarrito();
    document.getElementById("info_producto_encontrado").classList.add("d-none");
    document.getElementById("codigo_prod").value = "";
    productoActual = null;
}

// Limpiar carrito cuando una venta no se cierra
function limpiarCarrito() {
    if (carrito.length === 0) {
        alert("El carrito ya está vacío.");
        return;
    }
    if (confirm("¿Seguro que deseas vaciar todo el carrito y cancelar esta venta?")) {
        carrito = [];
        productoActual = null;
        const selectCli = document.getElementById("select_cliente");
        if (selectCli) selectCli.value = "CLI-GEN";
        actualizarTablaCarrito();
    }
}

// Solo Vendedor Pro y Admin pueden modificar o descontar el precio unitario
function modificarPrecioProducto(index) {
    const { puedeAjustar, rol } = obtenerConfigApp();
    if (!puedeAjustar) {
        alert(`Rol actual (${rol}): solo Vendedor Pro y Admin pueden modificar o descontar precios.`);
        return;
    }

    const item = carrito[index];
    if (!item) return;

    const respuesta = prompt(
        `Producto: ${item.nombre}\nPrecio original: $${Number.parseFloat(item.precioOriginal).toFixed(2)}\nPrecio actual: $${Number.parseFloat(item.precioUnitario).toFixed(2)}\n\nEscriba el nuevo precio (ej. 150.00) o porcentaje de descuento (ej. 15%):`,
        Number.parseFloat(item.precioUnitario).toFixed(2)
    );

    if (respuesta === null) return;

    const texto = respuesta.trim();
    let nuevoPrecio = Number.NaN;

    if (texto.endsWith("%")) {
        const porcentaje = Number.parseFloat(texto.slice(0, -1));
        if (!Number.isNaN(porcentaje) && porcentaje >= 0 && porcentaje <= 100) {
            nuevoPrecio = Number.parseFloat(item.precioOriginal) * (1 - (porcentaje / 100));
        }
    } else {
        nuevoPrecio = Number.parseFloat(texto);
    }

    if (Number.isNaN(nuevoPrecio) || nuevoPrecio < 0) {
        alert("Ingrese un precio válido mayor o igual a 0, o un porcentaje entre 0% y 100%.");
        return;
    }

    item.precioUnitario = Number(nuevoPrecio.toFixed(2));
    actualizarTablaCarrito();
}

function actualizarTablaCarrito() {
    const tbody = document.getElementById("tabla_carrito");
    if (!tbody) return;

    const { puedeAjustar } = obtenerConfigApp();
    const numCols = puedeAjustar ? 5 : 4;
    tbody.innerHTML = "";
    let totalGeneral = 0;

    if (carrito.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${numCols}" class="text-center text-muted py-4">No hay productos en el carrito</td></tr>`;
        document.getElementById("total_venta").innerText = "0.00";
        return;
    }

    carrito.forEach((prod, index) => {
        const unitario = Number.parseFloat(prod.precioUnitario);
        const original = Number.parseFloat(prod.precioOriginal);
        const cantidad = Number.parseInt(prod.cantidad, 10);
        const importe = unitario * cantidad;
        totalGeneral += importe;

        const huboCambio = unitario !== original;
        const badgeCambio = huboCambio
            ? ` <span class="badge bg-warning text-dark ms-1">Ajustado (orig. $${original.toFixed(2)})</span>`
            : "";

        const celdaAccion = puedeAjustar
            ? `<td class="text-center"><button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="modificarPrecioProducto(${index})">Ajustar</button></td>`
            : "";

        tbody.innerHTML += `
            <tr>
                <td class="text-center fw-bold">${cantidad}</td>
                <td>${escaparHTML(prod.nombre)}${badgeCambio}</td>
                <td>$${unitario.toFixed(2)}</td>
                <td class="fw-bold">$${importe.toFixed(2)}</td>
                ${celdaAccion}
            </tr>
        `;
    });

    document.getElementById("total_venta").innerText = totalGeneral.toFixed(2);
}

// Ventana de Confirmación de Venta
function procesarVenta() {
    if (carrito.length === 0) {
        alert("El carrito está vacío. Agregue productos antes de continuar.");
        return;
    }

    // Validar ID de empleado antes de continuar
    const inputEmp = document.getElementById("id_empleado_venta");
    const idEmpleado = inputEmp ? inputEmp.value.trim().toUpperCase() : "ADMIN";
    if (!idEmpleado) {
        alert("Por favor ingrese su ID de Vendedor en la parte superior antes de continuar.");
        if (inputEmp) inputEmp.focus();
        return;
    }

    const tieneAjustes = carrito.some(
        p => Number.parseFloat(p.precioUnitario) !== Number.parseFloat(p.precioOriginal)
    );
    const totalVenta = document.getElementById("total_venta").innerText;
    const selectMetodo = document.getElementById("metodo_pago");
    const textoMetodo = selectMetodo.options[selectMetodo.selectedIndex].text;

    const selectCliente = document.getElementById("select_cliente");
    const textoCliente = selectCliente ? selectCliente.options[selectCliente.selectedIndex].text : "Público General";

    document.getElementById("modal_lbl_vendedor").innerText = idEmpleado;
    document.getElementById("modal_lbl_cliente").innerText = textoCliente;
    document.getElementById("modal_lbl_tarifa").value = tieneAjustes
        ? "Precio ajustado / con descuento"
        : "Precio de lista";
    document.getElementById("modal_lbl_total").innerText = totalVenta;
    document.getElementById("modal_lbl_metodo").innerText = textoMetodo;

    document.getElementById("modal_confirmacion_venta").style.display = "block";
}

function cancelarVenta() {
    document.getElementById("modal_confirmacion_venta").style.display = "none";
}

function seleccionarComprobante(tipo) {
    tipoComprobanteActual = tipo;
    if (tipo === "ticket") {
        document.getElementById("btn_modo_ticket").className = "btn btn-success btn-sm px-4 rounded-pill fw-bold";
        document.getElementById("btn_modo_factura").className = "btn btn-outline-secondary btn-sm px-4 rounded-pill fw-bold";
    } else {
        document.getElementById("btn_modo_factura").className = "btn btn-success btn-sm px-4 rounded-pill fw-bold";
        document.getElementById("btn_modo_ticket").className = "btn btn-outline-secondary btn-sm px-4 rounded-pill fw-bold";
    }
}

// Al hacer clic en "Confirmar venta": descuenta stock y registra la venta completa
function confirmarYDescontarStock() {
    const totalVenta = document.getElementById("modal_lbl_total").innerText;
    const metodoPagoTexto = document.getElementById("modal_lbl_metodo").innerText;
    const condicionPrecio = document.getElementById("modal_lbl_tarifa").value;

    const idCliente = document.getElementById("select_cliente")?.value || "CLI-GEN";
    const idEmpleado = document.getElementById("id_empleado_venta")?.value.trim().toUpperCase() || "ADMIN";
    const idMetodoPago = document.getElementById("metodo_pago")?.value || 1;

    fetch("menuinicio.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            accion_procesar_venta: true,
            id_cliente: idCliente,
            id_empleado: idEmpleado,
            id_metodop: idMetodoPago,
            carrito: carrito
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.exito) {
                document.getElementById("modal_confirmacion_venta").style.display = "none";
                generarComprobanteImpresion(condicionPrecio, totalVenta, metodoPagoTexto, tipoComprobanteActual, idEmpleado);
            } else {
                alert("Error al actualizar inventario: " + data.mensaje);
            }
        })
        .catch(err => {
            console.error("Error:", err);
            alert("No se pudo confirmar la venta con el servidor. Verifique la conexión.");
        });
}

// Impresión del Ticket con formato estructurado
function generarComprobanteImpresion(condicionPrecio, total, metodo, tipo, idEmpleado = "") {
    const { usuario } = obtenerConfigApp();
    let detalleProductos = "";
    const fechaActual = new Date().toLocaleDateString("es-MX");

    carrito.forEach(prod => {
        const unitario = Number.parseFloat(prod.precioUnitario);
        const cantidad = Number.parseInt(prod.cantidad, 10);
        const subtotal = (unitario * cantidad).toFixed(2);
        detalleProductos += `${cantidad} x ${escaparHTML(prod.nombre)} ($${unitario.toFixed(2)} c/u) ---- $${subtotal}<br>`;
    });

    const tipoSeguro = escaparHTML(String(tipo).toUpperCase());
    const condicionSegura = escaparHTML(condicionPrecio);
    const totalSeguro = escaparHTML(total);
    const metodoSeguro = escaparHTML(metodo);
    const fechaSegura = escaparHTML(fechaActual);
    const atendioTexto = idEmpleado && idEmpleado !== "ADMIN" ? `${usuario} (${idEmpleado})` : usuario;
    const usuarioSeguro = escaparHTML(atendioTexto);

    const ventanaImp = window.open("", "_blank", "width=400,height=650");
    if (!ventanaImp || ventanaImp.closed || typeof ventanaImp.closed === "undefined") {
        alert("Por favor permita las ventanas emergentes (pop-ups) en su navegador para imprimir el ticket.");
    } else {
        ventanaImp.document.write(`
            <html>
            <head>
                <title>Ticket de Venta - Pinturas Tapir</title>
                <style>
                    body {
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 13px;
                        width: 300px;
                        margin: 0 auto;
                        padding: 10px;
                        background: #ffffff;
                        color: #000000;
                    }
                    .center { text-align: center; }
                    .bold { font-weight: bold; }
                    .right { text-align: right; }
                    .line {
                        border-bottom: 1px dashed #000000;
                        margin: 8px 0;
                    }
                    table { width: 100%; font-size: 13px; font-family: 'Courier New', Courier, monospace; }
                </style>
            </head>
            <body>
                <div class="center bold" style="font-size: 15px;">PINTURAS TAPIR</div>
                <div class="center" style="font-size: 11px;">
                    Dirección: C.57 #404, Centro, 97700,<br>Tizimín, Yuc.<br>
                    Contacto: 986 107 8065<br>
                    Sucursal: Centro
                </div>

                <div class="line"></div>

                <div class="center bold">
                    COMPROBANTE DE ${tipoSeguro}
                </div>

                <div class="line"></div>

                <div>
                    <strong>Fecha:</strong> ${fechaSegura}<br>
                    <strong>Atendió:</strong> ${usuarioSeguro}<br>
                    <strong>Precio:</strong> ${condicionSegura}
                </div>

                <div class="line"></div>

                <table>
                    <tr>
                        <td class="bold">Descripción</td>
                        <td class="bold right">Costo</td>
                    </tr>
                </table>

                <div class="line"></div>

                <div style="font-size: 12px; line-height: 1.4;">
                    ${detalleProductos}
                </div>

                <div class="line"></div>

                <div class="bold right" style="font-size: 14px; margin-bottom: 5px;">
                    Total: $${totalSeguro}
                </div>

                <div style="font-size: 12px;">
                    Método: ${metodoSeguro} &nbsp;&nbsp;&nbsp;&nbsp; Pagado:
                </div>
                <div class="bold" style="font-size: 12px; margin-top: 2px;">
                    $${totalSeguro}
                </div>

                <div class="line"></div>

                <div class="center bold" style="margin-top: 10px;">
                    ¡Gracias por su compra!
                </div>

                <script>
                    window.print();
                </script>
            </body>
            </html>
        `);
        ventanaImp.document.close();
    }

    carrito = [];
    actualizarTablaCarrito();
    setTimeout(() => { location.reload(); }, 1000);
}