<?php
$usuario_actual = $_SESSION['usuario'] ?? null;
$real_user = $_SESSION['usuario_real'] ?? $usuario_actual;
$notificaciones_usuario = [];
$cant_notif_no_leidas = 0;

if ($usuario_actual && $conn) {
    $uid = $usuario_actual['id_usuario'];
    $urol = $usuario_actual['id_rol'];
    $uuf = $usuario_actual['unidad_funcional'];

    $res_n = $conn->query("SELECT * FROM notificaciones WHERE id_usuario = $uid ORDER BY fecha DESC LIMIT 10");
    if ($res_n) {
        while ($row = $res_n->fetch_assoc()) {
            $notificaciones_usuario[] = $row;
            if (!$row['leida']) $cant_notif_no_leidas++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiConsorcio - Gestor de Mantenimiento Edilicio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .card-header { background-color: #ffffff; border-bottom: 1px solid #eef2f5; font-weight: 600; border-radius: 12px 12px 0 0 !important; padding: 16px 20px; }
        .badge-uf { font-size: 0.85rem; padding: 6px 10px; border-radius: 20px; }
        .rating-stars { color: #ffc107; }
        .btn-rounded { border-radius: 20px; }
    </style>
</head>
<body>

<!-- BARRA DE NAVEGACIÓN PRINCIPAL -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="#">
            <i class="fa-solid fa-building-user fa-lg me-2"></i> MiConsorcio
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link active" href="#reclamos"><i class="fa-solid fa-list-check me-1"></i> Reclamos</a></li>
                <li class="nav-item"><a class="nav-link" href="#presupuestos"><i class="fa-solid fa-calculator me-1"></i> Presupuestos</a></li>
                <li class="nav-item"><a class="nav-link" href="#agenda"><i class="fa-solid fa-calendar-days me-1"></i> Agenda Visitas</a></li>
                <li class="nav-item"><a class="nav-link" href="#bitacora"><i class="fa-solid fa-clock-rotate-left me-1"></i> Historial/Bitácora</a></li>
                <li class="nav-item"><a class="nav-link" href="#comunicados"><i class="fa-solid fa-bullhorn me-1"></i> Comunicados</a></li>
                
                <?php if ($usuario_actual['id_rol'] == 1 || $usuario_actual['id_rol'] == 5): ?>
                    <li class="nav-item"><a class="nav-link text-warning fw-bold" href="#usuarios"><i class="fa-solid fa-users-gear me-1"></i> ABM Usuarios</a></li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center text-white gap-3">
                <!-- MENU NOTIFICACIONES DESPLEGABLE -->
                <div class="dropdown">
                    <button class="btn btn-outline-light position-relative btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($cant_notif_no_leidas > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $cant_notif_no_leidas; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3 shadow-lg border-0 rounded-3" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                            <h6 class="fw-bold mb-0 text-dark">Notificaciones</h6>
                            <form method="POST" action="index.php" class="d-inline">
                                <input type="hidden" name="accion" value="limpiar_notificaciones">
                                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Marcar leídas</button>
                            </form>
                        </div>
                        <?php if (empty($notificaciones_usuario)): ?>
                            <p class="text-muted small mb-0">Sin notificaciones pendientes.</p>
                        <?php else: ?>
                            <?php foreach ($notificaciones_usuario as $n): ?>
                                <div class="border-bottom py-2">
                                    <div class="fw-bold small text-primary"><?php echo htmlspecialchars($n['titulo']); ?></div>
                                    <div class="small text-dark"><?php echo htmlspecialchars($n['mensaje']); ?></div>
                                    <div class="text-muted" style="font-size:0.75rem;"><?php echo $n['fecha']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- DATOS DEL USUARIO -->
                <div class="text-end">
                    <div class="fw-bold mb-0 small"><?php echo htmlspecialchars($usuario_actual['nombre']); ?></div>
                    <span class="badge bg-light text-dark badge-uf">
                        <?php echo htmlspecialchars($usuario_actual['nombre_rol']); ?>
                        <?php if ($usuario_actual['unidad_funcional']): ?>
                            | UF: <?php echo htmlspecialchars($usuario_actual['unidad_funcional']); ?>
                        <?php endif; ?>
                    </span>
                </div>

                <form method="POST" action="index.php" class="mb-0">
                    <input type="hidden" name="accion" value="logout">
                    <button type="submit" class="btn btn-outline-light btn-sm" title="Cerrar Sesión">
                        <i class="fa-solid fa-power-off"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<!-- SELECTOR DE SIMULACIÓN SYSADMIN -->
<?php if ($real_user['id_rol'] == 5): ?>
    <div class="bg-dark text-white py-2 shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <span class="small text-warning fw-bold"><i class="fa-solid fa-flask-vial me-1"></i> MODO ADMINISTRADOR DE SISTEMA (Simulador de Perfiles)</span>
            <form method="POST" action="index.php" class="d-flex align-items-center gap-2">
                <input type="hidden" name="accion" value="cambiar_rol_demo">
                <select name="nuevo_rol_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="5" <?php if ($usuario_actual['id_rol'] == 5) echo 'selected'; ?>>Ver como: Admin de Sistema (Global)</option>
                    <option value="1" <?php if ($usuario_actual['id_rol'] == 1) echo 'selected'; ?>>Ver como: Admin de Consorcio</option>
                    <option value="2" <?php if ($usuario_actual['id_rol'] == 2) echo 'selected'; ?>>Ver como: Propietario (3°A)</option>
                    <option value="3" <?php if ($usuario_actual['id_rol'] == 3) echo 'selected'; ?>>Ver como: Inquilino (3°A)</option>
                    <option value="4" <?php if ($usuario_actual['id_rol'] == 4) echo 'selected'; ?>>Ver como: Proveedor Plomero</option>
                </select>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="container py-4">

    <!-- MENSAJES FEEDBACK -->
    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo $mensaje_exito; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($mensaje_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $mensaje_error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
