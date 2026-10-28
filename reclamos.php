<?php
$rol = $usuario_actual['id_rol'];
$uid = $usuario_actual['id_usuario'];
$uf = $usuario_actual['unidad_funcional'];
?>
<div id="reclamos" class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-clipboard-list text-primary me-2"></i> Gestión de Reclamos e Incidencias</span>
        <?php if ($rol != 4): ?>
            <button class="btn btn-primary btn-sm btn-rounded" data-bs-toggle="modal" data-bs-target="#modalNuevoReclamo">
                <i class="fa-solid fa-plus me-1"></i> Crear Nuevo Reclamo
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php
        if ($rol == 1) { // Admin Consorcio: Solo reclamos de Consorcio / Áreas Comunes
            $sql_rec = "SELECT r.*, u.nombre as solicitante FROM reclamos r JOIN usuarios u ON r.id_usuario = u.id_usuario WHERE r.tipo = 'Consorcio' ORDER BY r.fecha_creacion DESC";
        } else if ($rol == 4) { // Proveedor: Reclamos pendientes para cotizar
            $sql_rec = "SELECT r.*, u.nombre as solicitante FROM reclamos r JOIN usuarios u ON r.id_usuario = u.id_usuario WHERE r.estado = 'Pendiente' ORDER BY r.fecha_creacion DESC";
        } else if ($rol == 2 || $rol == 3) { // Propietario / Inquilino: EXCLUSIVAMENTE su UF
            $sql_rec = "SELECT r.*, u.nombre as solicitante FROM reclamos r JOIN usuarios u ON r.id_usuario = u.id_usuario WHERE r.unidad_funcional = '$uf' AND r.tipo = 'Particular' ORDER BY r.fecha_creacion DESC";
        } else { // SysAdmin
            $sql_rec = "SELECT r.*, u.nombre as solicitante FROM reclamos r JOIN usuarios u ON r.id_usuario = u.id_usuario ORDER BY r.fecha_creacion DESC";
        }

        $res_reclamos = $conn->query($sql_rec);
        ?>

        <div class="row g-3">
            <?php if (!$res_reclamos || $res_reclamos->num_rows == 0): ?>
                <div class="col-12 text-center text-muted py-4">
                    <i class="fa-solid fa-folder-open fa-2x mb-2"></i>
                    <p>No se encontraron reclamos registrados para esta vista.</p>
                </div>
            <?php else: ?>
                <?php while ($r = $res_reclamos->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-secondary">
                                    <?php echo ($r['tipo'] === 'Consorcio') ? 'Consorcio' : 'UF: ' . htmlspecialchars($r['unidad_funcional']); ?>
                                </span>
                                <span class="badge <?php 
                                    if ($r['estado'] === 'Pendiente') echo 'bg-warning text-dark';
                                    else if ($r['estado'] === 'En proceso') echo 'bg-info text-dark';
                                    else if ($r['estado'] === 'Resuelto') echo 'bg-success';
                                    else echo 'bg-danger';
                                ?>"><?php echo htmlspecialchars($r['estado']); ?></span>
                            </div>

                            <h5 class="fw-bold text-dark"><?php echo htmlspecialchars($r['titulo']); ?></h5>
                            <p class="text-muted small flex-grow-1"><?php echo htmlspecialchars($r['descripcion']); ?></p>

                            <?php if (!empty($r['foto'])): ?>
                                <div class="mb-2 text-center">
                                    <a href="uploads/<?php echo htmlspecialchars($r['foto']); ?>" target="_blank">
                                        <img src="uploads/<?php echo htmlspecialchars($r['foto']); ?>" class="img-thumbnail" style="max-height: 120px; object-fit: cover;" alt="Foto Reclamo">
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="small text-secondary mb-2">
                                <i class="fa-solid fa-tag me-1"></i> Tipo: <strong><?php echo $r['tipo']; ?></strong><br>
                                <i class="fa-solid fa-user me-1"></i> Por: <?php echo htmlspecialchars($r['solicitante']); ?><br>
                                <i class="fa-solid fa-clock me-1"></i> <?php echo $r['fecha_creacion']; ?>
                            </div>

                            <!-- ACCIONES -->
                            <div class="border-top pt-2 mt-auto d-flex flex-wrap gap-1">
                                <?php if (($rol == 2 || $rol == 5) && $r['estado'] === 'Pendiente Propietario'): ?>
                                    <form method="POST" action="index.php" class="d-inline w-100">
                                        <input type="hidden" name="accion" value="aprobar_reclamo_inquilino">
                                        <input type="hidden" name="id_reclamo" value="<?php echo $r['id_reclamo']; ?>">
                                        <button name="decision" value="Pendiente" class="btn btn-success btn-sm w-100 mb-1"><i class="fa-solid fa-check me-1"></i> Aprobar para Consorcio</button>
                                        <button name="decision" value="Rechazado" class="btn btn-outline-danger btn-sm w-100"><i class="fa-solid fa-xmark me-1"></i> Rechazar</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (($rol == 4 || $rol == 5) && $r['estado'] === 'Pendiente'): ?>
                                    <button class="btn btn-outline-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#modalPresupuestar<?php echo $r['id_reclamo']; ?>">
                                        <i class="fa-solid fa-calculator me-1"></i> Cotizar Presupuesto
                                    </button>
                                <?php endif; ?>

                                <?php if ($rol != 1 && $rol != 4 && $r['estado'] !== 'Pendiente Propietario' && $r['estado'] !== 'Rechazado' && !($r['tipo'] === 'Consorcio' && ($rol == 2 || $rol == 3))): ?>
                                    <form method="POST" action="index.php" class="d-inline me-1">
                                        <input type="hidden" name="accion" value="cambiar_estado_reclamo">
                                        <input type="hidden" name="id_reclamo" value="<?php echo $r['id_reclamo']; ?>">
                                        <select name="nuevo_estado" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                            <option value="" disabled selected>Cambiar Estado</option>
                                            <option value="Pendiente">Pendiente</option>
                                            <option value="En proceso">En proceso</option>
                                            <option value="Resuelto">Resuelto</option>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL PRESUPUESTAR -->
                    <?php if ($rol == 4 || $rol == 5): ?>
                        <div class="modal fade" id="modalPresupuestar<?php echo $r['id_reclamo']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="index.php">
                                        <input type="hidden" name="accion" value="cargar_presupuesto">
                                        <input type="hidden" name="id_reclamo" value="<?php echo $r['id_reclamo']; ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Enviar Presupuesto</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small text-muted mb-2">Reclamo: <strong><?php echo htmlspecialchars($r['titulo']); ?></strong> (UF: <?php echo $r['unidad_funcional']; ?>)</p>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Monto Total ($)</label>
                                                <input type="number" step="0.01" name="monto" class="form-control" placeholder="45000.00" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Detalle de Trabajos y Materiales</label>
                                                <textarea name="detalle" class="form-control" rows="3" required placeholder="Incluye mano de obra, insumos..."></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Fecha Estimada de Inicio</label>
                                                <input type="date" name="fecha_estimada" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i> Enviar Cotización</button>
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