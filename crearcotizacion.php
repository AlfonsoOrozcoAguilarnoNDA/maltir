<?php
// Modelo: KIMI 2.5
// Módulo: Crear Cotización con Partidas
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
// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Confirmar cotización (guardar definitivamente)
    if (isset($_POST['confirmar_cotizacion']) && isset($_POST['idCotizacion_temp'])) {
        $idCotizacion = intval($_POST['idCotizacion_temp']);
        
        // Actualizar fecha y usuario
        $sql = "UPDATE cotizaciones_maestro SET 
                fecha_cotizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                usuario_modifica = '$session_usuario'
                WHERE idCotizacion = $idCotizacion";
        mysqli_query($link, $sql);
        
        echo '<div class="alert alert-success alert-sistema">Cotización confirmada correctamente. Número: <strong>' . $idCotizacion . '</strong></div>';
        unset($_SESSION['cotizacion_temp']);
    }
    
    // Guardar cabecera temporal
    elseif (isset($_POST['guardar_cabecera'])) {
        $comentario = mysqli_real_escape_string($link, $_POST['comentario'] ?? '');
        
        $sql = "INSERT INTO cotizaciones_maestro 
                (comentario, cerrada, activo, fecha_alta, ultima_actualizacion, usuario_alta, usuario_modifica) 
                VALUES 
                ('$comentario', 'no', 'si', 
                 CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 
                 CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), 
                 '$session_usuario', '$session_usuario')";
        
        if (mysqli_query($link, $sql)) {
            $_SESSION['cotizacion_temp'] = mysqli_insert_id($link);
            echo '<div class="alert alert-info alert-sistema">Cotización iniciada. Agregue las partidas.</div>';
        }
    }
    
    // Agregar partida
    elseif (isset($_POST['agregar_partida']) && isset($_SESSION['cotizacion_temp'])) {
        $idCotizacion = intval($_SESSION['cotizacion_temp']);
        $idArticulo = intval($_POST['idArticulo']);
        $idUnidadMedida = mysqli_real_escape_string($link, $_POST['idUnidadMedida']);
        $idIncoterm = mysqli_real_escape_string($link, $_POST['idIncoterm']);
        $cantidad_solicitada = floatval($_POST['cantidad_solicitada']);
        $fecha_requerida = mysqli_real_escape_string($link, $_POST['fecha_requerida']);
        
        $sql = "INSERT INTO cotizaciones_detalle 
                (idCotizacion, idArticulo, idUnidadMedida, idIncoterm, 
                 cantidad_solicitada, cantidad_recibida, fecha_requerida, 
                 comprar, cantidad_autorizada, activo, 
                 fecha_alta, ultima_actualizacion, usuario_alta, usuario_modifica) 
                VALUES 
                ($idCotizacion, $idArticulo, '$idUnidadMedida', '$idIncoterm',
                 $cantidad_solicitada, 0, '$fecha_requerida',
                 'no', 0, 'si',
                 CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                 CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                 '$session_usuario', '$session_usuario')";
        
        if (mysqli_query($link, $sql)) {
            echo '<div class="alert alert-success alert-sistema">Partida agregada correctamente.</div>';
        } else {
            echo '<div class="alert alert-danger alert-sistema">Error al agregar partida: ' . mysqli_error($link) . '</div>';
        }
    }
    
    // Eliminar partida
    elseif (isset($_POST['eliminar_partida']) && isset($_SESSION['cotizacion_temp'])) {
        $idDetalle = intval($_POST['idDetalle']);
        $sql = "UPDATE cotizaciones_detalle SET activo = 'no', 
                ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                usuario_modifica = '$session_usuario'
                WHERE idDetalle = $idDetalle AND idCotizacion = " . intval($_SESSION['cotizacion_temp']);
        mysqli_query($link, $sql);
        echo '<div class="alert alert-info alert-sistema">Partida eliminada.</div>';
    }
}

