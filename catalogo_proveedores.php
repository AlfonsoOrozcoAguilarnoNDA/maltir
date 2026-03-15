<?php
// Modelo: KIMI 2.5
// Módulo: Catálogo de Proveedores
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
    $idProveedor = intval($_POST['idProveedor']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $razon_social = trim($_POST['razon_social']);
    $rfc = trim($_POST['rfc']);
    $persona_contacto = trim($_POST['persona_contacto']);
    $comentario = trim($_POST['comentario']);
    
    if (empty($nombre)) {
        $mensaje = 'El campo Nombre es obligatorio.';
        $tipo_mensaje = 'danger';
    } else {
        if ($idProveedor > 0) {
            // EDITAR
            $sql = "UPDATE cat_proveedores SET 
                nombre = '$nombre',
                descripcion = '$descripcion',
                razon_social = '$razon_social',
                rfc = '$rfc',
                persona_contacto = '$persona_contacto',
                comentario = '$comentario',
                ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                usuario_modifica = '$session_usuario'
                WHERE idProveedor = $idProveedor";
            mysqli_query($link, $sql);
            $mensaje = 'Proveedor actualizado correctamente.';
            $tipo_mensaje = 'success';
        } else {
            // CREAR
            $sql = "INSERT INTO cat_proveedores (
                nombre, descripcion, razon_social, rfc, persona_contacto, comentario,
                activo, fecha_alta, fecha_baja, ultima_actualizacion, usuario_alta, usuario_modifica
            ) VALUES (
                '$nombre', '$descripcion', '$razon_social', '$rfc', '$persona_contacto', '$comentario',
                'si', CONVERT_TZ(NOW(),'UTC','America/Mexico_City'), NULL, CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
                '$session_usuario', '$session_usuario'
            )";
            mysqli_query($link, $sql);
            $mensaje = 'Proveedor creado correctamente.';
            $tipo_mensaje = 'success';
        }
    }
}

// ACTIVAR / DESACTIVAR
if (isset($_POST['cambiar_estado'])) {
    $id = intval($_POST['id']);
    $nuevo_estado = $_POST['nuevo_estado'];
    $fecha_baja = ($nuevo_estado == 'no') ? "CONVERT_TZ(NOW(),'UTC','America/Mexico_City')" : "NULL";
    
    $sql = "UPDATE cat_proveedores SET 
        activo = '$nuevo_estado',
        fecha_baja = $fecha_baja,
        ultima_actualizacion = CONVERT_TZ(NOW(),'UTC','America/Mexico_City'),
        usuario_modifica = '$session_usuario'
        WHERE idProveedor = $id";
    mysqli_query($link, $sql);
    $mensaje = 'Estado actualizado correctamente.';
    $tipo_mensaje = 'success';
}

// OBTENER DATOS PARA EDICIÓN
$editando = false;
$datos_edit = ['idProveedor' => 0, 'nombre' => '', 'descripcion' => '', 'razon_social' => '', 'rfc' => '', 'persona_contacto' => '', 'comentario' => ''];
if (isset($_GET['editar'])) {
    $id_edit = intval($_GET['editar']);
    $res = mysqli_query($link, "SELECT * FROM cat_proveedores WHERE idProveedor = $id_edit");
    if (mysqli_num_rows($res) > 0) {
        $datos_edit = mysqli_fetch_assoc($res);
        $editando = true;
    }
}

// PAGINACIÓN
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 50;
$inicio = ($pagina - 1) * $registros_por_pagina;

$total_res = mysqli_query($link, "SELECT COUNT(*) as total FROM cat_proveedores");
$total_row = mysqli_fetch_assoc($total_res);
$total_registros = $total_row['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

$sql = "SELECT * FROM cat_proveedores ORDER BY nombre LIMIT $inicio, $registros_por_pagina";
$resultado = mysqli_query($link, $sql);
?>

<div class="container-fluid">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-truck mr-2"></i>Catálogo de Proveedores</h2>
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
            <?php echo $editando ? 'Editar Proveedor' : 'Nuevo Proveedor'; ?>
        </div>
        <div class="card-body">
            <form method="POST" class="form-sistema">
                <input type="hidden" name="idProveedor" value="<?php echo $datos_edit['idProveedor']; ?>">
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" name="nombre" class="form-control" maxlength="200"
                                   value="<?php echo $datos_edit['nombre']; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Razón Social</label>
                            <input type="text" name="razon_social" class="form-control" maxlength="300"
                                   value="<?php echo $datos_edit['razon_social']; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>RFC</label>
                            <input type="text" name="rfc" class="form-control" maxlength="20"
                                   value="<?php echo $datos_edit['rfc']; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Contacto</label>
                            <input type="text" name="persona_contacto" class="form-control" maxlength="200"
                                   value="<?php echo $datos_edit['persona_contacto']; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Descripción</label>
                            <input type="text" name="descripcion" class="form-control" maxlength="500"
                                   value="<?php echo $datos_edit['descripcion']; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Comentarios</label>
                            <input type="text" name="comentario" class="form-control" maxlength="65535"
                                   value="<?php echo $datos_edit['comentario']; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="text-right">
                    <?php if ($editando): ?>
                        <a href="catalogo_proveedores.php" class="btn btn-cancelar mr-2">Cancelar</a>
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
            <span><i class="fas fa-list mr-2"></i>Listado de Proveedores</span>
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
                            <th>Razón Social</th>
                            <th>RFC</th>
                            <th>Contacto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($resultado)): ?>
                            <tr>
                                <td><?php echo $row['idProveedor']; ?></td>
                                <td><strong><?php echo $row['nombre']; ?></strong></td>
                                <td><?php echo $row['razon_social'] ? $row['razon_social'] : '-'; ?></td>
                                <td><?php echo $row['rfc'] ? $row['rfc'] : '-'; ?></td>
                                <td><?php echo $row['persona_contacto'] ? $row['persona_contacto'] : '-'; ?></td>
                                <td>
                                    <span class="badge badge-status-<?php echo $row['activo'] == 'si' ? 'ok' : 'warning'; ?>">
                                        <?php echo $row['activo'] == 'si' ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                    <?php if ($row['activo'] == 'no' && $row['fecha_baja']): ?>
                                        <br><small class="text-muted"><?php echo $row['fecha_baja']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?editar=<?php echo $row['idProveedor']; ?>" class="btn btn-editar btn-sm mr-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-<?php echo $row['activo'] == 'si' ? 'eliminar' : 'activar'; ?> btn-sm" 
                                            data-toggle="modal" data-target="#modal<?php echo $row['idProveedor']; ?>">
                                        <i class="fas fa-<?php echo $row['activo'] == 'si' ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    
                                    <div class="modal fade" id="modal<?php echo $row['idProveedor']; ?>" tabindex="-1" role="dialog">
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
                                                    <p>¿Está seguro que desea <strong><?php echo $row['activo'] == 'si' ? 'DESACTIVAR' : 'ACTIVAR'; ?></strong> el proveedor?</p>
                                                    <p class="mb-0"><strong>Nombre:</strong> <?php echo $row['nombre']; ?></p>
                                                    <?php if ($row['activo'] == 'si'): ?>
                                                        <p class="text-muted mt-2 mb-0">Se registrará la fecha de baja automáticamente.</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="id" value="<?php echo $row['idProveedor']; ?>">
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
