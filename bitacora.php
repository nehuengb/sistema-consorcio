<?php
$rol = $usuario_actual['id_rol'];
$uid = $usuario_actual['id_usuario'];
$uf = $usuario_actual['unidad_funcional'];
$u_nombre = $usuario_actual['nombre'];
?>
<div id="bitacora" class="card">
    <div class="card-header">
        <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Historial y Bitácora de Trabajos Concluidos
    </div>
    <div class="card-body">
        <?php
        if ($rol == 1) { // Admin Consorcio: Solo trabajos de Consorcio
            $sql_bit = "SELECT * FROM bitacora_historial WHERE tipo_ambito = 'Consorcio' ORDER BY fecha_finalizado DESC";
        } else if ($rol == 2 || $rol == 3) { // Propietario / Inquilino: EXCLUSIVAMENTE su UF
            $sql_bit = "SELECT * FROM bitacora_historial WHERE unidad_funcional = '$uf' AND tipo_ambito = 'Particular' ORDER BY fecha_finalizado DESC";
        } else if ($rol == 4) { // Proveedor: Solo sus trabajos
            $sql_bit = "SELECT * FROM bitacora_historial WHERE proveedor_nombre = '$u_nombre' ORDER BY fecha_finalizado DESC";
        } else { // SysAdmin / Otros
            $sql_bit = "SELECT * FROM bitacora_historial ORDER BY fecha_finalizado DESC";
        }

        $res_bit = $conn->query($sql_bit);
        ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Ámbito</th>
                        <th>UF</th>
                        <th>Título de la Incidencia</th>
                        <th>Trabajo Realizado</th>
                        <th>Monto Final</th>
                        <th>Proveedor / Técnico</th>
                        <th>Fecha Finalización</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$res_bit || $res_bit->num_rows == 0): ?>
                        <tr><td colspan="7" class="text-center text-muted">No hay registros asentados en el historial para esta vista.</td></tr>
                    <?php else: ?>
                        <?php while ($b = $res_bit->fetch_assoc()): ?>
                            <tr>
                                <td><span class="badge <?php echo $b['tipo_ambito'] === 'Consorcio' ? 'bg-primary' : 'bg-secondary'; ?>"><?php echo $b['tipo_ambito']; ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($b['unidad_funcional']); ?></span></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($b['titulo']); ?></td>
                                <td class="small"><?php echo htmlspecialchars($b['descripcion_trabajo']); ?></td>
                                <td class="fw-bold text-success">$<?php echo number_format($b['monto_final'], 2); ?></td>
                                <td><?php echo htmlspecialchars($b['proveedor_nombre']); ?></td>
                                <td class="small text-muted"><?php echo $b['fecha_finalizado']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>