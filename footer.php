    </div>

    <!-- MODAL NUEVO RECLAMO GLOBAL -->
    <?php if (($usuario_actual['id_rol'] ?? 0) != 4): ?>
        <div class="modal fade" id="modalNuevoReclamo" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="nuevo_reclamo">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Registrar Incidencia / Reclamo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Título de la Incidencia</label>
                                <input type="text" name="titulo" class="form-control" placeholder="Ej: Pérdida de agua en pasillo" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tipo de Reclamo</label>
                                <select name="tipo" class="form-select" <?php if ($usuario_actual['id_rol'] == 1) echo 'disabled'; ?>>
                                    <option value="Consorcio" <?php if ($usuario_actual['id_rol'] == 1) echo 'selected'; ?>>Consorcio (Sectores Compartidos)</option>
                                    <option value="Particular">Particular (Departamento/UF)</option>
                                </select>
                                <?php if ($usuario_actual['id_rol'] == 1): ?>
                                    <small class="text-muted">El Administrador de Consorcio solo puede registrar reclamos en sectores compartidos.</small>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Descripción Detallada</label>
                                <textarea name="descripcion" class="form-control" rows="3" required placeholder="Explique qué ocurrió, ubicación exacta..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Foto / Evidencia (Opcional)</label>
                                <input type="file" name="foto" class="form-control" accept="image/*">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i> Guardar Reclamo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>