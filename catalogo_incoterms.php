<?php
// Modelo: KIMI 2.5
// Módulo: Catálogo de Incoterms
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
    $idIncoterm = strtoupper(trim($_POST['idIncoterm']));
    $descripcion = trim($_POST['descripcion']);
    $comentario = trim($_POST['comentario']);
    
    if (empty($idIncoterm) || empty($descripcion)) {
        $mensaje = 'Los campos Código y Descripción son obligatorios.';
        $tipo_mensaje = 'danger';
    } elseif (strlen($idIncoterm) > 10) {
        $mensaje = 'El código no puede exceder 10 caracteres.';
        $tipo_mensaje = 'danger';
    } else {
        $check = mysqli_query($link, "SELECT idIncoterm FROM cat_incoterms WHERE idIncoterm = '$idIncoterm'");
        
        if (mysqli_num_rows($check) > 0) {
            $sql = "UPDATE cat_incoterms SET 
                descripcion = '$descripcion',
                comentario = '$comentario',
                ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                usuario_modifica = '$session_usuario'
                WHERE idIncoterm = '$idIncoterm'";
            mysqli_query($link, $sql);
            $mensaje = 'Incoterm actualizado correctamente.';
            $tipo_mensaje = 'success';
        } else {
            $sql = "INSERT INTO cat_incoterms (
                idIncoterm, descripcion, comentario,
                activo, fecha_alta, ultima_actualizacion, usuario_alta, usuario_modifica
            ) VALUES (
                '$idIncoterm', '$descripcion', '$comentario',
                'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                '$session_usuario', '$session_usuario'
            )";
            mysqli_query($link, $sql);
            $mensaje = 'Incoterm creado correctamente.';
            $tipo_mensaje = 'success';
        }
    }
}

// ACTIVAR / DESACTIVAR
if (isset($_POST['cambiar_estado'])) {
    $id = $_POST['id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    $sql = "UPDATE cat_incoterms SET 
        activo = '$nuevo_estado',
        ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
        usuario_modifica = '$session_usuario'
        WHERE idIncoterm = '$id'";
    mysqli_query($link, $sql);
    $mensaje = 'Estado actualizado correctamente.';
    $tipo_mensaje = 'success';
}

// OBTENER DATOS PARA EDICIÓN
$editando = false;
$datos_edit = null;
if (isset($_GET['editar'])) {
    $id_edit = $_GET['editar'];
    $res = mysqli_query($link, "SELECT * FROM cat_incoterms WHERE idIncoterm = '$id_edit'");
    if (mysqli_num_rows($res) > 0) {
        $datos_edit = mysqli_fetch_assoc($res);
        $editando = true;
    }
}

// PAGINACIÓN
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 50;
$inicio = ($pagina - 1) * $registros_por_pagina;

$total_res = mysqli_query($link, "SELECT COUNT(*) as total FROM cat_incoterms");
$total_row = mysqli_fetch_assoc($total_res);
$total_registros = $total_row['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

$sql = "SELECT * FROM cat_incoterms ORDER BY idIncoterm LIMIT $inicio, $registros_por_pagina";
$resultado = mysqli_query($link, $sql);
?>

<div class="container-fluid">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-ship mr-2"></i>Catálogo de Incoterms</h2>
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
            <?php echo $editando ? 'Editar Incoterm' : 'Nuevo Incoterm'; ?>
        </div>
        <div class="card-body">
            <form method="POST" class="form-sistema">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Código (PK) *</label>
                            <input type="text" name="idIncoterm" class="form-control" maxlength="10" 
                                   value="<?php echo $editando ? $datos_edit['idIncoterm'] : ''; ?>"
                                   <?php echo $editando ? 'readonly' : ''; ?> required>
                            <small class="ayuda-campo">Ej: EXW, FOB, CIF, DDP</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Descripción *</label>
                            <input type="text" name="descripcion" class="form-control" maxlength="200"
                                   value="<?php echo $editando ? $datos_edit['descripcion'] : ''; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Comentario / Condiciones</label>
                            <input type="text" name="comentario" class="form-control" maxlength="500"
                                   value="<?php echo $editando ? $datos_edit['comentario'] : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <?php if ($editando): ?>
                        <a href="catalogo_incoterms.php" class="btn btn-cancelar mr-2">Cancelar</a>
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
            <span><i class="fas fa-list mr-2"></i>Listado de Incoterms</span>
            <a href="?module=reporte" target="_blank" class="btn btn-reporte btn-sm">
                <i class="fas fa-file-alt mr-1"></i> Reporte
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sistema mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Comentario</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($resultado)): ?>
                            <tr>
                                <td><strong><?php echo $row['idIncoterm']; ?></strong></td>
                                <td><?php echo $row['descripcion']; ?></td>
                                <td><?php echo $row['comentario'] ? substr($row['comentario'], 0, 50) . '...' : '-'; ?></td>
                                <td>
                                    <span class="badge badge-status-<?php echo $row['activo'] == 'si' ? 'ok' : 'warning'; ?>">
                                        <?php echo $row['activo'] == 'si' ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?editar=<?php echo $row['idIncoterm']; ?>" class="btn btn-editar btn-sm mr-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-<?php echo $row['activo'] == 'si' ? 'eliminar' : 'activar'; ?> btn-sm" 
                                            data-toggle="modal" data-target="#modal<?php echo $row['idIncoterm']; ?>">
                                        <i class="fas fa-<?php echo $row['activo'] == 'si' ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    
                                    <div class="modal fade" id="modal<?php echo $row['idIncoterm']; ?>" tabindex="-1" role="dialog">
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
                                                    <p>¿Está seguro que desea <strong><?php echo $row['activo'] == 'si' ? 'DESACTIVAR' : 'ACTIVAR'; ?></strong> el Incoterm?</p>
                                                    <p class="mb-0"><strong>Código:</strong> <?php echo $row['idIncoterm']; ?></p>
                                                    <p class="mb-0"><strong>Descripción:</strong> <?php echo $row['descripcion']; ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="id" value="<?php echo $row['idIncoterm']; ?>">
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
