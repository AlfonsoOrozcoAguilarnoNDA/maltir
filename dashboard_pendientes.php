<?php
/**
 * Proyecto MalTir - Sistema de Gestión de Compras por Cotización
 * 
 * Fecha: 15 de marzo de 2026
 * Programador: Alfonso Orozco Aguilar
 * Licencia: GNU Lesser General Public License v2.1 (LGPL 2.1)
 * 
 * Este archivo forma parte del experimento de Vibe Coding "MalTir" (Prudencia),
 * diseñado para evaluar la coherencia lógica de Kimi 2.5 en entornos transaccionales.
 * La lógica de negocio está basada en el sistema original de 2006 (VB6 + SQL 2000).
 * 
 * Repositorio Oficial (Versión más reciente): 
 * https://github.com/AlfonsoOrozcoAguilarnoNDA/maltir 
 * 
 * Más información, bitácoras y resultados del experimento en:
 * https://vibecodingmexico.com/?s=maltir 
 * 
 * "Los datos no mienten, las personas sí."
 */

// Modelo: KIMI 2.5
// Módulo: Dashboard Principal de Seguimiento de Compras Pendientes
// CORREGIDO: Ahora muestra pendientes por respuesta_cotizacion (cada proveedor separado)
// ya que una partida puede tener split entre múltiples proveedores
?>

<?php require_once 'headerkimi.php'; ?>

<link rel="stylesheet" href="compraskimi.css">

<div id="subcontainer">

<?php
$usuario_sanitizado = mysqli_real_escape_string($link, $session_usuario);

// Procesar filtros
$idProveedor_filtro = isset($_POST['idProveedor']) ? intval($_POST['idProveedor']) : 0;
$fecha_desde = isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : '';
$fecha_hasta = isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : '';

// Construir WHERE base - ahora desde respuesta_cotizacion
$where = "WHERE r.autorizado = 'si' AND r.activo = 'si' AND c.activo = 'si'";

if ($idProveedor_filtro > 0) {
    $where .= " AND r.idProveedor = $idProveedor_filtro";
}
if ($fecha_desde) {
    $where .= " AND d.fecha_requerida >= '$fecha_desde'";
}
if ($fecha_hasta) {
    $where .= " AND d.fecha_requerida <= '$fecha_hasta'";
}

// Obtener pendientes desde respuesta_cotizacion (no desde detalle directamente)
// Esto permite ver split de compras: una partida puede tener múltiples proveedores
$sql = "SELECT 
            r.idRespuesta,
            r.idProveedor,
            r.precio_total,
            r.cantidad_cotizada,
            r.cantidad_autorizada,
            r.fecha_comprometida,
            r.ajuste,
            r.fecha_autorizacion,
            d.idDetalle,
            d.idCotizacion,
            d.fecha_requerida,
            d.cantidad_recibida as total_recibido_detalle, -- Lo que ya llegó de esta partida (de todos los proveedores)
            a.nombre as articulo_nombre,
            a.idArticulo,
            u.idUnidadMedida,
            p.nombre as proveedor_nombre,
            c.comentario as cotizacion_comentario,
            -- Calcular cuánto ya se recibió específicamente de este proveedor/respuesta
            -- Nota: En el modelo actual, cantidad_recibida está en detalle (acumulado de todos los proveedores)
            -- Para split exacto, se necesitaría una tabla de recepciones por respuesta
            -- Por ahora, asumimos proporcional o usamos el total recibido del detalle
            (SELECT SUM(cantidad) FROM kardex k 
             WHERE k.idArticulo = a.idArticulo 
             AND k.referencia LIKE CONCAT('Cot ', d.idCotizacion, '%')
             AND k.tipo_movimiento = 'entrada'
             AND k.fecha_movimiento >= r.fecha_autorizacion) as cantidad_recibida_estimada
        FROM respuesta_cotizacion r
        JOIN cotizaciones_detalle d ON r.idDetalle = d.idDetalle AND d.activo = 'si'
        JOIN cat_articulos a ON d.idArticulo = a.idArticulo
        JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
        JOIN cotizaciones_maestro c ON d.idCotizacion = c.idCotizacion
        JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
        $where
        HAVING (r.cantidad_autorizada - IFNULL(cantidad_recibida_estimada, 0)) > 0
        ORDER BY d.fecha_requerida ASC, r.idRespuesta";

$res = mysqli_query($link, $sql);

