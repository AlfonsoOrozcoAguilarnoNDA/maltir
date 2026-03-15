<?php
// Modelo: KIMI 2.5
// Módulo: Catálogo de Artículos
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
// ============================================
// PROCESAMIENTO POST
// ============================================

$mensaje = '';
$tipo_mensaje = '';

// ALTA / EDICIÓN
if (isset($_POST['guardar'])) {
    $idArticulo = intval($_POST['idArticulo']);
    $nombre = trim($_POST['nombre']);
    $clave_interna = trim($_POST['clave_interna']);
    $descripcion_sat = trim($_POST['descripcion_sat']);
    $comentario = trim($_POST['comentario']);
    $es_servicio = $_POST['es_servicio'];
    $es_fisico = $_POST['es_fisico'];
    $maneja_inventario = $_POST['maneja_inventario'];
    $tasa_iva = floatval($_POST['tasa_iva']);
    $existencia = floatval($_POST['existencia']);
    $min_stock = floatval($_POST['min_stock']);
    $para_venta = $_POST['para_venta'];
    $comentario_corto = trim($_POST['comentario_corto']);
    $idUnidadMedida = $_POST['idUnidadMedida'];
    
    if (empty($nombre)) {
        $mensaje = 'El campo Nombre es obligatorio.';
        $tipo_mensaje = 'danger';
    } elseif (empty($idUnidadMedida)) {
        $mensaje = 'Debe seleccionar una unidad de medida.';
        $tipo_mensaje = 'danger';
    } else {
        if ($idArticulo > 0) {
            // EDITAR
            $sql = "UPDATE cat_articulos SET 
                nombre = '$nombre',
                clave_interna = '$clave_interna',
                descripcion_sat = '$descripcion_sat',
                comentario = '$comentario',
                es_servicio = '$es_servicio',
                es_fisico = '$es_fisico',
                maneja_inventario = '$maneja_inventario',
                tasa_iva = $tasa_iva,
                existencia = $existencia,
                min_stock = $min_stock,
                para_venta = '$para_venta',
                comentario_corto = '$comentario_corto',
                idUnidadMedida = '$idUnidadMedida',
                ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                usuario_modifica = '$session_usuario'
                WHERE idArticulo = $idArticulo";
            mysqli_query($link, $sql);
            $mensaje = 'Artículo actualizado correctamente.';
            $tipo_mensaje = 'success';
        } else {
            // CREAR
            $sql = "INSERT INTO cat_articulos (
                nombre, clave_interna, descripcion_sat, comentario,
                es_servicio, es_fisico, maneja_inventario, tasa_iva, existencia, min_stock,
                para_venta, comentario_corto, idUnidadMedida,
                activo, fecha_alta, ultima_actualizacion, usuario_alta, usuario_modifica
            ) VALUES (
                '$nombre', '$clave_interna', '$descripcion_sat', '$comentario',
                '$es_servicio', '$es_fisico', '$maneja_inventario', $tasa_iva, $existencia, $min_stock,
                '$para_venta', '$comentario_corto', '$idUnidadMedida',
                'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                '$session_usuario', '$session_usuario'
            )";
            mysqli_query($link, $sql);
            $mensaje = 'Artículo creado correctamente.';
            $tipo_mensaje = 'success';
        }
    }
}

// ACTIVAR / DESACTIVAR
if (isset($_POST['cambiar_estado'])) {
    $id = intval($_POST['id']);
    $nuevo_estado = $_POST['nuevo_estado'];
    $sql = "UPDATE cat_articulos SET 
        activo = '$nuevo_estado',
        ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
        usuario_modifica = '$session_usuario'
        WHERE idArticulo = $id";
    mysqli_query($link, $sql);
    $mensaje = 'Estado actualizado correctamente.';
    $tipo_mensaje = 'success';
}

// OBTENER DATOS PARA EDICIÓN
$editando = false;
$datos_edit = ['idArticulo' => 0, 'nombre' => '', 'clave_interna' => '', 'descripcion_sat' => '', 'comentario' => '',
    'es_servicio' => 'no', 'es_fisico' => 'si', 'maneja_inventario' => 'si', 'tasa_iva' => 0, 'existencia' => 0,
    'min_stock' => 0, 'para_venta' => 'no', 'comentario_corto' => '', 'idUnidadMedida' => ''];
if (isset($_GET['editar'])) {
    $id_edit = intval($_GET['editar']);
    $res = mysqli_query($link, "SELECT * FROM cat_articulos WHERE idArticulo = $id_edit");
    if (mysqli_num_rows($res) > 0) {
        $datos_edit = mysqli_fetch_assoc($res);
        $editando = true;
    }
}

