<?php
// Modelo: KIMI 2.5
// Módulo: Consulta de Movimientos de Kardex
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
$fecha_desde = isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : date('Y-m-d H:i:s');
$idArticulo_filtro = isset($_POST['idArticulo']) ? intval($_POST['idArticulo']) : 0;

// Paginación
$pagina = isset($_POST['pagina']) ? intval($_POST['pagina']) : 1;
$registros_por_pagina = 50;
$offset = ($pagina - 1) * $registros_por_pagina;

// Construir WHERE
$where = "WHERE k.fecha_movimiento BETWEEN '$fecha_desde' AND '$fecha_hasta'";
if ($idArticulo_filtro > 0) {
    $where .= " AND k.idArticulo = $idArticulo_filtro";
}

// Contar total para paginación
$sql_count = "SELECT COUNT(*) as total FROM kardex k $where";
$res_count = mysqli_query($link, $sql_count);
$total_registros = mysqli_fetch_assoc($res_count)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener datos
$sql = "SELECT k.*, a.nombre as articulo_nombre 
        FROM kardex k
        JOIN cat_articulos a ON k.idArticulo = a.idArticulo
        $where
        ORDER BY k.fecha_movimiento DESC
        LIMIT $offset, $registros_por_pagina";
$res = mysqli_query($link, $sql);

// Calcular totales del filtro
$sql_totales = "SELECT 
                SUM(CASE WHEN k.tipo_movimiento = 'entrada' THEN k.cantidad ELSE 0 END) as total_entradas,
                SUM(CASE WHEN k.tipo_movimiento = 'salida' THEN k.cantidad ELSE 0 END) as total_salidas,
                SUM(CASE WHEN k.tipo_movimiento = 'ajuste' THEN k.cantidad ELSE 0 END) as total_ajustes
                FROM kardex k $where";
$res_totales = mysqli_query($link, $sql_totales);
$totales = mysqli_fetch_assoc($res_totales);
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header">
            <i class="fas fa-warehouse mr-2"></i> Consulta de Kardex
        </div>
        <div class="card-body">
            
            <!-- Filtros -->
            <form method="POST" class="form-sistema mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Fecha Desde</label>
                            <input type="datetime-local" name="fecha_desde" class="form-control" value="<?php echo $fecha_desde; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Fecha Hasta</label>
                            <input type="datetime-local" name="fecha_hasta" class="form-control" value="<?php echo $fecha_hasta; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Artículo (opcional)</label>
                            <select name="idArticulo" class="form-control custom-select">
                                <option value="0">Todos los artículos</option>
                                <?php
                                $res_art = mysqli_query($link, "SELECT idArticulo, nombre FROM cat_articulos WHERE activo = 'si' ORDER BY nombre");
                                while ($row = mysqli_fetch_assoc($res_art)) {
                                    $selected = ($idArticulo_filtro == $row['idArticulo']) ? 'selected' : '';
                                    echo '<option value="' . $row['idArticulo'] . '" ' . $selected . '>' . htmlspecialchars($row['nombre']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-guardar btn-block">
                                <i class="fas fa-search mr-2"></i> Filtrar
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
                            <th>ID</th>
                            <th>Fecha Movimiento</th>
                            <th>Tipo</th>
                            <th>Artículo</th>
                            <th>Cantidad</th>
                            <th>Referencia</th>
                            <th>Comentario</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        while ($row = mysqli_fetch_assoc($res)) {
                            $clase_tipo = '';
                            $icono_tipo = '';
                            switch($row['tipo_movimiento']) {
                                case 'entrada':
                                    $clase_tipo = 'text-success';
                                    $icono_tipo = '<i class="fas fa-arrow-down mr-1"></i>';
                                    break;
                                case 'salida':
                                    $clase_tipo = 'text-danger';
                                    $icono_tipo = '<i class="fas fa-arrow-up mr-1"></i>';
                                    break;
                                case 'ajuste':
                                    $clase_tipo = 'text-warning';
                                    $icono_tipo = '<i class="fas fa-balance-scale mr-1"></i>';
                                    break;
                            }
                            
                            echo '<tr>';
                            echo '<td>' . $row['idKardex'] . '</td>';
                            echo '<td>' . date('d/m/Y H:i', strtotime($row['fecha_movimiento'])) . '</td>';
                            echo '<td class="' . $clase_tipo . ' font-weight-bold">' . $icono_tipo . ucfirst($row['tipo_movimiento']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['articulo_nombre']) . '</td>';
                            echo '<td class="font-weight-bold">' . number_format($row['cantidad'], 6) . '</td>';
                            echo '<td>' . htmlspecialchars($row['referencia'] ?? '-') . '</td>';
                            echo '<td>' . htmlspecialchars($row['comentario'] ?? '-') . '</td>';
                            echo '<td>' . htmlspecialchars($row['usuario_alta']) . '</td>';
                            echo '</tr>';
                        }
                        if (mysqli_num_rows($res) == 0) {
                            echo '<tr><td colspan="8" class="text-center text-muted">No se encontraron movimientos</td></tr>';
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
                            <input type="hidden" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                            <input type="hidden" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                            <input type="hidden" name="idArticulo" value="<?php echo $idArticulo_filtro; ?>">
                            <button type="submit" class="page-link"><?php echo $i; ?></button>
                        </form>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <!-- Totales -->
            <div class="card mt-4 bg-light">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-calculator mr-2"></i> Totales del Período Filtrado</h6>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="alert alert-success mb-0">
                                <h4 class="mb-0"><?php echo number_format($totales['total_entradas'] ?? 0, 6); ?></h4>
                                <small>Total Entradas</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-danger mb-0">
                                <h4 class="mb-0"><?php echo number_format($totales['total_salidas'] ?? 0, 6); ?></h4>
                                <small>Total Salidas</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-warning mb-0">
                                <h4 class="mb-0"><?php echo number_format($totales['total_ajustes'] ?? 0, 6); ?></h4>
                                <small>Total Ajustes</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
</div>

</div>

<?php require_once 'footerkimi.php'; ?>
