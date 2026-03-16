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
// Módulo: Entrada de Mercancía - Registro de Recepción Física con Kardex
?>
<?php require_once 'headerkimi.php'; ?>

<link rel="stylesheet" href="compraskimi.css">

<div id="subcontainer">

<?php
// Hardcoded: máximo porcentaje de excedente permitido
$max_excedente_pct = 10;

$mensaje = '';
$error = '';

// Procesar entrada de mercancía
if (isset($_POST['guardar_entrada'])) {
    $idCotizacion = intval($_POST['idCotizacion']);
    $idDetalle = intval($_POST['idDetalle']);
    $cantidad_recibir = floatval($_POST['cantidad_recibir']);
    $numero_remision = mysqli_real_escape_string($link, $_POST['numero_remision'] ?? '');
    $comentario = mysqli_real_escape_string($link, $_POST['comentario'] ?? '');
    $confirmo_presentacion = isset($_POST['confirmo_presentacion']) ? 'si' : 'no';
    
    if ($confirmo_presentacion != 'si') {
        $error = 'Debe confirmar que la presentación recibida corresponde a la solicitada.';
    } elseif ($cantidad_recibir <= 0) {
        $error = 'La cantidad a recibir debe ser mayor a cero.';
    } else {
        // Obtener datos de la partida y validar
        $sql_partida = "SELECT d.*, r.idRespuesta, r.cantidad_autorizada, r.cantidad_recibida as r_cant_rec,
                       c.cerrada, a.nombre as articulo_nombre, a.maneja_inventario, a.es_servicio
                       FROM cotizaciones_detalle d
                       JOIN cotizaciones_maestro c ON d.idCotizacion = c.idCotizacion
                       JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.autorizado = 'si' AND r.activo = 'si'
                       JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                       WHERE d.idDetalle = $idDetalle AND d.idCotizacion = $idCotizacion
                       AND d.activo = 'si' AND d.comprar = 'si'";
        $res_partida = mysqli_query($link, $sql_partida);
        
        if ($res_partida && $row = mysqli_fetch_assoc($res_partida)) {
            // Validar que no esté cerrada
            if ($row['cerrada'] == 'si') {
                $error = 'Esta cotización ya está cerrada. No se pueden registrar entradas.';
            } else {
                $cantidad_autorizada = $row['cantidad_autorizada'];
                $cantidad_ya_recibida = $row['r_cant_rec'];
                $cantidad_total_recibida = $cantidad_ya_recibida + $cantidad_recibir;
                
                // Validar excedente
                $excedente_pct = (($cantidad_total_recibida - $cantidad_autorizada) / $cantidad_autorizada) * 100;
                
                if ($excedente_pct > $max_excedente_pct) {
                    $error = 'La cantidad a recibir excede el ' . $max_excedente_pct . '% permitido sobre la cantidad autorizada.';
                } else {
                    // INICIAR TRANSACCIÓN
                    mysqli_begin_transaction($link);
                    
                    try {
                        $fecha_hoy = date('Y-m-d H:i:s');
                        
                        // 1. Actualizar cantidad_recibida en respuesta_cotizacion
                        $sql_update_resp = "UPDATE respuesta_cotizacion 
                                           SET cantidad_recibida = $cantidad_total_recibida,
                                               ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                                               usuario_modifica = '$session_usuario'
                                           WHERE idRespuesta = " . $row['idRespuesta'];
                        if (!mysqli_query($link, $sql_update_resp)) {
                            throw new Exception('Error al actualizar respuesta: ' . mysqli_error($link));
                        }
                        
                        // 2. Actualizar cantidad_recibida en cotizaciones_detalle
                        $sql_update_det = "UPDATE cotizaciones_detalle 
                                          SET cantidad_recibida = cantidad_recibida + $cantidad_recibir,
                                              fecha_recibida = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                                              ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                                              usuario_modifica = '$session_usuario'
                                          WHERE idDetalle = $idDetalle";
                        if (!mysqli_query($link, $sql_update_det)) {
                            throw new Exception('Error al actualizar detalle: ' . mysqli_error($link));
                        }
                        
                        // 3. INSERTAR EN KARDEX - Solo si maneja inventario y no es servicio
                        if ($row['maneja_inventario'] == 'si' && $row['es_servicio'] == 'no') {
                            $idArticulo = $row['idArticulo'];
                            $referencia_kardex = 'COT-' . $idCotizacion . ' / REM: ' . $numero_remision;
                            
                            $sql_kardex = "INSERT INTO kardex 
                                          (idArticulo, tipo_movimiento, cantidad, referencia, comentario, 
                                           fecha_movimiento, usuario_alta) 
                                          VALUES 
                                          ($idArticulo, 'entrada', $cantidad_recibir, 
                                           '$referencia_kardex', '$comentario',
                                           CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), '$session_usuario')";
                            
                            if (!mysqli_query($link, $sql_kardex)) {
                                throw new Exception('Error al registrar en kardex: ' . mysqli_error($link));
                            }
                        }
                        
                        // 4. Actualizar existencia en cat_articulos (solo si maneja inventario)
                        if ($row['maneja_inventario'] == 'si' && $row['es_servicio'] == 'no') {
                            $sql_update_art = "UPDATE cat_articulos 
                                              SET existencia = existencia + $cantidad_recibir,
                                                  ultima_compra = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                                                  ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                                                  usuario_modifica = '$session_usuario'
                                              WHERE idArticulo = " . $row['idArticulo'];
                            if (!mysqli_query($link, $sql_update_art)) {
                                throw new Exception('Error al actualizar artículo: ' . mysqli_error($link));
                            }
                        }
                        
                        // COMMIT
                        mysqli_commit($link);
                        $mensaje = 'Entrada registrada correctamente. Se recibieron ' . number_format($cantidad_recibir, 6) . ' unidades de ' . htmlspecialchars($row['articulo_nombre']);
                        
                        if ($row['maneja_inventario'] == 'si' && $row['es_servicio'] == 'no') {
                            $mensaje .= '. Movimiento registrado en kardex.';
                        }
                        
                    } catch (Exception $e) {
                        mysqli_rollback($link);
                        $error = $e->getMessage();
                    }
                }
            }
        } else {
            $error = 'Partida no encontrada o no autorizada para compra.';
        }
    }
}

