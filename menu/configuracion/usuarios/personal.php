<?php
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_empleado'])) {
    $id_empleado  = trim($_POST['id_empleado'] ?? '');
    $nombre       = trim($_POST['nombre'] ?? '');
    $telefono     = trim($_POST['telefono'] ?? '');
    $correo       = trim($_POST['correo'] ?? '');
    $contrasena   = password_hash($_POST['contrasena'] ?? '', PASSWORD_DEFAULT);
    $rol          = trim($_POST['rol'] ?? '');
    $genero       = trim($_POST['genero'] ?? '');
    $permisos     = isset($_POST['permisos']) && is_array($_POST['permisos']) ? implode(', ', $_POST['permisos']) : '';
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $hora_comida  = trim($_POST['hora_comida'] ?? '');
    $direccion    = trim($_POST['direccion'] ?? '');
    $edad         = trim($_POST['edad'] ?? '');
    
    $foto_empleado = '';
    if (isset($_FILES['foto_empleado']) && $_FILES['foto_empleado']['error'] === UPLOAD_ERR_OK) {
        $nombreFoto = time() . '_' . basename($_FILES['foto_empleado']['name']);
        $rutaDestino = '../uploads/' . $nombreFoto;
        if (!is_dir('../uploads')) {
            mkdir('../uploads', 0755, true);
        }
        if (move_uploaded_file($_FILES['foto_empleado']['tmp_name'], $rutaDestino)) {
            $foto_empleado = $nombreFoto;
        }
    }

    $stmt = $conexion->prepare(
        "INSERT INTO empleado (id_empleado, nombre, telefono, correo, contrasena, rol, genero, permisos, fecha_inicio, hora_comida, direccion, foto_empleado, edad) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "sssssssssssss",
        $id_empleado,
        $nombre,
        $telefono,
        $correo,
        $contrasena,
        $rol,
        $genero,
        $permisos,
        $fecha_inicio,
        $hora_comida,
        $direccion,
        $foto_empleado,
        $edad
    );

    if ($stmt->execute()) {
        $mensaje = "<div class='alert alert-success alert-dismissible fade show w-100' role='alert'>
                        Empleado registrado correctamente.
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Cerrar'></button>
                    </div>";
    } else {
        $mensaje = "<div class='alert alert-danger alert-dismissible fade show w-100' role='alert'>
                        Error al guardar: " . htmlspecialchars($stmt->error) . "
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Cerrar'></button>
                    </div>";
    }
    $stmt->close();
}
?>

