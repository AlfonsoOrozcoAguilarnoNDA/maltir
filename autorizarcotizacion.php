<?php
// Modelo: KIMI 2.5
// Módulo: Autorización Granular de Cotización por Partida
/**
 * Proyecto MalTir - Sistema de Gestión de Compras por Cotización
 * * Fecha: 15 de marzo de 2026
 * Programador: Alfonso Orozco Aguilar
 * Licencia: GNU Lesser General Public License v2.1 (LGPL 2.1)
 * * Este archivo forma parte del experimento de Vibe Coding "MalTir" (Prudencia),
 * diseñado para evaluar la coherencia lógica de Kimi 2.5 en entornos transaccionales.
 * La lógica de negocio está basada en el sistema original de 2006 (VB6 + SQL 2000).
 * * Repositorio Oficial (Versión más reciente): 
 * https://github.com/AlfonsoOrozcoAguilarnoNDA/maltir
 * * Más información, bitácoras y resultados del experimento en:
 * https://vibecodingmexico.com/?s=maltir
 * * "Los datos no mienten, las personas sí."
 */
?>

<?php require_once 'headerkimi.php'; ?>

<link rel="stylesheet" href="compraskimi.css">

<div id="subcontainer">

<?php
$mensaje = '';
$tipo_mensaje = '';

// Procesar POST de autorización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_autorizacion'])) {
    $idRespuesta = intval($_POST['idRespuesta']);
    $nueva_cantidad = floatval($_POST['cantidad_autorizada']);
    
    // Obtener datos actuales de la respuesta
    $sql = "SELECT r.*, d.cantidad_solicitada, d.idDetalle 
            FROM respuesta_cotizacion r
            JOIN cotizaciones_detalle d ON r.idDetalle = d.idDetalle
            JOIN cotizaciones_maestro m ON d.idCotizacion = m.idCotizacion
            WHERE r.idRespuesta = $idRespuesta AND r.activo = 'si' AND m.cerrada = 'no'";
    $res = mysqli_query($link, $sql);
    
    if ($res && $row = mysqli_fetch_assoc($res)) {
        // Validar que no se intente bajar la cantidad autorizada
        if ($nueva_cantidad < $row['cantidad_autorizada']) {
            $mensaje = 'Error: La cantidad autorizada no puede disminuirse.';
            $tipo_mensaje = 'danger';
        } else {
            // Actualizar respuesta_cotizacion
            $sql_update = "UPDATE respuesta_cotizacion SET 
                           autorizado = 'si',
                           cantidad_autorizada = $nueva_cantidad,
                           fecha_autorizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                           usuario_autoriza = '$session_usuario',
                           ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                           usuario_modifica = '$session_usuario'
                           WHERE idRespuesta = $idRespuesta";
            
            if (mysqli_query($link, $sql_update)) {
                // Actualizar cotizaciones_detalle: marcar comprar='si' y sumar cantidad_autorizada
                $idDetalle = $row['idDetalle'];
                $sql_det = "UPDATE cotizaciones_detalle SET 
                            comprar = 'si',
                            cantidad_autorizada = cantidad_autorizada + $nueva_cantidad,
                            ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                            usuario_modifica = '$session_usuario'
                            WHERE idDetalle = $idDetalle";
                mysqli_query($link, $sql_det);
                
                $mensaje = 'Autorización guardada correctamente. Esta acción es IRREVERSIBLE.';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al autorizar: ' . mysqli_error($link);
                $tipo_mensaje = 'danger';
            }
        }
    }
}

