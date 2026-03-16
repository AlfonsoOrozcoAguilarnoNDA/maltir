<?php
// Modelo: KIMI 2.5
// Módulo: Consulta General de Cotizaciones (Abiertas y Cerradas)
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
// Procesar filtros
$fecha_desde = isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : '';
$fecha_hasta = isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : '';
$idCotizacion_filtro = isset($_POST['idCotizacion_filtro']) ? intval($_POST['idCotizacion_filtro']) : 0;
$idProveedor_filtro = isset($_POST['idProveedor']) ? intval($_POST['idProveedor']) : 0;
$texto_busqueda = isset($_POST['texto_busqueda']) ? mysqli_real_escape_string($link, $_POST['texto_busqueda']) : '';
$cerrada_filtro = isset($_POST['cerrada']) ? $_POST['cerrada'] : 'todas';

// Paginación
$pagina = isset($_POST['pagina']) ? intval($_POST['pagina']) : 1;
$registros_por_pagina = 50;
$offset = ($pagina - 1) * $registros_por_pagina;

// Construir WHERE
$where_parts = ["m.activo = 'si'"];
if ($fecha_desde) $where_parts[] = "m.fecha_cotizacion >= '$fecha_desde'";
if ($fecha_hasta) $where_parts[] = "m.fecha_cotizacion <= '$fecha_hasta'";
if ($idCotizacion_filtro > 0) $where_parts[] = "m.idCotizacion = $idCotizacion_filtro";
if ($idProveedor_filtro > 0) $where_parts[] = "EXISTS (SELECT 1 FROM cotizaciones_detalle d 
    JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle 
    WHERE d.idCotizacion = m.idCotizacion AND r.idProveedor = $idProveedor_filtro)";
if ($texto_busqueda) $where_parts[] = "m.comentario LIKE '%$texto_busqueda%'";
if ($cerrada_filtro != 'todas') $where_parts[] = "m.cerrada = '$cerrada_filtro'";

$where = "WHERE " . implode(" AND ", $where_parts);

// Contar total
$sql_count = "SELECT COUNT(*) as total FROM cotizaciones_maestro m $where";
$res_count = mysqli_query($link, $sql_count);
$total_registros = mysqli_fetch_assoc($res_count)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener cotizaciones
$sql = "SELECT m.*, 
        (SELECT COUNT(*) FROM cotizaciones_detalle WHERE idCotizacion = m.idCotizacion AND activo = 'si') as total_partidas,
        (SELECT SUM(cantidad_solicitada) FROM cotizaciones_detalle WHERE idCotizacion = m.idCotizacion AND activo = 'si') as total_cantidad
        FROM cotizaciones_maestro m
        $where
        ORDER BY m.idCotizacion DESC
        LIMIT $offset, $registros_por_pagina";
