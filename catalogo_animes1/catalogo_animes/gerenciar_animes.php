<?php
require_once 'verifica_login.php';
require_once 'conexao.php';
require_once 'funcoes_anime.php';

/** @var mysqli $conn */

$erros = array();
$estudios = false;
$lista = false;

// Veio da biblioteca? Então guardamos os filtros para voltar a ela depois.
$dados_requisicao = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;
$veio_da_biblioteca = (isset($dados_requisicao['origem']) && $dados_requisicao['origem'] === 'biblioteca');
$filtros = filtros_de_retorno($dados_requisicao);
$url_volta = $veio_da_biblioteca ? url_biblioteca($filtros) : 'gerenciar_animes.php';

// Valores iniciais do formulário
$dados = array(
    'id'           => 0,
    'titulo'       => '',
    'estudio_id'   => 0,
    'genero'       => '',
    'temporadas'   => 1,
    'trailer_link' => '',
    'sinopse'      => '',
    'imagem_capa'  => '',
);

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Falha na conexão com o banco de dados.');
    }

    /* ---------- Abrir um anime para editar (GET ?editar=ID) ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['editar'])) {
        $anime = buscar_anime($conn, (int)$_GET['editar']);
        if (!$anime) {
            definir_mensagem('erro', 'Anime não encontrado.');
            header('Location: ' . $url_volta);
            exit;
        }
        $dados = $anime;
    }

    /* ---------- Salvar (cadastrar ou editar) ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $dados = array(
            'id'           => $id,
            'titulo'       => limpar_texto(isset($_POST['titulo']) ? $_POST['titulo'] : ''),
            'estudio_id'   => (int)limpar_texto(isset($_POST['estudio_id']) ? $_POST['estudio_id'] : 0),
            'genero'       => limpar_texto(isset($_POST['genero']) ? $_POST['genero'] : ''),
            'temporadas'   => (int)limpar_texto(isset($_POST['temporadas']) ? $_POST['temporadas'] : 0),
            'trailer_link' => limpar_texto(isset($_POST['trailer_link']) ? $_POST['trailer_link'] : ''),
            'sinopse'      => limpar_texto(isset($_POST['sinopse']) ? $_POST['sinopse'] : ''),
            'imagem_capa'  => '',
        );

        // Na edição, a capa atual vem do banco (não do formulário)
        if ($id > 0) {
            $atual = buscar_anime($conn, $id);
            if (!$atual) {
                definir_mensagem('erro', 'Anime não encontrado.');
                header('Location: ' . $url_volta);
                exit;
            }
            $dados['imagem_capa'] = $atual['imagem_capa'];
        }

        // Validações
        if ($dados['titulo'] === '') {
            $erros[] = 'Informe o título do anime.';
        } elseif (mb_strlen($dados['titulo']) > 150) {
            $erros[] = 'O título deve ter no máximo 150 caracteres.';
        }
        if ($dados['estudio_id'] <= 0 || !estudio_existe($conn, $dados['estudio_id'])) {
            $erros[] = 'Selecione um estúdio válido.';
        }
        if (!in_array($dados['genero'], lista_generos(), true)) {
            $erros[] = 'Selecione um gênero válido.';
        }
        if ($dados['temporadas'] < 1 || $dados['temporadas'] > 99) {
            $erros[] = 'A quantidade de temporadas deve ser entre 1 e 99.';
        }
        if (!trailer_valido($dados['trailer_link'])) {
            $erros[] = 'Informe um link válido do YouTube (youtube.com/watch?v=... ou youtu.be/...).';
        }
        if (mb_strlen($dados['sinopse']) > 2000) {
            $erros[] = 'A sinopse deve ter no máximo 2000 caracteres.';
        }

        $enviou_imagem = isset($_FILES['imagem_capa']) && $_FILES['imagem_capa']['error'] !== UPLOAD_ERR_NO_FILE;
        if ($id === 0 && !$enviou_imagem) {
            $erros[] = 'Envie a imagem de capa.';
        }

        // Upload com redimensionamento (só se não houver erros antes)
        $nova_capa = '';
        if (!$erros && $enviou_imagem) {
            $erro_upload = '';
            $resultado = salvar_capa($_FILES['imagem_capa'], $erro_upload);
            if ($resultado === false) {
                $erros[] = $erro_upload;
            } else {
                $nova_capa = $resultado;
            }
        }

        // Grava no banco
        if (!$erros) {
            try {
                $capa_final = ($nova_capa !== '') ? $nova_capa : $dados['imagem_capa'];

                if ($id > 0) {
                    consultar_preparado(
                        $conn,
                        'UPDATE animes
                         SET estudio_id = ?, titulo = ?, temporadas = ?, genero = ?,
                             trailer_link = ?, sinopse = ?, imagem_capa = ?
                         WHERE id = ?',
                        'isissssi',
                        array(
                            $dados['estudio_id'], $dados['titulo'], $dados['temporadas'], $dados['genero'],
                            $dados['trailer_link'], $dados['sinopse'], $capa_final, $id
                        )
                    );
                    if ($nova_capa !== '') {
                        apagar_capa($dados['imagem_capa']); // remove a capa antiga do servidor
                    }
                    definir_mensagem('sucesso', 'Anime atualizado com sucesso.');
                } else {
                    consultar_preparado(
                        $conn,
                        'INSERT INTO animes (estudio_id, titulo, temporadas, genero, trailer_link, sinopse, imagem_capa)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        'isissss',
                        array(
                            $dados['estudio_id'], $dados['titulo'], $dados['temporadas'], $dados['genero'],
                            $dados['trailer_link'], $dados['sinopse'], $capa_final
                        )
                    );
                    definir_mensagem('sucesso', 'Anime cadastrado com sucesso.');
                }
                header('Location: ' . $url_volta);
                exit;
            } catch (Throwable $erro_banco) {
                error_log('Erro ao salvar anime: ' . $erro_banco->getMessage());
                apagar_capa($nova_capa); // não deixa imagem "órfã" no servidor
                $erros[] = 'Não foi possível salvar no banco de dados. Tente novamente.';
            }
        }
    }

    /* ---------- Dados para a tela ---------- */
    $estudios = mysqli_query($conn, 'SELECT id, nome FROM estudios ORDER BY nome');
    if (!$estudios) {
        throw new Exception(mysqli_error($conn));
    }

    $lista = mysqli_query(
        $conn,
        'SELECT a.id, a.titulo, a.temporadas, a.genero, a.trailer_link, a.imagem_capa, e.nome AS estudio_nome
         FROM animes a
         INNER JOIN estudios e ON e.id = a.estudio_id
         ORDER BY a.id DESC'
    );
    if (!$lista) {
        throw new Exception(mysqli_error($conn));
    }
} catch (Throwable $erro_geral) {
    error_log('Erro em gerenciar_animes.php: ' . $erro_geral->getMessage());
    $erros[] = 'Erro ao acessar o banco de dados. Verifique a conexão e tente novamente.';
}

