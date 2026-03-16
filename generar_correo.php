<?php
// Modelo: KIMI 2.5
// Módulo: Generador de Texto para Correo Electrónico
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
$idCotizacion = isset($_POST['idCotizacion']) ? intval($_POST['idCotizacion']) : 0;

if ($idCotizacion == 0) {
    echo '<div class="alert alert-warning alert-sistema">No se especificó una cotización.</div>';
} else {
    // Obtener datos de la cotización
    $sql_cot = "SELECT * FROM cotizaciones_maestro WHERE idCotizacion = $idCotizacion AND activo = 'si'";
    $res_cot = mysqli_query($link, $sql_cot);
    $cotizacion = mysqli_fetch_assoc($res_cot);
    
    // Obtener proveedores con partidas autorizadas
    $sql_prov = "SELECT DISTINCT p.idProveedor, p.nombre, p.persona_contacto, p.rfc
                 FROM cat_proveedores p
                 JOIN respuesta_cotizacion r ON p.idProveedor = r.idProveedor
                 JOIN cotizaciones_detalle d ON r.idDetalle = d.idDetalle
                 WHERE d.idCotizacion = $idCotizacion AND r.autorizado = 'si' AND r.activo = 'si'
                 AND p.activo = 'si'
                 ORDER BY p.nombre";
    $res_prov = mysqli_query($link, $sql_prov);
    
    // Generar texto del correo
    $texto_correo = '';
    
    while ($prov = mysqli_fetch_assoc($res_prov)) {
        $nombre_proveedor = $prov['nombre'];
        $contacto = $prov['persona_contacto'] ? "Attn: " . $prov['persona_contacto'] : "";
        
        $texto_correo .= "Estimado proveedor {$nombre_proveedor},\n";
        if ($contacto) $texto_correo .= "{$contacto}\n";
        $texto_correo .= "\n";
        $texto_correo .= "Por medio del presente le solicitamos el siguiente material referente a nuestra cotización #{$idCotizacion}:\n\n";
        
        // Obtener partidas de este proveedor
        $sql_part = "SELECT d.*, a.nombre as articulo_nombre, u.idUnidadMedida, u.descripcion as unidad_desc,
                            i.idIncoterm, i.descripcion as incoterm_desc,
                            r.cantidad_autorizada, r.fecha_comprometida, r.precio_total
                     FROM cotizaciones_detalle d
                     JOIN cat_articulos a ON d.idArticulo = a.idArticulo
                     JOIN cat_unidades_medida u ON d.idUnidadMedida = u.idUnidadMedida
                     JOIN cat_incoterms i ON d.idIncoterm = i.idIncoterm
                     JOIN respuesta_cotizacion r ON d.idDetalle = r.idDetalle
                     WHERE d.idCotizacion = $idCotizacion 
                     AND r.idProveedor = {$prov['idProveedor']}
                     AND r.autorizado = 'si' AND r.activo = 'si' AND d.activo = 'si'
                     ORDER BY d.idDetalle";
        $res_part = mysqli_query($link, $sql_part);
        
        $texto_correo .= "DETALLE DE PARTIDAS:\n";
        $texto_correo .= str_repeat("-", 80) . "\n";
        
        $contador = 0;
        $hay_urgencia = false;
        
        while ($part = mysqli_fetch_assoc($res_part)) {
            $contador++;
            
            // Verificar urgencia
            $hoy = new DateTime();
            $fecha_req = new DateTime($part['fecha_requerida']);
            $dias_faltan = $hoy->diff($fecha_req)->days;
            if ($dias_faltan <= 7 && $hoy < $fecha_req) {
                $hay_urgencia = true;
            }
            
            $texto_correo .= "\n{$contador}. ARTÍCULO: {$part['articulo_nombre']}\n";
            $texto_correo .= "   Cantidad: " . number_format($part['cantidad_autorizada'], 6) . " {$part['idUnidadMedida']}\n";
            $texto_correo .= "   Incoterm requerido: {$part['idIncoterm']} ({$part['incoterm_desc']})\n";
            $texto_correo .= "   Fecha requerida: " . date('d/m/Y', strtotime($part['fecha_requerida'])) . "\n";
            if ($part['fecha_comprometida']) {
                $texto_correo .= "   Fecha comprometida por usted: " . date('d/m/Y', strtotime($part['fecha_comprometida'])) . "\n";
            }
            $texto_correo .= "   Precio autorizado: $" . number_format($part['precio_total'], 2) . "\n";
        }
        
        $texto_correo .= "\n" . str_repeat("-", 80) . "\n";
        
        // Comentario de urgencia si aplica
        if ($hay_urgencia) {
            $texto_correo .= "\n⚠️  URGENCIA: Algunas partidas requieren entrega en los próximos 7 días. ";
            $texto_correo .= "Solicitamos confirmar fechas de entrega lo antes posible.\n";
        }
        
        $texto_correo .= "\nAgradecemos su atención y quedamos a la espera de su confirmación.\n\n";
        $texto_correo .= "Saludos cordiales,\n";
        $texto_correo .= "Departamento de Compras\n";
        $texto_correo .= "Sistema de Compras MalTir\n";
        $texto_correo .= "Fecha de generación: " . date('d/m/Y H:i:s') . "\n";
        $texto_correo .= "\n" . str_repeat("=", 80) . "\n\n";
    }
}
?>

