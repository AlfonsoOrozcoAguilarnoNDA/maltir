<?PHP
// license GPL
// Alfonso Orozxco Aguilar
// para fines de depuracion. Mas adelante debe llevar password o seguridad a nivel ip.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once 'config.php';
//include_once 'func_db.php';
//include_once 'abyss/ui_functions.php';

// Aplicar seguridad
//check_authorization();

// Establecer zona horaria de México
date_default_timezone_set('America/Mexico_City');

echo "<!DOCTYPE html>

<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Panel Metro</title>

    <!-- Bootstrap 4.6 -->    
	<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>

    <!-- Font Awesome -->
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css'>

    <style>
        body {
            padding-top: 70px;   /* espacio para navbar fijo */
            padding-bottom: 60px; /* espacio para footer fijo */
            background: #f2f2f2;
        }

        .tile {
            width: 150px;
            height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px;
            transition: 0.2s;
        }

        .tile:hover {
            transform: scale(1.05);
            opacity: 0.9;
            text-decoration: none;
            color: white;
        }

		.tile.bg-light {
			color: #333 !important;
		}

		.tile.bg-light i {
			color: #333 !important;
		}
		.tile.bg-warning {
			color: #333 !important;
		}

		.tile.bg-warning i {
			color: #333 !important;
		}
		
        .tile i {
            font-size: 40px;
            margin-bottom: 10px;
        }
.titulo-directorio {
    font-size: 22px;
    font-weight: bold;
    margin: 20px 0 10px 0;
    color: #444;
    text-transform: uppercase;
    letter-spacing: 1px;
}
        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            height: 50px;
            background: #343a40;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
    </style>
</head>

<body>

<!-- ✅ NAVBAR FIJO -->
<nav class='navbar navbar-expand-lg navbar-dark bg-dark fixed-top'>
    <a class='navbar-brand' href='#'>Mi Panel</a>

    <button class='navbar-toggler' type='button' data-toggle='collapse' data-target='#menu'>
        <span class='navbar-toggler-icon'></span>
    </button>

    <div class='collapse navbar-collapse' id='menu'>
        <ul class='navbar-nav mr-auto'>
            
            <li class='nav-item'><a class='nav-link' href='https://google.com' target='_blank'>Google</a></li>
        

 <!-- ✅ Dropdown vertical con íconos -->
    <li class='nav-item dropdown'>
        <a class='nav-link dropdown-toggle' href='#' id='menuOpciones' role='button' data-toggle='dropdown'>
            <i class='fa fa-bars'></i> Opciones
        </a>

        <div class='dropdown-menu' aria-labelledby='menuOpciones'>

            <a class='dropdown-item' href='#'>
                <i class='fa fa-check-circle'></i> Prueba 1
            </a>

            <a class='dropdown-item' href='#'>
                <i class='fa fa-cog'></i> Prueba 2
            </a>

            <a class='dropdown-item' href='#'>
                <i class='fa fa-folder-open'></i> Prueba 3
            </a>        
        </div>
    </li>
	</ul>
        <a href='?module=logout' 
   class='btn btn-danger'
   onclick='return confirm(".chr(34)."¿Seguro que deseas salir?".chr(34).");'>
    <i class='fa fa-sign-out-alt' style='color:yellow;'></i> 
    <span style='color:yellow;'>Salir</span>
</a>
    </div>
</nav>

<!-- ✅ CONTENEDOR DE TILES -->
<div class='container'>
    <div class='row justify-content-center'>

        <!-- ✅ TILE EJEMPLO A GOOGLE -->
        <a href='https://google.com' target='_self' class='tile bg-light'>
            <i class='fa fa-globe'></i>
            <span>Google</span>
        </a>
		<a href='' target='_self' class='tile bg-dark'>
            <i class='fa fa-cog'></i>
            <span>Config</span>
        </a>
		<a href='' target='_self' class='tile bg-dark'>
            <i class='fa fa-database'></i>
            <span>Func_db</span>
        </a>
		<a href='' target='_self' class='tile bg-dark'>
            <i class='fa fa-puzzle-piece'></i>
            <span>ui_functions</span>
        </a>

        <!-- ✅ EJEMPLOS PARA TUS ARCHIVOS PHP -->
        <a href='archivo1.php' class='tile bg-success'>
            <i class='fa fa-file'></i>
            <span>Archivo 1</span>
        </a>        

        <!-- Aquí puedes seguir agregando tus 20–25 tiles -->
		".generar_tiles_php();
/*."
		<div class='titulo-directorio'><center>Directorio<br />AG</center></div>
		".generar_tiles_php("./ag")."
		<div class='titulo-directorio'><center>Directorio<br />Radix</center></div>
		".generar_tiles_php("abyss")*/
		
    echo "</div>
</div>

<!-- FOOTER FIJO -->
<footer>
    " . datosfooter()."
</footer>

<!-- Bootstrap JS -->
<script src='https://code.jquery.com/jquery-3.5.1.slim.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js'></script>

</body>
</html>";

function datosfooter(){
        $ip = $_SERVER['REMOTE_ADDR'];
        $phpVersion = phpversion();

        // Detectar versión de Bootstrap si está cargada
        $bootstrapVersion = 'N/D';
        if (isset($GLOBALS['bootstrap'])) {
            $bootstrapVersion = $GLOBALS['bootstrap'];
        }

        // Detectar versión de Font Awesome si está cargada
        $faVersion = 'N/D';
        if (defined('FA_VERSION')) {
            $faVersion = FA_VERSION;
        }
    return "Panel Metro &copy; ". date('Y') ." |
    IP: $ip | PHP: $phpVersion "; //| Bootstrap: $bootstrapVersion | FontAwesome: $faVersion";
}	

function generar_tiles_php($directorio = '.') {
    $mosaico = "";  

    // Detectar el archivo actual (ej: index.php, panel.php, dashboard.php, etc.)
    $archivoActual = basename($_SERVER['PHP_SELF']);

    // Archivos que NO deben aparecer como tiles
    $excluir = ['pru.php', $archivoActual
	,'ui_functions.php'
	,'config.php'
	,'func_db.php'
	];

    // Colores Bootstrap para rotar automáticamente
    $colores = ['bg-primary', 'bg-success', 'bg-warning', 'bg-info', 'bg-danger', 'bg-secondary'];
    $colorIndex = 0;

    // Leer archivos del directorio
    $archivos = scandir($directorio);

    foreach ($archivos as $archivo) {

        // Solo archivos .php
        if (substr($archivo, -4) !== '.php') {
            continue;
        }

        // Excluir archivos específicos
        if (in_array($archivo, $excluir)) {
            continue;
        }

        // Asignar color rotatorio
        $color = $colores[$colorIndex];
        $colorIndex = ($colorIndex + 1) % count($colores);

        // Nombre visible sin .php
        $nombre = ucfirst(str_replace('.php', '', $archivo));

        // ✅ Contar líneas de código
        $lineas = count(file($directorio . '/' . $archivo));

        // Generar tile
        $mosaico .= "
        <a href='$directorio/$archivo' class='tile $color'>
            <i class='fa fa-file-code'></i>
            <span>$nombre</span>
            <small style='font-size:12px; opacity:0.8;'>$lineas líneas</small>
        </a>
        ";
    }

    return $mosaico;
} // fin generar_tiles_php
?>
