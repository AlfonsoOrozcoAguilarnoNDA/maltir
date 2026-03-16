<?php
// Modelo: KIMI 2.5
// Módulo: Reporte Imprimible de Entregas por Cotización
// NOTA: Este archivo NO incluye headerkimi ni footerkimi - es independiente
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
<?php
require_once 'config.php';

$idCotizacion = isset($_POST['idCotizacion']) ? intval($_POST['idCotizacion']) : 
                (isset($_GET['idCotizacion']) ? intval($_GET['idCotizacion']) : 0);

if ($idCotizacion == 0) {
    die('No se especificó una cotización válida.');
}

// Obtener datos de la cotización
$sql_cot = "SELECT m.*, 
            (SELECT COUNT(*) FROM cotizaciones_detalle WHERE idCotizacion = m.idCotizacion AND activo = 'si') as total_partidas
            FROM cotizaciones_maestro m 
            WHERE m.idCotizacion = $idCotizacion AND m.activo = 'si'";
$res_cot = mysqli_query($link, $sql_cot);

if (mysqli_num_rows($res_cot) == 0) {
    die('Cotización no encontrada.');
}

$cotizacion = mysqli_fetch_assoc($res_cot);

// Obtener partidas con entregas
$sql_det = "SELECT d.*, 
            a.nombre as articulo_nombre, 
            u.idUnidadMedida,
            p.nombre as proveedor_nombre,
            r.precio_total, r.fecha_comprometida, r.cantidad_autorizada as cantidad_autorizada_resp
            FROM cotizaciones_detalle d
            JOIN cat_articulos a ON d.idArticulo = a.idArticulo
            JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
            LEFT JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle AND r.autorizado = 'si' AND r.activo = 'si'
            LEFT JOIN cat_proveedores p ON r.idProveedor = p.idProveedor
            WHERE d.idCotizacion = $idCotizacion AND d.activo = 'si'
            ORDER BY d.idDetalle";
$res_det = mysqli_query($link, $sql_det);

