<?php
// Modelo: KIMI 2.5
// Módulo: Cierre Formal de Cotización con Ajustes
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

// Procesar cierre definitivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_cierre'])) {
    $idCotizacion = intval($_POST['idCotizacion']);
    $motivo_cierre = mysqli_real_escape_string($link, $_POST['motivo_cierre']);
    $hubo_incidencia = mysqli_real_escape_string($link, $_POST['hubo_incidencia']);
    $descripcion_incidencia = mysqli_real_escape_string($link, $_POST['descripcion_incidencia'] ?? '');
    
    // Validar que si hay incidencia, tenga descripción
    if ($hubo_incidencia == 'si' && empty($descripcion_incidencia)) {
        $mensaje = 'Debe describir la incidencia cuando indica que hubo una.';
        $tipo_mensaje = 'danger';
    } else {
        // Actualizar cotización maestro
        $sql = "UPDATE cotizaciones_maestro SET 
                cerrada = 'si',
                fecha_cierre = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                motivo_cierre = '$motivo_cierre',
                hubo_incidencia = '$hubo_incidencia',
                descripcion_incidencia = '$descripcion_incidencia',
                ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                usuario_modifica = '$session_usuario'
                WHERE idCotizacion = $idCotizacion";
        
        if (mysqli_query($link, $sql)) {
            $mensaje = 'Cotización cerrada correctamente. Esta acción es IRREVERSIBLE.';
            $tipo_mensaje = 'success';
            unset($_POST['idCotizacion']); // Limpiar selección
        } else {
            $mensaje = 'Error al cerrar: ' . mysqli_error($link);
            $tipo_mensaje = 'danger';
        }
    }
}

// Procesar guardado de ajustes (antes del cierre)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_ajuste'])) {
    $idRespuesta = intval($_POST['idRespuesta']);
    $ajuste = floatval($_POST['ajuste']);
    
    // Validar que no exista ajuste previo
    $check = mysqli_query($link, "SELECT ajuste FROM respuesta_cotizacion WHERE idRespuesta = $idRespuesta AND ajuste = 0");
    if (mysqli_num_rows($check) == 0) {
        $mensaje = 'Error: Esta partida ya tiene un ajuste registrado. El ajuste es de una sola vez.';
        $tipo_mensaje = 'danger';
    } else {
        $sql = "UPDATE respuesta_cotizacion SET 
                ajuste = $ajuste,
                ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                usuario_modifica = '$session_usuario'
                WHERE idRespuesta = $idRespuesta";
        
        if (mysqli_query($link, $sql)) {
            $mensaje = 'Ajuste guardado correctamente.';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al guardar ajuste: ' . mysqli_error($link);
            $tipo_mensaje = 'danger';
        }
    }
}

