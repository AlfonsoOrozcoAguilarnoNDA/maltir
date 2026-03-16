<?php
// Modelo: KIMI 2.5
// Módulo: Capturar Respuesta de Cotización por Proveedor
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

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Guardar respuesta de proveedor
    if (isset($_POST['guardar_respuesta'])) {
        $idDetalle = intval($_POST['idDetalle']);
        $idProveedor = intval($_POST['idProveedor']);
        $idIncoterm = mysqli_real_escape_string($link, $_POST['idIncoterm']);
        $precio_total = floatval($_POST['precio_total']);
        $fecha_comprometida = mysqli_real_escape_string($link, $_POST['fecha_comprometida']);
        $idUnidadMedida = mysqli_real_escape_string($link, $_POST['idUnidadMedida']);
        $cantidad_cotizada = floatval($_POST['cantidad_cotizada']);
        
        // Validar checkbox de confirmación de presentación
        if (!isset($_POST['confirmo_presentacion'])) {
            $mensaje = 'Debe confirmar que la presentación corresponde a la solicitada originalmente.';
            $tipo_mensaje = 'danger';
        } else {
            // Validar fecha contra historial del kardex
            $sql_hist = "SELECT AVG(DATEDIFF(fecha_movimiento, 
                        (SELECT fecha_alta FROM cotizaciones_detalle WHERE idDetalle = $idDetalle))) as dias_promedio
                        FROM kardex k 
                        JOIN cotizaciones_detalle d ON k.idArticulo = d.idArticulo
                        JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle
                        WHERE r.idProveedor = $idProveedor AND k.tipo_movimiento = 'entrada'
                        HAVING dias_promedio IS NOT NULL";
            $res_hist = mysqli_query($link, $sql_hist);
            $advertencia_tiempo = '';
            
            if ($res_hist && $row_hist = mysqli_fetch_assoc($res_hist)) {
                $dias_promedio = ceil(abs($row_hist['dias_promedio']));
                $fecha_comp = new DateTime($fecha_comprometida);
                $fecha_hoy = new DateTime();
                $dias_comprometidos = $fecha_hoy->diff($fecha_comp)->days;
                
                if ($dias_comprometidos < $dias_promedio) {
                    $advertencia_tiempo = "ADVERTENCIA: Por experiencia histórica, este proveedor tarda en promedio $dias_promedio días en entregar. La fecha comprometida podría no ser alcanzable.";
                }
            }
            
            $sql = "INSERT INTO respuesta_cotizacion 
                    (idDetalle, idProveedor, idIncoterm, precio_total, fecha_comprometida,
                     idUnidadMedida, cantidad_cotizada, ajuste, autorizado, cantidad_autorizada,
                     activo, fecha_alta, ultima_actualizacion, usuario_alta, usuario_modifica) 
                    VALUES 
                    ($idDetalle, $idProveedor, '$idIncoterm', $precio_total, '$fecha_comprometida',
                     '$idUnidadMedida', $cantidad_cotizada, 0, 'no', 0,
                     'si',
                     CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                     CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                     '$session_usuario', '$session_usuario')";
            
            if (mysqli_query($link, $sql)) {
                $mensaje = 'Respuesta guardada correctamente. ' . $advertencia_tiempo;
                $tipo_mensaje = $advertencia_tiempo ? 'warning' : 'success';
            } else {
                $mensaje = 'Error al guardar: ' . mysqli_error($link);
                $tipo_mensaje = 'danger';
            }
        }
    }
}

