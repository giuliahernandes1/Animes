<?php
require_once 'verifica_login.php';
require_once 'conexao.php';

/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cria a pasta de uploads se não existir
if (!is_dir("uploads/estudios")) {
    mkdir("uploads/estudios", 0755, true);
}

// Redimensiona a imagem mantendo proporção e transparência (máx. 300x300)
function redimensionarESalvarImagem(array $arquivo, $largura = 300, $altura = 300)
{
    $dir     = "uploads/estudios/";
    $nome    = uniqid('estudio_', true) . '_' . basename($arquivo["name"]);
    $caminho = $dir . $nome;
    $tipo    = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

    if (@getimagesize($arquivo["tmp_name"]) === false) {
        return "Arquivo não é uma imagem válida.";
    }
    if ($arquivo["size"] > 5000000) {
        return "Imagem muito grande. Máximo: 5 MB.";
    }
    if (!in_array($tipo, ["jpg", "jpeg", "png"], true)) {
        return "Apenas JPG, JPEG e PNG são aceitos.";
    }

    $conteudo = file_get_contents($arquivo["tmp_name"]);
    $original = @imagecreatefromstring($conteudo);
    if (!$original) {
        return "Não foi possível processar a imagem.";
    }

    $w0    = imagesx($original);
    $h0    = imagesy($original);
    $ratio = min($largura / $w0, $altura / $h0, 1); // não amplia imagens menores
    $nw    = max(1, (int)round($w0 * $ratio));
    $nh    = max(1, (int)round($h0 * $ratio));
    $nova  = imagecreatetruecolor($nw, $nh);

    // Preserva transparência em imagens PNG
    if ($tipo === "png") {
        imagealphablending($nova, false);
        imagesavealpha($nova, true);
    }

    imagecopyresampled($nova, $original, 0, 0, 0, 0, $nw, $nh, $w0, $h0);

    $salvou = ($tipo === "png")
        ? imagepng($nova, $caminho, 6)
        : imagejpeg($nova, $caminho, 90);

    if (PHP_VERSION_ID < 80000) {
        imagedestroy($original);
        imagedestroy($nova);
    }

    if (!$salvou) {
        return "Erro ao salvar a imagem no servidor.";
    }

    return $caminho;
}

// 1. EXCLUIR ESTÚDIO
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];

    if ($id > 0) {
        // Verifica se existem animes vinculados a este estúdio
        $chk = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM animes WHERE estudio_id = ?");
        mysqli_stmt_bind_param($chk, "i", $id);
        mysqli_stmt_execute($chk);
        $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($chk))['total'];
        mysqli_stmt_close($chk);

        if ($total > 0) {
            $_SESSION['mensagem'] = [
                'tipo' => 'erro',
                'texto' => 'Não é possível excluir: este estúdio possui animes cadastrados.'
            ];
        } else {
            // Busca a imagem para apagar da pasta
            $stmt_img = mysqli_prepare($conn, "SELECT imagem FROM estudios WHERE id = ?");
            mysqli_stmt_bind_param($stmt_img, "i", $id);
            mysqli_stmt_execute($stmt_img);
            $dados_img = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_img));
            mysqli_stmt_close($stmt_img);

            $stmt = mysqli_prepare($conn, "DELETE FROM estudios WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                if (!empty($dados_img['imagem']) && is_file($dados_img['imagem'])) {
                    @unlink($dados_img['imagem']);
                }
                $_SESSION['mensagem'] = [
                    'tipo' => 'sucesso',
                    'texto' => 'Estúdio excluído com sucesso!'
                ];
            } else {
                $_SESSION['mensagem'] = [
                    'tipo' => 'erro',
                    'texto' => 'Erro ao excluir estúdio.'
                ];
            }
            mysqli_stmt_close($stmt);
        }
    }

    header('Location: gerenciar_estudios.php');
    exit;
}