$fecha_impresion = date('d/m/Y H:i:s');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Entrega - Cotización #<?php echo $idCotizacion; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="compraskimi.css">
    <style>
        body {
            padding: 20px;
            font-size: 12pt;
        }
        .reporte-header {
            border-bottom: 3px solid #34495e;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .reporte-titulo {
            font-size: 24pt;
            font-weight: bold;
            color: #2c3e50;
        }
        .tabla-reporte th {
            background-color: #34495e;
            color: white;
            font-size: 10pt;
        }
        .tabla-reporte td {
            font-size: 10pt;
            vertical-align: middle;
        }
        .estado-completo { color: #27ae60; font-weight: bold; }
        .estado-parcial { color: #f39c12; font-weight: bold; }
        .estado-pendiente { color: #95a5a6; }
        .info-box {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .firma-area {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 10px;
            width: 300px;
            text-align: center;
        }
        @media print {
            .no-imprimir {
                display: none !important;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Botón de imprimir (solo pantalla) -->
    <div class="no-imprimir mb-4 text-right">
        <button onclick="window.print()" class="btn btn-primary btn-lg">
            <i class="fas fa-print mr-2"></i> Imprimir Reporte
        </button>
        <button onclick="window.close()" class="btn btn-secondary btn-lg ml-2">
            <i class="fas fa-times mr-2"></i> Cerrar
        </button>
    </div>

    <!-- Encabezado del reporte -->
    <div class="reporte-header">
        <div class="row">
            <div class="col-md-8">
                <div class="reporte-titulo">REPORTE DE ENTREGA</div>
                <div class="text-muted">Sistema de Compras MalTir</div>
            </div>
            <div class="col-md-4 text-right">
                <div class="h3 mb-0">COTIZACIÓN #<?php echo $idCotizacion; ?></div>
                <div class="badge <?php echo ($cotizacion['cerrada'] == 'si') ? 'badge-danger' : 'badge-success'; ?> badge-lg">
                    <?php echo ($cotizacion['cerrada'] == 'si') ? 'CERRADA' : 'ABIERTA'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Información general -->
    <div class="info-box">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Fecha de Cotización:</strong> <?php echo $cotizacion['fecha_cotizacion'] ? date('d/m/Y H:i', strtotime($cotizacion['fecha_cotizacion'])) : 'Sin confirmar'; ?></p>
                <p><strong>Total de Partidas:</strong> <?php echo $cotizacion['total_partidas']; ?></p>
                <p><strong>Comentario:</strong> <?php echo nl2br(htmlspecialchars($cotizacion['comentario'])); ?></p>
            </div>
            <div class="col-md-6">
                <?php if ($cotizacion['cerrada'] == 'si'): ?>
                <p><strong>Fecha de Cierre:</strong> <?php echo date('d/m/Y H:i', strtotime($cotizacion['fecha_cierre'])); ?></p>
                <p><strong>Motivo de Cierre:</strong> <?php echo htmlspecialchars($cotizacion['motivo_cierre']); ?></p>
                <?php if ($cotizacion['hubo_incidencia'] == 'si'): ?>
                <p class="text-danger"><strong>⚠️ Hubo incidencias:</strong> <?php echo htmlspecialchars($cotizacion['descripcion_incidencia']); ?></p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabla de partidas -->
    <h5 class="mb-3"><i class="fas fa-list-ul mr-2"></i> Detalle de Partidas y Entregas</h5>
    <table class="table table-bordered tabla-reporte">
        <thead>
            <tr>
                <th>#</th>
                <th>Artículo</th>
                <th>Proveedor</th>
                <th>Solicitado</th>
                <th>Autorizado</th>
                <th>Recibido</th>
                <th>Diferencia</th>
                <th>Fecha Comprometida</th>
                <th>Fecha Recibida</th>
                <th>Días Diff.</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_solicitado = 0;
            $total_autorizado = 0;
            $total_recibido = 0;
            $contador = 0;
            
            while ($det = mysqli_fetch_assoc($res_det)) {
                $contador++;
                $cantidad_autorizada = $det['cantidad_autorizada'] > 0 ? $det['cantidad_autorizada'] : $det['cantidad_autorizada_resp'];
                
                $total_solicitado += $det['cantidad_solicitada'];
                $total_autorizado += $cantidad_autorizada;
                $total_recibido += $det['cantidad_recibida'];
                
                // Calcular diferencia de días
                $dias_diff = '-';
                $clase_dias = '';
                if ($det['fecha_comprometida'] && $det['fecha_recibida']) {
                    $fecha_comp = new DateTime($det['fecha_comprometida']);
                    $fecha_rec = new DateTime($det['fecha_recibida']);
                    $diff = $fecha_comp->diff($fecha_rec);
                    $dias_num = $diff->days;
                    
                    if ($fecha_rec > $fecha_comp) {
                        $dias_diff = '+' . $dias_num . ' días';
                        $clase_dias = 'text-danger';
                    } elseif ($fecha_rec < $fecha_comp) {
                        $dias_diff = '-' . $dias_num . ' días';
                        $clase_dias = 'text-success';
                    } else {
                        $dias_diff = '0 días';
                        $clase_dias = 'text-success';
                    }
                }
                
                // Estado
                if ($det['cantidad_recibida'] >= $cantidad_autorizada && $cantidad_autorizada > 0) {
                    $estado = '<span class="estado-completo"><i class="fas fa-check-circle mr-1"></i> Completo</span>';
                } elseif ($det['cantidad_recibida'] > 0) {
                    $estado = '<span class="estado-parcial"><i class="fas fa-clock mr-1"></i> Parcial</span>';
                } else {
                    $estado = '<span class="estado-pendiente"><i class="fas fa-hourglass mr-1"></i> Pendiente</span>';
                }
                
                $diferencia = $cantidad_autorizada - $det['cantidad_recibida'];
                $clase_diferencia = $diferencia > 0 ? 'text-danger' : 'text-success';
                
                echo '<tr>';
                echo '<td>' . $contador . '</td>';
                echo '<td>' . htmlspecialchars($det['articulo_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($det['proveedor_nombre'] ?? 'Sin asignar') . '</td>';
                echo '<td class="text-right">' . number_format($det['cantidad_solicitada'], 6) . ' ' . $det['idUnidadMedida'] . '</td>';
                echo '<td class="text-right">' . number_format($cantidad_autorizada, 6) . '</td>';
                echo '<td class="text-right font-weight-bold">' . number_format($det['cantidad_recibida'], 6) . '</td>';
                echo '<td class="text-right ' . $clase_diferencia . '">' . number_format($diferencia, 6) . '</td>';
                echo '<td>' . ($det['fecha_comprometida'] ? date('d/m/Y', strtotime($det['fecha_comprometida'])) : '-') . '</td>';
                echo '<td>' . ($det['fecha_recibida'] ? date('d/m/Y', strtotime($det['fecha_recibida'])) : '-') . '</td>';
                echo '<td class="' . $clase_dias . '">' . $dias_diff . '</td>';
                echo '<td>' . $estado . '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
        <tfoot class="thead-light">
            <tr>
                <td colspan="3" class="text-right font-weight-bold">TOTALES:</td>
                <td class="text-right font-weight-bold"><?php echo number_format($total_solicitado, 6); ?></td>
                <td class="text-right font-weight-bold"><?php echo number_format($total_autorizado, 6); ?></td>
                <td class="text-right font-weight-bold"><?php echo number_format($total_recibido, 6); ?></td>
                <td class="text-right font-weight-bold <?php echo ($total_autorizado - $total_recibido) > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo number_format($total_autorizado - $total_recibido, 6); ?>
                </td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>

    <!-- Pie de página -->
    <div class="row mt-5">
        <div class="col-md-6">
            <div class="firma-area">
                <p class="mb-0">_______________________________</p>
                <p>Recibió</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="firma-area float-right">
                <p class="mb-0">_______________________________</p>
                <p>Vo. Bo.</p>
            </div>
        </div>
    </div>

    <div class="text-center mt-5 text-muted small">
        <p>Reporte generado el <?php echo $fecha_impresion; ?> desde Sistema de Compras MalTir</p>
        <p>IP: <?php echo $_SERVER['REMOTE_ADDR']; ?> | Usuario: YO</p>
    </div>

</body>
</html>
