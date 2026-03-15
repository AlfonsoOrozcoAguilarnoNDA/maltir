<?php
/*
    This file is part of the project "maltir".

    Copyright (C) 2026 Alfonso Orozco Aguilar

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation; version 2.1 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with this program; if not, write to the Free Software Foundation,
    Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/
/**
 * Configuración de Base de Datos - Proyecto maltir
 * Laboratorio 1-1
 */

// Datos de conexión (Edita estos valores)
$host     = 'localhost';
$dbname   = 'tu_base_de_datos'; // Nombre de tu base de datos
$username = 'tu_usuario';
$password = 'tu_password';

// Crear la conexión mysqli
$link = mysqli_connect($host, $username, $password, $dbname);

// Verificar la conexión
if (!$link) {
    die("Error de conexión (" . mysqli_connect_errno() . "): " . mysqli_connect_error());
}

// Establecer el juego de caracteres a utf8mb4 (vital para nombres y descripciones)
if (!mysqli_set_charset($link, "utf8mb4")) {
    die("Error cargando el conjunto de caracteres utf8mb4: " . mysqli_error($link));
}

// Configurar la zona horaria (opcional, pero recomendado para logs)
date_default_timezone_set('America/Mexico_City');

/**
 * Nota de Laboratorio: 
 * Ahora, en tus otros archivos 
 * solo debes poner al principio: include_once 'config.php';
 * Y ya no necesitas declarar $link ni los datos de acceso.
 */
?>
