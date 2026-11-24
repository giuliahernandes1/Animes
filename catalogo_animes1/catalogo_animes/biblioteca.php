
<?php
require_once 'verifica_login.php';
require_once 'conexao.php';
require_once 'funcoes_anime.php';

// Compatibilidade com diferentes nomes de conexão
if (!isset($conn) && isset($conexao)) {
    $conn = $conexao;
}

if (!isset($conn) && isset($mysqli)) {
    $conn = $mysqli;
}

if (!isset($conn)) {
    die('Erro: conexão com o banco de dados não encontrada.');
}

// Recebe os filtros
$filtros = filtros_da_biblioteca($_GET);

try {

    // Consulta principal
    $sql = 'SELECT
                a.id,
                a.titulo,
                a.temporadas,
                a.genero,
                a.trailer_link,
                a.sinopse,
                a.imagem_capa,
                e.nome AS estudio_nome
            FROM animes a
            INNER JOIN estudios e ON e.id = a.estudio_id
            WHERE 1 = 1';

    $tipos = '';
    $params = array();

    // Busca por título ou sinopse
    if ($filtros['busca'] !== '') {

        $sql .= ' AND (a.titulo LIKE ? OR a.sinopse LIKE ?)';

        $termo = '%' . addcslashes($filtros['busca'], '%_\\') . '%';

        $tipos .= 'ss';

        $params[] = $termo;
        $params[] = $termo;
    }

    // Filtro por estúdio
    if ($filtros['estudio_id'] > 0) {

        $sql .= ' AND a.estudio_id = ?';

        $tipos .= 'i';

        $params[] = $filtros['estudio_id'];
    }

    // Filtro por gênero
    if ($filtros['genero'] !== '') {

        $sql .= ' AND a.genero = ?';

        $tipos .= 's';

        $params[] = $filtros['genero'];
    }

    $sql .= ' ORDER BY a.titulo ASC';

    // Prepara a consulta
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }

    // Vincula os parâmetros
    if ($tipos !== '') {

        $referencias = array();

        foreach ($params as $chave => $valor) {
            $referencias[$chave] = &$params[$chave];
        }

        mysqli_stmt_bind_param($stmt, $tipos, ...$referencias);
    }

    // Executa a consulta
    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    if (!$resultado) {
        throw new Exception(mysqli_stmt_error($stmt));
    }

    $total = mysqli_num_rows($resultado);

    // Busca os estúdios
    $estudios = mysqli_query(
        $conn,
        'SELECT id, nome FROM estudios ORDER BY nome'
    );

    if (!$estudios) {
        throw new Exception(mysqli_error($conn));
    }

} catch (Throwable $erro) {

    error_log('Erro em biblioteca.php: ' . $erro->getMessage());

    die('Erro ao consultar o banco de dados.');
}

// Verifica se existem filtros
$tem_filtro = (
    $filtros['busca'] !== '' ||
    $filtros['estudio_id'] > 0 ||
    $filtros['genero'] !== ''
);

// Mantém os filtros na edição
$parametros_edicao = parametros_de_retorno($filtros);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Biblioteca de Animes | Catálogo de Animes</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/estilo.css">

</head>