<div class="container-fluid">
    
    <div class="card card-modulo mb-4">
        <div class="card-header bg-info text-white">
            <i class="fas fa-envelope mr-2"></i> Generador de Correo Electrónico
            <?php if ($idCotizacion > 0): ?>
            <span class="badge badge-light float-right text-dark">Cotización #<?php echo $idCotizacion; ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            
            <?php if ($idCotizacion == 0): ?>
                <div class="alert alert-warning alert-sistema">
                    <i class="fas fa-exclamation-triangle mr-2"></i> 
                    Acceda a esta pantalla desde el Dashboard de Pendientes.
                </div>
            <?php else: ?>
                
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Instrucciones:</strong> El texto generado está listo para copiar y pegar en Outlook o Gmail. 
                    Use el botón "Copiar al portapapeles" y luego péguelo en su cliente de correo.
                </div>
                
                <!-- Área de texto generado -->
                <div class="form-group">
                    <label class="font-weight-bold">Texto del Correo:</label>
                    <textarea id="textoCorreo" class="form-control" rows="20" readonly style="font-family: monospace; white-space: pre; overflow-x: auto;"><?php echo htmlspecialchars($texto_correo); ?></textarea>
                </div>
                
                <!-- Botones de acción -->
                <div class="row">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-guardar btn-lg btn-block" onclick="copiarAlPortapapeles()">
                            <i class="fas fa-copy mr-2"></i> Copiar al Portapapeles
                        </button>
                    </div>
                    <div class="col-md-6">
                        <a href="dashboard_pendientes.php" class="btn btn-cancelar btn-lg btn-block">
                            <i class="fas fa-arrow-left mr-2"></i> Volver al Dashboard
                        </a>
                    </div>
                </div>
                
                <!-- Alerta de copiado -->
                <div id="alertaCopiado" class="alert alert-success alert-sistema mt-3" style="display:none;">
                    <i class="fas fa-check-circle mr-2"></i> ¡Texto copiado al portapapeles! Ahora puede pegarlo en su cliente de correo.
                </div>
                
                <!-- Resumen visual -->
                <hr class="separador-seccion mt-4">
                <h5 class="mb-3"><i class="fas fa-users mr-2"></i> Proveedores incluidos en este correo:</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Proveedor</th>
                                <th>Contacto</th>
                                <th>RFC</th>
                                <th>Partidas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            mysqli_data_seek($res_prov, 0);
                            while ($prov = mysqli_fetch_assoc($res_prov)) {
                                // Contar partidas
                                $sql_count = "SELECT COUNT(*) as total FROM respuesta_cotizacion r
                                              JOIN cotizaciones_detalle d ON r.idDetalle = d.idDetalle
                                              WHERE d.idCotizacion = $idCotizacion 
                                              AND r.idProveedor = {$prov['idProveedor']}
                                              AND r.autorizado = 'si' AND r.activo = 'si'";
                                $res_count = mysqli_query($link, $sql_count);
                                $count = mysqli_fetch_assoc($res_count)['total'];
                                
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($prov['nombre']) . '</td>';
                                echo '<td>' . htmlspecialchars($prov['persona_contacto'] ?? 'N/A') . '</td>';
                                echo '<td>' . htmlspecialchars($prov['rfc'] ?? 'N/A') . '</td>';
                                echo '<td><span class="badge badge-primary">' . $count . '</span></td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

<script>
function copiarAlPortapapeles() {
    var textarea = document.getElementById('textoCorreo');
    textarea.select();
    textarea.setSelectionRange(0, 99999); // Para móviles
    
    navigator.clipboard.writeText(textarea.value).then(function() {
        var alerta = document.getElementById('alertaCopiado');
        alerta.style.display = 'block';
        setTimeout(function() {
            alerta.style.display = 'none';
        }, 5000);
    }).catch(function(err) {
        // Fallback para navegadores antiguos
        document.execCommand('copy');
        var alerta = document.getElementById('alertaCopiado');
        alerta.style.display = 'block';
    });
}
</script>

</div>

<?php require_once 'footerkimi.php'; ?>
