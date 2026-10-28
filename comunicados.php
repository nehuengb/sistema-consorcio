<?php
$rol = $usuario_actual['id_rol'];
$uid = $usuario_actual['id_usuario'];
$uf = $usuario_actual['unidad_funcional'];
?>
        <div id="comunicados" class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-bullhorn text-primary me-2"></i> Comunicados y Avisos Comunitarios</span>
                <?php if ($rol == 1 || $rol == 5): ?>
                    <button class="btn btn-primary btn-sm btn-rounded" data-bs-toggle="modal" data-bs-target="#modalNuevoComunicado">
                        <i class="fa-solid fa-plus me-1"></i> Publicar Aviso
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php
                $res_com = $conn->query("SELECT c.*, u.nombre as admin_nombre FROM comunicados c JOIN usuarios u ON c.id_admin = u.id_usuario ORDER BY c.fecha DESC");
                ?>

                <div class="row g-3">
                    <?php if (!$res_com || $res_com->num_rows == 0): ?>
                        <div class="col-12 text-center text-muted py-3">No hay comunicados publicados.</div>
                    <?php else: ?>
                        <?php while ($c = $res_com->fetch_assoc()): ?>
                            <div class="col-md-6">
                                <div class="card border p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($c['titulo']); ?></h5>
                                        <span class="badge bg-light text-muted"><?php echo $c['fecha']; ?></span>
                                    </div>
                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($c['contenido']); ?></p>
                                    <div class="small text-secondary">Publicado por: <strong><?php echo htmlspecialchars($c['admin_nombre']); ?></strong></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MODAL CREAR COMUNICADO -->
        <?php if ($rol == 1 || $rol == 5): ?>
            <div class="modal fade" id="modalNuevoComunicado" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="index.php">
                            <input type="hidden" name="accion" value="crear_comunicado">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold">Publicar Aviso Comunitario</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Título del Comunicado</label>
                                    <input type="text" name="titulo" class="form-control" placeholder="Ej: Trabajo de fumigación en pasillos" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Contenido de la Notificación</label>
                                    <textarea name="contenido" class="form-control" rows="4" required placeholder="Escriba los detalles para los vecinos..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i> Publicar para la Comunidad</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>