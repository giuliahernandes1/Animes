<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Impede cache de páginas restritas no navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Verifica se o usuário está autenticado com um ID válido
if (empty($_SESSION['usuario_id']) || (int)$_SESSION['usuario_id'] <= 0) {
    header("Location: login.php");
    exit;
}