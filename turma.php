<?php
/*
 * turma.php - ÁREA DA TURMA
 * Existe uma para CADA turma automaticamente: o endereço é turma.php?id=NUMERO_DA_TURMA.
 * Todos que têm acesso à turma veem os vídeos e as atividades;
 * professores (da turma), adm e dev também podem publicar e excluir.
 */
require __DIR__ . '/auth.php';
exigir(array_keys(PAPEIS));

// Extrai o código de um link do YouTube (youtu.be/..., watch?v=..., embed/..., shorts/...).
function youtubeId($url)
{
    return preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/))([\w-]{11})~', $url, $m) ? $m[1] : null;
}

// Só entra quem tem acesso a esta turma (senão, é como se ela não existisse).
$id = (int)($_GET['id'] ?? $_POST['turma_id'] ?? 0);
$turma = null;
foreach (turmasVisiveis($pdo) as $t) {
    if ((int)$t['id'] === $id) $turma = $t;
}
if (!$turma) {
    http_response_code(404);
    exit('Turma não encontrada ou sem acesso. <a href="inicio.php">Voltar</a>');
}

$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && podePostar()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'add_pub') {
        $tipo   = $_POST['tipo'] ?? '';
        $titulo = trim($_POST['titulo'] ?? '');
        $desc   = trim($_POST['descricao'] ?? '');
        $url    = trim($_POST['url'] ?? '');
        $prazo  = $_POST['prazo'] ?? '';

        if (!in_array($tipo, ['video', 'atividade'])) $erros[] = 'Escolha o tipo da publicação.';
        if ($titulo === '') $erros[] = 'Informe o título.';
        if ($tipo === 'video' && $url === '') $erros[] = 'Cole o link do vídeo.';
        // O link precisa começar com http:// ou https:// (evita links perigosos).
        if ($url !== '' && !(preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL))) {
            $erros[] = 'O link é inválido. Ele deve começar com http:// ou https://';
        }
        $dt = DateTime::createFromFormat('Y-m-d', $prazo);
        if ($prazo !== '' && !($dt && $dt->format('Y-m-d') === $prazo)) $erros[] = 'Prazo inválido.';

        if (!$erros) {
            $pdo->prepare('INSERT INTO publicacoes (turma_id, autor_id, tipo, titulo, descricao, url, prazo)
                           VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$id, usuario()['id'], $tipo, $titulo, $desc, $url ?: null, ($tipo === 'atividade' && $prazo) ? $prazo : null]);
            header('Location: turma.php?id=' . $id . '&ok=' . urlencode('Publicado!'));
            exit;
        }
    } elseif ($acao === 'del_pub') {
        $pdo->prepare('DELETE FROM publicacoes WHERE id = ? AND turma_id = ?')->execute([(int)$_POST['pub'], $id]);
        header('Location: turma.php?id=' . $id . '&ok=' . urlencode('Publicação excluída.'));
        exit;
    }
}

