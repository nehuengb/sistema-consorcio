<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sistema_consorcio';
$db_ports = [3306, 3307];

$conn = null;
$db_error = null;

foreach ($db_ports as $port) {
    try {
        @$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $port);
        if (!$conn->connect_error) {
            $conn->set_charset("utf8mb4");
            break;
        }
    } catch (Exception $e) {
        $db_error = $e->getMessage();
    }
}

$mensaje_exito = '';
$mensaje_error = '';

function limpiar($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function agregarNotificacion($conn, $id_usuario, $titulo, $mensaje, $tipo = 'info') {
    if (!$conn) return;
    $stmt = $conn->prepare("INSERT INTO notificaciones (id_usuario, titulo, mensaje, tipo) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $id_usuario, $titulo, $mensaje, $tipo);
        $stmt->execute();
        $stmt->close();
    }
}

function obtenerPromedioProveedor($conn, $id_proveedor) {
    if (!$conn) return ['promedio' => 'S/C', 'cantidad' => 0];
    $resultado = ['promedio' => 'S/C', 'cantidad' => 0];
    
    $id_p = intval($id_proveedor);
    if ($id_p <= 0) return $resultado;
    
    $res = $conn->query("SELECT AVG(puntuacion) as promedio, COUNT(*) as cantidad FROM calificaciones WHERE id_proveedor = $id_p");
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['cantidad']) && $row['cantidad'] > 0) {
            $resultado = [
                'promedio' => number_format($row['promedio'], 1),
                'cantidad' => intval($row['cantidad'])
            ];
        }
    }
    return $resultado;
}
function registrarEnBitacora($conn, $id_reclamo, $tipo_ambito, $uf, $titulo, $descripcion, $monto, $proveedor_nombre) {
    if (!$conn) return;
    $stmt = $conn->prepare("INSERT INTO bitacora_historial (id_reclamo, tipo_ambito, unidad_funcional, titulo, descripcion_trabajo, monto_final, proveedor_nombre) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issssds", $id_reclamo, $tipo_ambito, $uf, $titulo, $descripcion, $monto, $proveedor_nombre);
        $stmt->execute();
        $stmt->close();
    }
}
?>