// 2. CADASTRAR OU EDITAR ESTÚDIO (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao         = $_POST['acao'] ?? 'cadastrar';
    $id           = (int)($_POST['id'] ?? 0);
    $nome         = trim($_POST['nome'] ?? '');
    $fundacao     = !empty($_POST['ano_fundacao']) ? (int)$_POST['ano_fundacao'] : null;
    $pais         = trim($_POST['pais'] ?? '');
    $imagem_atual = $_POST['imagem_atual'] ?? '';

    $imagem = $imagem_atual;
    $erro_upload = false;

    if ($nome === '') {
        $_SESSION['mensagem'] = [
            'tipo' => 'erro',
            'texto' => 'Informe o nome do estúdio.'
        ];
        header('Location: gerenciar_estudios.php');
        exit;
    }

    // Se o usuário enviou uma imagem nova
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['mensagem'] = [
                'tipo' => 'erro',
                'texto' => 'Erro no envio da imagem.'
            ];
            $erro_upload = true;
        } else {
            $upload = redimensionarESalvarImagem($_FILES['imagem']);
            if (strpos($upload, 'uploads/') === 0) {
                if (!empty($imagem_atual) && is_file($imagem_atual)) {
                    @unlink($imagem_atual);
                }
                $imagem = $upload;
            } else {
                $_SESSION['mensagem'] = [
                    'tipo' => 'erro',
                    'texto' => $upload
                ];
                $erro_upload = true;
            }
        }
    }

    if (!$erro_upload) {
        if ($acao === 'editar' && $id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE estudios SET nome = ?, fundacao = ?, pais = ?, imagem = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sissi", $nome, $fundacao, $pais, $imagem, $id);
            $texto_sucesso = "Estúdio atualizado com sucesso!";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO estudios (nome, fundacao, pais, imagem) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "siss", $nome, $fundacao, $pais, $imagem);
            $texto_sucesso = "Estúdio cadastrado com sucesso!";
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['mensagem'] = [
                'tipo' => 'sucesso',
                'texto' => $texto_sucesso
            ];
        } else {
            $_SESSION['mensagem'] = [
                'tipo' => 'erro',
                'texto' => 'Erro ao salvar no banco: ' . mysqli_error($conn)
            ];
        }
        mysqli_stmt_close($stmt);
    }

    header('Location: gerenciar_estudios.php');
    exit;
}

// Recupera mensagem da sessão (padrão Post/Redirect/Get)
$mensagem_alerta = null;
if (isset($_SESSION['mensagem'])) {
    $mensagem_alerta = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
}

