<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexao.php';

/** @var mysqli $conn */

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$erro = "";
$usuario_digitado = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';
    $usuario_digitado = $usuario;

    if ($usuario === '' || $senha === '') {
        $erro = "Preencha o usuário e a senha.";
    } else {
        $sql = "SELECT id, usuario, senha FROM usuarios WHERE usuario = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $usuario);
            mysqli_stmt_execute($stmt);
            $resultado = mysqli_stmt_get_result($stmt);
            $dados = mysqli_fetch_assoc($resultado);
            mysqli_stmt_close($stmt);

            if ($dados && password_verify($senha, $dados['senha'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = (int)$dados['id'];
                $_SESSION['usuario_nome'] = $dados['usuario'];
                header("Location: index.php");
                exit;
            } else {
                $erro = "Usuário ou senha inválidos!";
            }
        } else {
            error_log("Erro no login: " . mysqli_error($conn));
            $erro = "Erro ao conectar com o banco de dados. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Catálogo de Animes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <header>
        <h1>🎬 Catálogo de Animes</h1>
        <p>Sistema de gerenciamento de estúdios e animes.</p>
    </header>

    <main>
        <section aria-labelledby="titulo-login" class="caixa-login">
            <h2 id="titulo-login">Entrar no sistema</h2>
            <p><small>Credenciais de teste — Usuário: <strong>admin</strong> | Senha: <strong>123</strong></small></p>

            <?php if ($erro !== ""): ?>
                <p class="mensagem mensagem-erro" role="alert">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <form action="login.php" method="post">
                <p>
                    <label for="usuario">Usuário:</label><br>
                    <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($usuario_digitado, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required autofocus>
                </p>
                <p>
                    <label for="senha">Senha:</label><br>
                    <input type="password" id="senha" name="senha" autocomplete="current-password" required>
                </p>
                <div class="acoes-form">
                    <button type="submit">Entrar</button>
                </div>
            </form>
        </section>
    </main>

    <footer>
        <p>Catálogo de Animes</p>
    </footer>
</body>
</html>