$editando = ($dados['id'] > 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Animes | Catálogo de Animes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <header>
        <h1>📺 Gerenciar Animes</h1>
        <p>Cadastre, edite e exclua os animes vinculados aos estúdios.</p>
        <nav aria-label="Navegação principal">
            <ul>
                <li><a href="index.php">Início</a></li>
                <li><a href="gerenciar_estudios.php">Gerenciar Estúdios</a></li>
                <li><a href="gerenciar_animes.php" aria-current="page">Gerenciar Animes</a></li>
                <li><a href="biblioteca.php">Biblioteca de Animes</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <?php mostrar_mensagem(); ?>

        <section aria-labelledby="titulo-formulario">
            <h2 id="titulo-formulario"><?= $editando ? 'Editar Anime' : 'Cadastrar Anime' ?></h2>
            <?php mostrar_erros($erros); ?>

            <form class="form-grade" action="gerenciar_animes.php" method="post" enctype="multipart/form-data">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= (int)$dados['id'] ?>">
                <?php endif; ?>
                <?php if ($veio_da_biblioteca) { campos_ocultos_retorno($filtros); } ?>

                <p>
                    <label for="titulo">Título do anime:</label><br>
                    <input type="text" id="titulo" name="titulo" maxlength="150" value="<?= esc($dados['titulo']) ?>" placeholder="Ex.: Anime Exemplo" required>
                </p>
                <p>
                    <label for="estudio_id">Estúdio:</label><br>
                    <select id="estudio_id" name="estudio_id" required>
                        <option value="">Selecione um estúdio</option>
                        <?php if ($estudios): ?>
                            <?php while ($estudio = mysqli_fetch_assoc($estudios)): ?>
                                <option value="<?= (int)$estudio['id'] ?>"<?= ((int)$dados['estudio_id'] === (int)$estudio['id']) ? ' selected' : '' ?>>
                                    <?= esc($estudio['nome']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </p>
                <p>
                    <label for="genero">Gênero:</label><br>
                    <select id="genero" name="genero" required>
                        <option value="">Selecione um gênero</option>
                        <?php foreach (lista_generos() as $genero): ?>
                            <option value="<?= esc($genero) ?>"<?= ($dados['genero'] === $genero) ? ' selected' : '' ?>><?= esc($genero) ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="temporadas">Temporadas:</label><br>
                    <input type="number" id="temporadas" name="temporadas" min="1" max="99" value="<?= (int)$dados['temporadas'] ?>" required>
                </p>
                <p class="campo-largo">
                    <label for="trailer_link">Link do trailer (YouTube):</label><br>
                    <input type="text" id="trailer_link" name="trailer_link" value="<?= esc($dados['trailer_link']) ?>" placeholder="https://www.youtube.com/watch?v=...">
                </p>
                <p class="campo-largo">
                    <label for="sinopse">Sinopse:</label><br>
                    <textarea id="sinopse" name="sinopse" rows="5" maxlength="2000" placeholder="Escreva um resumo do anime..."><?= esc($dados['sinopse']) ?></textarea>
                </p>
                <p class="campo-largo">
                    <label for="imagem_capa">Imagem de capa<?= $editando ? ' (envie outra apenas se quiser trocar)' : '' ?>:</label><br>
                    <input type="file" id="imagem_capa" name="imagem_capa" accept="image/jpeg,image/png"<?= $editando ? '' : ' required' ?>>
                </p>
                <?php if ($editando && caminho_capa($dados['imagem_capa']) !== ''): ?>
                    <div class="campo-largo capa-atual">
                        <img class="capa-mini" src="<?= esc(caminho_capa($dados['imagem_capa'])) ?>" alt="Capa atual de <?= esc($dados['titulo']) ?>">
                        <span>Capa atual</span>
                    </div>
                <?php endif; ?>

                <div class="acoes-form">
                    <button type="submit"><?= $editando ? 'Salvar alterações' : 'Cadastrar Anime' ?></button>
                    <?php if ($editando): ?>
                        <a class="botao-cancelar" href="<?= esc($url_volta) ?>">Cancelar</a>
                    <?php else: ?>
                        <button type="reset">Limpar</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section aria-labelledby="titulo-tabela">
            <h2 id="titulo-tabela">Animes Cadastrados</h2>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Capa</th>
                            <th scope="col">Título</th>
                            <th scope="col">Estúdio</th>
                            <th scope="col">Gênero</th>
                            <th scope="col">Temporadas</th>
                            <th scope="col">Trailer</th>
                            <th scope="col">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$lista || mysqli_num_rows($lista) === 0): ?>
                            <tr><td colspan="7">Nenhum anime cadastrado ainda.</td></tr>
                        <?php else: ?>
                            <?php while ($anime = mysqli_fetch_assoc($lista)): ?>
                                <tr>
                                    <td>
                                        <?php if (caminho_capa($anime['imagem_capa']) !== ''): ?>
                                            <img class="capa-mini" src="<?= esc(caminho_capa($anime['imagem_capa'])) ?>" alt="Capa de <?= esc($anime['titulo']) ?>">
                                        <?php else: ?>
                                            <div class="imagem-exemplo">Sem capa</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc($anime['titulo']) ?></td>
                                    <td><?= esc($anime['estudio_nome']) ?></td>
                                    <td><span class="etiqueta"><?= esc($anime['genero']) ?></span></td>
                                    <td><?= (int)$anime['temporadas'] ?></td>
                                    <td>
                                        <?php if ($anime['trailer_link'] !== ''): ?>
                                            <a href="<?= esc($anime['trailer_link']) ?>" target="_blank" rel="noopener noreferrer">Assistir</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="gerenciar_animes.php?editar=<?= (int)$anime['id'] ?>">Editar</a> |
                                        <form class="form-inline" action="excluir_anime.php" method="post"
                                              onsubmit="return confirm('Tem certeza que deseja excluir este anime?');">
                                            <input type="hidden" name="id" value="<?= (int)$anime['id'] ?>">
                                            <button type="submit" class="btn-link">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
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