$idCotizacion_sel = isset($_POST['idCotizacion']) ? intval($_POST['idCotizacion']) : 0;
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-lock mr-2"></i> Cerrar Cotización
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
            
            <!-- Resumen de Partidas con Ajustes -->
            <h5 class="mb-3"><i class="fas fa-clipboard-check mr-2"></i> Partidas y Ajustes Finales</h5>
            
            <div class="table-responsive mb-4">
                <table class="table table-sistema">
                    <thead>
                        <tr>
                            <th>Partida</th>
                            <th>Artículo</th>
                            <th>Proveedor</th>
                            <th>Cantidad Autorizada</th>
                            <th>Cantidad Recibida</th>
                            <th>Ajuste Actual</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT d.idDetalle, a.nombre as articulo_nombre,
                                r.idRespuesta, r.idProveedor, r.cantidad_autorizada, r.ajuste,
                                p.nombre as proveedor_nombre,
                                SUM(d.cantidad_recibida) as total_recibido
                                FROM cotizaciones_detalle d
                                JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                                JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.activo = 'si' AND r.autorizado = 'si'
                                JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
                                WHERE d.idCotizacion = $idCotizacion_sel AND d.activo = 'si'
                                GROUP BY r.idRespuesta
                                ORDER BY d.idDetalle";
                        $res = mysqli_query($link, $sql);
                        
                        while ($row = mysqli_fetch_assoc($res)) {
                            echo '<tr>';
                            echo '<td>' . $row['idDetalle'] . '</td>';
                            echo '<td>' . htmlspecialchars($row['articulo_nombre']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['proveedor_nombre']) . '</td>';
                            echo '<td>' . number_format($row['cantidad_autorizada'], 6) . '</td>';
                            echo '<td>' . number_format($row['total_recibido'], 6) . '</td>';
                            
                            // Mostrar ajuste actual
                            if ($row['ajuste'] > 0) {
                                echo '<td class="text-warning font-weight-bold">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> +' . number_format($row['ajuste'], 6) . ' (excedente)
                                      </td>';
                            } elseif ($row['ajuste'] < 0) {
                                echo '<td class="text-danger font-weight-bold">
                                        <i class="fas fa-times-circle mr-1"></i> ' . number_format($row['ajuste'], 6) . ' (descontinuado)
                                      </td>';
                            } else {
                                echo '<td class="text-muted">-</td>';
                            }
                            
                            // Botón de ajuste si no tiene ajuste previo
                            if ($row['ajuste'] == 0) {
                                echo '<td>
                                    <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#modalAjuste' . $row['idRespuesta'] . '">
                                        <i class="fas fa-balance-scale mr-1"></i> Ajustar
                                    </button>
                                </td>';
                            } else {
                                echo '<td><span class="text-muted"><i class="fas fa-lock"></i> Fijado</span></td>';
                            }
                            
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Formulario de Cierre -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-lock mr-2"></i> Cierre Definitivo de Cotización
                </div>
                <div class="card-body">
                    <form method="POST" id="formCierre">
                        <input type="hidden" name="idCotizacion" value="<?php echo $idCotizacion_sel; ?>">
                        
                        <div class="form-group">
                            <label>Motivo de Cierre <span class="text-danger">*</span></label>
                            <textarea name="motivo_cierre" class="form-control" rows="3" required
                                      placeholder="Explique por qué se cierra esta cotización..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>¿Hubo incidencias? <span class="text-danger">*</span></label>
                            <select name="hubo_incidencia" class="form-control custom-select" id="selectIncidencia" required onchange="toggleIncidencia()">
                                <option value="no">No</option>
                                <option value="si">Sí</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="divDescripcionIncidencia" style="display:none;">
                            <label>Descripción de la Incidencia <span class="text-danger">*</span></label>
                            <textarea name="descripcion_incidencia" class="form-control" rows="3"
                                      placeholder="Describa la incidencia ocurrida..."></textarea>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>ADVERTENCIA:</strong> El cierre de cotización es <strong>IRREVERSIBLE</strong>. 
                            Una vez cerrada no podrá recibir mercancía ni modificar ningún dato.
                        </div>
                        
                        <button type="button" class="btn btn-danger btn-lg btn-block" data-toggle="modal" data-target="#modalConfirmarCierre">
                            <i class="fas fa-lock mr-2"></i> Cerrar Cotización Definitivamente
                        </button>
                        
                        <!-- Modal de Confirmación Final -->
                        <div class="modal fade" id="modalConfirmarCierre" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i> Confirmar Cierre Irreversible</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="lead">¿Está absolutamente seguro?</p>
                                        <p>El cierre de cotización es <strong>IRREVERSIBLE</strong>. Una vez cerrada:</p>
                                        <ul>
                                            <li>No podrá recibir más mercancía</li>
                                            <li>No podrá modificar cantidades ni ajustes</li>
                                            <li>No podrá agregar nuevas respuestas de proveedor</li>
                                            <li>Los ajustes registrados son permanentes</li>
                                        </ul>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="confirmar_cierre" class="btn btn-danger">
                                            <i class="fas fa-lock mr-2"></i> Sí, Cerrar Definitivamente
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </form>
                </div>
            </div>
            
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

<!-- Modales de Ajuste -->
<?php
if ($idCotizacion_sel) {
    $sql = "SELECT d.idDetalle, a.nombre as articulo_nombre,
            r.idRespuesta, r.cantidad_autorizada, r.cantidad_cotizada,
            p.nombre as proveedor_nombre
            FROM cotizaciones_detalle d
            JOIN cat_articulos a ON d.idArticulo = a.idArticulo
            JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.activo = 'si' AND r.autorizado = 'si' AND r.ajuste = 0
            JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
            WHERE d.idCotizacion = $idCotizacion_sel AND d.activo = 'si'";
    $res = mysqli_query($link, $sql);
    
    while ($row = mysqli_fetch_assoc($res)) {
?>
<div class="modal fade" id="modalAjuste<?php echo $row['idRespuesta']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-balance-scale mr-2"></i> Registrar Ajuste
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    
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
                    
                    <div class="form-group">
                        <label>Cantidad Autorizada</label>
                        <input type="text" class="form-control" value="<?php echo number_format($row['cantidad_autorizada'], 6); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Ajuste <span class="text-danger">*</span></label>
                        <input type="number" name="ajuste" class="form-control" step="0.000001" required>
                        <small class="ayuda-campo">
                            <span class="text-success">+Valor</span> = recibimos de más (excedente)<br>
                            <span class="text-danger">-Valor</span> = descontinuado/producto faltante
                        </small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        El ajuste es de <strong>UNA SOLA VEZ</strong> e irreversible.
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="guardar_ajuste" class="btn btn-warning">
                        <i class="fas fa-save mr-2"></i> Guardar Ajuste
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

<script>
function toggleIncidencia() {
    var select = document.getElementById('selectIncidencia');
    var div = document.getElementById('divDescripcionIncidencia');
    div.style.display = (select.value == 'si') ? 'block' : 'none';
}
</script>

</div>

<?php require_once 'footerkimi.php'; ?>
