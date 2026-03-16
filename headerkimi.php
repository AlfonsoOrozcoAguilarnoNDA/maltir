<?php
// Modelo: KIMI 2.5
// Módulo: Header Global del Sistema
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
// ============================================
// INICIALIZACIÓN GLOBAL
// ============================================
session_start();
// Tiempo de inicio para cálculo de render
$tiempo_inicio = microtime(true);

// Usuario de sesión (hardcoded por ahora)
$session_usuario = 'YO';

// Conexión a base de datos
require_once 'config.php';

// Desactivar modo estricto de MySQL
mysqli_query($link, "SET sql_mode = ''");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Compras</title>
    
    <!-- Bootstrap 4.6 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* Compensación para navbar fixed-top */
        body {
            padding-top: 70px;
            min-height: 100vh;
        }
        
        /* Navbar estilos */
        .navbar-sistema {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .navbar-brand i {
            margin-right: 8px;
            color: #3498db;
        }
        
        /* Badge del modelo */
        .badge-modelo {
            background: #e74c3c;
            color: white;
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 12px;
            margin-left: 10px;
        }
        
        /* Dropdown menus */
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border-radius: 8px;
        }
        
        .dropdown-item {
            padding: 10px 20px;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background: #ecf0f1;
            padding-left: 25px;
        }
        
        .dropdown-item i {
            width: 20px;
            margin-right: 8px;
            color: #7f8c8d;
        }
        
        /* Jumbotron */
        .jumbotron-sistema {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 0;
            border-radius: 0;
        }
        
        .jumbotron-sistema h1 {
            font-weight: 300;
            font-size: 2.5rem;
        }
        
        /* Usuario y logout */
        .usuario-info {
            display: flex;
            align-items: center;
            color: white;
        }
        
        .usuario-icono {
            font-size: 1.8rem;
            margin-right: 10px;
            color: #bdc3c7;
        }
        
        .btn-logout {
            background: transparent;
            border: 2px solid #e74c3c;
            color: #e74c3c;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: #e74c3c;
            color: white;
        }
        
        /* Link externo */
        .nav-link-externo {
            color: #1abc9c !important;
            font-weight: 500;
        }
        
        .nav-link-externo:hover {
            color: #16a085 !important;
        }
    </style>
</head>
<body>

<!-- ============================================
     NAVBAR FIXED-TOP
     ============================================ -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-sistema fixed-top">
    <div class="container-fluid">
        
        <!-- Brand -->
        <a class="navbar-brand" href="compras.php">
            <i class="fas fa-shopping-cart"></i>
            Sistema de Compras
            <span class="badge-modelo">KIMI 2.5</span>
        </a>
        
        <!-- Toggle móvil -->
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                
                <!-- Menú Admin -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="menuAdmin" role="button" data-toggle="dropdown">
                        <i class="fas fa-cog mr-1"></i> Admin
                    </a>
                    <div class="dropdown-menu" aria-labelledby="menuAdmin">
                        <a class="dropdown-item" href="#"><i class="fas fa-users"></i> Gestión de Usuarios</a>
                        <a class="dropdown-item" href="#"><i class="fas fa-database"></i> Respaldo BD</a>
                        <a class="dropdown-item" href="#"><i class="fas fa-chart-line"></i> Reportes Globales</a>
                    </div>
                </li>
                
                <!-- Menú Usuario Normal -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="menuUsuario" role="button" data-toggle="dropdown">
                        <i class="fas fa-briefcase mr-1"></i> Operaciones
                    </a>                    
                   <div class="dropdown-menu" aria-labelledby="menuUsuario">
    <a class="dropdown-item" href="crearcotizacion.php"><i class="fas fa-file-alt"></i> Crear Cotización</a>
    <a class="dropdown-item" href="consulta_cotizaciones.php"><i class="fas fa-search"></i> Consultar Cotizaciones</a>
    <a class="dropdown-item" href="capturarrespuestacotizacion.php"><i class="fas fa-file-signature"></i> Capturar Respuesta</a>
    <a class="dropdown-item" href="entradamercancia.php"><i class="fas fa-truck-loading"></i> Entrada de Mercancía</a>
    <a class="dropdown-item" href="cerrarcotizacion.php"><i class="fas fa-lock"></i> Cerrar Cotización</a>
    <a class="dropdown-item" href="dashboard_pendientes.php"><i class="fas fa-tachometer-alt"></i> Dashboard Pendientes</a>
</div>
                </li>
                
                <!-- Catálogos -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="menuCatalogos" role="button" data-toggle="dropdown">
                        <i class="fas fa-list mr-1"></i> Catálogos
                    </a>
                    <div class="dropdown-menu" aria-labelledby="menuCatalogos">
                        <a class="dropdown-item" href="catalogo_unidades.php"><i class="fas fa-ruler"></i> Unidades de Medida</a>
                        <a class="dropdown-item" href="catalogo_incoterms.php"><i class="fas fa-ship"></i> Incoterms</a>
                        <a class="dropdown-item" href="catalogo_proveedores.php"><i class="fas fa-truck"></i> Proveedores</a>
                        <a class="dropdown-item" href="catalogo_articulos.php"><i class="fas fa-box"></i> Artículos</a>
                    </div>
                </li>
                
                <!-- Link externo -->
                <li class="nav-item">
                    <a class="nav-link nav-link-externo" href="https://google.com" target="_blank">
                        <i class="fas fa-external-link-alt mr-1"></i> Google
                    </a>
                </li>
                
            </ul>
            
            <!-- Usuario y Logout -->
            <div class="usuario-info">
                <i class="fas fa-user-circle usuario-icono"></i>
                <span class="mr-3"><?php echo htmlspecialchars($session_usuario); ?></span>
                <button class="btn btn-logout" data-toggle="modal" data-target="#modalLogout">
                    <i class="fas fa-sign-out-alt mr-1"></i> Salir
                </button>
            </div>
            
        </div>
    </div>
</nav>

<!-- ============================================
     MODAL DE CONFIRMACIÓN PARA SALIR
     ============================================ -->
<div class="modal fade" id="modalLogout" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Confirmar Cierre de Sesión
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro que desea cerrar su sesión?</p>
                <p class="text-muted mb-0">Se perderán los cambios no guardados.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cancelar
                </button>
                <a href="login.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt mr-1"></i> Sí, Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     JUMBOTRON (visible en desktop, oculto en móvil)
     ============================================ -->
<div class="jumbotron jumbotron-sistema d-none d-md-block">
    <div class="container">
        <h1><i class="fas fa-shopping-basket mr-3"></i>Gestión de Compras</h1>
        <p class="lead mb-0">Sistema integral para administración de cotizaciones, proveedores y seguimiento de mercancía.</p>
    </div>
</div>

<!-- Bootstrap JS (requerido para dropdowns y modals) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Inicio del subcontainer (se cierra en footerkimi.php) -->
<div id="subcontainer">
