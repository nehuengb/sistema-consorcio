<?php
$rol = $usuario_actual['id_rol'];
$uid = $usuario_actual['id_usuario'];
$uf = $usuario_actual['unidad_funcional'];

// Auto-actualización a 'Realizada' para visitas pasadas
if ($conn) {
    // 1. Visitas de fechas pasadas
    $conn->query("UPDATE visitas SET estado = 'Realizada' WHERE fecha_visita < CURDATE() AND estado IN ('Programada', 'Aceptada')");
    
    // 2. Visitas de hoy cuya hora de fin ya pasó
    $res_hoy = $conn->query("SELECT id_visita, hora_inicio, hora_fin FROM visitas WHERE fecha_visita = CURDATE() AND estado IN ('Programada', 'Aceptada')");
    if ($res_hoy) {
        $hora_actual = date('H:i');
        while ($vh = $res_hoy->fetch_assoc()) {
            $h_fin = !empty($vh['hora_fin']) ? date('H:i', strtotime($vh['hora_fin'])) : '';
            if (!empty($h_fin) && $hora_actual >= $h_fin) {
                $id_v = intval($vh['id_visita']);
                $conn->query("UPDATE visitas SET estado = 'Realizada' WHERE id_visita = $id_v");
            }
        }
    }
}

// Obtener todas las visitas activas con datos de involucrados (proveedor, residente, UF)
$sql_activas = "SELECT v.id_visita, v.id_usuario as prov_id, r.id_usuario as res_id, r.unidad_funcional, v.fecha_visita, v.hora_inicio, v.hora_fin, v.estado 
                FROM visitas v 
                JOIN reclamos r ON v.id_reclamo = r.id_reclamo 
                WHERE v.estado IN ('Programada', 'Aceptada', 'Realizada')";
