<?php
require_once 'verifica_login.php';

$nome_usuario = isset($_SESSION['usuario_nome']) && $_SESSION['usuario_nome'] !== ''
    ? $_SESSION['usuario_nome']
    : 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Principal | Catálogo de Animes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <header>
        <h1>🎬 Catálogo de Animes</h1>
        <p>Bem-vindo(a), <strong><?= htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8') ?></strong>! Sistema de gerenciamento de estúdios e animes.</p>
        <nav aria-label="Navegação principal">
            <ul>
                <li><a href="index.php" aria-current="page">Início</a></li>
                <li><a href="gerenciar_estudios.php">Gerenciar Estúdios</a></li>
                <li><a href="gerenciar_animes.php">Gerenciar Animes</a></li>
                <li><a href="biblioteca.php">Biblioteca de Animes</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section aria-labelledby="titulo-menu">
            <h2 id="titulo-menu">Menu Principal</h2>
            <p>Escolha uma das opções abaixo para acessar o catálogo.</p>

            <div class="grade-menu">
                <article class="cartao-menu">
                    <h3>🏢 Gerenciar Estúdios</h3>
                    <p>Cadastre e consulte os estúdios responsáveis pelas animações.</p>
                    <a class="botao" href="gerenciar_estudios.php">Acessar estúdios</a>
                </article>

                <article class="cartao-menu">
                    <h3>📺 Gerenciar Animes</h3>
                    <p>Cadastre e consulte animes relacionados aos seus estúdios.</p>
                    <a class="botao" href="gerenciar_animes.php">Acessar animes</a>
                </article>

                <article class="cartao-menu">
                    <h3>📚 Biblioteca de Animes</h3>
                    <p>Consulte os animes cadastrados e pesquise utilizando os filtros.</p>
                    <a class="botao" href="biblioteca.php">Acessar biblioteca</a>
                </article>
            </div>
        </section>
    </main>

    <footer>
        <p>Catálogo de Animes</p>
    </footer>
</body>
</html>