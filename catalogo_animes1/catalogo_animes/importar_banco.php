<?php
/*
- importar_banco.php - USE UMA VEZ E DEPOIS APAGUE ESTE ARQUIVO.
- Cria o banco "catalogo_animes" com as tabelas usuarios, estudios e animes,
- o login de teste (admin / 123) e 3 estúdios de exemplo.
- Usa a mesma conexão do conexao.php (localhost / root / Senai@118).
 */
$servidor = "localhost";
$usuario  = "root";
$senha    = "Senai@118"; // se na sua máquina a senha for vazia, troque por ""

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($servidor, $usuario, $senha);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar banco | Catálogo de Animes</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 640px; margin: 40px auto; line-height: 1.5;">
    <h1>Importar banco do Catálogo de Animes</h1>

    <?php if (!$conn): ?>
        <p style="color: red;">
            <strong>Não consegui conectar ao MySQL:</strong>
            <?= htmlspecialchars((string)mysqli_connect_error(), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p>Se a mensagem falar de "Access denied", troque a senha na linha 10 deste arquivo.</p>
    <?php else: ?>
        <?php
        $existe = mysqli_query($conn, "SHOW DATABASES LIKE 'catalogo_animes'");
        $banco_ja_existe = ($existe && mysqli_num_rows($existe) > 0);
        ?>

        <?php if ($banco_ja_existe && !isset($_GET['confirmar'])): ?>
            <p style="color: #b45309;">
                <strong>Atenção:</strong> o banco <code>catalogo_animes</code> já existe neste computador.
                Importar de novo <strong>apaga e recria</strong> as tabelas <code>usuarios</code>, <code>estudios</code> e <code>animes</code>.
            </p>
            <p>
                <a href="importar_banco.php?confirmar=1">Sim, pode apagar e recriar</a> |
                <a href="login.php">Não, voltar ao login</a>
            </p>
        <?php else: ?>
            <?php
            $sql = <<<'SQLFIM'
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS catalogo_animes CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE catalogo_animes;

DROP TABLE IF EXISTS animes;
DROP TABLE IF EXISTS estudios;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE estudios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    fundacao INT NULL,
    pais VARCHAR(60) NULL,
    imagem VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE animes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudio_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    temporadas INT NOT NULL DEFAULT 1,
    genero VARCHAR(50) NOT NULL,
    trailer_link VARCHAR(255) NULL,
    sinopse TEXT NULL,
    imagem_capa VARCHAR(255) NULL,
    CONSTRAINT fk_animes_estudio
        FOREIGN KEY (estudio_id) REFERENCES estudios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO usuarios (usuario, senha) VALUES
('admin', '$2y$10$HbMZQFHQhlos6CB8/BkEA.0TBQtdQrcdLhNsjbk0taAsCIFEwFp1u');

INSERT INTO estudios (nome, fundacao, pais) VALUES
('MAPPA', 2011, 'Japão'),
('Ufotable', 2000, 'Japão'),
('Bones', 1998, 'Japão');

SET FOREIGN_KEY_CHECKS = 1;
SQLFIM;

            mysqli_set_charset($conn, "utf8mb4");
            $erro = "";

            if (mysqli_multi_query($conn, $sql)) {
                do {
                    if ($r = mysqli_store_result($conn)) {
                        mysqli_free_result($r);
                    }
                } while (mysqli_more_results($conn) && mysqli_next_result($conn));
            }

            if (mysqli_errno($conn)) {
                $erro = mysqli_error($conn);
            }
            ?>

            <?php if ($erro !== ""): ?>
                <p style="color: red;">
                    <strong>Erro ao importar:</strong>
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php else: ?>
                <?php
                mysqli_select_db($conn, "catalogo_animes");
                $tabelas = array();
                $res = mysqli_query($conn, "SHOW TABLES");
                if ($res) {
                    while ($linha = mysqli_fetch_row($res)) {
                        $tabelas[] = $linha[0];
                    }
                }
                ?>
                <p style="color: green;"><strong>Banco criado com sucesso!</strong></p>
                <p>Tabelas: <?= htmlspecialchars(implode(", ", $tabelas), ENT_QUOTES, 'UTF-8') ?></p>
                <p>Login de teste: <strong>admin</strong> / <strong>123</strong></p>
                <p>
                    <strong>Agora apague o arquivo <code>importar_banco.php</code></strong> e entre pelo
                    <a href="login.php">login</a>.
                </p>
            <?php endif; ?>
        <?php endif; ?>
        <?php mysqli_close($conn); ?>
    <?php endif; ?>
</body>
</html>