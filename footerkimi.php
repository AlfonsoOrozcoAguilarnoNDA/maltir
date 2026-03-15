<?php
// Modelo: KIMI 2.5
// Módulo: Footer Global del Sistema
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

</div><!-- Cierre de #subcontainer iniciado en headerkimi.php -->

<!-- ============================================
     FOOTER FIXED-BOTTOM
     ============================================ -->
<footer class="footer-sistema fixed-bottom">
    <div class="container-fluid">
        <div class="row align-items-center">
            
            <!-- Versión PHP -->
            <div class="col-md-3 text-center text-md-left">
                <small>
                    <i class="fab fa-php mr-1"></i>
                    PHP <?php echo phpversion(); ?>
                </small>
            </div>
            
            <!-- Derechos reservados -->
            <div class="col-md-6 text-center">
                <small>
                    <i class="fas fa-copyright mr-1"></i>
                    <?php echo date('Y'); ?> Sistema de Compras
                    <span class="mx-2">|</span>
                    Todos los derechos reservados
                </small>
            </div>
            
            <!-- IP y Tiempo de carga -->
            <div class="col-md-3 text-center text-md-right">
                <small>
                    <i class="fas fa-network-wired mr-1"></i>
                    IP: <?php echo isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'N/A'; ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-stopwatch mr-1"></i>
                    <?php 
                    $tiempo_fin = microtime(true);
                    $tiempo_total = $tiempo_fin - $tiempo_inicio;
                    echo number_format($tiempo_total, 3, '.', ''); 
                    ?> seg
                </small>
            </div>
            
        </div>
    </div>
</footer>

<style>
    /* ============================================
       ESTILOS DEL FOOTER
       ============================================ */
    .footer-sistema {
        background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        color: #bdc3c7;
        padding: 12px 0;
        font-size: 0.85rem;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
    }
    
    .footer-sistema i {
        color: #3498db;
    }
</style>

</body>
</html>