// Si la query anterior es muy compleja, usar esta versión simplificada:
// Muestra respuestas autorizadas donde el detalle aún tiene pendiente por recibir
$sql_simple = "SELECT 
                r.idRespuesta,
                r.idProveedor,
                r.precio_total,
                r.cantidad_cotizada,
                r.cantidad_autorizada,
                r.fecha_comprometida,
                r.ajuste,
                d.idDetalle,
                d.idCotizacion,
                d.fecha_requerida,
                d.cantidad_recibida,
                d.cantidad_solicitada,
                a.nombre as articulo_nombre,
                a.idArticulo,
                u.idUnidadMedida,
                p.nombre as proveedor_nombre,
                c.comentario as cotizacion_comentario
               FROM respuesta_cotizacion r
               JOIN cotizaciones_detalle d ON r.idDetalle = d.idDetalle AND d.activo = 'si'
               JOIN cat_articulos a ON d.idArticulo = a.idArticulo
               JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
               JOIN cotizaciones_maestro c ON d.idCotizacion = c.idCotizacion
               JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
               WHERE r.autorizado = 'si' 
               AND r.activo = 'si' 
               AND c.activo = 'si'
               AND d.cantidad_recibida < d.cantidad_autorizada";

if ($idProveedor_filtro > 0) {
    $sql_simple .= " AND r.idProveedor = $idProveedor_filtro";
}
if ($fecha_desde) {
    $sql_simple .= " AND d.fecha_requerida >= '$fecha_desde'";
}
if ($fecha_hasta) {
    $sql_simple .= " AND d.fecha_requerida <= '$fecha_hasta'";
}
$sql_simple .= " ORDER BY d.fecha_requerida ASC, r.idRespuesta";

$res = mysqli_query($link, $sql_simple);

// Calcular estatus basado en reglas
function calcularEstatus($fecha_requerida, $fecha_comprometida, $ajuste, $idArticulo, $idProveedor, $link) {
    $hoy = new DateTime();
    $fecha_req = new DateTime($fecha_requerida);
    $dias_para_requerida = $hoy->diff($fecha_req)->days;
    $es_pasado = $hoy > $fecha_req;
    
    // Si ya tiene ajuste positivo (excedente/urgente) = avión
    if ($ajuste > 0) {
        return ['clase' => 'status-avion', 'icono' => 'fa-plane', 'texto' => 'Envío urgente aéreo', 'color_fila' => 'table-warning'];
    }
    
    // Calcular si llegará a tiempo basado en historial kardex
    $dias_promedio = null;
    $sql_hist = "SELECT AVG(DATEDIFF(k.fecha_movimiento, r.fecha_autorizacion)) as dias_promedio
                 FROM kardex k
                 JOIN cotizaciones_detalle d ON k.idArticulo = d.idArticulo
                 JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle
                 WHERE k.idArticulo = $idArticulo AND r.idProveedor = $idProveedor
                 AND k.tipo_movimiento = 'entrada' AND r.fecha_autorizacion IS NOT NULL
                 HAVING dias_promedio IS NOT NULL";
    $res_hist = mysqli_query($link, $sql_hist);
    if ($res_hist && $row_hist = mysqli_fetch_assoc($res_hist)) {
        $dias_promedio = ceil(abs($row_hist['dias_promedio']));
    }
    
    // Si por historial no llegará a tiempo = bomba
    if ($dias_promedio !== null && $fecha_comprometida) {
        $fecha_comp = new DateTime($fecha_comprometida);
        $dias_necesarios = $hoy->diff($fecha_comp)->days;
        
        if ($dias_necesarios < $dias_promedio && !$es_pasado) {
            return ['clase' => 'status-bomba', 'icono' => 'fa-bomb', 'texto' => 'Va a estallar - no llegará', 'color_fila' => 'table-danger'];
        }
    }
    
    // Si faltan 7 días o menos = warning
    if ($dias_para_requerida <= 7 && !$es_pasado) {
        return ['clase' => 'status-warning', 'icono' => 'fa-exclamation-circle', 'texto' => 'Faltan ' . $dias_para_requerida . ' días', 'color_fila' => 'table-warning'];
    }
    
    // Si ya pasó la fecha requerida = bomba
    if ($es_pasado) {
        return ['clase' => 'status-bomba', 'icono' => 'fa-bomb', 'texto' => 'Vencido', 'color_fila' => 'table-danger'];
    }
    
    // Default = ok
    return ['clase' => 'status-ok', 'icono' => 'fa-check', 'texto' => 'A tiempo', 'color_fila' => ''];
}

