<?php
$servidor = "localhost";
$usuario  = "root";
$senha    = "Home@spSENAI2025!";
$banco    = "mente_conectada";

$conn = new mysqli($servidor, $usuario, $senha, $banco);
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>