$res_act = $conn->query($sql_activas);
$visitas_activas_js = [];
if ($res_act) {
    while ($r_act = $res_act->fetch_assoc()) {
        $visitas_activas_js[] = $r_act;
    }
}
?>
<div id="agenda" class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-calendar-days text-primary me-2"></i> Agenda Interactiva de Visitas</span>
        <?php if ($rol == 4 || $rol == 5): ?>
            <button class="btn btn-primary btn-sm btn-rounded" data-bs-toggle="modal" data-bs-target="#modalAgendarVisita">
                <i class="fa-solid fa-plus me-1"></i> Programar Visita
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php
        if ($rol == 1) {
            $sql_v = "SELECT v.*, r.titulo, r.unidad_funcional, r.tipo, r.id_reclamo, u.nombre as proveedor_nombre FROM visitas v JOIN reclamos r ON v.id_reclamo = r.id_reclamo JOIN usuarios u ON v.id_usuario = u.id_usuario WHERE r.tipo = 'Consorcio' ORDER BY v.fecha_visita ASC, v.hora_inicio ASC";
        } else if ($rol == 2 || $rol == 3) {
            $sql_v = "SELECT v.*, r.titulo, r.unidad_funcional, r.tipo, r.id_reclamo, u.nombre as proveedor_nombre FROM visitas v JOIN reclamos r ON v.id_reclamo = r.id_reclamo JOIN usuarios u ON v.id_usuario = u.id_usuario WHERE r.unidad_funcional = '$uf' AND r.tipo = 'Particular' ORDER BY v.fecha_visita ASC, v.hora_inicio ASC";
        } else if ($rol == 4) {
            $sql_v = "SELECT v.*, r.titulo, r.unidad_funcional, r.tipo, r.id_reclamo, u.nombre as proveedor_nombre FROM visitas v JOIN reclamos r ON v.id_reclamo = r.id_reclamo JOIN usuarios u ON v.id_usuario = u.id_usuario WHERE v.id_usuario = $uid ORDER BY v.fecha_visita ASC, v.hora_inicio ASC";
        } else {
            $sql_v = "SELECT v.*, r.titulo, r.unidad_funcional, r.tipo, r.id_reclamo, u.nombre as proveedor_nombre FROM visitas v JOIN reclamos r ON v.id_reclamo = r.id_reclamo JOIN usuarios u ON v.id_usuario = u.id_usuario ORDER BY v.fecha_visita ASC, v.hora_inicio ASC";
        }
        $res_vis = $conn->query($sql_v);
        ?>
        <div class="row g-3">
            <?php if (!$res_vis || $res_vis->num_rows == 0): ?>
                <div class="col-12 text-center text-muted py-4">
                    <i class="fa-regular fa-calendar-xmark fa-2x mb-2 d-block"></i>
                    No hay visitas técnicas agendadas.
                </div>
            <?php else: ?>
                <?php while ($v = $res_vis->fetch_assoc()): ?>
                    <?php $prom_v = obtenerPromedioProveedor($conn, $v['id_usuario']); ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border p-3 h-100 shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-secondary">
                                    <?php echo ($v['tipo'] === 'Consorcio' || $v['unidad_funcional'] === 'Consorcio') ? 'Consorcio' : 'UF: ' . htmlspecialchars($v['unidad_funcional']); ?>
                                </span>
                                <span class="badge <?php
                                    if ($v['estado'] === 'Aceptada') echo 'bg-success';
                                    else if ($v['estado'] === 'Programada') echo 'bg-warning text-dark';
                                    else if ($v['estado'] === 'Realizada') echo 'bg-info text-dark';
                                    else echo 'bg-danger';
                                ?>"><?php echo htmlspecialchars($v['estado']); ?></span>
                            </div>

                            <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($v['titulo']); ?></h6>
                            <p class="small text-muted flex-grow-1 mb-2"><strong>Motivo:</strong> <?php echo htmlspecialchars($v['motivo']); ?></p>

                            <div class="small text-secondary border-top pt-2 mt-auto mb-2">
                                <i class="fa-solid fa-user-gear me-1"></i> Especialista: <strong><?php echo htmlspecialchars($v['proveedor_nombre']); ?></strong> (<?php echo $prom_v['promedio']; ?> ⭐)<br>
                                <i class="fa-solid fa-calendar me-1"></i> Fecha: <strong><?php echo $v['fecha_visita']; ?></strong><br>
                                <?php 
                                $h_disp = date('H:i', strtotime($v['hora_inicio'])) . ' - ' . date('H:i', strtotime($v['hora_fin'])) . ' hs';
                                ?>
                                <i class="fa-solid fa-clock me-1"></i> Horario: <strong><?php echo $h_disp; ?></strong>
                            </div>

                            <!-- ACCIONES DE VISITA -->
                            <div class="border-top pt-2 mt-auto d-flex flex-wrap gap-1">
                                <!-- 1. RESIDENTE / PROPIETARIO / ADMIN: SOLO ACEPTAR Y RECHAZAR EN VISITAS PROGRAMADAS -->
                                <?php if (($rol == 1 || $rol == 2 || $rol == 3) && $v['estado'] === 'Programada'): ?>
                                    <form method="POST" action="index.php" class="d-flex gap-1 w-100 mb-1">
                                        <input type="hidden" name="accion" value="responder_visita">
                                        <input type="hidden" name="id_visita" value="<?php echo $v['id_visita']; ?>">
                                        <button name="nuevo_estado" value="Aceptada" class="btn btn-success btn-sm w-50 fw-bold"><i class="fa-solid fa-check me-1"></i> Aceptar</button>
                                        <button name="nuevo_estado" value="Rechazada" class="btn btn-outline-danger btn-sm w-50"><i class="fa-solid fa-xmark me-1"></i> Rechazar</button>
                                    </form>
                                <?php endif; ?>

                                <!-- 2. PROVEEDOR / SYSADMIN: REPROGRAMAR Y CANCELAR -->
                                <?php if ($rol == 4 || $rol == 5): ?>
                                    <?php if ($v['estado'] === 'Programada' || $v['estado'] === 'Aceptada'): ?>
                                        <div class="d-flex gap-1 w-100 mb-1">
                                            <button class="btn btn-outline-primary btn-sm w-50" data-bs-toggle="modal" data-bs-target="#modalReprogramar<?php echo $v['id_visita']; ?>">
                                                <i class="fa-solid fa-clock-rotate-left me-1"></i> Reprogramar
                                            </button>
                                            <form method="POST" action="index.php" class="w-50">
                                                <input type="hidden" name="accion" value="responder_visita">
                                                <input type="hidden" name="id_visita" value="<?php echo $v['id_visita']; ?>">
                                                <button name="nuevo_estado" value="Cancelada" class="btn btn-outline-danger btn-sm w-100"><i class="fa-solid fa-ban"></i> Cancelar</button>
                                            </form>
                                        </div>
                                    <?php elseif ($v['estado'] === 'Cancelada' || $v['estado'] === 'Rechazada'): ?>
                                        <button class="btn btn-outline-primary btn-sm w-100 mb-1" data-bs-toggle="modal" data-bs-target="#modalReprogramar<?php echo $v['id_visita']; ?>">
                                            <i class="fa-solid fa-clock-rotate-left me-1"></i> Reprogramar
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- 3. RECLASIFICACIÓN DE RECLAMO EN VISITA REALIZADA (SOLO PROVEEDOR) -->
                                <?php if ($v['estado'] === 'Realizada' && $v['tipo'] === 'Particular' && ($rol == 4 || $rol == 5)): ?>
                                    <div class="bg-light p-2 rounded border w-100 mt-1">
                                        <form method="POST" action="index.php">
                                            <input type="hidden" name="accion" value="reclasificar_reclamo">
                                            <input type="hidden" name="id_reclamo" value="<?php echo $v['id_reclamo']; ?>">
                                            <label class="form-label small fw-bold text-dark mb-1">¿Problemática relacionada con otra unidad?</label>
                                            <div class="input-group input-group-sm">
                                                <select name="es_consorcio" class="form-select form-select-sm">
                                                    <option value="no" selected>No (Mantener Particular)</option>
                                                    <option value="si">Sí (Convertir a Consorcio)</option>
                                                </select>
                                                <button type="submit" class="btn btn-warning btn-sm fw-bold">Guardar</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL REPROGRAMAR VISITA -->
                    <?php if ($rol == 4 || $rol == 5): ?>
                        <div class="modal fade" id="modalReprogramar<?php echo $v['id_visita']; ?>" tabindex="-1" data-id-visita="<?php echo $v['id_visita']; ?>" data-prov-id="<?php echo $v['id_usuario']; ?>">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="index.php">
                                        <input type="hidden" name="accion" value="reprogramar_visita">
                                        <input type="hidden" name="id_visita" value="<?php echo $v['id_visita']; ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Reprogramar Visita Técnica</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Nueva Fecha</label>
                                                <input type="date" name="nueva_fecha" class="form-control input-fecha-visita" min="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label fw-bold">Horario Inicio</label>
                                                    <select name="nueva_hora_inicio" class="form-select select-hora-inicio" required>
                                                        <?php for ($h = 8; $h <= 20; $h++): ?>
                                                            <?php $t1 = sprintf("%02d:00", $h); $t2 = sprintf("%02d:30", $h); ?>
                                                            <option value="<?php echo $t1; ?>"><?php echo $t1; ?> hs</option>
                                                            <?php if ($h < 20): ?><option value="<?php echo $t2; ?>"><?php echo $t2; ?> hs</option><?php endif; ?>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label fw-bold">Horario Fin</label>
                                                    <select name="nueva_hora_fin" class="form-select select-hora-fin" required>
                                                        <?php for ($h = 8; $h <= 20; $h++): ?>
                                                            <?php $t2 = sprintf("%02d:30", $h); $t3 = sprintf("%02d:00", $h + 1); ?>
                                                            <option value="<?php echo $t2; ?>"><?php echo $t2; ?> hs</option>
                                                            <?php if ($h < 20): ?><option value="<?php echo $t3; ?>"><?php echo $t3; ?> hs</option><?php endif; ?>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-check me-1"></i> Confirmar Cambio</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL AGENDAR VISITA (PROVEEDOR) -->