// Calcular días margen
function calcularDiasMargen($fecha_comprometida, $fecha_requerida) {
    $hoy = new DateTime();
    
    if ($fecha_comprometida) {
        $fecha_comp = new DateTime($fecha_comprometida);
        $diff = $hoy->diff($fecha_comp);
        $dias = $diff->days;
        return ($hoy > $fecha_comp) ? -$dias : $dias;
    }
    
    $fecha_req = new DateTime($fecha_requerida);
    $diff = $hoy->diff($fecha_req);
    $dias = $diff->days;
    return ($hoy > $fecha_req) ? -$dias : $dias;
}
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-tachometer-alt mr-2"></i> Dashboard de Partidas Pendientes
            <small class="float-right">Basado en respuestas de proveedor autorizadas</small>
        </div>
        <div class="card-body">
            
            <!-- Filtros -->
            <form method="POST" class="form-sistema mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Proveedor</label>
                            <select name="idProveedor" class="form-control custom-select" onchange="this.form.submit()">
                                <option value="0">Todos los proveedores</option>
                                <?php
                                $res_prov = mysqli_query($link, "SELECT DISTINCT p.idProveedor, p.nombre 
                                                                 FROM cat_proveedores p
                                                                 JOIN respuesta_cotizacion r ON p.idProveedor = r.idProveedor
                                                                 WHERE r.autorizado = 'si' AND r.activo = 'si'
                                                                 AND p.activo = 'si' ORDER BY p.nombre");
                                while ($row = mysqli_fetch_assoc($res_prov)) {
                                    $selected = ($idProveedor_filtro == $row['idProveedor']) ? 'selected' : '';
                                    echo '<option value="' . $row['idProveedor'] . '" ' . $selected . '>' . htmlspecialchars($row['nombre']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Fecha Requerida Desde</label>
                            <input type="datetime-local" name="fecha_desde" class="form-control" value="<?php echo $fecha_desde; ?>" onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Fecha Requerida Hasta</label>
                            <input type="datetime-local" name="fecha_hasta" class="form-control" value="<?php echo $fecha_hasta; ?>" onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-guardar btn-block">
                                <i class="fas fa-filter mr-2"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Leyenda de colores -->
            <div class="mb-3">
                <span class="mr-3"><i class="fas fa-check text-success mr-1"></i> A tiempo</span>
                <span class="mr-3"><i class="fas fa-exclamation-circle text-warning mr-1"></i> &lt; 7 días</span>
                <span class="mr-3"><i class="fas fa-plane mr-1" style="color:#e67e22"></i> Envío aéreo</span>
                <span><i class="fas fa-bomb text-danger mr-1"></i> Crítico</span>
            </div>
            
            <!-- Leyenda de Días Margen -->
            <div class="alert alert-secondary mb-3">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Días Margen:</strong> 
                <span class="text-success font-weight-bold">Positivo</span> = días de margen antes de fecha comprometida. 
                <span class="text-danger font-weight-bold">Negativo</span> = días de atraso después de fecha comprometida.
                <br><small class="text-muted">Nota: Una partida puede aparecer dividida si tiene múltiples proveedores autorizados (split).</small>
            </div>
            
            <!-- Tabla de Pendientes -->
            <div class="table-responsive">
                <table class="table table-sistema">
                    <thead>
                        <tr>
                            <th>Estatus</th>
                            <th>Cotización</th>
                            <th>Partida</th>
                            <th>Artículo</th>
                            <th>Proveedor</th>
                            <th>Autorizado</th>
                            <th>Recibido</th>
                            <th>Pendiente</th>
                            <th>Importe Estimado</th>
                            <th>Fecha Requerida</th>
                            <th>Fecha Comprometida</th>
                            <th>Días Margen</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_importe = 0;
                        $partidas_procesadas = []; // Para detectar splits
                        
                        while ($row = mysqli_fetch_assoc($res)) {
                            // Calcular pendiente para ESTA respuesta específica
                            // Nota: En split, el recibido del detalle es acumulado de todos los proveedores
                            // Estimamos proporcional o usamos lógica de negocio específica
                            $cantidad_autorizada_resp = $row['cantidad_autorizada'];
                            
                            // Si hay recibido en el detalle, estimamos cuánto corresponde a esta respuesta
                            // Lógica simplificada: si solo hay una respuesta autorizada para esta partida, todo el recibido es de ella
                            $sql_count_resp = "SELECT COUNT(*) as total_resp, SUM(cantidad_autorizada) as total_aut 
                                               FROM respuesta_cotizacion 
                                               WHERE idDetalle = {$row['idDetalle']} AND autorizado = 'si' AND activo = 'si'";
                            $res_count = mysqli_query($link, $sql_count_resp);
                            $count_data = mysqli_fetch_assoc($res_count);
                            
                            if ($count_data['total_resp'] == 1) {
                                // Solo un proveedor, todo el recibido es de él
                                $recibido_esta_resp = $row['cantidad_recibida'];
                            } else {
                                // Múltiples proveedores (split) - estimamos proporcional
                                $proporcion = $cantidad_autorizada_resp / $count_data['total_aut'];
                                $recibido_esta_resp = $row['cantidad_recibida'] * $proporcion;
                            }
                            
                            $cantidad_pendiente = $cantidad_autorizada_resp - $recibido_esta_resp;
                            
                            // Solo mostrar si realmente hay pendiente
                            if ($cantidad_pendiente <= 0.000001) continue;
                            
                            $precio_unitario = $row['precio_total'] / $row['cantidad_cotizada'];
                            $importe_estimado = $precio_unitario * $cantidad_pendiente;
                            $total_importe += $importe_estimado;
                            
                            $estatus = calcularEstatus($row['fecha_requerida'], $row['fecha_comprometida'], null, $row['ajuste'], $row['idArticulo'], $row['idProveedor'], $link);
                            
                            $dias_margen = calcularDiasMargen($row['fecha_comprometida'], $row['fecha_requerida']);
                            
                            // Clase para días margen
                            if ($dias_margen > 7) {
                                $clase_margen = 'text-success font-weight-bold';
                                $icono_margen = '<i class="fas fa-arrow-up mr-1"></i>';
                            } elseif ($dias_margen > 0) {
                                $clase_margen = 'text-warning font-weight-bold';
                                $icono_margen = '<i class="fas fa-clock mr-1"></i>';
                            } else {
                                $clase_margen = 'text-danger font-weight-bold';
                                $icono_margen = '<i class="fas fa-arrow-down mr-1"></i>';
                            }
                            
                            // Detectar si es split (múltiples proveedores en misma partida)
                            $es_split = ($count_data['total_resp'] > 1) ? ' <span class="badge badge-info" title="Split de compra">S</span>' : '';
                            
                            echo '<tr class="' . $estatus['color_fila'] . '">';
                            echo '<td class="text-center">';
                            echo '<i class="fas ' . $estatus['icono'] . ' ' . $estatus['clase'] . '" style="font-size:1.3rem;" title="' . $estatus['texto'] . '"></i>';
                            echo '</td>';
                            echo '<td class="font-weight-bold">#' . $row['idCotizacion'] . '</td>';
                            echo '<td>' . $row['idDetalle'] . $es_split . '</td>';
                            echo '<td>' . htmlspecialchars($row['articulo_nombre']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['proveedor_nombre']) . '</td>';
                            echo '<td class="text-right">' . number_format($cantidad_autorizada_resp, 6) . ' ' . $row['idUnidadMedida'] . '</td>';
                            echo '<td class="text-right text-muted">' . number_format($recibido_esta_resp, 6) . '</td>';
                            echo '<td class="font-weight-bold text-primary text-right">' . number_format($cantidad_pendiente, 6) . '</td>';
                            echo '<td class="text-right">$' . number_format($importe_estimado, 2) . '</td>';
                            echo '<td>' . date('d/m/Y', strtotime($row['fecha_requerida'])) . '</td>';
                            echo '<td>' . ($row['fecha_comprometida'] ? date('d/m/Y', strtotime($row['fecha_comprometida'])) : '-') . '</td>';
                            echo '<td class="' . $clase_margen . ' text-center">';
                            echo $icono_margen . $dias_margen;
                            echo '</td>';
                            echo '<td>';
                            echo '<form method="POST" action="generar_correo.php" style="display:inline;">';
                            echo '<input type="hidden" name="idCotizacion" value="' . $row['idCotizacion'] . '">';
                            echo '<button type="submit" class="btn btn-info btn-sm">';
                            echo '<i class="fas fa-envelope mr-1"></i> Correo';
                            echo '</button>';
                            echo '</form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        if (mysqli_num_rows($res) == 0 || $total_importe == 0) {
                            echo '<tr><td colspan="13" class="text-center text-muted py-4">';
                            echo '<i class="fas fa-check-circle fa-3x mb-3 text-success"></i><br>';
                            echo 'No hay partidas pendientes de recepción';
                            echo '</td></tr>';
                        }
                        ?>
                    </tbody>
                    <?php if ($total_importe > 0): ?>
                    <tfoot class="thead-light">
                        <tr>
                            <td colspan="8" class="text-right font-weight-bold">Total Importe Estimado:</td>
                            <td class="text-right font-weight-bold text-primary">$<?php echo number_format($total_importe, 2); ?></td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            
        </div>
    </div>
    
</div>

</div>

<?php require_once 'footerkimi.php'; ?>
