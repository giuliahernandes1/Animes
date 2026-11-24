<?php
$servidor = "localhost";
$usuario = "root";
$senha = "Senai@118";
$banco = "catalogo_animes";

$conn = mysqli_connect($servidor, $usuario, $senha, $banco);

if (!$conn) {
    die("Falha na conexão com o banco de dados: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>