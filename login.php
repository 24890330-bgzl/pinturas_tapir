<?php
session_start();

$errorLogin = '';
$usuarioSeleccionado = 'Vendedor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioSeleccionado = $_POST['usuario'] ?? '';
    $passwordIngresada   = $_POST['password'] ?? '';

    // Configuración de usuarios permitidos y sus claves exactas
    $usuariosPermitidos = [
        'Vendedor'     => ['nombre' => 'Wendy Araceli', 'clave' => 'vend1234'],
        'Vendedor Pro' => ['nombre' => 'Vendedor Pro',  'clave' => 'vendpro8'],
        'Contabilidad' => ['nombre' => 'Contabilidad',  'clave' => 'conta123'],
        'Admin'        => ['nombre' => 'Administrador', 'clave' => 'admin123'],
    ];

    if (isset($usuariosPermitidos[$usuarioSeleccionado])) {
        $hashValido = password_hash($usuariosPermitidos[$usuarioSeleccionado]['clave'], PASSWORD_DEFAULT);

        if (password_verify($passwordIngresada, $hashValido)) {
            session_regenerate_id(true);
            $_SESSION['rol']            = $usuarioSeleccionado;
            $_SESSION['usuario_nombre'] = $usuariosPermitidos[$usuarioSeleccionado]['nombre'];

            header('Location: menu/menuinicio.php?modulo=inicio');
            exit();
        }
    }

    $errorLogin = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Pinturas Tapir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: Arial, Helvetica, sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        .card-login {
            width: 480px;
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            padding: 35px;
        }

        .logo-login {
            max-height: 85px;
            width: auto;
            object-fit: contain;
            margin-bottom: 15px;
        }

        .btn-custom-blue {
            background-color: #0d6efd;
            color: #ffffff;
            font-weight: bold;
            padding: 10px;
            font-size: 1rem;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        .btn-custom-blue:hover {
            background-color: #0b5ed7;
            color: #ffffff;
        }
    </style>
</head>
<body>

    <div class="card-login text-center">
        <img src="imagenes/logo.jpg" alt="Pinturas Tapir Logo" class="logo-login">

        <h3 class="text-danger fw-bold mb-1" style="letter-spacing: 0.5px;">PINTURAS TAPIR</h3>
        <h5 class="text-secondary mb-4 fs-6">Iniciar sesión</h5>

        <?php if ($errorLogin !== ''): ?>
            <div class="alert alert-danger py-2 text-start small" role="alert">
                <?php echo htmlspecialchars($errorLogin, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="text-start">
            <div class="mb-3">
                <label for="usuario" class="form-label fw-bold text-dark small">Usuario</label>
                <select id="usuario" name="usuario" class="form-select form-select-lg fs-6" required>
                    <option value="Vendedor" <?php echo $usuarioSeleccionado === 'Vendedor' ? 'selected' : ''; ?>>Vendedor</option>
                    <option value="Vendedor Pro" <?php echo $usuarioSeleccionado === 'Vendedor Pro' ? 'selected' : ''; ?>>Vendedor Pro</option>
                    <option value="Contabilidad" <?php echo $usuarioSeleccionado === 'Contabilidad' ? 'selected' : ''; ?>>Contabilidad</option>
                    <option value="Admin" <?php echo $usuarioSeleccionado === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-bold text-dark small">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control form-control-lg fs-6" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-custom-blue w-100 border-0">Entrar</button>
        </form>
    </div>

</body>
</html>
