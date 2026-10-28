<?php
$rol = $usuario_actual['id_rol'];
$uid = $usuario_actual['id_usuario'];
$uf = $usuario_actual['unidad_funcional'];
?>
        <div id="presupuestos" class="card">
            <div class="card-header">
                <i class="fa-solid fa-calculator text-primary me-2"></i> Presupuestos y Cotizaciones Recibidas
            </div>
            <div class="card-body">
                <?php
                if ($rol == 1) {
                    $sql_p = "SELECT p.*, r.titulo, r.unidad_funcional, r.tipo, u.nombre as proveedor_nombre FROM presupuestos p JOIN reclamos r ON p.id_reclamo = r.id_reclamo JOIN usuarios u ON p.id_proveedor = u.id_usuario WHERE r.tipo = 'Consorcio' ORDER BY p.fecha_creacion DESC";
                } else if ($rol == 2 || $rol == 3) {
                    $sql_p = "SELECT p.*, r.titulo, r.unidad_funcional, r.tipo, u.nombre as proveedor_nombre FROM presupuestos p JOIN reclamos r ON p.id_reclamo = r.id_reclamo JOIN usuarios u ON p.id_proveedor = u.id_usuario WHERE r.unidad_funcional = '$uf' AND r.tipo = 'Particular' ORDER BY p.fecha_creacion DESC";
                } else if ($rol == 4) {
                    $sql_p = "SELECT p.*, r.titulo, r.unidad_funcional, r.tipo, u.nombre as proveedor_nombre FROM presupuestos p JOIN reclamos r ON p.id_reclamo = r.id_reclamo JOIN usuarios u ON p.id_proveedor = u.id_usuario WHERE p.id_proveedor = $uid ORDER BY p.fecha_creacion DESC";
                } else {
                    $sql_p = "SELECT p.*, r.titulo, r.unidad_funcional, r.tipo, u.nombre as proveedor_nombre FROM presupuestos p JOIN reclamos r ON p.id_reclamo = r.id_reclamo JOIN usuarios u ON p.id_proveedor = u.id_usuario ORDER BY p.fecha_creacion DESC";
                }

                $res_pres = $conn->query($sql_p);
                ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Reclamo</th>
                                <th>UF</th>
                                <th>Proveedor</th>
                                <th>Monto</th>
                                <th>Detalle</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$res_pres || $res_pres->num_rows == 0): ?>
                                <tr><td colspan="7" class="text-center text-muted">No hay presupuestos visibles en este panel.</td></tr>
                            <?php else: ?>
                                <?php while ($p = $res_pres->fetch_assoc()): ?>
                                    <?php 
                                        $prom = obtenerPromedioProveedor($conn, $p['id_proveedor']); 
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($p['titulo']); ?></strong></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($p['unidad_funcional']); ?></span></td>
                                        <td>
                                            <?php echo htmlspecialchars($p['proveedor_nombre']); ?><br>
                                            <span class="rating-stars small">
                                                <i class="fa-solid fa-star"></i> <?php echo $prom['promedio']; ?> (<?php echo $prom['cantidad']; ?>)
                                            </span>
                                        </td>
                                        <td class="fw-bold text-success">$<?php echo number_format($p['monto'], 2); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($p['detalle']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                if ($p['estado'] === 'Aprobado') echo 'bg-success';
                                                else if ($p['estado'] === 'Aprobado Inquilino') echo 'bg-info text-dark';
                                                else if ($p['estado'] === 'Rechazado') echo 'bg-danger';
                                                else echo 'bg-warning text-dark';
                                            ?>"><?php echo htmlspecialchars($p['estado']); ?></span>
                                        </td>
                                        <td>
                                            <!-- APROBACIÓN DE PRESUPUESTOS (OCULTAR SI YA FUE APROBADO O RECHAZADO) -->
                                            <?php if ($p['estado'] === 'Pendiente'): ?>
                                                <?php if (($rol == 1 && $p['tipo'] === 'Consorcio') || ($p['tipo'] === 'Particular' && ($rol == 2 || $rol == 3)) || $rol == 5): ?>
                                                    <form method="POST" action="index.php" class="d-inline">
                                                        <input type="hidden" name="accion" value="evaluar_presupuesto">
                                                        <input type="hidden" name="id_presupuesto" value="<?php echo $p['id_presupuesto']; ?>">
                                                        <button name="decision" value="Aprobado" class="btn btn-success btn-sm me-1"><i class="fa-solid fa-check"></i> Aprobar</button>
                                                        <button name="decision" value="Rechazado" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-xmark"></i> Rechazar</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php elseif ($p['estado'] === 'Aprobado Inquilino'): ?>
                                                <?php if ($rol == 2 || $rol == 5): ?>
                                                    <form method="POST" action="index.php" class="d-inline">
                                                        <input type="hidden" name="accion" value="evaluar_presupuesto">
                                                        <input type="hidden" name="id_presupuesto" value="<?php echo $p['id_presupuesto']; ?>">
                                                        <button name="decision" value="Aprobado" class="btn btn-success btn-sm me-1"><i class="fa-solid fa-check"></i> Confirmación Final (Propietario)</button>
                                                        <button name="decision" value="Rechazado" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-xmark"></i> Rechazar</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="small text-muted">Pendiente confirma Propietario</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="small text-muted"><i class="fa-solid fa-lock me-1"></i> Finalizado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>