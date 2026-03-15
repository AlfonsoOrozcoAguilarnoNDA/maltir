<?php
// Modelo: KIMI 2.5
// Módulo: Catálogo de Unidades de Medida
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
    $idUnidadMedida = strtoupper(trim($_POST['idUnidadMedida']));
    $clave_interna = trim($_POST['clave_interna']);
    $clave_sat = trim($_POST['clave_sat']);
    $descripcion = trim($_POST['descripcion']);
    $comentario = trim($_POST['comentario']);
    
    if (empty($idUnidadMedida) || empty($clave_interna) || empty($descripcion)) {
        $mensaje = 'Los campos Clave, Clave Interna y Descripción son obligatorios.';
        $tipo_mensaje = 'danger';
    } elseif (strlen($idUnidadMedida) > 7) {
        $mensaje = 'La clave no puede exceder 7 caracteres.';
        $tipo_mensaje = 'danger';
    } else {
        // Verificar si existe para editar o crear
        $check = mysqli_query($link, "SELECT idUnidadMedida FROM cat_unidades_medida WHERE idUnidadMedida = '$idUnidadMedida'");
        
        if (mysqli_num_rows($check) > 0) {
            // EDITAR
            $sql = "UPDATE cat_unidades_medida SET 
                clave_interna = '$clave_interna',
                clave_sat = '$clave_sat',
                descripcion = '$descripcion',
                comentario = '$comentario',
                ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                usuario_modifica = '$session_usuario'
                WHERE idUnidadMedida = '$idUnidadMedida'";
            mysqli_query($link, $sql);
            $mensaje = 'Unidad de medida actualizada correctamente.';
            $tipo_mensaje = 'success';
        } else {
            // CREAR
            $sql = "INSERT INTO cat_unidades_medida (
                idUnidadMedida, clave_interna, clave_sat, descripcion, comentario,
                activo, fecha_alta, ultima_actualizacion, usuario_alta, usuario_modifica
            ) VALUES (
                '$idUnidadMedida', '$clave_interna', '$clave_sat', '$descripcion', '$comentario',
                'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                '$session_usuario', '$session_usuario'
            )";
            mysqli_query($link, $sql);
            $mensaje = 'Unidad de medida creada correctamente.';
            $tipo_mensaje = 'success';
        }
    }
}

