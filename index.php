<?php
session_start();
require_once 'conexion.php';
require_once 'acciones.php';

$usuario_actual = $_SESSION['usuario'] ?? null;

if (!$usuario_actual) {
    $mensaje_error = $_SESSION['mensaje_error'] ?? '';
    unset($_SESSION['mensaje_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiConsorcio - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="container" style="max-width: 420px;">
        <div class="card p-4 shadow-lg border-0 rounded-4">
            <div class="text-center mb-4">
                <i class="fa-solid fa-building-user text-primary display-4 mb-2"></i>
                <h3 class="fw-bold text-dark">MiConsorcio</h3>
                <p class="text-muted small">Sistema Gestor de Mantenimiento Edilicio</p>
            </div>

            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?php echo htmlspecialchars($mensaje_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <input type="hidden" name="accion" value="login">
                <div class="mb-3">
                    <label class="form-label fw-bold">Correo Electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="ejemplo@consorcio.com" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
    exit();
}

require_once 'header.php';
require_once 'reclamos.php';
require_once 'presupuestos.php';
require_once 'visitas.php';
require_once 'bitacora.php';
require_once 'comunicados.php';
require_once 'usuarios.php';
require_once 'footer.php';
?>