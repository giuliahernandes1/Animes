<?php
/* ==========================================================
   funcoes_anime.php
   Funções usadas por gerenciar_animes.php, biblioteca.php
   e excluir_anime.php (Dev Backend 2).
   Requer: $conn (mysqli) criada pelo conexao.php
   ========================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('PASTA_CAPAS', 'uploads/capas/');
define('LARGURA_MAX_CAPA', 400);
define('ALTURA_MAX_CAPA', 600);
define('TAMANHO_MAX_UPLOAD', 5 * 1024 * 1024); // 5 MB (igual aos estúdios)

/* ---------- Utilidades ---------- */

// Protege o texto antes de mostrar no HTML (evita XSS)
function esc($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

// Limpa um valor vindo de formulário
function limpar_texto($valor)
{
    return is_scalar($valor) ? trim((string)$valor) : '';
}

function lista_generos()
{
    return array(
        'Ação', 'Aventura', 'Comédia', 'Drama', 'Esportes',
        'Fantasia', 'Ficção científica', 'Romance', 'Slice of life', 'Terror'
    );
}

/* ---------- Banco de dados (prepared statements) ---------- */

/**
 * Prepara, vincula os parâmetros e executa. Devolve o statement.
 * @param mysqli $conn
 * @param string $sql
 * @param string $tipos
 * @param array $params
 * @return mysqli_stmt
 */
function consultar_preparado($conn, $sql, $tipos = '', $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }
    if ($tipos !== '') {
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_stmt_error($stmt));
    }
    return $stmt;
}

/**
 * @param mysqli $conn
 * @param int $id
 * @return array|null
 */
function buscar_anime($conn, $id)
{
    $stmt = consultar_preparado(
        $conn,
        'SELECT id, estudio_id, titulo, temporadas, genero, trailer_link, sinopse, imagem_capa
         FROM animes WHERE id = ?',
        'i',
        array($id)
    );
    $resultado = mysqli_stmt_get_result($stmt);
    $anime = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);
    return $anime ? $anime : null;
}

/**
 * @param mysqli $conn
 * @param int $estudio_id
 * @return bool
 */
function estudio_existe($conn, $estudio_id)
{
    $stmt = consultar_preparado($conn, 'SELECT id FROM estudios WHERE id = ?', 'i', array($estudio_id));
    $resultado = mysqli_stmt_get_result($stmt);
    $existe = mysqli_num_rows($resultado) > 0;
    mysqli_stmt_close($stmt);
    return $existe;
}

/* ---------- Validação do link do trailer (Regex) ---------- */

// Aceita https://www.youtube.com/watch?v=XXXXXXXXXXX  ou  https://youtu.be/XXXXXXXXXXX
// O código do vídeo tem sempre 11 caracteres (letras, números, _ e -).
function trailer_valido($link)
{
    if ($link === '') {
        return true; // o trailer é opcional
    }
    $regex = '~^https?://(www\.)?(youtube\.com/watch\?v=|youtu\.be/)[A-Za-z0-9_-]{11}([&?][^\s]*)?$~i';
    return preg_match($regex, $link) === 1;
}

/* ---------- Upload da capa com redimensionamento (PHP GD) ---------- */