// ACTIVAR / DESACTIVAR
if (isset($_POST['cambiar_estado'])) {
    $id = $_POST['id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    $sql = "UPDATE cat_unidades_medida SET 
        activo = '$nuevo_estado',
        ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
        usuario_modifica = '$session_usuario'
        WHERE idUnidadMedida = '$id'";
    mysqli_query($link, $sql);
    $mensaje = 'Estado actualizado correctamente.';
    $tipo_mensaje = 'success';
}

// OBTENER DATOS PARA EDICIÓN
$editando = false;
$datos_edit = null;
if (isset($_GET['editar'])) {
    $id_edit = $_GET['editar'];
    $res = mysqli_query($link, "SELECT * FROM cat_unidades_medida WHERE idUnidadMedida = '$id_edit'");
    if (mysqli_num_rows($res) > 0) {
        $datos_edit = mysqli_fetch_assoc($res);
        $editando = true;
    }
}

// PAGINACIÓN
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 50;
$inicio = ($pagina - 1) * $registros_por_pagina;

// CONTAR TOTAL
$total_res = mysqli_query($link, "SELECT COUNT(*) as total FROM cat_unidades_medida");
$total_row = mysqli_fetch_assoc($total_res);
$total_registros = $total_row['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// CONSULTA PAGINADA
$sql = "SELECT * FROM cat_unidades_medida ORDER BY idUnidadMedida LIMIT $inicio, $registros_por_pagina";
$resultado = mysqli_query($link, $sql);
?>

<div class="container-fluid">
    
    <!-- Título -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-ruler mr-2"></i>Catálogo de Unidades de Medida</h2>
        <span class="badge badge-modelo">KIMI 2.5</span>
    </div>
    
    <!-- Mensaje -->
    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-sistema alert-dismissible fade show">
            <?php echo $mensaje; ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>
    
    <!-- Formulario Alta/Edición -->
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-<?php echo $editando ? 'edit' : 'plus'; ?> mr-2"></i>
            <?php echo $editando ? 'Editar Unidad de Medida' : 'Nueva Unidad de Medida'; ?>
        </div>
        <div class="card-body">
            <form method="POST" class="form-sistema">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Clave (PK) *</label>
                            <input type="text" name="idUnidadMedida" class="form-control" maxlength="7" 
                                   value="<?php echo $editando ? $datos_edit['idUnidadMedida'] : ''; ?>"
                                   <?php echo $editando ? 'readonly' : ''; ?> required>
                            <small class="ayuda-campo">Máx. 7 caracteres, mayúsculas recomendadas (ej: PZA, KG)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Clave Interna *</label>
                            <input type="text" name="clave_interna" class="form-control" maxlength="30"
                                   value="<?php echo $editando ? $datos_edit['clave_interna'] : ''; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Clave SAT</label>
                            <input type="text" name="clave_sat" class="form-control" maxlength="30"
                                   value="<?php echo $editando ? $datos_edit['clave_sat'] : ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Descripción *</label>
                            <input type="text" name="descripcion" class="form-control" maxlength="200"
                                   value="<?php echo $editando ? $datos_edit['descripcion'] : ''; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Comentario</label>
                            <input type="text" name="comentario" class="form-control" maxlength="500"
                                   value="<?php echo $editando ? $datos_edit['comentario'] : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <?php if ($editando): ?>
                        <a href="catalogo_unidades.php" class="btn btn-cancelar mr-2">Cancelar</a>
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
            <span><i class="fas fa-list mr-2"></i>Listado de Unidades</span>
            <a href="?module=reporte" target="_blank" class="btn btn-reporte btn-sm">
                <i class="fas fa-file-alt mr-1"></i> Reporte
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sistema mb-0">
                    <thead>
                        <tr>
                            <th>Clave</th>
                            <th>Clave Interna</th>
                            <th>Clave SAT</th>
                            <th>Descripción</th>
                            <th>Comentario</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($resultado)): ?>
                            <tr>
                                <td><strong><?php echo $row['idUnidadMedida']; ?></strong></td>
                                <td><?php echo $row['clave_interna']; ?></td>
                                <td><?php echo $row['clave_sat'] ? $row['clave_sat'] : '-'; ?></td>
                                <td><?php echo $row['descripcion']; ?></td>
                                <td><?php echo $row['comentario'] ? substr($row['comentario'], 0, 30) . '...' : '-'; ?></td>
                                <td>
                                    <span class="badge badge-status-<?php echo $row['activo'] == 'si' ? 'ok' : 'warning'; ?>">
                                        <?php echo $row['activo'] == 'si' ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?editar=<?php echo $row['idUnidadMedida']; ?>" class="btn btn-editar btn-sm mr-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-<?php echo $row['activo'] == 'si' ? 'eliminar' : 'activar'; ?> btn-sm" 
                                            data-toggle="modal" data-target="#modal<?php echo $row['idUnidadMedida']; ?>">
                                        <i class="fas fa-<?php echo $row['activo'] == 'si' ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    
                                    <!-- Modal Confirmación -->
                                    <div class="modal fade" id="modal<?php echo $row['idUnidadMedida']; ?>" tabindex="-1" role="dialog">
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
                                                    <p>¿Está seguro que desea <strong><?php echo $row['activo'] == 'si' ? 'DESACTIVAR' : 'ACTIVAR'; ?></strong> la unidad de medida?</p>
                                                    <p class="mb-0"><strong>Clave:</strong> <?php echo $row['idUnidadMedida']; ?></p>
                                                    <p class="mb-0"><strong>Descripción:</strong> <?php echo $row['descripcion']; ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="id" value="<?php echo $row['idUnidadMedida']; ?>">
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
            
            <!-- Paginación -->
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
