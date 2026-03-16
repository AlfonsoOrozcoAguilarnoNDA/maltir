<?php
/**
 * Proyecto MalTir - Sistema de Gestión de Compras por Cotización
 * 
 * Fecha: 16 de marzo de 2026
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
// Actualizado: Columna "Días Margen" agregada - positivo = a tiempo, negativo = atrasado
?>

<?php require_once 'headerkimi.php'; ?>

<link rel="stylesheet" href="compraskimi.css">

<div id="subcontainer">

<?php
// Procesar filtros
$idProveedor_filtro = isset($_POST['idProveedor']) ? intval($_POST['idProveedor']) : 0;
$fecha_desde = isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : '';
$fecha_hasta = isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : '';

// Construir WHERE base
$where = "WHERE d.activo = 'si' AND d.comprar = 'si' AND d.cantidad_recibida < d.cantidad_autorizada";

if ($idProveedor_filtro > 0) {
    $where .= " AND r.idProveedor = $idProveedor_filtro";
}
if ($fecha_desde) {
    $where .= " AND d.fecha_requerida >= '$fecha_desde'";
}
if ($fecha_hasta) {
    $where .= " AND d.fecha_requerida <= '$fecha_hasta'";
}

// Obtener partidas pendientes
$sql = "SELECT d.*, 
        a.nombre as articulo_nombre, 
        u.idUnidadMedida,
        p.nombre as proveedor_nombre,
        c.idCotizacion,
        c.comentario as cotizacion_comentario,
        r.precio_total,
        r.cantidad_cotizada,
        r.ajuste,
        r.fecha_comprometida
        FROM cotizaciones_detalle d
        JOIN cat_articulos a ON d.idArticulo = a.idArticulo
        JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
        JOIN cotizaciones_maestro c ON d.idCotizacion = c.idCotizacion
        JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.autorizado = 'si' AND r.activo = 'si'
        JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
        $where
        ORDER BY d.fecha_requerida ASC";

$res = mysqli_query($link, $sql);

// Calcular estatus basado en reglas
function calcularEstatus($fecha_requerida, $fecha_comprometida, $fecha_recibida, $ajuste, $idArticulo, $idProveedor, $link) {
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
    
    // Si ya pasó la fecha requerida y no se recibió = bomba
    if ($es_pasado && !$fecha_recibida) {
        return ['clase' => 'status-bomba', 'icono' => 'fa-bomb', 'texto' => 'Vencido - no recibido', 'color_fila' => 'table-danger'];
    }
    
    // Default = ok
    return ['clase' => 'status-ok', 'icono' => 'fa-check', 'texto' => 'A tiempo', 'color_fila' => ''];
}

// Calcular días margen (nueva función)
function calcularDiasMargen($fecha_comprometida, $fecha_requerida) {
    $hoy = new DateTime();
    
    // Si hay fecha comprometida, calcular respecto a esa
    if ($fecha_comprometida) {
        $fecha_comp = new DateTime($fecha_comprometida);
        $diff = $hoy->diff($fecha_comp);
        $dias = $diff->days;
        
        // Si hoy > fecha comprometida = negativo (atrasado)
        if ($hoy > $fecha_comp) {
            return -$dias; // Negativo = días de atraso
        } else {
            return $dias; // Positivo = días de margen
        }
    }
    
    // Si no hay fecha comprometida, calcular respecto a fecha requerida
    $fecha_req = new DateTime($fecha_requerida);
    $diff = $hoy->diff($fecha_req);
    $dias = $diff->days;
    
    if ($hoy > $fecha_req) {
        return -$dias; // Negativo = días de atraso vs requerida
    } else {
        return $dias; // Positivo = días de margen vs requerida
    }
}
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-tachometer-alt mr-2"></i> Dashboard de Partidas Pendientes
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
                                                                 JOIN cotizaciones_detalle d ON r.idDetalle = d.idDetalle
                                                                 WHERE d.comprar = 'si' AND d.cantidad_recibida < d.cantidad_autorizada
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
            </div>
            
            <!-- Tabla de Pendientes -->
            <div class="table-responsive">
                <table class="table table-sistema">
                    <thead>
                        <tr>
                            <th>Estatus</th>
                            <th>Cotización</th>
                            <th>Artículo</th>
                            <th>Proveedor</th>
                            <th>Cantidad Pendiente</th>
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
                        while ($row = mysqli_fetch_assoc($res)) {
                            $cantidad_pendiente = $row['cantidad_autorizada'] - $row['cantidad_recibida'];
                            $precio_unitario = $row['precio_total'] / $row['cantidad_cotizada'];
                            $importe_estimado = $precio_unitario * $cantidad_pendiente;
                            $total_importe += $importe_estimado;
                            
                            $estatus = calcularEstatus($row['fecha_requerida'], $row['fecha_comprometida'], $row['fecha_recibida'], $row['ajuste'], $row['idArticulo'], $row['idProveedor'], $link);
                            
                            // Calcular días margen
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
                            
                            echo '<tr class="' . $estatus['color_fila'] . '">';
                            echo '<td class="text-center">';
                            echo '<i class="fas ' . $estatus['icono'] . ' ' . $estatus['clase'] . '" style="font-size:1.3rem;" title="' . $estatus['texto'] . '"></i>';
                            echo '</td>';
                            echo '<td class="font-weight-bold">#' . $row['idCotizacion'] . '</td>';
                            echo '<td>' . htmlspecialchars($row['articulo_nombre']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['proveedor_nombre']) . '</td>';
                            echo '<td class="font-weight-bold">' . number_format($cantidad_pendiente, 6) . ' ' . $row['idUnidadMedida'] . '</td>';
                            echo '<td class="text-right">$' . number_format($importe_estimado, 2) . '</td>';
                            echo '<td>' . date('d/m/Y', strtotime($row['fecha_requerida'])) . '</td>';
                            echo '<td>' . ($row['fecha_comprometida'] ? date('d/m/Y', strtotime($row['fecha_comprometida'])) : '-') . '</td>';
                            
                            // Nueva columna: Días Margen
                            echo '<td class="' . $clase_margen . '">';
                            echo $icono_margen . $dias_margen;
                            if ($dias_margen > 0) {
                                echo ' <small>días</small>';
                            } else {
                                echo ' <small>días atraso</small>';
                            }
                            echo '</td>';
                            
                            echo '<td>';
                            echo '<form method="POST" action="generar_correo.php" style="display:inline;">';
                            echo '<input type="hidden" name="idCotizacion" value="' . $row['idCotizacion'] . '">';
                            echo '<button type="submit" class="btn btn-info btn-sm">';
                            echo '<i class="fas fa-envelope mr-1"></i> Generar correo';
                            echo '</button>';
                            echo '</form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        if (mysqli_num_rows($res) == 0) {
                            echo '<tr><td colspan="10" class="text-center text-muted py-4">';
                            echo '<i class="fas fa-check-circle fa-3x mb-3 text-success"></i><br>';
                            echo 'No hay partidas pendientes de recepción';
                            echo '</td></tr>';
                        }
                        ?>
                    </tbody>
                    <?php if (mysqli_num_rows($res) > 0): ?>
                    <tfoot class="thead-light">
                        <tr>
                            <td colspan="5" class="text-right font-weight-bold">Total Importe Estimado:</td>
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