// CARGAR UNIDADES DE MEDIDA ACTIVAS
$unidades = mysqli_query($link, "SELECT idUnidadMedida, descripcion FROM cat_unidades_medida WHERE activo = 'si' ORDER BY descripcion");

// PAGINACIÓN
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 50;
$inicio = ($pagina - 1) * $registros_por_pagina;

$total_res = mysqli_query($link, "SELECT COUNT(*) as total FROM cat_articulos");
$total_row = mysqli_fetch_assoc($total_res);
$total_registros = $total_row['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

$sql = "SELECT a.*, u.descripcion as unidad_desc 
        FROM cat_articulos a 
        LEFT JOIN cat_unidades_medida u ON a.idUnidadMedida = u.idUnidadMedida 
        ORDER BY a.nombre 
        LIMIT $inicio, $registros_por_pagina";
$resultado = mysqli_query($link, $sql);
?>

<div class="container-fluid">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-box mr-2"></i>Catálogo de Artículos</h2>
        <span class="badge badge-modelo">KIMI 2.5</span>
    </div>
    
    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-sistema alert-dismissible fade show">
            <?php echo $mensaje; ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>
    
    <!-- Formulario -->
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-<?php echo $editando ? 'edit' : 'plus'; ?> mr-2"></i>
            <?php echo $editando ? 'Editar Artículo' : 'Nuevo Artículo'; ?>
        </div>
        <div class="card-body">
            <form method="POST" class="form-sistema">
                <input type="hidden" name="idArticulo" value="<?php echo $datos_edit['idArticulo']; ?>">
                
                <!-- Lógica de naturaleza del artículo -->
                <div class="alert alert-info mb-4">
                    <h6 class="mb-2"><i class="fas fa-info-circle mr-2"></i>Lógica de clasificación:</h6>
                    <ul class="mb-0 pl-3">
                        <li><strong>Servicio puro:</strong> es_servicio='si' → NO maneja inventario (no entra al kardex)</li>
                        <li><strong>Artículo físico:</strong> es_fisico='si' → Sí maneja inventario</li>
                        <li><strong>Licencia/Intangible:</strong> es_fisico='no' + maneja_inventario='si' → Control de stock limitado</li>
                    </ul>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" name="nombre" class="form-control" maxlength="200"
                                   value="<?php echo $datos_edit['nombre']; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Clave Interna</label>
                            <input type="text" name="clave_interna" class="form-control" maxlength="50"
                                   value="<?php echo $datos_edit['clave_interna']; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Unidad de Medida *</label>
                            <select name="idUnidadMedida" class="custom-select" required>
                                <option value="">Seleccione...</option>
                                <?php mysqli_data_seek($unidades, 0); while ($u = mysqli_fetch_assoc($unidades)): ?>
                                    <option value="<?php echo $u['idUnidadMedida']; ?>" 
                                        <?php echo $datos_edit['idUnidadMedida'] == $u['idUnidadMedida'] ? 'selected' : ''; ?>>
                                        <?php echo $u['idUnidadMedida'] . ' - ' . $u['descripcion']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Tasa IVA (%)</label>
                            <input type="number" name="tasa_iva" class="form-control" step="0.000001" min="0" max="100"
                                   value="<?php echo $datos_edit['tasa_iva']; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Descripción SAT</label>
                            <input type="text" name="descripcion_sat" class="form-control" maxlength="500"
                                   value="<?php echo $datos_edit['descripcion_sat']; ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Comentario Corto</label>
                            <input type="text" name="comentario_corto" class="form-control" maxlength="200"
                                   value="<?php echo $datos_edit['comentario_corto']; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Existencia</label>
                            <input type="number" name="existencia" class="form-control" step="0.000001"
                                   value="<?php echo $datos_edit['existencia']; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Stock Mínimo</label>
                            <input type="number" name="min_stock" class="form-control" step="0.000001"
                                   value="<?php echo $datos_edit['min_stock']; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>¿Es Servicio?</label>
                            <select name="es_servicio" class="custom-select">
                                <option value="no" <?php echo $datos_edit['es_servicio'] == 'no' ? 'selected' : ''; ?>>No</option>
                                <option value="si" <?php echo $datos_edit['es_servicio'] == 'si' ? 'selected' : ''; ?>>Sí</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>¿Es Físico?</label>
                            <select name="es_fisico" class="custom-select">
                                <option value="si" <?php echo $datos_edit['es_fisico'] == 'si' ? 'selected' : ''; ?>>Sí</option>
                                <option value="no" <?php echo $datos_edit['es_fisico'] == 'no' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>¿Maneja Inventario?</label>
                            <select name="maneja_inventario" class="custom-select">
                                <option value="si" <?php echo $datos_edit['maneja_inventario'] == 'si' ? 'selected' : ''; ?>>Sí</option>
                                <option value="no" <?php echo $datos_edit['maneja_inventario'] == 'no' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>¿Para Venta?</label>
                            <select name="para_venta" class="custom-select">
                                <option value="no" <?php echo $datos_edit['para_venta'] == 'no' ? 'selected' : ''; ?>>No</option>
                                <option value="si" <?php echo $datos_edit['para_venta'] == 'si' ? 'selected' : ''; ?>>Sí</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Comentarios</label>
                            <input type="text" name="comentario" class="form-control" maxlength="65535"
                                   value="<?php echo $datos_edit['comentario']; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="text-right">
                    <?php if ($editando): ?>
                        <a href="catalogo_articulos.php" class="btn btn-cancelar mr-2">Cancelar</a>
                    <?php endif; ?>
                    <button type="submit" name="guardar" class="btn btn-guardar">
                        <i class="fas fa-save mr-1"></i> <?php echo $editando ? 'Actualizar' : 'Guardar'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Listado -->
    <div class="card card-modulo">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list mr-2"></i>Listado de Artículos</span>
            <a href="?module=reporte" target="_blank" class="btn btn-reporte btn-sm">
                <i class="fas fa-file-alt mr-1"></i> Reporte
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sistema mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Clave</th>
                            <th>Unidad</th>
                            <th>Naturaleza</th>
                            <th>Existencia</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($resultado)): ?>
                            <tr>
                                <td><?php echo $row['idArticulo']; ?></td>
                                <td><strong><?php echo $row['nombre']; ?></strong></td>
                                <td><?php echo $row['clave_interna'] ? $row['clave_interna'] : '-'; ?></td>
                                <td><?php echo $row['unidad_desc']; ?></td>
                                <td>
                                    <?php if ($row['es_servicio'] == 'si'): ?>
                                        <span class="badge badge-info">Servicio</span>
                                    <?php elseif ($row['es_fisico'] == 'no'): ?>
                                        <span class="badge badge-warning">Intangible</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Físico</span>
                                    <?php endif; ?>
                                    <?php if ($row['maneja_inventario'] == 'si'): ?>
                                        <span class="badge badge-success ml-1">Con Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($row['existencia'], 2); ?></td>
                                <td>
                                    <span class="badge badge-status-<?php echo $row['activo'] == 'si' ? 'ok' : 'warning'; ?>">
                                        <?php echo $row['activo'] == 'si' ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?editar=<?php echo $row['idArticulo']; ?>" class="btn btn-editar btn-sm mr-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-<?php echo $row['activo'] == 'si' ? 'eliminar' : 'activar'; ?> btn-sm" 
                                            data-toggle="modal" data-target="#modal<?php echo $row['idArticulo']; ?>">
                                        <i class="fas fa-<?php echo $row['activo'] == 'si' ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    
                                    <div class="modal fade" id="modal<?php echo $row['idArticulo']; ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header bg-<?php echo $row['activo'] == 'si' ? 'danger' : 'success'; ?> text-white">
                                                    <h5 class="modal-title">
                                                        <i class="fas fa-<?php echo $row['activo'] == 'si' ? 'ban' : 'check'; ?> mr-2"></i>
                                                        Confirmar Cambio de Estado
                                                    </h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>¿Está seguro que desea <strong><?php echo $row['activo'] == 'si' ? 'DESACTIVAR' : 'ACTIVAR'; ?></strong> el artículo?</p>
                                                    <p class="mb-0"><strong>Nombre:</strong> <?php echo $row['nombre']; ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="id" value="<?php echo $row['idArticulo']; ?>">
                                                        <input type="hidden" name="nuevo_estado" value="<?php echo $row['activo'] == 'si' ? 'no' : 'si'; ?>">
                                                        <button type="submit" name="cambiar_estado" class="btn btn-<?php echo $row['activo'] == 'si' ? 'danger' : 'success'; ?>">
                                                            Sí, <?php echo $row['activo'] == 'si' ? 'Desactivar' : 'Activar'; ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_paginas > 1): ?>
                <nav class="pagination-sistema p-3">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($pagina > 1): ?>
                            <li class="page-item"><a class="page-link" href="?pagina=<?php echo $pagina-1; ?>"><i class="fas fa-chevron-left"></i></a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($pagina < $total_paginas): ?>
                            <li class="page-item"><a class="page-link" href="?pagina=<?php echo $pagina+1; ?>"><i class="fas fa-chevron-right"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

</div>

<?php require_once 'footerkimi.php'; ?>