// 3. BUSCAR DADOS PARA EDIÇÃO
$estudio_edicao = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM estudios WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $estudio_edicao = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// 4. LISTAR ESTÚDIOS
$resultado_estudios = mysqli_query($conn, "SELECT * FROM estudios ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Estúdios | Catálogo de Animes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <header>
        <h1>🏢 Gerenciar Estúdios</h1>
        <p>Cadastre, edite e exclua os estúdios de animação.</p>
        <nav aria-label="Navegação principal">
            <ul>
                <li><a href="index.php">Início</a></li>
                <li><a href="gerenciar_estudios.php" aria-current="page">Gerenciar Estúdios</a></li>
                <li><a href="gerenciar_animes.php">Gerenciar Animes</a></li>
                <li><a href="biblioteca.php">Biblioteca de Animes</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <?php if ($mensagem_alerta): ?>
            <?php $classe = ($mensagem_alerta['tipo'] === 'erro') ? 'mensagem-erro' : 'mensagem-sucesso'; ?>
            <p class="mensagem <?= $classe ?>" role="status"><?= htmlspecialchars($mensagem_alerta['texto'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <section aria-labelledby="titulo-cadastro">
            <h2 id="titulo-cadastro">Cadastrar Estúdio</h2>
            <form class="form-grade" action="gerenciar_estudios.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="cadastrar">
                <p>
                    <label for="nome">Nome do estúdio:</label><br>
                    <input type="text" id="nome" name="nome" placeholder="Ex.: MAPPA, Madhouse..." required>
                </p>
                <p>
                    <label for="ano_fundacao">Ano de fundação:</label><br>
                    <input type="number" id="ano_fundacao" name="ano_fundacao" min="1900" max="2100" placeholder="Ex.: 2011">
                </p>
                <p>
                    <label for="pais">País:</label><br>
                    <input type="text" id="pais" name="pais" placeholder="Ex.: Japão">
                </p>
                <p class="campo-largo">
                    <label for="imagem">Imagem/Logo do estúdio:</label><br>
                    <input type="file" id="imagem" name="imagem" accept="image/jpeg,image/png"><br>
                    <small>Formatos aceitos: JPG, JPEG ou PNG (máx. 5 MB).</small>
                </p>
                <div class="acoes-form">
                    <button type="submit">Cadastrar Estúdio</button>
                    <button type="reset">Limpar</button>
                </div>
            </form>
        </section>

        <section aria-labelledby="titulo-tabela">
            <h2 id="titulo-tabela">Estúdios Cadastrados</h2>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Imagem</th>
                            <th scope="col">Nome</th>
                            <th scope="col">Ano de Fundação</th>
                            <th scope="col">País</th>
                            <th scope="col">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado_estudios && mysqli_num_rows($resultado_estudios) > 0): ?>
                            <?php while ($estudio = mysqli_fetch_assoc($resultado_estudios)): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($estudio['imagem']) && is_file($estudio['imagem'])): ?>
                                            <img class="capa-mini" src="<?= htmlspecialchars($estudio['imagem'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($estudio['nome'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?php else: ?>
                                            <div class="imagem-exemplo">Sem logo</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($estudio['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($estudio['fundacao'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($estudio['pais'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <a href="gerenciar_estudios.php?editar=<?= (int)$estudio['id'] ?>#formulario-edicao">Editar</a> |
                                        <a href="gerenciar_estudios.php?excluir=<?= (int)$estudio['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir este estúdio?');">Excluir</a> |
                                        <a href="biblioteca.php?estudio_id=<?= (int)$estudio['id'] ?>">Ver animes</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">Nenhum estúdio cadastrado até o momento.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="formulario-edicao" aria-labelledby="titulo-edicao">
            <h2 id="titulo-edicao">Área de Edição</h2>
            <?php if ($estudio_edicao): ?>
                <form class="form-grade" action="gerenciar_estudios.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?= (int)$estudio_edicao['id'] ?>">
                    <input type="hidden" name="imagem_atual" value="<?= htmlspecialchars($estudio_edicao['imagem'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <p>
                        <label for="edit_nome">Nome do estúdio:</label><br>
                        <input type="text" id="edit_nome" name="nome" value="<?= htmlspecialchars($estudio_edicao['nome'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </p>
                    <p>
                        <label for="edit_ano_fundacao">Ano de fundação:</label><br>
                        <input type="number" id="edit_ano_fundacao" name="ano_fundacao" min="1900" max="2100" value="<?= htmlspecialchars((string)($estudio_edicao['fundacao'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </p>
                    <p>
                        <label for="edit_pais">País:</label><br>
                        <input type="text" id="edit_pais" name="pais" value="<?= htmlspecialchars($estudio_edicao['pais'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </p>
                    <p class="campo-largo">
                        <label for="edit_imagem">Nova imagem do estúdio (deixe em branco para manter a atual):</label><br>
                        <input type="file" id="edit_imagem" name="imagem" accept="image/jpeg,image/png"><br>
                        <small>Formatos aceitos: JPG, JPEG ou PNG (máx. 5 MB).</small>
                    </p>
                    <?php if (!empty($estudio_edicao['imagem']) && is_file($estudio_edicao['imagem'])): ?>
                        <div class="campo-largo capa-atual">
                            <img class="capa-mini" src="<?= htmlspecialchars($estudio_edicao['imagem'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagem atual de <?= htmlspecialchars($estudio_edicao['nome'], ENT_QUOTES, 'UTF-8') ?>">
                            <span>Imagem atual</span>
                        </div>
                    <?php endif; ?>
                    <div class="acoes-form">
                        <button type="submit">Salvar Alterações</button>
                        <a class="botao-cancelar" href="gerenciar_estudios.php">Cancelar edição</a>
                    </div>
                </form>
            <?php else: ?>
                <p>Clique em <strong>Editar</strong> na tabela acima para alterar os dados de um estúdio.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>Catálogo de Animes</p>
    </footer>
</body>
</html>