$res = mysqli_query($link, $sql);
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-search mr-2"></i> Consulta de Cotizaciones
        </div>
        <div class="card-body">
            
            <!-- Filtros -->
            <form method="POST" class="form-sistema mb-4">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>No. Cotización</label>
                            <input type="number" name="idCotizacion_filtro" class="form-control" value="<?php echo $idCotizacion_filtro ? $idCotizacion_filtro : ''; ?>" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Fecha Desde</label>
                            <input type="datetime-local" name="fecha_desde" class="form-control" value="<?php echo $fecha_desde; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Fecha Hasta</label>
                            <input type="datetime-local" name="fecha_hasta" class="form-control" value="<?php echo $fecha_hasta; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Proveedor</label>
                            <select name="idProveedor" class="form-control custom-select">
                                <option value="0">Todos</option>
                                <?php
                                $res_prov = mysqli_query($link, "SELECT idProveedor, nombre FROM cat_proveedores WHERE activo = 'si' ORDER BY nombre");
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
                            <label>Estado</label>
                            <select name="cerrada" class="form-control custom-select">
                                <option value="todas" <?php echo ($cerrada_filtro == 'todas') ? 'selected' : ''; ?>>Todas</option>
                                <option value="no" <?php echo ($cerrada_filtro == 'no') ? 'selected' : ''; ?>>Abiertas</option>
                                <option value="si" <?php echo ($cerrada_filtro == 'si') ? 'selected' : ''; ?>>Cerradas</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Buscar en Comentario</label>
                            <input type="text" name="texto_busqueda" class="form-control" value="<?php echo htmlspecialchars($_POST['texto_busqueda'] ?? ''); ?>" placeholder="Texto a buscar...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-guardar btn-block">
                                <i class="fas fa-search mr-2"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Tabla de Resultados -->
            <div class="table-responsive">
                <table class="table table-sistema">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Fecha</th>
                            <th>Comentario</th>
                            <th>Partidas</th>
                            <th>Cantidad Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        while ($row = mysqli_fetch_assoc($res)) {
                            $estado_class = ($row['cerrada'] == 'si') ? 'badge-danger' : 'badge-success';
                            $estado_texto = ($row['cerrada'] == 'si') ? 'Cerrada' : 'Abierta';
                            
                            echo '<tr>';
                            echo '<td class="font-weight-bold">' . $row['idCotizacion'] . '</td>';
                            echo '<td>' . ($row['fecha_cotizacion'] ? date('d/m/Y H:i', strtotime($row['fecha_cotizacion'])) : 'Sin confirmar') . '</td>';
                            echo '<td>' . htmlspecialchars(substr($row['comentario'], 0, 60)) . (strlen($row['comentario']) > 60 ? '...' : '') . '</td>';
                            echo '<td>' . $row['total_partidas'] . '</td>';
                            echo '<td>' . number_format($row['total_cantidad'] ?? 0, 6) . '</td>';
                            echo '<td><span class="badge ' . $estado_class . '">' . $estado_texto . '</span></td>';
                            echo '<td>
                                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#modalVer' . $row['idCotizacion'] . '">
                                    <i class="fas fa-eye mr-1"></i> Ver
                                </button>';
                            if ($row['cerrada'] == 'no') {
                                echo ' <a href="autorizarcotizacion.php?idCotizacion=' . $row['idCotizacion'] . '" class="btn btn-guardar btn-sm">
                                    <i class="fas fa-check mr-1"></i> Autorizar
                                </a>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                        if (mysqli_num_rows($res) == 0) {
                            echo '<tr><td colspan="7" class="text-center text-muted">No se encontraron cotizaciones</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación" class="pagination-sistema">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="pagina" value="<?php echo $i; ?>">
                            <?php foreach ($_POST as $key => $val): if ($key != 'pagina'): ?>
                            <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($val); ?>">
                            <?php endif; endforeach; ?>
                            <button type="submit" class="page-link"><?php echo $i; ?></button>
                        </form>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

<!-- Modales de Detalle -->
<?php
mysqli_data_seek($res, 0); // Reset pointer
while ($cot = mysqli_fetch_assoc($res)) {
    $idCot = $cot['idCotizacion'];
?>
<div class="modal fade" id="modalVer<?php echo $idCot; ?>" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice mr-2"></i> Cotización #<?php echo $idCot; ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                
                <!-- Datos Generales -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Fecha:</strong> <?php echo $cot['fecha_cotizacion'] ? date('d/m/Y H:i', strtotime($cot['fecha_cotizacion'])) : 'Sin confirmar'; ?></p>
                        <p><strong>Comentario:</strong> <?php echo nl2br(htmlspecialchars($cot['comentario'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Estado:</strong> 
                            <span class="badge <?php echo ($cot['cerrada'] == 'si') ? 'badge-danger' : 'badge-success'; ?>">
                                <?php echo ($cot['cerrada'] == 'si') ? 'Cerrada' : 'Abierta'; ?>
                            </span>
                        </p>
                        <?php if ($cot['cerrada'] == 'si'): ?>
                        <p><strong>Fecha Cierre:</strong> <?php echo date('d/m/Y H:i', strtotime($cot['fecha_cierre'])); ?></p>
                        <p><strong>Motivo:</strong> <?php echo htmlspecialchars($cot['motivo_cierre']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Partidas -->
                <h6 class="font-weight-bold mb-3"><i class="fas fa-list-ul mr-2"></i> Partidas</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Artículo</th>
                                <th>Solicitado</th>
                                <th>Recibido</th>
                                <th>Diferencia</th>
                                <th>Fecha Comprometida</th>
                                <th>Fecha Recibida</th>
                                <th>Días Diferencia</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql_det = "SELECT d.*, a.nombre as articulo_nombre, u.idUnidadMedida,
                                        r.fecha_comprometida, r.fecha_autorizacion
                                        FROM cotizaciones_detalle d
                                        JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                                        JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
                                        LEFT JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.autorizado = 'si' AND r.activo = 'si'
                                        WHERE d.idCotizacion = $idCot AND d.activo = 'si'
                                        ORDER BY d.idDetalle";
                            $res_det = mysqli_query($link, $sql_det);
                            
                            while ($det = mysqli_fetch_assoc($res_det)) {
                                $diferencia = $det['cantidad_solicitada'] - $det['cantidad_recibida'];
                                
                                // Calcular días de diferencia
                                $dias_diff = '-';
                                if ($det['fecha_comprometida'] && $det['fecha_recibida']) {
                                    $fecha_comp = new DateTime($det['fecha_comprometida']);
                                    $fecha_rec = new DateTime($det['fecha_recibida']);
                                    $diff = $fecha_comp->diff($fecha_rec);
                                    $dias_diff = $diff->days;
                                    if ($fecha_rec > $fecha_comp) {
                                        $dias_diff = '+' . $dias_diff . ' (tarde)';
                                    } elseif ($fecha_rec < $fecha_comp) {
                                        $dias_diff = '-' . $dias_diff . ' (antes)';
                                    } else {
                                        $dias_diff = '0 (a tiempo)';
                                    }
                                }
                                
                                // Estado
                                if ($det['cantidad_recibida'] >= $det['cantidad_solicitada']) {
                                    $estado = '<span class="badge badge-success">Completo</span>';
                                } elseif ($det['cantidad_recibida'] > 0) {
                                    $estado = '<span class="badge badge-warning">Parcial</span>';
                                } else {
                                    $estado = '<span class="badge badge-secondary">Pendiente</span>';
                                }
                                
                                echo '<tr>';
                                echo '<td>' . $det['idDetalle'] . '</td>';
                                echo '<td>' . htmlspecialchars($det['articulo_nombre']) . '</td>';
                                echo '<td>' . number_format($det['cantidad_solicitada'], 6) . ' ' . $det['idUnidadMedida'] . '</td>';
                                echo '<td>' . number_format($det['cantidad_recibida'], 6) . '</td>';
                                echo '<td class="' . ($diferencia > 0 ? 'text-danger' : 'text-success') . '">' . number_format($diferencia, 6) . '</td>';
                                echo '<td>' . ($det['fecha_comprometida'] ? date('d/m/Y', strtotime($det['fecha_comprometida'])) : '-') . '</td>';
                                echo '<td>' . ($det['fecha_recibida'] ? date('d/m/Y', strtotime($det['fecha_recibida'])) : '-') . '</td>';
                                echo '<td>' . $dias_diff . '</td>';
                                echo '<td>' . $estado . '</td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($cot['cerrada'] == 'no'): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle mr-2"></i> 
                    Esta cotización está abierta. Puede autorizar partidas en el módulo de Autorización.
                </div>
                <?php endif; ?>
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancelar" data-dismiss="modal">Cerrar</button>
                <?php if ($cot['cerrada'] == 'no'): ?>
                <a href="autorizarcotizacion.php?idCotizacion=<?php echo $idCot; ?>" class="btn btn-guardar">
                    <i class="fas fa-check mr-2"></i> Ir a Autorizar
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php 
}
?>

</div>

<?php require_once 'footerkimi.php'; ?>
