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
// VERSIÓN CORREGIDA: 
// - El cierre de cotización NO bloquea recepciones físicas
// - Bloqueo real: no hay partidas autorizadas pendientes o ya se recibió 100%+
// - Muestra precio estimado para verificación de cambios de precio por proveedor
// - Transacción completa y anti-doble click
?>

<?php require_once 'headerkimi.php'; ?>

<link rel="stylesheet" href="compraskimi.css">

<div id="subcontainer">

<?php
// Hardcoded: máximo porcentaje de excedente permitido
$max_excedente_pct = 10;

$mensaje = '';
$tipo_mensaje = '';
$idCotizacion_sel = isset($_POST['idCotizacion']) ? intval($_POST['idCotizacion']) : 0;
$usuario_sanitizado = mysqli_real_escape_string($link, $session_usuario);

// Procesar recepción con TRANSACCIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_entrada'])) {
    
    // Validar checkbox de confirmación
    if (!isset($_POST['confirmo_presentacion'])) {
        $mensaje = 'Debe confirmar que la presentación recibida corresponde a la solicitada.';
        $tipo_mensaje = 'danger';
    } else {
        // Iniciar transacción
        mysqli_begin_transaction($link);
        
        try {
            $idCotizacion = intval($_POST['idCotizacion']);
            $idDetalle = intval($_POST['idDetalle']);
            $cantidad_recibir = floatval($_POST['cantidad_recibir']);
            
            // Obtener datos de la partida con bloqueo FOR UPDATE
            $sql = "SELECT d.*, a.idArticulo, a.existencia as existencia_actual, 
                           r.precio_total, r.cantidad_cotizada, r.cantidad_autorizada as cant_aut_resp
                    FROM cotizaciones_detalle d
                    JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                    JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.autorizado = 'si' AND r.activo = 'si'
                    WHERE d.idDetalle = $idDetalle AND d.idCotizacion = $idCotizacion AND d.activo = 'si'
                    FOR UPDATE";
            $res = mysqli_query($link, $sql);
            
            if (!$res || !($partida = mysqli_fetch_assoc($res))) {
                throw new Exception('Partida no encontrada o no está autorizada.');
            }
            
            // Validar que la partida tenga cantidad pendiente por recibir
            $cantidad_autorizada = $partida['cantidad_autorizada'] > 0 ? $partida['cantidad_autorizada'] : $partida['cant_aut_resp'];
            $cantidad_actual_recibida = $partida['cantidad_recibida'];
            $cantidad_pendiente = $cantidad_autorizada - $cantidad_actual_recibida;
            
            if ($cantidad_pendiente <= 0) {
                throw new Exception('Esta partida ya fue recibida completamente. No puede recibir más mercancía.');
            }
            
            // Validar que no se exceda lo autorizado + margen permitido
            $max_permitido = $cantidad_autorizada * (1 + ($max_excedente_pct / 100));
            $nueva_cantidad_recibida = $cantidad_actual_recibida + $cantidad_recibir;
            
            if ($nueva_cantidad_recibida > $max_permitido) {
                throw new Exception('La cantidad a recibir excede el máximo permitido del ' . $max_excedente_pct . '% sobre lo autorizado (' . number_format($max_permitido, 6) . ').');
            }
            
            // 1. Actualizar cotizaciones_detalle
            $sql_update = "UPDATE cotizaciones_detalle SET 
                          cantidad_recibida = $nueva_cantidad_recibida,
                          fecha_recibida = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                          ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                          usuario_modifica = '$usuario_sanitizado'
                          WHERE idDetalle = $idDetalle";
            
            if (!mysqli_query($link, $sql_update)) {
                throw new Exception('Error al actualizar detalle: ' . mysqli_error($link));
            }
            
            // 2. Registrar en kardex (entrada)
            $idArticulo = $partida['idArticulo'];
            $referencia = 'Cot ' . $idCotizacion;
            $comentario_kardex = 'Recepción cotización ' . $idCotizacion . ' - Detalle ' . $idDetalle;
            
            $sql_kardex = "INSERT INTO kardex 
                          (idArticulo, tipo_movimiento, cantidad, referencia, comentario, fecha_movimiento, usuario_alta)
                          VALUES 
                          ($idArticulo, 'entrada', $cantidad_recibir, '$referencia', '$comentario_kardex',
                           CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), '$usuario_sanitizado')";
            
            if (!mysqli_query($link, $sql_kardex)) {
                throw new Exception('Error al registrar kardex: ' . mysqli_error($link));
            }
            
            // 3. Actualizar existencia en cat_articulos
            $nueva_existencia = $partida['existencia_actual'] + $cantidad_recibir;
            
            $sql_exist = "UPDATE cat_articulos SET 
                          existencia = $nueva_existencia,
                          ultima_compra = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                          ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                          usuario_modifica = '$usuario_sanitizado'
                          WHERE idArticulo = $idArticulo";
            
            if (!mysqli_query($link, $sql_exist)) {
                throw new Exception('Error al actualizar existencia: ' . mysqli_error($link));
            }
            
            // Confirmar transacción
            mysqli_commit($link);
            
            $mensaje = 'Entrada registrada correctamente. Cantidad recibida: ' . number_format($cantidad_recibir, 6);
            $tipo_mensaje = 'success';
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            mysqli_rollback($link);
            $mensaje = $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Verificar estado de cotización ingresada
$cotizacion_bloqueada = false;
$mensaje_bloqueo = '';
$partidas_pendientes = [];

if ($idCotizacion_sel > 0) {
    // Verificar si existe y está activa
    $sql = "SELECT * FROM cotizaciones_maestro WHERE idCotizacion = $idCotizacion_sel AND activo = 'si'";
    $res = mysqli_query($link, $sql);
    
    if (mysqli_num_rows($res) == 0) {
        $cotizacion_bloqueada = true;
        $mensaje_bloqueo = 'La cotización no existe o está eliminada.';
    } else {
        // CORRECCIÓN: El cierre de cotización NO bloquea recepciones físicas
        // El bloqueo real es: no hay partidas autorizadas con cantidad pendiente por recibir
        
        $sql_pend = "SELECT d.*, a.nombre as articulo_nombre, u.idUnidadMedida,
                            r.precio_total, r.cantidad_cotizada, r.cantidad_autorizada as cant_aut_resp,
                            p.nombre as proveedor_nombre
                     FROM cotizaciones_detalle d
                     JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                     JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
                     JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.autorizado = 'si' AND r.activo = 'si'
                     JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
                     WHERE d.idCotizacion = $idCotizacion_sel 
                     AND d.activo = 'si' 
                     AND d.comprar = 'si'
                     AND d.cantidad_recibida < IFNULL(d.cantidad_autorizada, r.cantidad_autorizada)
                     ORDER BY d.idDetalle";
        $res_pend = mysqli_query($link, $sql_pend);
        
        while ($row = mysqli_fetch_assoc($res_pend)) {
            // Calcular precio unitario y pendiente
            $cantidad_autorizada = $row['cantidad_autorizada'] > 0 ? $row['cantidad_autorizada'] : $row['cant_aut_resp'];
            $precio_unitario = $row['precio_total'] / $row['cantidad_cotizada'];
            $row['precio_unitario'] = $precio_unitario;
            $row['cantidad_autorizada_real'] = $cantidad_autorizada;
            $row['cantidad_pendiente'] = $cantidad_autorizada - $row['cantidad_recibida'];
            $partidas_pendientes[] = $row;
        }
        
        if (count($partidas_pendientes) == 0) {
            $cotizacion_bloqueada = true;
            // Mensaje corregido: no es por cierre, es por completitud o falta de autorización
            $mensaje_bloqueo = 'No hay partidas autorizadas pendientes de recepción. Verifique que: (1) existan respuestas autorizadas, (2) no se haya recibido ya el 100% o más de lo autorizado.';
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
                
                <?php if ($cotizacion_bloqueada): ?>
                    <div class="alert alert-warning alert-sistema">
                        <i class="fas fa-info-circle mr-2"></i> <?php echo $mensaje_bloqueo; ?>
                    </div>
                <?php else: ?>
                    
                    <h5 class="mb-3">
                        <i class="fas fa-clipboard-list mr-2"></i> 
                        Partidas Pendientes de Recepción - Cotización #<?php echo $idCotizacion_sel; ?>
                    </h5>
                    
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Nota:</strong> Solo se muestran partidas autorizadas con cantidad pendiente por recibir.
                        <?php if ($max_excedente_pct > 0): ?>
                        El excedente máximo permitido es del <strong><?php echo $max_excedente_pct; ?>%</strong>.
                        <?php endif; ?>
                    </div>
                    
                    <!-- Alerta sobre responsabilidades -->
                    <div class="alert alert-secondary mb-3">
                        <i class="fas fa-calculator mr-2"></i>
                        <strong>Verificación de Precio:</strong> El monto estimado se calcula con el precio cotizado originalmente. 
                        Si el proveedor cambió el precio, el departamento de Compras debe gestionar la diferencia (nota de crédito/factura complementaria).
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sistema">
                            <thead>
                                <tr>
                                    <th>Partida</th>
                                    <th>Artículo</th>
                                    <th>Proveedor</th>
                                    <th>Autorizado</th>
                                    <th>Recibido</th>
                                    <th>Pendiente</th>
                                    <th>Precio Unit.</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partidas_pendientes as $partida): 
                                    $monto_estimado = $partida['precio_unitario'] * $partida['cantidad_pendiente'];
                                ?>
                                <tr>
                                    <td><?php echo $partida['idDetalle']; ?></td>
                                    <td><?php echo htmlspecialchars($partida['articulo_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($partida['proveedor_nombre']); ?></td>
                                    <td><?php echo number_format($partida['cantidad_autorizada_real'], 6) . ' ' . $partida['idUnidadMedida']; ?></td>
                                    <td><?php echo number_format($partida['cantidad_recibida'], 6); ?></td>
                                    <td class="font-weight-bold text-primary"><?php echo number_format($partida['cantidad_pendiente'], 6); ?></td>
                                    <td>$<?php echo number_format($partida['precio_unitario'], 2); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-guardar btn-sm" 
                                                data-toggle="modal" 
                                                data-target="#modalEntrada<?php echo $partida['idDetalle']; ?>">
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

<!-- Modales de Entrada por Partida -->
<?php foreach ($partidas_pendientes as $partida): 
    $max_permitido = $partida['cantidad_autorizada_real'] * (1 + ($max_excedente_pct / 100));
    $precio_unit = $partida['precio_unitario'];
?>
<div class="modal fade" id="modalEntrada<?php echo $partida['idDetalle']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" id="formEntrada<?php echo $partida['idDetalle']; ?>" onsubmit="return validarYDesactivar(<?php echo $partida['idDetalle']; ?>)">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-dolly mr-2"></i> Registrar Entrada
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    
                    <input type="hidden" name="idCotizacion" value="<?php echo $idCotizacion_sel; ?>">
                    <input type="hidden" name="idDetalle" value="<?php echo $partida['idDetalle']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-info mb-3">
                                <strong>Artículo:</strong> <?php echo htmlspecialchars($partida['articulo_nombre']); ?><br>
                                <strong>Proveedor:</strong> <?php echo htmlspecialchars($partida['proveedor_nombre']); ?><br>
                                <strong>Cantidad Autorizada:</strong> <?php echo number_format($partida['cantidad_autorizada_real'], 6) . ' ' . $partida['idUnidadMedida']; ?><br>
                                <strong>Ya Recibido:</strong> <?php echo number_format($partida['cantidad_recibida'], 6); ?><br>
                                <strong class="text-primary">Pendiente:</strong> <?php echo number_format($partida['cantidad_pendiente'], 6); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-warning mb-3">
                                <strong>Precio Unitario Cotizado:</strong> $<?php echo number_format($precio_unit, 2); ?><br>
                                <strong>Monto Estimado Pendiente:</strong> 
                                <span class="h4 text-primary">$<?php echo number_format($partida['precio_unitario'] * $partida['cantidad_pendiente'], 2); ?></span>
                                <hr class="my-2">
                                <small class="text-muted">
                                    Si el proveedor cambió el precio, notifique a Compras. 
                                    El almacén solo registra cantidades físicas.
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cantidad a Recibir <span class="text-danger">*</span></label>
                                <input type="number" name="cantidad_recibir" id="cantidad<?php echo $partida['idDetalle']; ?>" 
                                       class="form-control form-control-lg" 
                                       step="0.000001" 
                                       min="0.000001" 
                                       max="<?php echo $max_permitido; ?>" 
                                       oninput="calcularMonto(<?php echo $partida['idDetalle']; ?>, <?php echo $precio_unit; ?>)"
                                       required>
                                <small class="ayuda-campo">
                                    Máximo permitido: <?php echo number_format($max_permitido, 6); ?> 
                                    (incluye <?php echo $max_excedente_pct; ?>% de excedente)
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Monto Estimado (readonly - para verificación)</label>
                                <input type="text" id="montoEstimado<?php echo $partida['idDetalle']; ?>" 
                                       class="form-control form-control-lg bg-light text-primary font-weight-bold" 
                                       readonly 
                                       value="$0.00">
                                <small class="ayuda-campo text-success">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Compare con lo que indica el proveedor en albarán/factura
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advertencia de excedente -->
                    <div id="alertaExcedente<?php echo $partida['idDetalle']; ?>" class="alert alert-danger" style="display:none;">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>¡Advertencia!</strong> La cantidad excede lo autorizado.
                        <br><small>Si el proveedor envió de más, Compras deberá gestionar la factura complementaria.</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="confirmo_presentacion" 
                                   id="confirmo<?php echo $partida['idDetalle']; ?>" required>
                            <label class="form-check-label" for="confirmo<?php echo $partida['idDetalle']; ?>">
                                <strong>Confirmo que la presentación recibida corresponde a la presentación solicitada</strong>
                            </label>
                        </div>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="guardar_entrada" id="btnGuardar<?php echo $partida['idDetalle']; ?>" class="btn btn-guardar btn-lg">
                        <i class="fas fa-save mr-2"></i> Guardar Entrada
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Calcular monto estimado en tiempo real
function calcularMonto(id, precioUnitario) {
    var cantidad = parseFloat(document.getElementById('cantidad' + id).value) || 0;
    var monto = cantidad * precioUnitario;
    document.getElementById('montoEstimado' + id).value = '$' + monto.toFixed(2);
    
    // Mostrar/ocultar alerta de excedente
    var autorizado = <?php echo $partida['cantidad_autorizada_real']; ?>;
    var maxExcedente = autorizado * (1 + <?php echo $max_excedente_pct; ?> / 100);
    var alerta = document.getElementById('alertaExcedente' + id);
    
    if (cantidad > autorizado) {
        alerta.style.display = 'block';
    } else {
        alerta.style.display = 'none';
    }
}

// Validación y anti-doble click
function validarYDesactivar(id) {
    var cantidad = parseFloat(document.getElementById('cantidad' + id).value);
    var maximo = parseFloat(document.getElementById('cantidad' + id).max);
    var minimo = parseFloat(document.getElementById('cantidad' + id).min);
    
    // Validar rango
    if (isNaN(cantidad) || cantidad < minimo) {
        alert('La cantidad debe ser mayor a ' + minimo);
        return false;
    }
    if (cantidad > maximo) {
        alert('La cantidad no puede exceder ' + maximo.toFixed(6));
        return false;
    }
    
    // Validar 6 decimales máximo
    var cantidadStr = document.getElementById('cantidad' + id).value;
    if (cantidadStr.indexOf('.') !== -1) {
        var decimales = cantidadStr.split('.')[1];
        if (decimales && decimales.length > 6) {
            alert('La cantidad no puede tener más de 6 decimales');
            return false;
        }
    }
    
    // Deshabilitar botón para evitar doble click
    var boton = document.getElementById('btnGuardar' + id);
    boton.disabled = true;
    boton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Guardando...';
    
    return true;
}
</script>
<?php endforeach; ?>

</div>

<?php require_once 'footerkimi.php'; ?>
