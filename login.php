<?php
// Modelo: KIMI 2.5
// Módulo: Login de Acceso
// Nota: Pantalla independiente, sin headerkimi ni footerkimi
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
// CONFIGURACIÓN HARDCODED
// ============================================

// Ruta de wallpaper de fondo - si no existe se usa degradado CSS
$wallpaper = 'img/fondo_login.jpg';

// Logo: usar imagen o ícono Font Awesome
// 'imagen' = usar $logo_imagen | 'icono' = usar Font Awesome
$tipo_logo = 'icono';
$logo_imagen = 'img/logo.png'; // solo si $tipo_logo = 'imagen'
$logo_icono = 'fa-shopping-cart'; // ícono FA relacionado con compras

// Mensaje del día (aviso interno)
$mensaje_dia = 'El viernes fumigan a las 18:00, favor de no dejar comida en los escritorios.';

// ============================================
// PROCESAMIENTO DEL LOGIN (DUMMY)
// ============================================
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Validación: ambos campos obligatorios
    if (empty($usuario) || empty($password)) {
        $error = 'Usuario y contraseña son obligatorios.';
    } else {
        // Login dummy: cualquier usuario/password no vacío accede
        header('Location: compras.php');
        exit;
    }
}

// Verificar si existe el wallpaper
$existe_wallpaper = file_exists($wallpaper);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Compras - Login</title>
    
    <!-- Bootstrap 4.6 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" crossorigin="anonymous">    
    <style>
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        <?php if ($existe_wallpaper): ?>
        .login-background {
            background-image: url('<?php echo $wallpaper; ?>');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        <?php else: ?>
        .login-background {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        <?php endif; ?>
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icono {
            font-size: 64px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .logo-imagen {
            max-width: 150px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .titulo-sistema {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .subtitulo {
            font-size: 14px;
            color: #666;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-right: none;
            border-radius: 8px 0 0 8px;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .mensaje-dia {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-top: 20px;
            font-size: 13px;
            color: #856404;
        }
        
        .mensaje-dia i {
            margin-right: 8px;
            color: #ffc107;
        }
        
        .error-message {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 12px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .version-tag {
            position: fixed;
            bottom: 15px;
            right: 15px;
            background: rgba(0,0,0,0.5);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body class="login-background">

<div class="login-container">
    <div class="login-card">
        
        <!-- Logo -->
        <div class="logo-container">
            <?php if ($tipo_logo === 'imagen' && file_exists($logo_imagen)): ?>
                <img src="<?php echo $logo_imagen; ?>" alt="Logo" class="logo-imagen">
            <?php else: ?>
                <i class="fas <?php echo $logo_icono; ?> logo-icono"></i>
            <?php endif; ?>
            <div class="titulo-sistema">Sistema de Compras</div>
            <div class="subtitulo">Acceso al sistema</div>
        </div>
        
        <!-- Error -->
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulario -->
        <form method="POST" action="" novalidate>
            
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                    </div>
                    <input type="text" 
                           class="form-control" 
                           id="usuario" 
                           name="usuario" 
                           placeholder="Ingrese su usuario"
                           required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="Ingrese su contraseña"
                           required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-login">
                <i class="fas fa-sign-in-alt mr-2"></i> Entrar al Sistema
            </button>
            
        </form>
        
        <!-- Mensaje del día -->
        <div class="mensaje-dia">
            <i class="fas fa-bullhorn"></i>
            <strong>Aviso interno:</strong> <?php echo $mensaje_dia; ?>
        </div>
        
    </div>
</div>

<div class="version-tag">
    <i class="fas fa-robot mr-1"></i> Generado por KIMI 2.5
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Validación client-side -->
<script>
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByTagName('form');
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();
</script>
</body>
</html>