// Obtener cotización seleccionada
$idCotizacion_sel = isset($_POST['idCotizacion']) ? intval($_POST['idCotizacion']) : (isset($_GET['idCotizacion']) ? intval($_GET['idCotizacion']) : 0);
$idProveedor_sel = isset($_POST['idProveedor']) ? intval($_POST['idProveedor']) : 0;
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-reply mr-2"></i> Capturar Respuesta de Proveedor
        </div>
        <div class="card-body">
            
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-sistema">
                <?php echo $mensaje; ?>
            </div>
            <?php endif; ?>
            
            <!-- Selección de Cotización y Proveedor -->
            <form method="POST" class="form-sistema mb-4">
                <div class="row">
                    <div class="col-md-6">
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
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Proveedor <span class="text-danger">*</span></label>
                            <select name="idProveedor" class="form-control custom-select" onchange="this.form.submit()" required>
                                <option value="">Seleccione proveedor...</option>
                                <?php
                                $res = mysqli_query($link, "SELECT idProveedor, nombre FROM cat_proveedores WHERE activo = 'si' ORDER BY nombre");
                                while ($row = mysqli_fetch_assoc($res)) {
                                    $selected = ($idProveedor_sel == $row['idProveedor']) ? 'selected' : '';
                                    echo '<option value="' . $row['idProveedor'] . '" ' . $selected . '>' . htmlspecialchars($row['nombre']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
            
            <?php if ($idCotizacion_sel && $idProveedor_sel): ?>
            
            <!-- Advertencia importante sobre presentación -->
            <div class="alert alert-warning alert-sistema mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <strong>Importante:</strong> Si el proveedor cotizó en una presentación diferente a la solicitada, 
                la conversión es responsabilidad del capturista — el error es del proveedor si no usó la presentación pedida.
            </div>
            
            <!-- Partidas pendientes de respuesta -->
            <div class="table-responsive">
                <table class="table table-sistema">
                    <thead>
                        <tr>
                            <th>Partida</th>
                            <th>Artículo</th>
                            <th>Unidad Solicitada</th>
                            <th>Cantidad Solicitada</th>
                            <th>Fecha Requerida</th>
                            <th>Incoterm Requerido</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT d.*, a.nombre as articulo_nombre, u.descripcion as unidad_desc, 
                                i.descripcion as incoterm_desc,
                                (SELECT COUNT(*) FROM respuesta_cotizacion r 
                                 WHERE r.idDetalle = d.idDetalle AND r.idProveedor = $idProveedor_sel AND r.activo = 'si') as tiene_respuesta
                                FROM cotizaciones_detalle d
                                JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                                JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
                                JOIN cat_incoterms i ON d.idIncoterm = i.idIncoterm
                                WHERE d.idCotizacion = $idCotizacion_sel AND d.activo = 'si'
                                ORDER BY d.idDetalle";
                        $res = mysqli_query($link, $sql);
                        
                        while ($row = mysqli_fetch_assoc($res)) {
                            echo '<tr>';
                            echo '<td>' . $row['idDetalle'] . '</td>';
                            echo '<td>' . htmlspecialchars($row['articulo_nombre']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['idUnidadMedida'] . ' - ' . $row['unidad_desc']) . '</td>';
                            echo '<td>' . number_format($row['cantidad_solicitada'], 6) . '</td>';
                            echo '<td>' . date('d/m/Y', strtotime($row['fecha_requerida'])) . '</td>';
                            echo '<td>' . htmlspecialchars($row['idIncoterm']) . '</td>';
                            
                            if ($row['tiene_respuesta'] > 0) {
                                echo '<td><span class="badge badge-success">Respondido</span></td>';
                                echo '<td><button class="btn btn-secondary btn-sm" disabled><i class="fas fa-check"></i></button></td>';
                            } else {
                                echo '<td><span class="badge badge-warning">Pendiente</span></td>';
                                echo '<td>
                                    <button type="button" class="btn btn-guardar btn-sm" data-toggle="modal" data-target="#modalRespuesta' . $row['idDetalle'] . '">
                                        <i class="fas fa-edit mr-1"></i> Capturar
                                    </button>
                                </td>';
                            }
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

<!-- Modales de Captura por Partida -->
<?php
if ($idCotizacion_sel && $idProveedor_sel) {
    $res = mysqli_query($link, "SELECT d.*, a.nombre as articulo_nombre, u.descripcion as unidad_desc
                                FROM cotizaciones_detalle d
                                JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                                JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
                                WHERE d.idCotizacion = $idCotizacion_sel AND d.activo = 'si'");
    
    while ($row = mysqli_fetch_assoc($res)) {
        // Verificar si ya tiene respuesta
        $check = mysqli_query($link, "SELECT idRespuesta FROM respuesta_cotizacion 
                                      WHERE idDetalle = {$row['idDetalle']} AND idProveedor = $idProveedor_sel AND activo = 'si'");
        if (mysqli_num_rows($check) > 0) continue;
?>
<div class="modal fade" id="modalRespuesta<?php echo $row['idDetalle']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-reply mr-2"></i> Capturar Respuesta - <?php echo htmlspecialchars($row['articulo_nombre']); ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    
                    <div class="alert alert-info">
                        <strong>Artículo:</strong> <?php echo htmlspecialchars($row['articulo_nombre']); ?><br>
                        <strong>Cantidad Solicitada:</strong> <?php echo number_format($row['cantidad_solicitada'], 6) . ' ' . $row['idUnidadMedida']; ?><br>
                        <strong>Fecha Requerida:</strong> <?php echo date('d/m/Y', strtotime($row['fecha_requerida'])); ?>
                    </div>
                    
                    <input type="hidden" name="idDetalle" value="<?php echo $row['idDetalle']; ?>">
                    <input type="hidden" name="idProveedor" value="<?php echo $idProveedor_sel; ?>">
                    <input type="hidden" name="idCotizacion" value="<?php echo $idCotizacion_sel; ?>">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Precio Total (IVA incluido, SIN aduanas) <span class="text-danger">*</span></label>
                                <input type="number" name="precio_total" class="form-control" step="0.000001" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fecha Comprometida <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="fecha_comprometida" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Incoterm Ofrecido <span class="text-danger">*</span></label>
                                <select name="idIncoterm" class="form-control custom-select" required>
                                    <option value="">Seleccione...</option>
                                    <?php
                                    $res_inc = mysqli_query($link, "SELECT idIncoterm, descripcion FROM cat_incoterms WHERE activo = 'si' ORDER BY idIncoterm");
                                    while ($inc = mysqli_fetch_assoc($res_inc)) {
                                        echo '<option value="' . $inc['idIncoterm'] . '">' . htmlspecialchars($inc['idIncoterm'] . ' - ' . $inc['descripcion']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Unidad de Medida (NUESTRA presentación) <span class="text-danger">*</span></label>
                                <select name="idUnidadMedida" class="form-control custom-select" required>
                                    <option value="">Seleccione...</option>
                                    <?php
                                    $res_um = mysqli_query($link, "SELECT idUnidadMedida, descripcion FROM cat_unidades_medida WHERE activo = 'si' ORDER BY descripcion");
                                    while ($um = mysqli_fetch_assoc($res_um)) {
                                        $selected = ($um['idUnidadMedida'] == $row['idUnidadMedida']) ? 'selected' : '';
                                        echo '<option value="' . $um['idUnidadMedida'] . '" ' . $selected . '>' . htmlspecialchars($um['idUnidadMedida'] . ' - ' . $um['descripcion']) . '</option>';
                                    }
                                    ?>
                                </select>
                                <small class="ayuda-campo">Debe coincidir con la unidad solicitada originalmente</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cantidad Cotizada (en NUESTRA presentación) <span class="text-danger">*</span></label>
                                <input type="number" name="cantidad_cotizada" class="form-control" step="0.000001" min="0.000001" value="<?php echo $row['cantidad_solicitada']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirmo_presentacion" id="confirmo_presentacion<?php echo $row['idDetalle']; ?>" required>
                            <label class="form-check-label" for="confirmo_presentacion<?php echo $row['idDetalle']; ?>">
                                <strong>Confirmo que la presentación capturada corresponde a la presentación solicitada originalmente</strong>
                            </label>
                        </div>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="guardar_respuesta" class="btn btn-guardar">
                        <i class="fas fa-save mr-2"></i> Guardar Respuesta
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
