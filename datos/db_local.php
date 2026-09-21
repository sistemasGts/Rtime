<?php
/*
$host = "localhost";
$user = "root";
$password = "";
$database = "tsoluciona";

// Crear la conexión inicial
$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die('Connection Failed: ' . mysqli_connect_error());
}
*/
/*
$host = "tsoluciona-db.czkntryqkyrf.us-east-1.rds.amazonaws.com";
$user = "tsoluciona";
$password = "elQDL4SitNMeIIc5xFVt";
$database = "tsoluciona";
*/

$host = "localhost";
$user = "root";
$password = "";
$database = "rt_local";


// Verifica si la función ya está declarada antes de definirla
if (!function_exists('createConnection')) {
    function createConnection() {
        global $host, $user, $password, $database;

        $con2 = new mysqli($host, $user, $password, $database);

        if ($con2->connect_error) {
            if (defined('DB_SOFT_FAIL') && DB_SOFT_FAIL === true) {
                error_log('DB_SOFT_FAIL conexión: ' . $con2->connect_error);
                return null;
            }
            die('Error de conexión: ' . $con2->connect_error);
        }

        $con2->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $con2->set_charset('utf8mb4');

        return $con2;
    }
}

// Verifica si la función ya está declarada antes de definirla
if (!function_exists('checkConnection')) {
    function checkConnection($con2) {
        global $host, $user, $password, $database;

        if (empty($con2) || !($con2 instanceof mysqli)) {
            return createConnection();
        }

        if (!$con2->ping()) {
            $con2->close();
            $con2 = createConnection();
        }

        return $con2;
    }
}

// Iniciar conexión
$con2 = createConnection();

// Antes de ejecutar cualquier consulta, verificar conexión
if ($con2 instanceof mysqli) {
    $con2 = checkConnection($con2);
}
?>