// LEFT JOIN: mantém a publicação mesmo se o autor tiver sido excluído.
$q = $pdo->prepare('SELECT p.*, u.nome AS autor FROM publicacoes p
                    LEFT JOIN usuarios u ON u.id = p.autor_id
                    WHERE p.turma_id = ? ORDER BY p.criado_em DESC');
$q->execute([$id]);
$todas = $q->fetchAll();
$videos     = array_filter($todas, fn($p) => $p['tipo'] === 'video');
$atividades = array_filter($todas, fn($p) => $p['tipo'] === 'atividade');

$DIAS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
$diasTurma = explode(',', $turma['dias']);
$ok = $_GET['ok'] ?? '';

// Botão de excluir (só aparece para quem pode publicar).
function botaoExcluir($pubId, $turmaId)
{
    if (!podePostar()) return;
    echo '<form method="post" onsubmit="return confirm(\'Excluir esta publicação?\')" class="excluir">',
         '<input type="hidden" name="acao" value="del_pub"><input type="hidden" name="turma_id" value="', $turmaId, '">',
         '<input type="hidden" name="pub" value="', $pubId, '"><button class="link">Excluir</button></form>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($turma['nome']) ?> · Área da turma</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="app.css">
</head>
<body>
<header class="topo" style="border-bottom: 8px solid <?= e($turma['cor']) ?>">
  <div>
    <h1><?= e($turma['nome']) ?></h1>
    <p>Sala <?= e($turma['sala']) ?> · <?= e($turma['turno']) ?></p>
  </div>
  <div class="acoes"><?php barraUsuario(); ?></div>
</header>

<main class="conteudo" style="--cor: <?= e($turma['cor']) ?>">
  <?php if ($ok): ?><div class="sucesso" role="status"><?= e($ok) ?></div><?php endif; ?>
  <?php if ($erros): ?>
    <div class="alerta" role="alert"><strong>Não foi possível publicar:</strong>
      <ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="painel semana-painel">
    <strong>Dias de aula</strong>
    <div class="semana">
      <?php foreach ($DIAS as $d): ?><span class="<?= in_array($d, $diasTurma) ? 'on' : '' ?>"><?= $d ?></span><?php endforeach; ?>
    </div>
  </div>

  <?php if (podePostar()): ?>
    <form method="post" class="painel nova-pub">
      <h2>Nova publicação</h2>
      <input type="hidden" name="acao" value="add_pub">
      <input type="hidden" name="turma_id" value="<?= $id ?>">
      <div class="duas">
        <label>Tipo
          <select name="tipo" id="tipo">
            <option value="video" <?= ($_POST['tipo'] ?? '') === 'video' ? 'selected' : '' ?>>Vídeo</option>
            <option value="atividade" <?= ($_POST['tipo'] ?? '') === 'atividade' ? 'selected' : '' ?>>Atividade</option>
          </select>
        </label>
        <label>Título <input name="titulo" required value="<?= e($_POST['titulo'] ?? '') ?>"></label>
      </div>
      <label>Descrição / instruções <textarea name="descricao" rows="3"><?= e($_POST['descricao'] ?? '') ?></textarea></label>
      <div class="duas">
        <label id="campo-url">Link <small class="dica" id="dica-url">(YouTube ou outro site)</small>
          <input name="url" type="url" placeholder="https://" value="<?= e($_POST['url'] ?? '') ?>"></label>
        <label id="campo-prazo">Prazo de entrega <small class="dica">opcional</small>
          <input type="date" name="prazo" value="<?= e($_POST['prazo'] ?? '') ?>"></label>
      </div>
      <button>Publicar</button>
    </form>
  <?php endif; ?>

  <div class="duas-colunas">
    <section>
      <h2 class="titulo-secao">Vídeos</h2>
      <?php if (!$videos): ?><p class="painel vazio">Nenhum vídeo publicado ainda.</p><?php endif; ?>
      <?php foreach ($videos as $p): $yt = youtubeId((string)$p['url']); ?>
        <article class="painel pub">
          <?php if ($yt): ?>
            <!-- Vídeos do YouTube aparecem direto na página -->
            <div class="video"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>" title="<?= e($p['titulo']) ?>"
              loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
          <?php endif; ?>
          <h3><?= e($p['titulo']) ?></h3>
          <?php if ($p['descricao']): ?><p><?= nl2br(e($p['descricao'])) ?></p><?php endif; ?>
          <?php if (!$yt && $p['url']): ?><a class="abrir" href="<?= e($p['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir vídeo ↗</a><?php endif; ?>
          <small class="dica"><?= e($p['autor'] ?? 'Professor removido') ?> · <?= e(date('d/m/Y', strtotime($p['criado_em']))) ?></small>
          <?php botaoExcluir($p['id'], $id); ?>
        </article>
      <?php endforeach; ?>
    </section>

    <section>
      <h2 class="titulo-secao">Atividades</h2>
      <?php if (!$atividades): ?><p class="painel vazio">Nenhuma atividade publicada ainda.</p><?php endif; ?>
      <?php foreach ($atividades as $p):
        $vencida = $p['prazo'] && $p['prazo'] < date('Y-m-d'); ?>
        <article class="painel pub">
          <h3><?= e($p['titulo']) ?></h3>
          <?php if ($p['prazo']): ?>
            <span class="etiqueta <?= $vencida ? 'vencida' : '' ?>">
              <?= $vencida ? 'Encerrada em ' : 'Entrega até ' ?><?= e(date('d/m/Y', strtotime($p['prazo']))) ?>
            </span>
          <?php endif; ?>
          <?php if ($p['descricao']): ?><p><?= nl2br(e($p['descricao'])) ?></p><?php endif; ?>
          <?php if ($p['url']): ?><a class="abrir" href="<?= e($p['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir material ↗</a><?php endif; ?>
          <small class="dica"><?= e($p['autor'] ?? 'Professor removido') ?> · <?= e(date('d/m/Y', strtotime($p['criado_em']))) ?></small>
          <?php botaoExcluir($p['id'], $id); ?>
        </article>
      <?php endforeach; ?>
    </section>
  </div>
</main>

<script>
// Vídeo: link obrigatório e sem prazo. Atividade: link opcional e com prazo.
const tipo = document.getElementById('tipo');
function ajustar() {
  if (!tipo) return;
  const video = tipo.value === 'video';
  document.getElementById('campo-prazo').hidden = video;
  document.querySelector('#campo-url input').required = video;
}
if (tipo) { tipo.addEventListener('change', ajustar); ajustar(); }
</script>
</body>
</html>