<div class="w-100 bg-white p-2 rounded text-start">
    <?php echo $mensaje; ?>

    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <div>
            <h5 class="fw-bold mb-0">Registro de Personal</h5>
            <small class="text-muted">Complete los datos correspondientes</small>
        </div>
        <a href="menuinicio.php?modulo=configuracion&tab=usuarios&submodulo=menu" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>

    <form action="menuinicio.php?modulo=configuracion&tab=usuarios&submodulo=personal" method="POST" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="guardar_empleado" value="1">

        <div class="col-md-4">
            <label for="id_empleado" class="form-label small fw-bold">ID Empleado: *</label>
            <input type="text" id="id_empleado" name="id_empleado" class="form-control form-control-sm" placeholder="Ej. X01" required>
        </div>
        <div class="col-md-4">
            <label for="nombre" class="form-label small fw-bold">Nombre Completo: *</label>
            <input type="text" id="nombre" name="nombre" class="form-control form-control-sm" placeholder="Ej. Bethsi Jazmin" required>
        </div>
        <div class="col-md-4">
            <label for="telefono" class="form-label small fw-bold">Teléfono: *</label>
            <input type="tel" id="telefono" name="telefono" class="form-control form-control-sm" placeholder="Ej. 9991234567" required>
        </div>
        <div class="col-md-4">
            <label for="correo" class="form-label small fw-bold">Correo Electrónico: *</label>
            <input type="email" id="correo" name="correo" class="form-control form-control-sm" placeholder="usuario@dominio.com" required>
        </div>
        <div class="col-md-4">
            <label for="contrasena" class="form-label small fw-bold">Contraseña: *</label>
            <input type="password" id="contrasena" name="contrasena" class="form-control form-control-sm" placeholder="Mínimo 8 caracteres" required>
        </div>
        <div class="col-md-4">
            <label for="rol" class="form-label small fw-bold">Rol: *</label>
            <select id="rol" name="rol" class="form-select form-select-sm" required>
                <option value="" disabled selected>-- Seleccione --</option>
                <option value="Admin">Admin</option>
                <option value="Vendedor">Vendedor</option>
                <option value="Vendedor">Vendedor Pro</option>
                <option value="Vendedor">Bodeguero</option>
                <option value="Vendedor">Repartidor</option>
                <option value="Vendedor">Contador</option>
            </select>
        </div>
        <div class="col-md-4">
            <span class="form-label small fw-bold d-block">Género: *</span>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="genero_m" name="genero" value="Masculino" required>
                <label class="form-check-label small" for="genero_m">M</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="genero_f" name="genero" value="Femenino">
                <label class="form-check-label small" for="genero_f">F</label>
            </div>
        </div>
        <div class="col-md-8">
            <span class="form-label small fw-bold d-block">Permisos Especiales:</span>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="permiso_caja" name="permisos[]" value="Caja">
                <label class="form-check-label small" for="permiso_caja">Acceso a Caja</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="permiso_inventario" name="permisos[]" value="Inventario">
                <label class="form-check-label small" for="permiso_inventario">Modificar Inventario</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="permiso_reportes" name="permisos[]" value="Reportes">
                <label class="form-check-label small" for="permiso_reportes">Exportar Reportes</label>
            </div>
        </div>
        <div class="form-group">
            <label for="fecha_inicio">Fecha Nacimiento: *</label>
            <input 
                type="date" 
                id="fecha_inicio" 
                name="fecha_inicio" 
                onchange="calcularEdadAutomatica()" 
                required
            >
        </div>
        <div class="col-md-4">
            <label for="hora_comida" class="form-label small fw-bold">Hora de Comida: *</label>
            <input type="time" id="hora_comida" name="hora_comida" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-4">
            <label for="foto_empleado" class="form-label small fw-bold">Foto / Documento: *</label>
            <input type="file" id="foto_empleado" name="foto_empleado" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-12">
            <label for="direccion" class="form-label small fw-bold">Dirección Domiciliaria: *</label>
            <textarea id="direccion" name="direccion" class="form-control form-control-sm" rows="2" placeholder="Ingrese la dirección..." required></textarea>
        </div>
        <div class="form-group">
            <label for="edad">Edad:</label>
            <input 
                type="text" 
                id="edad" 
                name="edad" 
                placeholder="--" 
                readonly
            >
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
            <button type="submit" class="btn btn-success btn-sm px-4">Registrar Empleado</button>
            <button type="reset" class="btn btn-warning btn-sm text-white px-3">Limpiar Formulario</button>
            <a href="menuinicio.php?modulo=configuracion&tab=usuarios&submodulo=menu" class="btn btn-secondary btn-sm px-3">Cancelar</a>
        </div>
    </form>
</div>

<script>
function calcularEdadAutomatica() {         
  const fechaNacimientoInput = document.getElementById('fecha_inicio');
  const edadInput = document.getElementById('edad');

  if (!fechaNacimientoInput.value) {
    edadInput.value = '';
    return;
  }

  const hoy = new Date();
  const fechaNac = new Date(fechaNacimientoInput.value);

  let edad = hoy.getFullYear() - fechaNac.getFullYear();
  const diferenciaMeses = hoy.getMonth() - fechaNac.getMonth();

  if (diferenciaMeses < 0 || (diferenciaMeses === 0 && hoy.getDate() < fechaNac.getDate())) {
    edad--;
  }

  if (edad < 0 || Number.isNaN(edad)) {
    edadInput.value = 'Fecha inválida';
  } else {
    edadInput.value = edad;
  }
}
</script>
