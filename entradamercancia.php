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
// Módulo: Registro de Recepción Física de Mercancía
// VERSIÓN FINAL: Actualiza cantidad_recibida en respuesta_cotizacion (tracking por proveedor)
?>

<?php require_once 'headerkimi.php'; ?>

<link rel="stylesheet" href="compraskimi.css">

<div id="subcontainer">

<?php
$max_excedente_pct = 10;
$mensaje = '';
$tipo_mensaje = '';
$idCotizacion_sel = isset($_POST['idCotizacion']) ? intval($_POST['idCotizacion']) : 0;
$usuario_sanitizado = mysqli_real_escape_string($link, $session_usuario);

// Procesar recepción con TRANSACCIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_entrada'])) {
    
    if (!isset($_POST['confirmo_presentacion'])) {
        $mensaje = 'Debe confirmar que la presentación recibida corresponde a la solicitada.';
        $tipo_mensaje = 'danger';
    } else {
        mysqli_begin_transaction($link);
        
        try {
            $idCotizacion = intval($_POST['idCotizacion']);
            $idRespuesta = intval($_POST['idRespuesta']);
            $cantidad_recibir = floatval($_POST['cantidad_recibir']);
            
            // Obtener datos de la respuesta específica del proveedor
            $sql = "SELECT r.*, d.idDetalle, d.cantidad_recibida as total_recibido_detalle,
                           a.idArticulo, a.existencia as existencia_actual
                    FROM respuesta_cotizacion r
                    JOIN cotizaciones_detalle d ON r.idDetalle = d.idDetalle
                    JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                    WHERE r.idRespuesta = $idRespuesta 
                    AND r.autorizado = 'si' 
                    AND r.activo = 'si'
                    FOR UPDATE";
            
            $res = mysqli_query($link, $sql);
            
            if (!$res || !($respuesta = mysqli_fetch_assoc($res))) {
                throw new Exception('Respuesta de proveedor no encontrada o no autorizada.');
            }
            
            // Validar pendiente para ESTE proveedor específico
            $cantidad_autorizada = floatval($respuesta['cantidad_autorizada']);
            $cantidad_recibida_actual = floatval($respuesta['cantidad_recibida']);
            $cantidad_pendiente = $cantidad_autorizada - $cantidad_recibida_actual;
            
            if ($cantidad_pendiente <= 0) {
                throw new Exception('Esta partida ya fue recibida completamente de este proveedor.');
            }
            
            // Validar excedente
            $max_permitido = $cantidad_autorizada * (1 + ($max_excedente_pct / 100));
            $nueva_cantidad_recibida_resp = $cantidad_recibida_actual + $cantidad_recibir;
            
            if ($nueva_cantidad_recibida_resp > $max_permitido) {
                throw new Exception('La cantidad excede el máximo permitido del ' . $max_excedente_pct . '%.');
            }
            
            // 1. Actualizar respuesta_cotizacion (tracking por proveedor)
            $sql_resp = "UPDATE respuesta_cotizacion SET 
                        cantidad_recibida = $nueva_cantidad_recibida_resp,
                        ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                        usuario_modifica = '$usuario_sanitizado'
                        WHERE idRespuesta = $idRespuesta";
            
            if (!mysqli_query($link, $sql_resp)) {
                throw new Exception('Error al actualizar respuesta_cotizacion: ' . mysqli_error($link));
            }
            
            // 2. Actualizar cotizaciones_detalle (acumulado total)
            $idDetalle = intval($respuesta['idDetalle']);
            $nuevo_total_detalle = floatval($respuesta['total_recibido_detalle']) + $cantidad_recibir;
            
            $sql_det = "UPDATE cotizaciones_detalle SET 
                       cantidad_recibida = $nuevo_total_detalle,
                       fecha_recibida = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                       ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                       usuario_modifica = '$usuario_sanitizado'
                       WHERE idDetalle = $idDetalle";
            
            if (!mysqli_query($link, $sql_det)) {
                throw new Exception('Error al actualizar cotizaciones_detalle: ' . mysqli_error($link));
            }
            
            // 3. Registrar en kardex
            $idArticulo = intval($respuesta['idArticulo']);
            $referencia = 'Cot ' . $idCotizacion . ' - Prov ' . intval($respuesta['idProveedor']);
            
            $sql_kardex = "INSERT INTO kardex 
                          (idArticulo, tipo_movimiento, cantidad, referencia, comentario, fecha_movimiento, usuario_alta)
                          VALUES 
                          ($idArticulo, 'entrada', $cantidad_recibir, '$referencia', 
                           'Recepción cotización $idCotizacion, respuesta #$idRespuesta',
                           CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), '$usuario_sanitizado')";
            
            if (!mysqli_query($link, $sql_kardex)) {
                throw new Exception('Error al registrar kardex: ' . mysqli_error($link));
            }
            
            // 4. Actualizar existencia en cat_articulos
            $nueva_existencia = floatval($respuesta['existencia_actual']) + $cantidad_recibir;
            
            $sql_exist = "UPDATE cat_articulos SET 
                          existencia = $nueva_existencia,
                          ultima_compra = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                          ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                          usuario_modifica = '$usuario_sanitizado'
                          WHERE idArticulo = $idArticulo";
            
            if (!mysqli_query($link, $sql_exist)) {
                throw new Exception('Error al actualizar cat_articulos: ' . mysqli_error($link));
            }
            
            mysqli_commit($link);
            $mensaje = 'Entrada registrada correctamente. Proveedor: ' . htmlspecialchars($respuesta['idProveedor']) . 
                      ', Cantidad: ' . number_format($cantidad_recibir, 6);
            $tipo_mensaje = 'success';
            
        } catch (Exception $e) {
            mysqli_rollback($link);
            $mensaje = $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener partidas pendientes por PROVEEDOR (desde respuesta_cotizacion)
$partidas_pendientes = [];

if ($idCotizacion_sel > 0) {
    $sql = "SELECT r.idRespuesta,
                   r.idDetalle,
                   r.idProveedor,
                   r.precio_total,
                   r.cantidad_cotizada,
                   r.cantidad_autorizada,
                   r.cantidad_recibida,
                   r.fecha_comprometida,
                   r.ajuste,
                   d.idCotizacion,
                   d.fecha_requerida,
                   a.nombre as articulo_nombre,
                   a.idArticulo,
                   u.idUnidadMedida,
                   p.nombre as proveedor_nombre
            FROM respuesta_cotizacion r
            JOIN cotizaciones_detalle d ON r.idDetalle = d.idDetalle AND d.activo = 'si'
            JOIN cat_articulos a ON d.idArticulo = a.idArticulo
            JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
            JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
            WHERE d.idCotizacion = $idCotizacion_sel
            AND r.autorizado = 'si'
            AND r.activo = 'si'
            AND (r.cantidad_autorizada - r.cantidad_recibida) > 0.000001
            ORDER BY d.idDetalle, p.nombre";
    
    $res = mysqli_query($link, $sql);
    
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $row['cantidad_pendiente'] = floatval($row['cantidad_autorizada']) - floatval($row['cantidad_recibida']);
            $row['precio_unitario'] = floatval($row['precio_total']) / floatval($row['cantidad_cotizada']);
            $partidas_pendientes[] = $row;
        }
    }
}
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-dolly mr-2"></i> Entrada de Mercancía
        </div>
        <div class="card-body">
            
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-sistema">
                <i class="fas fa-info-circle mr-2"></i> <?php echo $mensaje; ?>
            </div>
            <?php endif; ?>
            
            <!-- Búsqueda de Cotización -->
            <form method="POST" class="form-sistema mb-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Número de Cotización <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="idCotizacion" class="form-control" 
                                       value="<?php echo $idCotizacion_sel; ?>" 
                                       placeholder="Ingrese número de cotización" required>
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-guardar">
                                        <i class="fas fa-search mr-2"></i> Buscar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            
            <?php if ($idCotizacion_sel > 0): ?>
                
                <?php if (count($partidas_pendientes) == 0): ?>
                    <div class="alert alert-info alert-sistema">
                        <i class="fas fa-info-circle mr-2"></i> 
                        No hay partidas pendientes de recepción para esta cotización.
                    </div>
                <?php else: ?>
                    
                    <h5 class="mb-3">
                        <i class="fas fa-clipboard-list mr-2"></i> 
                        Partidas Pendientes - Cotización #<?php echo $idCotizacion_sel; ?>
                    </h5>
                    
                    <div class="table-responsive">
                        <table class="table table-sistema">
                            <thead>
                                <tr>
                                    <th>Partida</th>
                                    <th>Proveedor</th>
                                    <th>Artículo</th>
                                    <th>Autorizado</th>
                                    <th>Recibido</th>
                                    <th>Pendiente</th>
                                    <th>Precio Unit.</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partidas_pendientes as $item): ?>
                                <tr>
                                    <td><?php echo intval($item['idDetalle']); ?></td>
                                    <td><?php echo htmlspecialchars($item['proveedor_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($item['articulo_nombre']); ?></td>
                                    <td><?php echo number_format($item['cantidad_autorizada'], 6) . ' ' . htmlspecialchars($item['idUnidadMedida']); ?></td>
                                    <td><?php echo number_format($item['cantidad_recibida'], 6); ?></td>
                                    <td class="font-weight-bold text-primary"><?php echo number_format($item['cantidad_pendiente'], 6); ?></td>
                                    <td>$<?php echo number_format($item['precio_unitario'], 2); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-guardar btn-sm" 
                                                data-toggle="modal" 
                                                data-target="#modalEntrada<?php echo intval($item['idRespuesta']); ?>">
                                            <i class="fas fa-plus mr-1"></i> Recibir
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php endif; ?>
                
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

<!-- Modales de Entrada -->
<?php foreach ($partidas_pendientes as $item): 
    $max_permitido = floatval($item['cantidad_autorizada']) * (1 + ($max_excedente_pct / 100));
?>
<div class="modal fade" id="modalEntrada<?php echo intval($item['idRespuesta']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" onsubmit="return validarYDesactivar(<?php echo intval($item['idRespuesta']); ?>)">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-dolly mr-2"></i> Registrar Entrada
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    
                    <input type="hidden" name="idCotizacion" value="<?php echo $idCotizacion_sel; ?>">
                    <input type="hidden" name="idRespuesta" value="<?php echo intval($item['idRespuesta']); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <strong>Proveedor:</strong> <?php echo htmlspecialchars($item['proveedor_nombre']); ?><br>
                                <strong>Artículo:</strong> <?php echo htmlspecialchars($item['articulo_nombre']); ?><br>
                                <strong>Autorizado:</strong> <?php echo number_format($item['cantidad_autorizada'], 6); ?><br>
                                <strong>Ya Recibido:</strong> <?php echo number_format($item['cantidad_recibida'], 6); ?><br>
                                <strong class="text-primary">Pendiente:</strong> <?php echo number_format($item['cantidad_pendiente'], 6); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-warning">
                                <strong>Precio Unitario:</strong> $<?php echo number_format($item['precio_unitario'], 2); ?><br>
                                <strong>Monto Estimado Pendiente:</strong> 
                                <span class="h4 text-primary">
                                    $<?php echo number_format($item['precio_unitario'] * $item['cantidad_pendiente'], 2); ?>
                                </span>
                                <hr class="my-2">
                                <small class="text-muted">
                                    Si el proveedor cambió el precio, notifique a Compras.
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cantidad a Recibir <span class="text-danger">*</span></label>
                                <input type="number" 
                                       name="cantidad_recibir" 
                                       id="cantidad<?php echo intval($item['idRespuesta']); ?>" 
                                       class="form-control form-control-lg" 
                                       step="0.000001" 
                                       min="0.000001" 
                                       max="<?php echo $max_permitido; ?>" 
                                       required
                                       oninput="calcularMonto(<?php echo intval($item['idRespuesta']); ?>, <?php echo $item['precio_unitario']; ?>)">
                                <small class="ayuda-campo">
                                    Máximo: <?php echo number_format($max_permitido, 6); ?> 
                                    (incluye <?php echo $max_excedente_pct; ?>% excedente)
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Monto Estimado (verificación)</label>
                                <input type="text" 
                                       id="monto<?php echo intval($item['idRespuesta']); ?>" 
                                       class="form-control form-control-lg bg-light text-primary font-weight-bold" 
                                       readonly 
                                       value="$0.00">
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirmo_presentacion" required>
                            <label class="form-check-label">
                                <strong>Confirmo que la presentación recibida corresponde a la presentación solicitada</strong>
                            </label>
                        </div>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="guardar_entrada" id="btnGuardar<?php echo intval($item['idRespuesta']); ?>" class="btn btn-guardar btn-lg">
                        <i class="fas fa-save mr-2"></i> Guardar Entrada
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calcularMonto(id, precio) {
    var cantidad = parseFloat(document.getElementById('cantidad' + id).value) || 0;
    document.getElementById('monto' + id).value = '$' + (cantidad * precio).toFixed(2);
}

function validarYDesactivar(id) {
    var btn = document.getElementById('btnGuardar' + id);
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Guardando...';
    return true;
}
</script>
<?php endforeach; ?>

</div>

<?php require_once 'footerkimi.php'; ?>