$idCotizacion_sel = isset($_POST['idCotizacion']) ? intval($_POST['idCotizacion']) : 0;
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-check-double mr-2"></i> Autorizar Cotización por Partida
        </div>
        <div class="card-body">
            
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-sistema">
                <i class="fas fa-info-circle mr-2"></i> <?php echo $mensaje; ?>
            </div>
            <?php endif; ?>
            
            <!-- Selección de Cotización -->
            <form method="POST" class="form-sistema mb-4">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Cotización Abierta <span class="text-danger">*</span></label>
                            <select name="idCotizacion" class="form-control custom-select" onchange="this.form.submit()" required>
                                <option value="">Seleccione cotización...</option>
                                <?php
                                $res = mysqli_query($link, "SELECT idCotizacion, comentario, fecha_cotizacion 
                                                            FROM cotizaciones_maestro 
                                                            WHERE cerrada = 'no' AND activo = 'si' 
                                                            ORDER BY idCotizacion DESC");
                                while ($row = mysqli_fetch_assoc($res)) {
                                    $selected = ($idCotizacion_sel == $row['idCotizacion']) ? 'selected' : '';
                                    $fecha = $row['fecha_cotizacion'] ? date('d/m/Y', strtotime($row['fecha_cotizacion'])) : 'Sin confirmar';
                                    echo '<option value="' . $row['idCotizacion'] . '" ' . $selected . '>
                                            #' . $row['idCotizacion'] . ' - ' . $fecha . ' - ' . htmlspecialchars(substr($row['comentario'], 0, 50)) . '
                                          </option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
            
            <?php if ($idCotizacion_sel): ?>
            
            <!-- Listado de Partidas con Respuestas -->
            <div class="table-responsive">
                <table class="table table-sistema">
                    <thead>
                        <tr>
                            <th>Partida</th>
                            <th>Artículo</th>
                            <th>Cantidad Solicitada</th>
                            <th>Proveedor</th>
                            <th>Precio Total</th>
                            <th>Fecha Comprometida</th>
                            <th>Estado Autorización</th>
                            <th>Cantidad Autorizada</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT d.idDetalle, d.cantidad_solicitada, d.cantidad_autorizada as total_autorizada_detalle,
                                a.nombre as articulo_nombre,
                                r.idRespuesta, r.idProveedor, r.precio_total, r.fecha_comprometida, 
                                r.autorizado, r.cantidad_autorizada, p.nombre as proveedor_nombre
                                FROM cotizaciones_detalle d
                                JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                                LEFT JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.activo = 'si'
                                LEFT JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
                                WHERE d.idCotizacion = $idCotizacion_sel AND d.activo = 'si'
                                ORDER BY d.idDetalle, r.precio_total ASC";
                        $res = mysqli_query($link, $sql);
                        
                        $partida_actual = 0;
                        while ($row = mysqli_fetch_assoc($res)) {
                            // Si es nueva partida, mostrar separador visual
                            if ($partida_actual != $row['idDetalle']) {
                                $partida_actual = $row['idDetalle'];
                                $pendiente = $row['cantidad_solicitada'] - $row['total_autorizada_detalle'];
                                echo '<tr class="table-secondary">';
                                echo '<td colspan="9" class="font-weight-bold">';
                                echo '<i class="fas fa-box mr-2"></i> Partida #' . $row['idDetalle'] . ' - ' . htmlspecialchars($row['articulo_nombre']);
                                echo ' <span class="badge badge-info">Solicitado: ' . number_format($row['cantidad_solicitada'], 6) . '</span>';
                                echo ' <span class="badge badge-success">Autorizado: ' . number_format($row['total_autorizada_detalle'], 6) . '</span>';
                                if ($pendiente > 0) {
                                    echo ' <span class="badge badge-warning">Pendiente: ' . number_format($pendiente, 6) . '</span>';
                                }
                                echo '</td>';
                                echo '</tr>';
                            }
                            
                            if ($row['idRespuesta']) {
                                echo '<tr>';
                                echo '<td></td>';
                                echo '<td></td>';
                                echo '<td></td>';
                                echo '<td>' . htmlspecialchars($row['proveedor_nombre']) . '</td>';
                                echo '<td>$' . number_format($row['precio_total'], 2) . '</td>';
                                echo '<td>' . date('d/m/Y', strtotime($row['fecha_comprometida'])) . '</td>';
                                
                                if ($row['autorizado'] == 'si') {
                                    echo '<td><span class="badge badge-success"><i class="fas fa-check mr-1"></i> Autorizado</span></td>';
                                    echo '<td class="font-weight-bold text-success">' . number_format($row['cantidad_autorizada'], 6) . '</td>';
                                    echo '<td><button class="btn btn-secondary btn-sm" disabled><i class="fas fa-lock"></i></button></td>';
                                } else {
                                    $pendiente_partida = $row['cantidad_solicitada'] - $row['total_autorizada_detalle'];
                                    echo '<td><span class="badge badge-warning">Pendiente</span></td>';
                                    echo '<td>-</td>';
                                    echo '<td>';
                                    if ($pendiente_partida > 0) {
                                        echo '<button type="button" class="btn btn-guardar btn-sm" data-toggle="modal" data-target="#modalAutorizar' . $row['idRespuesta'] . '">
                                                <i class="fas fa-check mr-1"></i> Autorizar
                                              </button>';
                                    } else {
                                        echo '<span class="text-muted">Cantidad completa</span>';
                                    }
                                    echo '</td>';
                                }
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Nota:</strong> Una misma partida puede autorizarse a más de un proveedor (split de compra). 
                Diferentes partidas pueden autorizarse a diferentes proveedores. La autorización es <strong>IRREVERSIBLE</strong>.
            </div>
            
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

<!-- Modales de Autorización -->
<?php
if ($idCotizacion_sel) {
    $sql = "SELECT d.idDetalle, d.cantidad_solicitada, d.cantidad_autorizada as total_autorizada_detalle,
            a.nombre as articulo_nombre,
            r.idRespuesta, r.idProveedor, r.precio_total, r.cantidad_cotizada,
            p.nombre as proveedor_nombre
            FROM cotizaciones_detalle d
            JOIN cat_articulos a ON d.idArticulo = a.idArticulo
            JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.activo = 'si' AND r.autorizado = 'no'
            JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
            WHERE d.idCotizacion = $idCotizacion_sel AND d.activo = 'si'";
    $res = mysqli_query($link, $sql);
    
    while ($row = mysqli_fetch_assoc($res)) {
        $pendiente = $row['cantidad_solicitada'] - $row['total_autorizada_detalle'];
        $max_autorizar = min($row['cantidad_cotizada'], $pendiente);
?>
<div class="modal fade" id="modalAutorizar<?php echo $row['idRespuesta']; ?>" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" onsubmit="return confirm('¿Confirma la autorización? Esta acción es IRREVERSIBLE.');">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle mr-2"></i> Autorizar Compra
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>La autorización es IRREVERSIBLE</strong> y no podrá modificarse una vez guardada.
                    </div>
                    
                    <input type="hidden" name="idRespuesta" value="<?php echo $row['idRespuesta']; ?>">
                    <input type="hidden" name="idCotizacion" value="<?php echo $idCotizacion_sel; ?>">
                    
                    <div class="form-group">
                        <label>Artículo</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['articulo_nombre']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Proveedor</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['proveedor_nombre']); ?>" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cantidad Cotizada</label>
                                <input type="text" class="form-control" value="<?php echo number_format($row['cantidad_cotizada'], 6); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Pendiente por Autorizar</label>
                                <input type="text" class="form-control" value="<?php echo number_format($pendiente, 6); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Cantidad a Autorizar <span class="text-danger">*</span></label>
                        <input type="number" name="cantidad_autorizada" class="form-control" 
                               step="0.000001" min="0.000001" 
                               max="<?php echo $max_autorizar; ?>" 
                               value="<?php echo $max_autorizar; ?>" required>
                        <small class="ayuda-campo">Máximo: <?php echo number_format($max_autorizar, 6); ?> (no puede disminuirse después)</small>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="confirmar_autorizacion" class="btn btn-guardar">
                        <i class="fas fa-lock mr-2"></i> Confirmar Autorización
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php 
    }
}
?>

</div>

<?php require_once 'footerkimi.php'; ?>
