<?php

require_once 'verifica_login.php';
require_once 'conexao.php';
require_once 'funcoes_anime.php';

/** @var mysqli $conn */

// Verifica se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gerenciar_animes.php');
    exit;
}

// Verifica a origem da solicitação
$veio_da_biblioteca = (
    isset($_POST['origem']) &&
    $_POST['origem'] === 'biblioteca'
);

// Define a página de retorno
$url_volta = 'gerenciar_animes.php';

if ($veio_da_biblioteca) {
    $url_volta = url_biblioteca(
        filtros_de_retorno($_POST)
    );
}

// Recebe o ID do anime
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

try {
    // Garante que a conexão com o banco existe
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Falha na conexão com o banco de dados.');
    }

    // Verifica se o ID é válido
    if ($id <= 0) {
        throw new Exception('ID inválido.');
    }

    // Busca o anime no banco de dados
    $anime = buscar_anime($conn, $id);

    if (!$anime) {
        definir_mensagem('erro', 'Anime não encontrado.');
    } else {
        // Prepara a exclusão
        $sql = 'DELETE FROM animes WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new Exception(mysqli_error($conn));
        }

        // Vincula o ID
        mysqli_stmt_bind_param($stmt, 'i', $id);

        // Executa a exclusão
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(mysqli_stmt_error($stmt));
        }

        mysqli_stmt_close($stmt);

        // Exclui a imagem de capa
        apagar_capa($anime['imagem_capa']);

        // Mensagem de sucesso
        definir_mensagem(
            'sucesso',
            'Anime excluído com sucesso.'
        );
    }

} catch (Throwable $erro) {
    error_log('Erro ao excluir anime: ' . $erro->getMessage());

    definir_mensagem(
        'erro',
        'Não foi possível excluir o anime. Tente novamente.'
    );
}

// Redireciona para a página anterior
header('Location: ' . $url_volta);
exit;