// Devolve o caminho salvo (ex.: uploads/capas/capa_xxx.jpg), ou false (com a mensagem em $erro).
function salvar_capa($arquivo, &$erro)
{
    if (!isset($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        $erro = 'Não foi possível enviar a imagem. Tente novamente.';
        return false;
    }
    if ($arquivo['size'] > TAMANHO_MAX_UPLOAD) {
        $erro = 'A imagem deve ter no máximo 5 MB.';
        return false;
    }

    // getimagesize confere o conteúdo real do arquivo (não confia só na extensão)
    $info = @getimagesize($arquivo['tmp_name']);
    if ($info === false) {
        $erro = 'O arquivo enviado não é uma imagem válida.';
        return false;
    }

    $tipo = $info[2];
    if ($tipo === IMAGETYPE_JPEG) {
        $origem = @imagecreatefromjpeg($arquivo['tmp_name']);
        $extensao = 'jpg';
    } elseif ($tipo === IMAGETYPE_PNG) {
        $origem = @imagecreatefrompng($arquivo['tmp_name']);
        $extensao = 'png';
    } else {
        $erro = 'Use uma imagem nos formatos JPG ou PNG.';
        return false;
    }

    if (!$origem) {
        $erro = 'Não foi possível processar a imagem.';
        return false;
    }

    // Calcula o novo tamanho mantendo a proporção (sem ampliar)
    $largura = imagesx($origem);
    $altura = imagesy($origem);
    $escala = min(LARGURA_MAX_CAPA / $largura, ALTURA_MAX_CAPA / $altura, 1);
    $nova_largura = max(1, (int)round($largura * $escala));
    $nova_altura = max(1, (int)round($altura * $escala));

    $nova = imagecreatetruecolor($nova_largura, $nova_altura);
    if ($extensao === 'png') {
        // mantém a transparência do PNG
        imagealphablending($nova, false);
        imagesavealpha($nova, true);
    }
    imagecopyresampled($nova, $origem, 0, 0, 0, 0, $nova_largura, $nova_altura, $largura, $altura);

    if (!is_dir(PASTA_CAPAS)) {
        mkdir(PASTA_CAPAS, 0755, true);
    }
    $nome = 'capa_' . md5(uniqid('', true)) . '.' . $extensao;
    $caminho = PASTA_CAPAS . $nome;

    if ($extensao === 'jpg') {
        $salvou = imagejpeg($nova, $caminho, 85);
    } else {
        $salvou = imagepng($nova, $caminho, 6);
    }

    // Libera a memória utilizada pelas imagens GD (importante em versões < PHP 8.0)
    if (PHP_VERSION_ID < 80000) {
        imagedestroy($origem);
        imagedestroy($nova);
    }

    if (!$salvou) {
        $erro = 'Não foi possível salvar a imagem no servidor.';
        return false;
    }
    return $caminho;
}

// Devolve o caminho da capa se o arquivo existir; senão, texto vazio.
// O basename() garante que só arquivos da pasta de capas são usados.
function caminho_capa($valor)
{
    if ($valor === '' || $valor === null) {
        return '';
    }
    $caminho = PASTA_CAPAS . basename($valor);
    return is_file($caminho) ? $caminho : '';
}

// Apaga o arquivo de capa do servidor (usado na exclusão e na troca de capa)
function apagar_capa($valor)
{
    $caminho = caminho_capa($valor);
    if ($caminho !== '') {
        @unlink($caminho);
    }
}

/* ---------- Filtros da biblioteca (preservados entre as telas) ---------- */

function normalizar_filtros($busca, $estudio, $genero)
{
    return array(
        'busca'      => limpar_texto($busca),
        'estudio_id' => (int)limpar_texto($estudio),
        'genero'     => limpar_texto($genero),
    );
}

// Lê os filtros que vêm do formulário da biblioteca
function filtros_da_biblioteca($dados)
{
    return normalizar_filtros(
        isset($dados['busca']) ? $dados['busca'] : '',
        isset($dados['estudio_id']) ? $dados['estudio_id'] : 0,
        isset($dados['genero']) ? $dados['genero'] : ''
    );
}

// Lê os filtros guardados nos campos f_busca, f_estudio e f_genero
function filtros_de_retorno($dados)
{
    return normalizar_filtros(
        isset($dados['f_busca']) ? $dados['f_busca'] : '',
        isset($dados['f_estudio']) ? $dados['f_estudio'] : 0,
        isset($dados['f_genero']) ? $dados['f_genero'] : ''
    );
}

// Endereço da biblioteca já com os filtros aplicados
function url_biblioteca($f)
{
    $q = array();
    if ($f['busca'] !== '') {
        $q['busca'] = $f['busca'];
    }
    if ($f['estudio_id'] > 0) {
        $q['estudio_id'] = $f['estudio_id'];
    }
    if ($f['genero'] !== '') {
        $q['genero'] = $f['genero'];
    }
    return 'biblioteca.php' . ($q ? '?' . http_build_query($q) : '');
}

// Parte do endereço (link de Editar) que carrega os filtros
function parametros_de_retorno($f)
{
    $params = array('origem' => 'biblioteca');
    if ($f['busca'] !== '') {
        $params['f_busca'] = $f['busca'];
    }
    if ($f['estudio_id'] > 0) {
        $params['f_estudio'] = $f['estudio_id'];
    }
    if ($f['genero'] !== '') {
        $params['f_genero'] = $f['genero'];
    }
    return http_build_query($params);
}

// Campos escondidos que carregam os filtros em um formulário POST
function campos_ocultos_retorno($f)
{
    echo '<input type="hidden" name="origem" value="biblioteca">' . "\n";
    echo '<input type="hidden" name="f_busca" value="' . esc($f['busca']) . '">' . "\n";
    echo '<input type="hidden" name="f_estudio" value="' . esc($f['estudio_id']) . '">' . "\n";
    echo '<input type="hidden" name="f_genero" value="' . esc($f['genero']) . '">' . "\n";
}

/* ---------- Mensagens (sucesso e erro) ---------- */

function definir_mensagem($tipo, $texto)
{
    $_SESSION['mensagem'] = array('tipo' => $tipo, 'texto' => $texto);
}

function mostrar_mensagem()
{
    if (!isset($_SESSION['mensagem'])) {
        return;
    }
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
    $classe = ($mensagem['tipo'] === 'erro') ? 'mensagem-erro' : 'mensagem-sucesso';
    echo '<p class="mensagem ' . $classe . '" role="status">' . esc($mensagem['texto']) . '</p>';
}

function mostrar_erros($erros)
{
    if (!$erros) {
        return;
    }
    echo '<div class="mensagem mensagem-erro" role="alert"><p>Corrija os itens abaixo:</p><ul>';
    foreach ($erros as $erro) {
        echo '<li>' . esc($erro) . '</li>';
    }
    echo '</ul></div>';
}