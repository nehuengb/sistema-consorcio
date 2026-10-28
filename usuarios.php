<?php
$rol = $usuario_actual['id_rol'];
$uid = $usuario_actual['id_usuario'];
$uf = $usuario_actual['unidad_funcional'];
?>
        <?php if ($rol == 1 || $rol == 5): ?>
            <div id="usuarios" class="card border-warning">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-users-gear me-2"></i> ABM Gestión de Usuarios del Consorcio</span>
                    <button class="btn btn-dark btn-sm btn-rounded" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
                        <i class="fa-solid fa-user-plus me-1"></i> Alta Nuevo Usuario
                    </button>
                </div>
                <div class="card-body">
                    <?php
                    $res_u = $conn->query("SELECT u.*, r.nombre_rol FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol ORDER BY u.id_rol ASC, u.nombre ASC");
                    $lista_usuarios = [];
                    if ($res_u && $res_u->num_rows > 0) {
                        while ($usr_row = $res_u->fetch_assoc()) {
                            $lista_usuarios[] = $usr_row;
                        }
                    }
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>UF</th>
                                    <th>Teléfono / Rubro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lista_usuarios)): ?>
                                    <tr><td colspan="6" class="text-center text-muted">No hay usuarios registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($lista_usuarios as $usr): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($usr['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($usr['email']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($usr['nombre_rol']); ?></span></td>
                                            <td><?php echo $usr['unidad_funcional'] ? htmlspecialchars($usr['unidad_funcional']) : '-'; ?></td>
                                            <td class="small"><?php echo $usr['rubro'] ? htmlspecialchars($usr['rubro']) : htmlspecialchars($usr['telefono']); ?></td>
                                            <td>
                                                <?php if ($usr['id_usuario'] != $uid && !($rol == 1 && $usr['id_rol'] == 5)): ?>
                                                    <!-- BOTÓN EDITAR -->
                                                    <button type="button" class="btn btn-outline-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario<?php echo $usr['id_usuario']; ?>" title="Editar Usuario">
                                                        <i class="fa-solid fa-pen-to-square"></i> Editar
                                                    </button>

                                                    <!-- BOTÓN ELIMINAR -->
                                                    <form method="POST" action="index.php" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este usuario?');">
                                                        <input type="hidden" name="accion" value="eliminar_usuario">
                                                        <input type="hidden" name="id_eliminar" value="<?php echo $usr['id_usuario']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar Usuario"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted">Protegido</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- MODALES DE EDICIÓN RENDERIZADOS FUERA DE LA TABLA -->
            <?php foreach ($lista_usuarios as $usr): ?>
                <?php if ($usr['id_usuario'] != $uid && !($rol == 1 && $usr['id_rol'] == 5)): ?>
                    <div class="modal fade" id="modalEditarUsuario<?php echo $usr['id_usuario']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="index.php">
                                    <input type="hidden" name="accion" value="editar_usuario">
                                    <input type="hidden" name="id_usuario_edit" value="<?php echo $usr['id_usuario']; ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Modificar Información de Usuario</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Nombre Completo</label>
                                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($usr['nombre']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Correo Electrónico</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usr['email']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Nueva Contraseña (Opcional)</label>
                                            <input type="password" name="password" class="form-control" placeholder="Dejar en blanco si no desea cambiarla">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Rol de Usuario</label>
                                            <select name="id_rol" class="form-select" required>
                                                <option value="1" <?php if ($usr['id_rol'] == 1) echo 'selected'; ?>>Administrador de Consorcio</option>
                                                <option value="2" <?php if ($usr['id_rol'] == 2) echo 'selected'; ?>>Propietario</option>
                                                <option value="3" <?php if ($usr['id_rol'] == 3) echo 'selected'; ?>>Inquilino</option>
                                                <option value="4" <?php if ($usr['id_rol'] == 4) echo 'selected'; ?>>Proveedor</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Unidad Funcional</label>
                                            <input type="text" name="unidad_funcional" class="form-control" value="<?php echo htmlspecialchars($usr['unidad_funcional'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Teléfono</label>
                                            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($usr['telefono'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Rubro (Proveedores)</label>
                                            <input type="text" name="rubro" class="form-control" value="<?php echo htmlspecialchars($usr['rubro'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- MODAL CREAR USUARIO -->
            <div class="modal fade" id="modalCrearUsuario" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="index.php">
                            <input type="hidden" name="accion" value="crear_usuario">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold">Alta de Nuevo Usuario</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nombre Completo</label>
                                    <input type="text" name="nombre" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Correo Electrónico</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Contraseña</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Rol</label>
                                    <select name="id_rol_nuevo" class="form-select" required>
                                        <option value="1">Administrador de Consorcio</option>
                                        <option value="2">Propietario</option>
                                        <option value="3">Inquilino</option>
                                        <option value="4">Proveedor</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Unidad Funcional (Si es vecino)</label>
                                    <input type="text" name="unidad_funcional" class="form-control" placeholder="Ej: 3°A">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Teléfono</label>
                                    <input type="text" name="telefono" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Rubro (Si es proveedor)</label>
                                    <input type="text" name="rubro" class="form-control" placeholder="Ej: Plomería">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-warning"><i class="fa-solid fa-check me-1"></i> Registrar Usuario</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
