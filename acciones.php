<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $accion = $_POST['accion'] ?? '';
    $u_id = $_SESSION['usuario']['id_usuario'] ?? 0;
    $u_rol = $_SESSION['usuario']['id_rol'] ?? 0;
    $u_uf = $_SESSION['usuario']['unidad_funcional'] ?? '';
    $u_nombre = $_SESSION['usuario']['nombre'] ?? '';

    // 1. INICIAR SESIÓN
    if ($accion === 'login') {
        $email = limpiar($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['mensaje_error'] = "Por favor complete todos los campos requeridos para iniciar sesión.";
        } else {
            $stmt = $conn->prepare("SELECT u.*, r.nombre_rol FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol WHERE u.email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows === 1) {
                $user = $res->fetch_assoc();
                if ($password === $user['password'] || password_verify($password, $user['password'])) {
                    $_SESSION['usuario_real'] = $user;
                    $_SESSION['usuario'] = $user;
                    $_SESSION['mensaje_exito'] = "¡Bienvenido/a, " . $user['nombre'] . "!";
                } else {
                    $_SESSION['mensaje_error'] = "Contraseña incorrecta. Verifique sus credenciales e intente nuevamente.";
                }
            } else {
                $_SESSION['mensaje_error'] = "El correo electrónico ingresado no corresponde a un usuario registrado.";
            }
            $stmt->close();
        }
        header("Location: index.php");
        exit();
    }

    // 2. CERRAR SESIÓN
    if ($accion === 'logout') {
        session_destroy();
        header("Location: index.php");
        exit();
    }

    // VERIFICAR AUTENTICACIÓN
    if (!$u_id) {
        $_SESSION['mensaje_error'] = "Su sesión ha expirado. Por favor ingrese nuevamente.";
        header("Location: index.php");
        exit();
    }

    // 3. CAMBIAR ROL DEMO (SYSADMIN)
    if ($accion === 'cambiar_rol_demo') {
        $real_rol = $_SESSION['usuario_real']['id_rol'] ?? $_SESSION['usuario']['id_rol'] ?? 0;
        if ($real_rol == 5) {
            $nuevo_rol = intval($_POST['nuevo_rol_id'] ?? 5);
            $res_sim = $conn->query("SELECT u.*, r.nombre_rol FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol WHERE u.id_rol = $nuevo_rol LIMIT 1");
            if ($res_sim && $res_sim->num_rows > 0) {
                $_SESSION['usuario'] = $res_sim->fetch_assoc();
                $_SESSION['rol_simulado'] = true;
                $_SESSION['mensaje_exito'] = "Modo de vista cambiado a: " . $_SESSION['usuario']['nombre_rol'];
            } else {
                $_SESSION['usuario'] = $_SESSION['usuario_real'];
                $_SESSION['mensaje_exito'] = "Vista restaurada a Administrador de Sistema.";
            }
        }
        header("Location: index.php");
        exit();
    }

    // 4. LIMPIAR NOTIFICACIONES
    if ($accion === 'limpiar_notificaciones') {
        $stmt = $conn->prepare("UPDATE notificaciones SET leida = 1 WHERE id_usuario = ?");
        $stmt->bind_param("i", $u_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['mensaje_exito'] = "Notificaciones marcadas como leídas.";
        header("Location: index.php");
        exit();
    }

    // 5. REGISTRAR NUEVO RECLAMO
    if ($accion === 'nuevo_reclamo') {
        if ($u_rol == 4) {
            $_SESSION['mensaje_error'] = "Los proveedores no están autorizados para registrar reclamos.";
        } else {
            $titulo = limpiar($_POST['titulo'] ?? '');
            $descripcion = limpiar($_POST['descripcion'] ?? '');
            $tipo = $_POST['tipo'] ?? 'Consorcio';

            if ($u_rol == 1) {
                $tipo = 'Consorcio';
                $uf_reclamo = 'Consorcio';
            } else {
                $uf_reclamo = !empty($u_uf) ? $u_uf : 'Sin UF';
            }

            if (empty($titulo) || empty($descripcion)) {
                $_SESSION['mensaje_error'] = "Debe ingresar el título y la descripción del reclamo.";
            } else {
                $foto_nombre = '';
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['foto']['tmp_name'];
                    $name = $_FILES['foto']['name'];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $ext_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (in_array($ext, $ext_permitidas)) {
                        $upload_dir = __DIR__ . '/uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $foto_nombre = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $name);
                        $move_uploaded_filename = $upload_dir . $foto_nombre;
                        @move_uploaded_file($tmp_name, $move_uploaded_filename);
                    }
                }

                $estado = 'Pendiente';
                if ($u_rol == 3 && $tipo === 'Particular') {
                    $estado = 'Pendiente Propietario';
                }

                $stmt = $conn->prepare("INSERT INTO reclamos (id_usuario, titulo, descripcion, tipo, estado, foto, unidad_funcional) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssss", $u_id, $titulo, $descripcion, $tipo, $estado, $foto_nombre, $uf_reclamo);

                if ($stmt->execute()) {
                    $_SESSION['mensaje_exito'] = "Reclamo registrado exitosamente ($estado).";

                    if ($estado === 'Pendiente Propietario') {
                        $res_prop = $conn->query("SELECT id_usuario FROM usuarios WHERE unidad_funcional = '$u_uf' AND id_rol = 2");
                        if ($res_prop) {
                            while ($p = $res_prop->fetch_assoc()) {
                                agregarNotificacion($conn, $p['id_usuario'], "Reclamo de Inquilino Pendiente", "Un inquilino de su UF $u_uf registró el reclamo '$titulo' que requiere su autorización.", 'warning');
                            }
                        }
                    } else if ($tipo === 'Consorcio') {
                        $res_adm = $conn->query("SELECT id_usuario FROM usuarios WHERE id_rol = 1");
                        if ($res_adm) {
                            while ($a = $res_adm->fetch_assoc()) {
                                agregarNotificacion($conn, $a['id_usuario'], "Nuevo Reclamo de Consorcio", "Se ha registrado el reclamo '$titulo' en áreas comunes.", 'info');
                            }
                        }
                    }
                } else {
                    $_SESSION['mensaje_error'] = "Error al guardar el reclamo en la base de datos.";
                }
                $stmt->close();
            }
        }
        header("Location: index.php");
        exit();
    }

    // 6. PROPIETARIO APRUEBA RECLAMO DE INQUILINO
    if ($accion === 'aprobar_reclamo_inquilino') {
        $id_reclamo = intval($_POST['id_reclamo'] ?? 0);
        $decision = $_POST['decision'] ?? 'Pendiente';

        $stmt = $conn->prepare("UPDATE reclamos SET estado = ? WHERE id_reclamo = ?");
        $stmt->bind_param("si", $decision, $id_reclamo);
        if ($stmt->execute()) {
            $_SESSION['mensaje_exito'] = "El reclamo fue actualizado a '$decision'.";
            $res_rec = $conn->query("SELECT id_usuario, titulo FROM reclamos WHERE id_reclamo = $id_reclamo");
            if ($res_rec && $rec_data = $res_rec->fetch_assoc()) {
                $nmsg = "El propietario actualizó el estado de tu reclamo '{$rec_data['titulo']}' a: $decision.";
                agregarNotificacion($conn, $rec_data['id_usuario'], "Actualización de Reclamo", $nmsg, 'info');
            }
        }
        $stmt->close();
        header("Location: index.php");
        exit();
    }

    // 7. CAMBIAR ESTADO DE RECLAMO (ADMIN / RESIDENTE)
    if ($accion === 'cambiar_estado_reclamo') {
        $id_reclamo = intval($_POST['id_reclamo'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';

        if ($u_rol == 4) {
            $_SESSION['mensaje_error'] = "Los proveedores no tienen permiso para alterar el estado de los reclamos.";
        } else {
            $res_chk = $conn->query("SELECT * FROM reclamos WHERE id_reclamo = $id_reclamo");
            if ($res_chk && $r_info = $res_chk->fetch_assoc()) {
                if ($u_rol == 1 && $r_info['tipo'] !== 'Consorcio') {
                    $_SESSION['mensaje_error'] = "El Administrador de Consorcio solo gestiona reclamos de áreas comunes.";
                } else if ($r_info['estado'] === 'Rechazado') {
                    $_SESSION['mensaje_error'] = "No se puede modificar un reclamo que ya fue rechazado.";
                } else {
                    $stmt = $conn->prepare("UPDATE reclamos SET estado = ? WHERE id_reclamo = ?");
                    $stmt->bind_param("si", $nuevo_estado, $id_reclamo);
                    if ($stmt->execute()) {
                        $_SESSION['mensaje_exito'] = "Estado del reclamo actualizado a '$nuevo_estado'.";

                        if ($nuevo_estado === 'Resuelto') {
                            $res_p = $conn->query("SELECT p.monto, p.detalle, u.nombre as prov_nombre FROM presupuestos p JOIN usuarios u ON p.id_proveedor = u.id_usuario WHERE p.id_reclamo = $id_reclamo AND p.estado IN ('Aprobado', 'Aprobado Inquilino') LIMIT 1");
                            $monto = 0.00;
                            $prov = 'Servicio Concluido';
                            $det = $r_info['descripcion'];
                            if ($res_p && $p_data = $res_p->fetch_assoc()) {
                                $monto = $p_data['monto'];
                                $prov = $p_data['prov_nombre'];
                                $det = $p_data['detalle'];
                            }
                            registrarEnBitacora($conn, $id_reclamo, $r_info['tipo'], $r_info['unidad_funcional'], $r_info['titulo'], $det, $monto, $prov);
                        }

                        if ($r_info['id_usuario'] != $u_id) {
                            agregarNotificacion($conn, $r_info['id_usuario'], "Estado de Reclamo Actualizado", "Tu reclamo '{$r_info['titulo']}' cambió a estado: $nuevo_estado.", 'info');
                        }
                    }
                    $stmt->close();
                }
            }
        }
        header("Location: index.php");
        exit();
    }

    // 8. PROVEEDOR CARGA PRESUPUESTO
    if ($accion === 'cargar_presupuesto') {
        if ($u_rol != 4 && $u_rol != 5) {
            $_SESSION['mensaje_error'] = "Solo los proveedores pueden cotizar presupuestos.";
        } else {
            $id_reclamo = intval($_POST['id_reclamo'] ?? 0);
            $monto = floatval($_POST['monto'] ?? 0);
            $detalle = limpiar($_POST['detalle'] ?? '');
            $fecha_est = $_POST['fecha_estimada'] ?? '';

            $res_rec = $conn->query("SELECT * FROM reclamos WHERE id_reclamo = $id_reclamo");
            if ($res_rec && $rec_info = $res_rec->fetch_assoc()) {
                if ($rec_info['estado'] !== 'Pendiente') {
                    $_SESSION['mensaje_error'] = "No se pueden enviar presupuestos para reclamos que ya están en proceso o resueltos.";
                } else if ($monto <= 0 || empty($detalle)) {
                    $_SESSION['mensaje_error'] = "Debe ingresar un monto válido y un detalle del trabajo.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO presupuestos (id_reclamo, id_proveedor, monto, detalle, fecha_estimada, estado) VALUES (?, ?, ?, ?, ?, 'Pendiente')");
                    $stmt->bind_param("iidss", $id_reclamo, $u_id, $monto, $detalle, $fecha_est);
                    if ($stmt->execute()) {
                        $_SESSION['mensaje_exito'] = "Presupuesto enviado por $" . number_format($monto, 2);

                        if ($rec_info['tipo'] === 'Consorcio') {
                            $res_adm = $conn->query("SELECT id_usuario FROM usuarios WHERE id_rol = 1");
                            if ($res_adm) {
                                while ($a = $res_adm->fetch_assoc()) {
                                    agregarNotificacion($conn, $a['id_usuario'], "Nuevo Presupuesto Recibido", "Un proveedor envió cotización para '{$rec_info['titulo']}'.", 'info');
                                }
                            }
                        } else {
                            $res_vec = $conn->query("SELECT id_usuario FROM usuarios WHERE unidad_funcional = '{$rec_info['unidad_funcional']}' AND id_rol IN (2, 3)");
                            if ($res_vec) {
                                while ($v = $res_vec->fetch_assoc()) {
                                    agregarNotificacion($conn, $v['id_usuario'], "Nuevo Presupuesto Disponible", "Recibiste una propuesta de presupuesto para '{$rec_info['titulo']}'.", 'info');
                                }
                            }
                        }
                    }
                    $stmt->close();
                }
            }
        }
        header("Location: index.php");
        exit();
    }

    // 9. EVALUAR PRESUPUESTO
    if ($accion === 'evaluar_presupuesto') {
        $id_presupuesto = intval($_POST['id_presupuesto'] ?? 0);
        $decision = $_POST['decision'] ?? '';

        $stmt = $conn->prepare("SELECT p.*, r.tipo, r.unidad_funcional, r.titulo, r.id_reclamo FROM presupuestos p JOIN reclamos r ON p.id_reclamo = r.id_reclamo WHERE p.id_presupuesto = ?");
        $stmt->bind_param("i", $id_presupuesto);
        $stmt->execute();
        $res_p = $stmt->get_result();

        if ($res_p && $p_info = $res_p->fetch_assoc()) {
            $id_reclamo = $p_info['id_reclamo'];
            if ($u_rol == 1 && $p_info['tipo'] !== 'Consorcio') {
                $_SESSION['mensaje_error'] = "El Administrador de Consorcio solo evalúa presupuestos de áreas comunes.";
            } else {
                $nuevo_estado_p = $decision;
                if ($u_rol == 3 && $decision === 'Aprobado') {
                    $nuevo_estado_p = 'Aprobado Inquilino';
                }

                $stmt_u = $conn->prepare("UPDATE presupuestos SET estado = ? WHERE id_presupuesto = ?");
                $stmt_u->bind_param("si", $nuevo_estado_p, $id_presupuesto);
                if ($stmt_u->execute()) {
                    $_SESSION['mensaje_exito'] = "El presupuesto fue actualizado a estado '$nuevo_estado_p'.";

                    if ($nuevo_estado_p === 'Aprobado') {
                        $conn->query("UPDATE presupuestos SET estado = 'Rechazado' WHERE id_reclamo = $id_reclamo AND id_presupuesto != $id_presupuesto");
                        $conn->query("UPDATE reclamos SET estado = 'En proceso' WHERE id_reclamo = $id_reclamo");
                    }

                    agregarNotificacion($conn, $p_info['id_proveedor'], "Presupuesto Evaluado", "Tu presupuesto para '{$p_info['titulo']}' fue cambiado a: $nuevo_estado_p.", 'success');
                }
                $stmt_u->close();
            }
        }
        $stmt->close();
        header("Location: index.php");
        exit();
    }

    // 10. AGENDAR VISITA TÉCNICA (PERMITE PROPUESTAS, BLOQUEA SOLO SI YA ESTÁ ACEPTADA/REALIZADA)
    if ($accion === 'agendar_visita') {
        $id_reclamo = intval($_POST['id_reclamo'] ?? 0);
        $motivo = limpiar($_POST['motivo'] ?? '');
        $fecha_visita = $_POST['fecha_visita'] ?? '';
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $hora_fin = $_POST['hora_fin'] ?? '';

        if (empty($fecha_visita) || empty($hora_inicio) || empty($hora_fin) || empty($motivo)) {
            $_SESSION['mensaje_error'] = "Debe completar todos los datos de la visita técnica.";
        } else if ($hora_fin <= $hora_inicio) {
            $_SESSION['mensaje_error'] = "La hora de finalización debe ser posterior a la hora de inicio.";
        } else if ($fecha_visita < date('Y-m-d')) {
            $_SESSION['mensaje_error'] = "No se pueden agendar visitas en fechas pasadas.";
        } else {
            $res_rec = $conn->query("SELECT id_usuario, unidad_funcional, titulo FROM reclamos WHERE id_reclamo = $id_reclamo");
            $rec_info = ($res_rec && $res_rec->num_rows > 0) ? $res_rec->fetch_assoc() : null;
            $residente_id = $rec_info ? $rec_info['id_usuario'] : 0;
            $uf_rec = $rec_info ? $rec_info['unidad_funcional'] : '';

            // SOLO BLOQUEA SI YA EXISTE UNA VISITA 'ACEPTADA' O 'REALIZADA' EN ESE HORARIO
            $sql_chk = "SELECT v.id_visita FROM visitas v JOIN reclamos r ON v.id_reclamo = r.id_reclamo 
                        WHERE v.fecha_visita = '$fecha_visita' 
                          AND v.estado IN ('Aceptada', 'Realizada') 
                          AND (v.id_usuario = $u_id OR r.id_usuario = $residente_id OR r.unidad_funcional = '$uf_rec')
                          AND ('$hora_inicio' < v.hora_fin AND '$hora_fin' > v.hora_inicio)";
            $res_chk = $conn->query($sql_chk);

            if ($res_chk && $res_chk->num_rows > 0) {
                $h_in_f = date('H:i', strtotime($hora_inicio));
                $h_fi_f = date('H:i', strtotime($hora_fin));
                $_SESSION['mensaje_error'] = "El horario $h_in_f - $h_fi_f hs ya está reservado por una visita confirmada.";
            } else {
                $stmt = $conn->prepare("INSERT INTO visitas (id_reclamo, id_usuario, motivo, fecha_visita, hora_inicio, hora_fin, estado) VALUES (?, ?, ?, ?, ?, ?, 'Programada')");
                $stmt->bind_param("iissss", $id_reclamo, $u_id, $motivo, $fecha_visita, $hora_inicio, $hora_fin);
                if ($stmt->execute()) {
                    $h_in_f = date('H:i', strtotime($hora_inicio));
                    $h_fi_f = date('H:i', strtotime($hora_fin));
                    $_SESSION['mensaje_exito'] = "Visita agendada para el $fecha_visita ($h_in_f - $h_fi_f hs).";

                    if ($rec_info) {
                        $res_vec = $conn->query("SELECT id_usuario FROM usuarios WHERE unidad_funcional = '{$rec_info['unidad_funcional']}' AND id_rol IN (2, 3)");
                        if ($res_vec) {
                            while ($v = $res_vec->fetch_assoc()) {
                                agregarNotificacion($conn, $v['id_usuario'], "Visita Técnica Programada", "Nueva opción de visita propuesta para el $fecha_visita ($h_in_f - $h_fi_f hs) en '{$rec_info['titulo']}'.", 'info');
                            }
                        }
                    }
                }
                $stmt->close();
            }
        }
        header("Location: index.php");
        exit();
    }

    // 11. RESPONDER / EVALUAR VISITA (RECHAZO AUTOMÁTICO DE PROPUESTAS COMPETIDORAS Y SOLAPADAS)
    if ($accion === 'responder_visita' || $accion === 'evaluar_visita') {
        $id_visita = intval($_POST['id_visita'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] ?? 'Programada';

        $res_v = $conn->query("SELECT v.*, r.id_reclamo, r.titulo, r.unidad_funcional, r.id_usuario as residente_id FROM visitas v JOIN reclamos r ON v.id_reclamo = r.id_reclamo WHERE v.id_visita = $id_visita");
        $v_info = ($res_v && $res_v->num_rows > 0) ? $res_v->fetch_assoc() : null;

        if ($v_info) {
            $stmt = $conn->prepare("UPDATE visitas SET estado = ? WHERE id_visita = ?");
            $stmt->bind_param("si", $nuevo_estado, $id_visita);
            if ($stmt->execute()) {
                $_SESSION['mensaje_exito'] = "La visita fue actualizada a '$nuevo_estado'.";

                if ($nuevo_estado === 'Aceptada') {
                    $id_rec = intval($v_info['id_reclamo']);
                    $fecha_v = $v_info['fecha_visita'];
                    $h_in = $v_info['hora_inicio'];
                    $h_fi = $v_info['hora_fin'];
                    $prov_id = intval($v_info['id_usuario']);
                    $res_id = intval($v_info['residente_id']);
                    $uf_v = $v_info['unidad_funcional'];

                    // A) Rechazar las demás propuestas pendientes del MISMO reclamo
                    $conn->query("UPDATE visitas SET estado = 'Rechazada' WHERE id_reclamo = $id_rec AND id_visita != $id_visita AND estado = 'Programada'");

                    // B) Rechazar otras propuestas pendientes en ese rango horario para el Proveedor, Residente o UF
                    $sql_rechazo = "UPDATE visitas v JOIN reclamos r ON v.id_reclamo = r.id_reclamo 
                                    SET v.estado = 'Rechazada' 
                                    WHERE v.fecha_visita = '$fecha_v' 
                                      AND v.id_visita != $id_visita 
                                      AND v.estado = 'Programada' 
                                      AND ('$h_in' < v.hora_fin AND '$h_fi' > v.hora_inicio) 
                                      AND (v.id_usuario = $prov_id OR r.id_usuario = $res_id OR r.unidad_funcional = '$uf_v')";
                    $conn->query($sql_rechazo);

                    $h_in_f = date('H:i', strtotime($h_in));
                    $h_fi_f = date('H:i', strtotime($h_fi));
                    if ($prov_id != $u_id) {
                        agregarNotificacion($conn, $prov_id, "Visita Confirmada", "La visita para '{$v_info['titulo']}' del $fecha_v ($h_in_f - $h_fi_f hs) fue ACEPTADA.", 'success');
                    }
                } else if ($nuevo_estado === 'Rechazada') {
                    if ($v_info['id_usuario'] != $u_id) {
                        agregarNotificacion($conn, $v_info['id_usuario'], "Visita Rechazada", "La propuesta de visita para '{$v_info['titulo']}' fue RECHAZADA.", 'warning');
                    }
                } else if ($nuevo_estado === 'Cancelada') {
                    if ($v_info['id_usuario'] != $u_id) {
                        agregarNotificacion($conn, $v_info['id_usuario'], "Visita Cancelada", "La visita para '{$v_info['titulo']}' fue CANCELADA.", 'danger');
                    }
                }
            }
            $stmt->close();
        }
        header("Location: index.php");
        exit();
    }

    // 12. REPROGRAMAR VISITA
    if ($accion === 'reprogramar_visita') {
        $id_visita = intval($_POST['id_visita'] ?? 0);
        $nueva_fecha = $_POST['nueva_fecha'] ?? '';
        $h_in = $_POST['nueva_hora_inicio'] ?? '';
        $h_fi = $_POST['nueva_hora_fin'] ?? '';

        if ($nueva_fecha < date('Y-m-d')) {
            $_SESSION['mensaje_error'] = "No puede reprogramar una visita a una fecha pasada.";
        } else if ($h_fi <= $h_in) {
            $_SESSION['mensaje_error'] = "La hora de finalización debe ser posterior a la hora de inicio.";
        } else {
            $res_v_cur = $conn->query("SELECT v.*, r.id_usuario as residente_id, r.unidad_funcional FROM visitas v JOIN reclamos r ON v.id_reclamo = r.id_reclamo WHERE v.id_visita = $id_visita");
            $v_cur = ($res_v_cur && $res_v_cur->num_rows > 0) ? $res_v_cur->fetch_assoc() : null;

            if ($v_cur) {
                $p_id = $v_cur['id_usuario'];
                $r_id = $v_cur['residente_id'];
                $uf_cur = $v_cur['unidad_funcional'];

                $sql_chk = "SELECT v.id_visita FROM visitas v JOIN reclamos r ON v.id_reclamo = r.id_reclamo 
                            WHERE v.fecha_visita = '$nueva_fecha' 
                              AND v.id_visita != $id_visita 
                              AND v.estado IN ('Aceptada', 'Realizada') 
                              AND (v.id_usuario = $p_id OR r.id_usuario = $r_id OR r.unidad_funcional = '$uf_cur')
                              AND ('$h_in' < v.hora_fin AND '$h_fi' > v.hora_inicio)";
                $res_chk = $conn->query($sql_chk);

                if ($res_chk && $res_chk->num_rows > 0) {
                    $_SESSION['mensaje_error'] = "No se puede reprogramar: el horario ya está ocupado por una visita confirmada.";
                } else {
                    $stmt = $conn->prepare("UPDATE visitas SET fecha_visita = ?, hora_inicio = ?, hora_fin = ?, estado = 'Programada' WHERE id_visita = ?");
                    $stmt->bind_param("sssi", $nueva_fecha, $h_in, $h_fi, $id_visita);
                    if ($stmt->execute()) {
                        $h_in_f = date('H:i', strtotime($h_in));
                        $h_fi_f = date('H:i', strtotime($h_fi));
                        $_SESSION['mensaje_exito'] = "Visita reprogramada para el $nueva_fecha ($h_in_f - $h_fi_f hs).";
                    }
                    $stmt->close();
                }
            }
        }
        header("Location: index.php");
        exit();
    }

    // 13. RECLASIFICAR RECLAMO
    if ($accion === 'reclasificar_reclamo') {
        if ($u_rol == 4 || $u_rol == 5) {
            $id_reclamo = intval($_POST['id_reclamo'] ?? 0);
            $es_consorcio = $_POST['es_consorcio'] ?? 'no';

            if ($es_consorcio === 'si') {
                $stmt = $conn->prepare("UPDATE reclamos SET tipo = 'Consorcio' WHERE id_reclamo = ?");
                $stmt->bind_param("i", $id_reclamo);
                if ($stmt->execute()) {
                    $_SESSION['mensaje_exito'] = "El reclamo fue reclasificado exitosamente como tipo Consorcio (Áreas Comunes).";

                    $res_adm = $conn->query("SELECT id_usuario FROM usuarios WHERE id_rol = 1");
                    if ($res_adm) {
                        while ($a = $res_adm->fetch_assoc()) {
                            agregarNotificacion($conn, $a['id_usuario'], "Reclamo Reclasificado", "Un especialista reclasificó un reclamo particular a Consorcio.", 'warning');
                        }
                    }
                }
                $stmt->close();
            } else {
                $_SESSION['mensaje_exito'] = "El reclamo se mantiene como Particular.";
            }
        }
        header("Location: index.php");
        exit();
    }

    // 14. CALIFICAR PROVEEDOR
    if ($accion === 'calificar_proveedor') {
        $id_reclamo = intval($_POST['id_reclamo'] ?? 0);
        $id_proveedor = intval($_POST['id_proveedor'] ?? 0);
        $puntuacion = intval($_POST['puntuacion'] ?? 5);
        $comentario = limpiar($_POST['comentario'] ?? '');

        if ($puntuacion < 1 || $puntuacion > 5) {
            $_SESSION['mensaje_error'] = "La puntuación debe estar comprendida entre 1 y 5 estrellas.";
        } else {
            $stmt = $conn->prepare("INSERT INTO calificaciones (id_reclamo, id_proveedor, id_usuario, puntuacion, comentario) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $id_reclamo, $id_proveedor, $u_id, $puntuacion, $comentario);
            if ($stmt->execute()) {
                $_SESSION['mensaje_exito'] = "¡Gracias! Tu calificación fue registrada correctamente.";
                agregarNotificacion($conn, $id_proveedor, "Nueva Calificación Recibida", "Recibiste $puntuacion estrella(s) por tu servicio realizado.", 'success');
            } else {
                $_SESSION['mensaje_error'] = "Error al guardar la calificación.";
            }
            $stmt->close();
        }
        header("Location: index.php");
        exit();
    }

    // 15. CREAR USUARIO (ADMIN)
    if ($accion === 'crear_usuario' && ($u_rol == 1 || $u_rol == 5)) {
        $nombre = limpiar($_POST['nombre'] ?? '');
        $email = limpiar($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_rol_nuevo = intval($_POST['id_rol_nuevo'] ?? 3);
        $uf_nueva = limpiar($_POST['unidad_funcional'] ?? '');
        $telefono = limpiar($_POST['telefono'] ?? '');
        $rubro = limpiar($_POST['rubro'] ?? '');

        if (empty($nombre) || empty($email) || empty($password)) {
            $_SESSION['mensaje_error'] = "Por favor complete nombre, correo y contraseña obligatoriamente.";
        } else {
            try {
                $hash_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, id_rol, unidad_funcional, telefono, rubro) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssisss", $nombre, $email, $hash_pass, $id_rol_nuevo, $uf_nueva, $telefono, $rubro);
                if ($stmt->execute()) {
                    $_SESSION['mensaje_exito'] = "Usuario '$nombre' creado exitosamente.";
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                $_SESSION['mensaje_error'] = "El correo electrónico '$email' ya está registrado por otro usuario.";
            }
        }
        header("Location: index.php");
        exit();
    }

    // 16. EDITAR USUARIO (ADMIN)
    if ($accion === 'editar_usuario' && ($u_rol == 1 || $u_rol == 5)) {
        $id_edit = intval($_POST['id_usuario_edit'] ?? 0);
        $nombre = limpiar($_POST['nombre'] ?? '');
        $email = limpiar($_POST['email'] ?? '');
        $id_rol_edit = intval($_POST['id_rol'] ?? 3);
        $uf_edit = limpiar($_POST['unidad_funcional'] ?? '');
        $telefono = limpiar($_POST['telefono'] ?? '');
        $rubro = limpiar($_POST['rubro'] ?? '');
        $new_pass = $_POST['password'] ?? '';

        if (empty($nombre) || empty($email)) {
            $_SESSION['mensaje_error'] = "El nombre y el correo electrónico son obligatorios.";
        } else {
            $chk_mail = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
            $chk_mail->bind_param("si", $email, $id_edit);
            $chk_mail->execute();
            $res_mail = $chk_mail->get_result();

            if ($res_mail && $res_mail->num_rows > 0) {
                $_SESSION['mensaje_error'] = "El correo electrónico '$email' ya pertenece a otro usuario registrado.";
                $chk_mail->close();
            } else {
                $chk_mail->close();
                try {
                    if (!empty($new_pass)) {
                        $pass_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = ?, id_rol = ?, unidad_funcional = ?, telefono = ?, rubro = ? WHERE id_usuario = ?");
                        $stmt->bind_param("sssisssi", $nombre, $email, $pass_hash, $id_rol_edit, $uf_edit, $telefono, $rubro, $id_edit);
                    } else {
                        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, id_rol = ?, unidad_funcional = ?, telefono = ?, rubro = ? WHERE id_usuario = ?");
                        $stmt->bind_param("ssisssi", $nombre, $email, $id_rol_edit, $uf_edit, $telefono, $rubro, $id_edit);
                    }

                    if ($stmt->execute()) {
                        $_SESSION['mensaje_exito'] = "Usuario '$nombre' actualizado correctamente.";
                        if ($id_edit === $u_id) {
                            $_SESSION['usuario']['nombre'] = $nombre;
                            $_SESSION['usuario']['email'] = $email;
                            $_SESSION['usuario']['unidad_funcional'] = $uf_edit;
                            $_SESSION['usuario']['telefono'] = $telefono;
                            $_SESSION['usuario']['rubro'] = $rubro;
                        }
                    } else {
                        $_SESSION['mensaje_error'] = "Error al actualizar información del usuario.";
                    }
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    $_SESSION['mensaje_error'] = "El correo electrónico '$email' ya se encuentra registrado en el sistema.";
                }
            }
        }
        header("Location: index.php");
        exit();
    }

    // 17. ELIMINAR USUARIO (ADMIN)
    if ($accion === 'eliminar_usuario' && ($u_rol == 1 || $u_rol == 5)) {
        $id_elim = intval($_POST['id_eliminar'] ?? 0);
        if ($id_elim === $u_id) {
            $_SESSION['mensaje_error'] = "No puede eliminar su propia cuenta activa.";
        } else {
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            $stmt->bind_param("i", $id_elim);
            if ($stmt->execute()) {
                $_SESSION['mensaje_exito'] = "Usuario eliminado correctamente del sistema.";
            } else {
                $_SESSION['mensaje_error'] = "No se puede eliminar el usuario porque tiene registros vinculados.";
            }
            $stmt->close();
        }
        header("Location: index.php");
        exit();
    }

    // 18. PUBLICAR COMUNICADO (ADMIN)
    if ($accion === 'publicar_comunicado' && ($u_rol == 1 || $u_rol == 5)) {
        $titulo = limpiar($_POST['titulo'] ?? '');
        $contenido = limpiar($_POST['contenido'] ?? '');

        if (empty($titulo) || empty($contenido)) {
            $_SESSION['mensaje_error'] = "Debe completar el título y el contenido del comunicado.";
        } else {
            $stmt = $conn->prepare("INSERT INTO comunicados (id_admin, titulo, contenido) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $u_id, $titulo, $contenido);
            if ($stmt->execute()) {
                $_SESSION['mensaje_exito'] = "Aviso publicado exitosamente en la cartelera.";
                $res_u = $conn->query("SELECT id_usuario FROM usuarios WHERE id_usuario != $u_id");
                if ($res_u) {
                    while ($u = $res_u->fetch_assoc()) {
                        agregarNotificacion($conn, $u['id_usuario'], "Nuevo Comunicado de Administración", "Se ha publicado el aviso: '$titulo'.", 'info');
                    }
                }
            }
            $stmt->close();
        }
        header("Location: index.php");
        exit();
    }
}