<body>

    <header>

        <h1>📚 Biblioteca de Animes</h1>

        <p>Visualize e filtre todos os animes cadastrados.</p>

        <nav aria-label="Navegação principal">

            <ul>

                <li><a href="index.php">Início</a></li>

                <li><a href="gerenciar_estudios.php">Gerenciar Estúdios</a></li>

                <li><a href="gerenciar_animes.php">Gerenciar Animes</a></li>

                <li>
                    <a href="biblioteca.php" aria-current="page">
                        Biblioteca de Animes
                    </a>
                </li>

                <li><a href="logout.php">Sair</a></li>

            </ul>

        </nav>

    </header>

    <main>

        <?php mostrar_mensagem(); ?>

        <!-- FILTROS -->

        <section aria-labelledby="titulo-filtros">

            <h2 id="titulo-filtros">Filtros</h2>

            <form class="form-grade" action="biblioteca.php" method="get">

                <p>

                    <label for="busca">Busca geral:</label><br>

                    <input
                        type="search"
                        id="busca"
                        name="busca"
                        value="<?= esc($filtros['busca']) ?>"
                        placeholder="Título ou palavra-chave"
                    >

                </p>

                <p>

                    <label for="filtro_estudio">Estúdio:</label><br>

                    <select id="filtro_estudio" name="estudio_id">

                        <option value="">Todos os estúdios</option>

                        <?php while ($estudio = mysqli_fetch_assoc($estudios)): ?>

                            <option
                                value="<?= (int)$estudio['id'] ?>"
                                <?= ($filtros['estudio_id'] === (int)$estudio['id']) ? 'selected' : '' ?>
                            >

                                <?= esc($estudio['nome']) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </p>

                <p>

                    <label for="filtro_genero">Gênero:</label><br>

                    <select id="filtro_genero" name="genero">

                        <option value="">Todos os gêneros</option>

                        <?php foreach (lista_generos() as $genero): ?>

                            <option
                                value="<?= esc($genero) ?>"
                                <?= ($filtros['genero'] === $genero) ? 'selected' : '' ?>
                            >

                                <?= esc($genero) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </p>

                <div class="acoes-form">

                    <button type="submit">Filtrar</button>

                    <a class="botao-cancelar" href="biblioteca.php">
                        Limpar filtros
                    </a>

                </div>

            </form>

        </section>

        <!-- TABELA DE ANIMES -->

        <section aria-labelledby="titulo-tabela">

            <h2 id="titulo-tabela">Animes Cadastrados</h2>

            <p class="info-resultado">

                <?= $total ?> anime(s) encontrado(s)

                <?= $tem_filtro ? ' com os filtros aplicados' : '' ?>.

            </p>

            <div class="tabela-responsiva">

                <table>

                    <thead>

                        <tr>

                            <th scope="col">Capa</th>
                            <th scope="col">Título</th>
                            <th scope="col">Estúdio</th>
                            <th scope="col">Gênero</th>
                            <th scope="col">Temporadas</th>
                            <th scope="col">Sinopse</th>
                            <th scope="col">Trailer</th>
                            <th scope="col">Ações</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($total === 0): ?>

                            <tr>
                                <td colspan="8">Nenhum anime encontrado.</td>
                            </tr>

                        <?php endif; ?>

                        <?php while ($anime = mysqli_fetch_assoc($resultado)): ?>

                            <tr>

                                <!-- CAPA -->

                                <td>

                                    <?php if (caminho_capa($anime['imagem_capa']) !== ''): ?>

                                        <img
                                            class="capa-mini"
                                            src="<?= esc(caminho_capa($anime['imagem_capa'])) ?>"
                                            alt="Capa de <?= esc($anime['titulo']) ?>"
                                        >

                                    <?php else: ?>

                                        <div class="imagem-exemplo">
                                            Sem capa
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <!-- INFORMAÇÕES -->

                                <td><?= esc($anime['titulo']) ?></td>

                                <td><?= esc($anime['estudio_nome']) ?></td>

                                <td>
                                    <span class="etiqueta">
                                        <?= esc($anime['genero']) ?>
                                    </span>
                                </td>

                                <td><?= (int)$anime['temporadas'] ?></td>

                                <td class="sinopse-curta">
                                    <?= esc($anime['sinopse']) ?>
                                </td>

                                <!-- TRAILER -->

                                <td>

                                    <?php if ($anime['trailer_link'] !== ''): ?>

                                        <a
                                            href="<?= esc($anime['trailer_link']) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Assistir
                                        </a>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>

                                <!-- AÇÕES -->

                                <td>

                                    <a href="gerenciar_animes.php?editar=<?= (int)$anime['id'] ?>&amp;<?= esc($parametros_edicao) ?>">
                                        Editar
                                    </a>

                                    |

                                    <form
                                        class="form-inline"
                                        action="excluir_anime.php"
                                        method="post"
                                        onsubmit="return confirm('Tem certeza que deseja excluir este anime?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$anime['id'] ?>"
                                        >

                                        <?php campos_ocultos_retorno($filtros); ?>

                                        <button type="submit" class="btn-link">
                                            Excluir
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <footer>

        <p>Catálogo de Animes</p>

    </footer>

</body>

</html>