$idCotizacion_temp = $_SESSION['cotizacion_temp'] ?? null;
$cotizacion_confirmada = false;

// Verificar si la cotización temporal ya fue confirmada (tiene fecha_cotizacion real)
if ($idCotizacion_temp) {
    $res = mysqli_query($link, "SELECT fecha_cotizacion FROM cotizaciones_maestro WHERE idCotizacion = $idCotizacion_temp");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        if ($row['fecha_cotizacion'] != null) {
            $cotizacion_confirmada = true;
        }
    }
}
?>

<div class="container-fluid">
    
    <!-- Cabecera -->
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-file-invoice-dollar mr-2"></i> Crear Nueva Cotización
            <?php if ($idCotizacion_temp): ?>
                <span class="badge badge-light float-right">Cotización #<?php echo $idCotizacion_temp; ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            
            <?php if (!$idCotizacion_temp): ?>
                <!-- Formulario inicial de cabecera -->
                <form method="POST" class="form-sistema">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Comentario General</label>
                                <textarea name="comentario" class="form-control" rows="3" placeholder="Comentarios generales de la cotización..."></textarea>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="guardar_cabecera" class="btn btn-guardar">
                        <i class="fas fa-play mr-2"></i> Iniciar Cotización
                    </button>
                </form>
                
            <?php else: ?>
                <!-- Cabecera guardada, mostrar datos -->
                <?php
                $res = mysqli_query($link, "SELECT * FROM cotizaciones_maestro WHERE idCotizacion = $idCotizacion_temp");
                $cot = mysqli_fetch_assoc($res);
                ?>
                <div class="row">
                    <div class="col-md-12">
                        <p><strong>Comentario:</strong> <?php echo htmlspecialchars($cot['comentario']); ?></p>
                        <?php if ($cotizacion_confirmada): ?>
                            <div class="alert alert-warning alert-sistema">
                                <i class="fas fa-lock mr-2"></i> Esta cotización ya fue confirmada. No se pueden agregar ni modificar partidas.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <?php if ($idCotizacion_temp && !$cotizacion_confirmada): ?>
    <!-- Sección de Partidas -->
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-list-ul mr-2"></i> Agregar Partidas
        </div>
        <div class="card-body">
            
            <form method="POST" class="form-sistema" id="formPartida">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Artículo <span class="text-danger">*</span></label>
                            <select name="idArticulo" class="form-control custom-select" required>
                                <option value="">Seleccione...</option>
                                <?php
                                $res = mysqli_query($link, "SELECT idArticulo, nombre FROM cat_articulos WHERE activo = 'si' ORDER BY nombre");
                                while ($row = mysqli_fetch_assoc($res)) {
                                    echo '<option value="' . $row['idArticulo'] . '">' . htmlspecialchars($row['nombre']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Unidad de Medida <span class="text-danger">*</span></label>
                            <select name="idUnidadMedida" class="form-control custom-select" required>
                                <option value="">Seleccione...</option>
                                <?php
                                $res = mysqli_query($link, "SELECT idUnidadMedida, descripcion FROM cat_unidades_medida WHERE activo = 'si' ORDER BY descripcion");
                                while ($row = mysqli_fetch_assoc($res)) {
                                    echo '<option value="' . $row['idUnidadMedida'] . '">' . htmlspecialchars($row['idUnidadMedida'] . ' - ' . $row['descripcion']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Incoterm <span class="text-danger">*</span></label>
                            <select name="idIncoterm" class="form-control custom-select" required>
                                <option value="">Seleccione...</option>
                                <?php
                                $res = mysqli_query($link, "SELECT idIncoterm, descripcion FROM cat_incoterms WHERE activo = 'si' ORDER BY idIncoterm");
                                while ($row = mysqli_fetch_assoc($res)) {
                                    echo '<option value="' . $row['idIncoterm'] . '">' . htmlspecialchars($row['idIncoterm'] . ' - ' . $row['descripcion']) . '</option>';
                                }
                                ?>
                            </select>
                            <small class="ayuda-campo">Obligatorio por partida</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Cantidad Solicitada <span class="text-danger">*</span></label>
                            <input type="number" name="cantidad_solicitada" class="form-control" step="0.000001" min="0.000001" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Fecha Requerida <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_requerida" class="form-control" required>
                        </div>
                    </div>
                </div>
                <button type="submit" name="agregar_partida" class="btn btn-guardar">
                    <i class="fas fa-plus mr-2"></i> Agregar Partida
                </button>
            </form>
            
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($idCotizacion_temp): ?>
    <!-- Listado de Partidas -->
    <div class="card card-modulo">
        <div class="card-header">
            <i class="fas fa-clipboard-list mr-2"></i> Partidas de la Cotización
        </div>
        <div class="card-body">
            
            <div class="table-responsive">
                <table class="table table-sistema">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Artículo</th>
                            <th>Unidad</th>
                            <th>Incoterm</th>
                            <th>Cantidad Solicitada</th>
                            <th>Fecha Requerida</th>
                            <?php if (!$cotizacion_confirmada): ?>
                            <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT d.*, a.nombre as articulo_nombre, u.descripcion as unidad_desc, i.descripcion as incoterm_desc
                                FROM cotizaciones_detalle d
                                JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                                JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
                                JOIN cat_incoterms i ON d.idIncoterm = i.idIncoterm
                                WHERE d.idCotizacion = $idCotizacion_temp AND d.activo = 'si'
                                ORDER BY d.idDetalle";
                        $res = mysqli_query($link, $sql);
                        $contador = 0;
                        while ($row = mysqli_fetch_assoc($res)) {
                            $contador++;
                            echo '<tr>';
                            echo '<td>' . $contador . '</td>';
                            echo '<td>' . htmlspecialchars($row['articulo_nombre']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['idUnidadMedida']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['idIncoterm']) . '</td>';
                            echo '<td>' . number_format($row['cantidad_solicitada'], 6) . '</td>';
                            echo '<td>' . $row['fecha_requerida'] . '</td>';
                            if (!$cotizacion_confirmada) {
                                echo '<td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="idDetalle" value="' . $row['idDetalle'] . '">
                                        <button type="submit" name="eliminar_partida" class="btn btn-eliminar btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>';
                            }
                            echo '</tr>';
                        }
                        if ($contador == 0) {
                            echo '<tr><td colspan="7" class="text-center text-muted">No hay partidas agregadas</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($contador > 0 && !$cotizacion_confirmada): ?>
            <hr class="separador-seccion">
            <div class="text-center">
                <button type="button" class="btn btn-guardar btn-lg" data-toggle="modal" data-target="#modalConfirmar">
                    <i class="fas fa-check-circle mr-2"></i> Confirmar Cotización
                </button>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    <?php endif; ?>
    
</div>

<!-- Modal de Confirmación -->
<?php if ($idCotizacion_temp && !$cotizacion_confirmada): ?>
<div class="modal fade" id="modalConfirmar" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i> Confirmar Cotización</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>Advertencia:</strong> Una vez confirmada la cotización <strong>NO</strong> podrá:
                    <ul class="mb-0 mt-2">
                        <li>Agregar más partidas</li>
                        <li>Modificar cantidades solicitadas</li>
                        <li>Cambiar condiciones de las partidas</li>
                    </ul>
                </div>
                <p class="mb-0">¿Desea continuar con la confirmación?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cancelar</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="idCotizacion_temp" value="<?php echo $idCotizacion_temp; ?>">
                    <button type="submit" name="confirmar_cotizacion" class="btn btn-guardar">
                        <i class="fas fa-check mr-2"></i> Sí, Confirmar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div>

<?php require_once 'footerkimi.php'; ?>