<?php if ($rol == 4 || $rol == 5): ?>
    <div class="modal fade" id="modalAgendarVisita" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="index.php">
                    <input type="hidden" name="accion" value="agendar_visita">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Programar Nueva Visita Técnica</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Seleccionar Reclamo (Solo con Presupuesto Aprobado)</label>
                            <select name="id_reclamo" id="selectReclamoAgendar" class="form-select" required>
                                <?php
                                if ($rol == 5) {
                                    $recs = $conn->query("SELECT DISTINCT r.id_reclamo, r.titulo, r.unidad_funcional, r.id_usuario FROM reclamos r JOIN presupuestos p ON r.id_reclamo = p.id_reclamo WHERE p.estado IN ('Aprobado', 'Aprobado Inquilino') AND r.estado != 'Resuelto'");
                                } else {
                                    $recs = $conn->query("SELECT DISTINCT r.id_reclamo, r.titulo, r.unidad_funcional, r.id_usuario FROM reclamos r JOIN presupuestos p ON r.id_reclamo = p.id_reclamo WHERE p.id_proveedor = $uid AND p.estado IN ('Aprobado', 'Aprobado Inquilino') AND r.estado != 'Resuelto'");
                                }
                                if (!$recs || $recs->num_rows == 0) {
                                    echo "<option value='' disabled>No tiene reclamos con presupuesto aceptado para agendar visitas.</option>";
                                } else {
                                    while ($rc = $recs->fetch_assoc()) {
                                        $amb = ($rc['unidad_funcional'] === 'Consorcio') ? 'Consorcio' : 'UF: ' . $rc['unidad_funcional'];
                                        echo "<option value='{$rc['id_reclamo']}' data-res-id='{$rc['id_usuario']}' data-uf='{$rc['unidad_funcional']}'>#{$rc['id_reclamo']} - {$rc['titulo']} ($amb)</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Motivo de la Visita</label>
                            <input type="text" name="motivo" class="form-control" placeholder="Ej: Inspección técnica y reparación de fuga" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Fecha de Visita</label>
                            <input type="date" name="fecha_visita" class="form-control input-fecha-visita" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">Horario Inicio</label>
                                <select name="hora_inicio" class="form-select select-hora-inicio" required>
                                    <?php for ($h = 8; $h <= 20; $h++): ?>
                                        <?php $t1 = sprintf("%02d:00", $h); $t2 = sprintf("%02d:30", $h); ?>
                                        <option value="<?php echo $t1; ?>"><?php echo $t1; ?> hs</option>
                                        <?php if ($h < 20): ?><option value="<?php echo $t2; ?>"><?php echo $t2; ?> hs</option><?php endif; ?>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Horario Fin</label>
                                <select name="hora_fin" class="form-select select-hora-fin" required>
                                    <?php for ($h = 8; $h <= 20; $h++): ?>
                                        <?php $t2 = sprintf("%02d:30", $h); $t3 = sprintf("%02d:00", $h + 1); ?>
                                        <option value="<?php echo $t2; ?>"><?php echo $t2; ?> hs</option>
                                        <?php if ($h < 20): ?><option value="<?php echo $t3; ?>"><?php echo $t3; ?> hs</option><?php endif; ?>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-check me-1"></i> Agendar Visita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- SCRIPT DE DESHABILITACIÓN DINÁMICA DE HORARIOS EN COLISIÓN -->
<script>
window.visitasActivas = <?php echo json_encode($visitas_activas_js); ?>;
window.currentUserId = <?php echo intval($uid); ?>;

function timeToMin(t) {
    if (!t) return 0;
    var parts = t.trim().split(':');
    if (parts.length >= 2) {
        return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
    }
    return 0;
}

function actualizarColisionesHorarias(modal) {
    var inputFecha = modal.querySelector('.input-fecha-visita') || modal.querySelector('input[name="fecha_visita"]') || modal.querySelector('input[name="nueva_fecha"]');
    var selectInicio = modal.querySelector('.select-hora-inicio') || modal.querySelector('select[name="hora_inicio"]') || modal.querySelector('select[name="nueva_hora_inicio"]');
    var selectFin = modal.querySelector('.select-hora-fin') || modal.querySelector('select[name="hora_fin"]') || modal.querySelector('select[name="nueva_hora_fin"]');
    var selectReclamo = modal.querySelector('select[name="id_reclamo"]');
    var idVisitaActual = modal.getAttribute('data-id-visita') || 0;

    if (!selectInicio || !selectFin) return;
    var fechaSel = inputFecha ? inputFecha.value : '';

    var selectedResId = null;
    var selectedUf = null;
    if (selectReclamo && selectReclamo.selectedIndex >= 0) {
        var optRec = selectReclamo.options[selectReclamo.selectedIndex];
        selectedResId = optRec.getAttribute('data-res-id');
        selectedUf = optRec.getAttribute('data-uf');
    }

    // 1. Resetear opciones
    Array.from(selectInicio.options).forEach(function(opt) {
        opt.disabled = false;
        opt.removeAttribute('disabled');
        opt.text = opt.text.replace(' (Ocupado)', '');
    });
    Array.from(selectFin.options).forEach(function(opt) {
        opt.disabled = false;
        opt.removeAttribute('disabled');
        opt.text = opt.text.replace(' (Ocupado)', '');
    });

    // 2. Filtrar colisiones con visitas confirmadas
    var ocupados = [];
    if (fechaSel && window.visitasActivas) {
        window.visitasActivas.forEach(function(v) {
            if (idVisitaActual && parseInt(v.id_visita, 10) == parseInt(idVisitaActual, 10)) return;
            if (v.fecha_visita === fechaSel) {
                var esAceptada = (v.estado === 'Aceptada' || v.estado === 'Realizada');
                var esMismoProv = (parseInt(v.prov_id, 10) === window.currentUserId);
                var esMismoRes = (selectedResId && parseInt(v.res_id, 10) === parseInt(selectedResId, 10));
                var esMismaUf = (selectedUf && v.unidad_funcional === selectedUf);

                if (esAceptada && (esMismoProv || esMismoRes || esMismaUf)) {
                    var minIn = timeToMin(v.hora_inicio);
                    var minFi = timeToMin(v.hora_fin);
                    if (minIn || minFi) ocupados.push([minIn, minFi]);
                }
            }
        });
    }

    // Inhabilitar inicio si colisiona
    Array.from(selectInicio.options).forEach(function(opt) {
        var m = timeToMin(opt.value);
        var colisiona = false;
        ocupados.forEach(function(rng) {
            if (m >= rng[0] && m < rng[1]) colisiona = true;
        });
        if (colisiona) {
            opt.disabled = true;
            opt.setAttribute('disabled', 'disabled');
            opt.text = opt.text.replace(' (Ocupado)', '') + ' (Ocupado)';
        }
    });

    // 3. REGLA: HORA FIN DEBE SER ESTRICTAMENTE MAYOR A HORA INICIO
    var mInicio = timeToMin(selectInicio.value);

    Array.from(selectFin.options).forEach(function(opt) {
        var mFin = timeToMin(opt.value);
        var baseTxt = opt.text.replace(' (Ocupado)', '');

        var colisiona = false;
        ocupados.forEach(function(rng) {
            if (mFin > rng[0] && mFin <= rng[1]) colisiona = true;
        });

        if (mFin <= mInicio) {
            opt.disabled = true;
            opt.setAttribute('disabled', 'disabled');
            opt.text = baseTxt;
        } else if (colisiona) {
            opt.disabled = true;
            opt.setAttribute('disabled', 'disabled');
            opt.text = baseTxt + ' (Ocupado)';
        } else {
            opt.disabled = false;
            opt.removeAttribute('disabled');
            opt.text = baseTxt;
        }
    });

    // Ajustar valor seleccionado en fin si quedó <= inicio
    var mFinActual = timeToMin(selectFin.value);
    if (mFinActual <= mInicio) {
        for (var i = 0; i < selectFin.options.length; i++) {
            var optF = selectFin.options[i];
            if (!optF.disabled && timeToMin(optF.value) > mInicio) {
                selectFin.value = optF.value;
                break;
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal').forEach(function(modal) {
        var inputFecha = modal.querySelector('.input-fecha-visita') || modal.querySelector('input[name="fecha_visita"]') || modal.querySelector('input[name="nueva_fecha"]');
        var selectInicio = modal.querySelector('.select-hora-inicio') || modal.querySelector('select[name="hora_inicio"]') || modal.querySelector('select[name="nueva_hora_inicio"]');
        var selectFin = modal.querySelector('.select-hora-fin') || modal.querySelector('select[name="hora_fin"]') || modal.querySelector('select[name="nueva_hora_fin"]');
        var selectReclamo = modal.querySelector('select[name="id_reclamo"]');

        if (inputFecha) inputFecha.addEventListener('change', function() { actualizarColisionesHorarias(modal); });
        if (selectReclamo) selectReclamo.addEventListener('change', function() { actualizarColisionesHorarias(modal); });
        if (selectInicio) selectInicio.addEventListener('change', function() { actualizarColisionesHorarias(modal); });
        if (selectFin) selectFin.addEventListener('change', function() { actualizarColisionesHorarias(modal); });
        modal.addEventListener('shown.bs.modal', function() { actualizarColisionesHorarias(modal); });
    });
});
</script>
 