// Buscar cotización para mostrar partidas pendientes
$idCotizacion_buscar = isset($_POST['buscar_cotizacion']) ? intval($_POST['idCotizacion']) : 0;
$mostrar_formulario = false;
$partidas_pendientes = [];

if ($idCotizacion_buscar > 0) {
    // Validar que la cotización exista y no esté cerrada
    $sql_cot = "SELECT * FROM cotizaciones_maestro WHERE idCotizacion = $idCotizacion_buscar AND activo = 'si'";
    $res_cot = mysqli_query($link, $sql_cot);
    
    if ($res_cot && $cot = mysqli_fetch_assoc($res_cot)) {
        if ($cot['cerrada'] == 'si') {
            $error = 'Esta cotización ya está cerrada. No se pueden registrar entradas.';
        } else {
            // Buscar partidas pendientes
            $sql_pendientes = "SELECT d.*, a.nombre as articulo_nombre, a.idArticulo, a.maneja_inventario, a.es_servicio,
                             u.idUnidadMedida,
                             r.idRespuesta, r.cantidad_autorizada, r.cantidad_recibida as r_cant_rec,
                             p.nombre as proveedor_nombre, r.fecha_comprometida
                             FROM cotizaciones_detalle d
                             JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                             JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
                             JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.autorizado = 'si' AND r.activo = 'si'
                             JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
                             WHERE d.idCotizacion = $idCotizacion_buscar
                             AND d.activo = 'si' AND d.comprar = 'si'
                             AND r.cantidad_recibida < r.cantidad_autorizada
                             ORDER BY d.fecha_requerida ASC";
            $res_pendientes = mysqli_query($link, $sql_pendientes);
            
            if ($res_pendientes) {
                while ($row = mysqli_fetch_assoc($res_pendientes)) {
                    $partidas_pendientes[] = $row;
                }
            }
            
            if (empty($partidas_pendientes)) {
                $error = 'Esta cotización ya fue entregada completamente. No hay partidas pendientes de recepción.';
            } else {
                $mostrar_formulario = true;
            }
        }
    } else {
        $error = 'Cotización no encontrada.';
    }
}
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-dolly mr-2"></i> Entrada de Mercancía</span>
            <small class="text-light">Modelo: KIMI 2.5</small>
        </div>
        <div class="card-body">
            
            <?php if ($mensaje): ?>
            <div class="alert alert-success alert-sistema">
                <i class="fas fa-check-circle mr-2"></i><?php echo $mensaje; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-sistema">
                <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <!-- Buscador de cotización -->
            <?php if (!$mostrar_formulario): ?>
            <form method="POST" class="form-sistema mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Número de Cotización</label>
                            <input type="number" name="idCotizacion" class="form-control" required min="1">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" name="buscar_cotizacion" class="btn btn-guardar btn-block">
                                <i class="fas fa-search mr-2"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
            
            <!-- Formulario de entrada -->
            <?php if ($mostrar_formulario && !empty($partidas_pendientes)): ?>
            <h5 class="mb-3">Cotización #<?php echo $idCotizacion_buscar; ?> - Partidas Pendientes</h5>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                Máximo excedente permitido: <strong><?php echo $max_excedente_pct; ?>%</strong>
            </div>
            
            <div class="table-responsive mb-4">
                <table class="table table-sistema">
                    <thead>
                        <tr>
                            <th>Artículo</th>
                            <th>Proveedor</th>
                            <th>Autorizado</th>
                            <th>Recibido</th>
                            <th>Pendiente</th>
                            <th>Fecha Comprometida</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partidas_pendientes as $partida): 
                            $pendiente = $partida['cantidad_autorizada'] - $partida['r_cant_rec'];
                            $es_servicio = ($partida['es_servicio'] == 'si');
                            $no_inventario = ($partida['maneja_inventario'] == 'no');
                        ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($partida['articulo_nombre']); ?>
                                <?php if ($es_servicio): ?>
                                <span class="badge badge-info ml-2">Servicio</span>
                                <?php elseif ($no_inventario): ?>
                                <span class="badge badge-secondary ml-2">Sin Inventario</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($partida['proveedor_nombre']); ?></td>
                            <td class="font-weight-bold"><?php echo number_format($partida['cantidad_autorizada'], 6); ?> <?php echo $partida['idUnidadMedida']; ?></td>
                            <td><?php echo number_format($partida['r_cant_rec'], 6); ?> <?php echo $partida['idUnidadMedida']; ?></td>
                            <td class="font-weight-bold text-primary"><?php echo number_format($pendiente, 6); ?> <?php echo $partida['idUnidadMedida']; ?></td>
                            <td><?php echo $partida['fecha_comprometida'] ? date('d/m/Y', strtotime($partida['fecha_comprometida'])) : '-'; ?></td>
                            <td>
                                <button type="button" class="btn btn-guardar btn-sm" data-toggle="modal" data-target="#modalEntrada<?php echo $partida['idDetalle']; ?>">
                                    <i class="fas fa-plus mr-1"></i> Recibir
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Modal de Entrada -->
                        <div class="modal fade" id="modalEntrada<?php echo $partida['idDetalle']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">Registrar Entrada - <?php echo htmlspecialchars($partida['articulo_nombre']); ?></h5>
                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="idCotizacion" value="<?php echo $idCotizacion_buscar; ?>">
                                            <input type="hidden" name="idDetalle" value="<?php echo $partida['idDetalle']; ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Cantidad a Recibir</label>
                                                        <input type="number" name="cantidad_recibir" class="form-control" step="0.000001" min="0.000001" max="<?php echo $pendiente * (1 + $max_excedente_pct/100); ?>" required>
                                                        <small class="text-muted">Máx permitido: <?php echo number_format($pendiente * (1 + $max_excedente_pct/100), 6); ?> (<?php echo $max_excedente_pct; ?>% excedente)</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Número de Remisión/Factura</label>
                                                        <input type="text" name="numero_remision" class="form-control" maxlength="100">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Comentario</label>
                                                <textarea name="comentario" class="form-control" rows="2" maxlength="500"></textarea>
                                            </div>
                                            
                                            <div class="alert alert-warning">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="confirmo_presentacion" id="confirmo<?php echo $partida['idDetalle']; ?>" value="si" required>
                                                    <label class="form-check-label font-weight-bold" for="confirmo<?php echo $partida['idDetalle']; ?>">
                                                        Confirmo que la presentación recibida corresponde a la presentación solicitada
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <?php 
                                            // Calcular si hay riesgo de excedente
                                            $max_permitido = $pendiente * (1 + $max_excedente_pct/100);
                                            ?>
                                            <div class="alert alert-secondary">
                                                <i class="fas fa-calculator mr-2"></i>
                                                <strong>Resumen:</strong><br>
                                                Autorizado: <?php echo number_format($partida['cantidad_autorizada'], 6); ?><br>
                                                Ya recibido: <?php echo number_format($partida['r_cant_rec'], 6); ?><br>
                                                Pendiente: <?php echo number_format($pendiente, 6); ?><br>
                                                <?php if (!$es_servicio && !$no_inventario): ?>
                                                <span class="text-success"><i class="fas fa-box mr-1"></i> Se registrará movimiento en kardex</span>
                                                <?php else: ?>
                                                <span class="text-info"><i class="fas fa-info-circle mr-1"></i> No aplica inventario (servicio o sin control de stock)</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
                                            <button type="submit" name="guardar_entrada" class="btn btn-guardar">
                                                <i class="fas fa-save mr-2"></i> Guardar Entrada
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="text-center">
                <a href="entradamercancia.php" class="btn btn-cancelar">
                    <i class="fas fa-arrow-left mr-2"></i> Buscar otra cotización
                </a>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

</div>

<?php require_once 'footerkimi.php'; ?>
