<?php
/*
 * ============================================================
 *  professor.php  -  ÁREA DO PROFESSOR (chamada e notas)
 * ============================================================
 *  Funciona assim:
 *    - O professor escolhe uma TURMA (clicando no botão colorido).
 *    - Na aba CHAMADA ele marca presença/falta de cada aluno num dia.
 *    - Na aba NOTAS ele cria uma avaliação (ex: "Prova 1") e lança a nota de cada aluno.
 *
 *  A turma, a aba e a data ficam na URL (ex: professor.php?turma=2&aba=notas).
 *  Isso se chama método GET: serve para "escolher o que ver".
 *  Já os botões de SALVAR usam POST: servem para "enviar dados".
 */

require __DIR__ . '/auth.php';   // login e conexão com o banco ($pdo)
exigir(['dev', 'adm', 'professor']);

/* ------------------------------------------------------------
 *  FUNÇÕES AUXILIARES
 * ------------------------------------------------------------ */

// (a função e() agora vem do auth.php)

// Confere se um texto é uma data válida no formato ano-mês-dia (ex: 2026-10-28).
function dataValida($texto)
{
    $d = DateTime::createFromFormat('Y-m-d', (string)$texto);
    return $d && $d->format('Y-m-d') === $texto;
}

// Abre a página de novo com os parâmetros dados e para o código.
// Usamos depois de salvar, para o F5 não repetir o envio do formulário.
function voltar($params)
{
    header('Location: professor.php?' . http_build_query($params));
    exit;
}

// Mostra a nota sem zeros sobrando: "8.50" vira "8,5" e "10.00" vira "10".
function fmtNota($n)
{
    return str_replace('.', ',', rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.'));
}

/* ------------------------------------------------------------
 *  DESCOBRIR O QUE O PROFESSOR ESTÁ VENDO (turma, aba, data, avaliação)
 *  $_GET = dados que vêm na URL; $_POST = dados de formulários enviados.
 *  O "??" significa "se não existir, use o valor seguinte".
 * ------------------------------------------------------------ */
// Professor vê só as turmas dele; dev e adm veem todas.
$turmas = turmasVisiveis($pdo);

// Procura, na lista de turmas, a que foi pedida; se não achar, usa a primeira.
$pedido = (int)($_GET['turma'] ?? $_POST['turma_id'] ?? 0);
$turma = null;
foreach ($turmas as $t) {
    if ((int)$t['id'] === $pedido) {
        $turma = $t;
    }
}
if (!$turma && $turmas) {
    $turma = $turmas[0];
}
$turmaId = $turma['id'] ?? 0;

// Aba: "notas" ou "chamada" (padrão).
$aba = ($_GET['aba'] ?? $_POST['aba'] ?? '') === 'notas' ? 'notas' : 'chamada';

// Data da chamada: a da URL se for válida, senão hoje.
$dataPedida = $_GET['data'] ?? $_POST['data'] ?? '';
$dataChamada = dataValida($dataPedida) ? $dataPedida : date('Y-m-d');

// Alunos da turma escolhida (só id e nome, em ordem alfabética).
$alunos = [];
if ($turma) {
    $st = $pdo->prepare('SELECT id, nome FROM alunos WHERE turma_id = ? ORDER BY nome');
    $st->execute([$turmaId]);
    $alunos = $st->fetchAll();
}

/* ------------------------------------------------------------
 *  PARTE 1: RECEBER O QUE O PROFESSOR ENVIOU (botões de salvar)
 * ------------------------------------------------------------ */
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $turma) {
    $acao = $_POST['acao'] ?? '';

    /* ---------- SALVAR CHAMADA ---------- */
    if ($acao === 'salvar_chamada') {

        if (!dataValida($dataPedida) || $dataPedida > date('Y-m-d')) {
            $erros[] = 'Escolha uma data válida (não pode ser no futuro).';
        }

        if (!$erros) {
            // Transação = "tudo ou nada": ou salva a chamada inteira, ou não salva nada.
            $pdo->beginTransaction();
            $sql = $pdo->prepare(
                'INSERT INTO presencas (aluno_id, data, presente) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE presente = VALUES(presente)'
            );
            // ON DUPLICATE KEY UPDATE: se já existe registro desse aluno nesse dia, atualiza;
            // se não existe, cria. Assim dá para corrigir uma chamada já feita.
            foreach ($alunos as $a) {
                // Os botões chegam como $_POST['presenca'][id_do_aluno] = '1' (presente) ou '0' (falta).
                $presente = (($_POST['presenca'][$a['id']] ?? '1') === '1') ? 1 : 0;
                $sql->execute([$a['id'], $dataPedida, $presente]);
            }
            $pdo->commit();   // confirma tudo
            voltar(['turma' => $turmaId, 'aba' => 'chamada', 'data' => $dataPedida, 'ok' => 'Chamada salva!']);
        }

    /* ---------- CRIAR AVALIAÇÃO ---------- */
    } elseif ($acao === 'add_avaliacao') {

        $disciplina = trim($_POST['disciplina'] ?? '');
        $titulo     = trim($_POST['titulo'] ?? '');
        $dataAval   = $_POST['data_aval'] ?? '';

        if ($disciplina === '' || $titulo === '') {
            $erros[] = 'Informe a disciplina e o título da avaliação.';
        }
        if (!dataValida($dataAval)) {
            $erros[] = 'Informe a data da avaliação.';
        }

        if (!$erros) {
            $pdo->prepare('INSERT INTO avaliacoes (turma_id, disciplina, titulo, data) VALUES (?, ?, ?, ?)')
                ->execute([$turmaId, $disciplina, $titulo, $dataAval]);
            // lastInsertId() devolve o id que o banco acabou de criar.
            voltar(['turma' => $turmaId, 'aba' => 'notas', 'aval' => $pdo->lastInsertId(), 'ok' => 'Avaliação criada! Agora lance as notas.']);
        }

    /* ---------- SALVAR NOTAS ---------- */
    } elseif ($acao === 'salvar_notas') {

        $avalId = (int)($_POST['aval'] ?? 0);

        // Garante que a avaliação pertence à turma que está na tela.
        $chk = $pdo->prepare('SELECT COUNT(*) FROM avaliacoes WHERE id = ? AND turma_id = ?');
        $chk->execute([$avalId, $turmaId]);
        if (!$chk->fetchColumn()) {
            $erros[] = 'Avaliação não encontrada.';
        }

        $paraSalvar = [];   // lista de [aluno, nota] já validados
        $paraLimpar = [];   // alunos com o campo vazio (nota removida)
        foreach ($alunos as $a) {
            $valor = trim($_POST['nota'][$a['id']] ?? '');
            $valor = str_replace(',', '.', $valor);   // aceita "7,5" além de "7.5"

            if ($valor === '') {
                $paraLimpar[] = $a['id'];
            } elseif (!is_numeric($valor) || $valor < 0 || $valor > 10) {
                $erros[] = 'Nota inválida para ' . $a['nome'] . ': use um valor de 0 a 10.';
            } else {
                $paraSalvar[] = [$a['id'], round((float)$valor, 2)];
            }
        }

        if (!$erros) {
            $pdo->beginTransaction();
            $salva = $pdo->prepare(
                'INSERT INTO notas (avaliacao_id, aluno_id, nota) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE nota = VALUES(nota)'
            );
            foreach ($paraSalvar as [$alunoId, $nota]) {   // [a, b] separa o par em duas variáveis
                $salva->execute([$avalId, $alunoId, $nota]);
            }
            $apaga = $pdo->prepare('DELETE FROM notas WHERE avaliacao_id = ? AND aluno_id = ?');
            foreach ($paraLimpar as $alunoId) {
                $apaga->execute([$avalId, $alunoId]);
            }
            $pdo->commit();
            voltar(['turma' => $turmaId, 'aba' => 'notas', 'aval' => $avalId, 'ok' => 'Notas salvas!']);
        }

    /* ---------- EXCLUIR AVALIAÇÃO (as notas dela saem junto) ---------- */
    } elseif ($acao === 'del_avaliacao') {
        $pdo->prepare('DELETE FROM avaliacoes WHERE id = ? AND turma_id = ?')
            ->execute([(int)$_POST['aval'], $turmaId]);
        voltar(['turma' => $turmaId, 'aba' => 'notas', 'ok' => 'Avaliação excluída.']);
    }
}

/* ------------------------------------------------------------
 *  PARTE 2: BUSCAR OS DADOS PARA MOSTRAR
 * ------------------------------------------------------------ */
$presencasDoDia = [];   // aluno => 1 (presente) ou 0 (falta), na data escolhida
$resumoFaltas   = [];   // aluno => ['faltas' => N, 'total' => N] (todas as chamadas)
$avaliacoes     = [];
$avaliacao      = null; // avaliação selecionada
$notasDaAval    = [];   // aluno => nota, na avaliação selecionada
$medias         = [];   // aluno => média de todas as avaliações da turma

if ($turma) {
    // JOIN junta duas tabelas: aqui, presenças + alunos, para filtrar pela turma.
    $q = $pdo->prepare('SELECT p.aluno_id, p.presente FROM presencas p
                        JOIN alunos al ON al.id = p.aluno_id
                        WHERE al.turma_id = ? AND p.data = ?');
    $q->execute([$turmaId, $dataChamada]);
    $presencasDoDia = $q->fetchAll(PDO::FETCH_KEY_PAIR);   // vira lista "id => valor"

    $q = $pdo->prepare('SELECT p.aluno_id, SUM(p.presente = 0) AS faltas, COUNT(*) AS total
                        FROM presencas p JOIN alunos al ON al.id = p.aluno_id
                        WHERE al.turma_id = ? GROUP BY p.aluno_id');
    $q->execute([$turmaId]);
    $resumoFaltas = $q->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);

    $q = $pdo->prepare('SELECT * FROM avaliacoes WHERE turma_id = ? ORDER BY data DESC, id DESC');
    $q->execute([$turmaId]);
    $avaliacoes = $q->fetchAll();

    // Avaliação escolhida na URL; se não houver, a mais recente.
    $avalPedida = (int)($_GET['aval'] ?? $_POST['aval'] ?? 0);
    foreach ($avaliacoes as $av) {
        if ((int)$av['id'] === $avalPedida) {
            $avaliacao = $av;
        }
    }
    if (!$avaliacao && $avaliacoes) {
        $avaliacao = $avaliacoes[0];
    }

    if ($avaliacao) {
        $q = $pdo->prepare('SELECT aluno_id, nota FROM notas WHERE avaliacao_id = ?');
        $q->execute([$avaliacao['id']]);
        $notasDaAval = $q->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // AVG() calcula a média; GROUP BY faz uma média para cada aluno.
    $q = $pdo->prepare('SELECT n.aluno_id, AVG(n.nota) FROM notas n
                        JOIN avaliacoes av ON av.id = n.avaliacao_id
                        WHERE av.turma_id = ? GROUP BY n.aluno_id');
    $q->execute([$turmaId]);
    $medias = $q->fetchAll(PDO::FETCH_KEY_PAIR);
}

$ok = $_GET['ok'] ?? '';   // mensagem de sucesso vinda da URL (ex: "Chamada salva!")
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Área do professor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&display=swap" rel="stylesheet">
<!-- Reaproveitamos o visual da secretaria e acrescentamos o que é só do professor -->
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="professor.css">
<link rel="stylesheet" href="app.css">
</head>
<body>

<header class="topo">
  <div>
    <h1>Área do professor</h1>
    <p>Faça a chamada e lance as notas da sua turma.</p>
  </div>
  <div class="acoes"><?php barraUsuario(); ?></div>
</header>

<main class="conteudo">

  <?php if ($ok): ?>
    <div class="sucesso" role="status"><?= e($ok) ?></div>
  <?php endif; ?>

  <?php if ($erros): ?>
    <div class="alerta" role="alert">
      <strong>Não foi possível salvar:</strong>
      <ul><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if (!$turma): ?>
    <p class="painel">Nenhuma turma cadastrada ainda. Crie uma na <a href="index.php">secretaria</a>.</p>
  <?php else: ?>

  <!-- ======== ESCOLHA DA TURMA ======== -->
  <nav class="painel turmas-nav" aria-label="Turmas">
    <?php foreach ($turmas as $t): ?>
      <!-- Cada turma é um link que recarrega a página com ?turma=ID -->
      <a class="turma-chip <?= $t['id'] == $turmaId ? 'ativa' : '' ?>"
         style="--cor: <?= e($t['cor']) ?>"
         href="professor.php?turma=<?= $t['id'] ?>&aba=<?= $aba ?>">
        <?= e($t['nome']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- ======== ABAS ======== -->
  <div class="abas" style="--cor: <?= e($turma['cor']) ?>">
    <a class="<?= $aba === 'chamada' ? 'ativa' : '' ?>" href="professor.php?turma=<?= $turmaId ?>&aba=chamada">Chamada</a>
    <a class="<?= $aba === 'notas' ? 'ativa' : '' ?>" href="professor.php?turma=<?= $turmaId ?>&aba=notas">Notas</a>
    <a href="turma.php?id=<?= $turmaId ?>">Área da turma ↗</a>
  </div>

  <section class="painel area" style="--cor: <?= e($turma['cor']) ?>">

  <?php if (!$alunos): ?>
    <p class="vazio">Esta turma ainda não tem alunos. Cadastre-os na <a href="index.php">secretaria</a>.</p>

  <?php elseif ($aba === 'chamada'): ?>
    <!-- ================== ABA CHAMADA ================== -->
    <div class="barra">
      <h2><?= e($turma['nome']) ?> · Chamada</h2>
      <!-- Formulário GET só para trocar a data: ao mudar, a página recarrega sozinha -->
      <form method="get" class="troca-data">
        <input type="hidden" name="turma" value="<?= $turmaId ?>">
        <input type="hidden" name="aba" value="chamada">
        <input type="date" name="data" value="<?= e($dataChamada) ?>" max="<?= date('Y-m-d') ?>"
               onchange="this.form.submit()" aria-label="Data da chamada">
      </form>
    </div>

    <?php if ($presencasDoDia): ?>
      <p class="dica">A chamada deste dia já foi feita. Se alterar, ela será atualizada.</p>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="acao" value="salvar_chamada">
      <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
      <input type="hidden" name="aba" value="chamada">
      <input type="hidden" name="data" value="<?= e($dataChamada) ?>">

      <div class="lista">
        <?php foreach ($alunos as $a):
          // Se já existe registro no dia, usa ele; senão, o padrão é "presente".
          $presente = isset($presencasDoDia[$a['id']]) ? (int)$presencasDoDia[$a['id']] : 1;
          $r = $resumoFaltas[$a['id']] ?? null; ?>
          <div class="linha">
            <span class="avatar"><?= e(mb_strtoupper(mb_substr($a['nome'], 0, 1))) ?></span>
            <span class="info">
              <strong><?= e($a['nome']) ?></strong>
              <small>
                <?php if ($r): ?>
                  <?= (int)$r['faltas'] ?> faltas em <?= (int)$r['total'] ?> aulas
                  (<?= round(100 * ($r['total'] - $r['faltas']) / $r['total']) ?>% de presença)
                <?php else: ?>
                  Sem chamadas anteriores
                <?php endif; ?>
              </small>
            </span>
            <!-- Dois botões de rádio: só um pode ficar marcado. name="presenca[ID]" agrupa por aluno. -->
            <span class="alterna">
              <label class="opcao pres">
                <input type="radio" name="presenca[<?= $a['id'] ?>]" value="1" <?= $presente ? 'checked' : '' ?>>
                <span>Presente</span>
              </label>
              <label class="opcao falta">
                <input type="radio" name="presenca[<?= $a['id'] ?>]" value="0" <?= !$presente ? 'checked' : '' ?>>
                <span>Falta</span>
              </label>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="salvar">Salvar chamada</button>
    </form>

  <?php else: ?>
    <!-- ================== ABA NOTAS ================== -->
    <div class="barra"><h2><?= e($turma['nome']) ?> · Notas</h2></div>

    <!-- Escolha da avaliação -->
    <?php if ($avaliacoes): ?>
      <div class="avals">
        <?php foreach ($avaliacoes as $av): ?>
          <a class="aval <?= ($avaliacao && $av['id'] == $avaliacao['id']) ? 'ativa' : '' ?>"
             href="professor.php?turma=<?= $turmaId ?>&aba=notas&aval=<?= $av['id'] ?>">
            <strong><?= e($av['titulo']) ?></strong>
            <small><?= e($av['disciplina']) ?> · <?= e(date('d/m/Y', strtotime($av['data']))) ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Lançar notas da avaliação escolhida -->
    <?php if ($avaliacao): ?>
      <form method="post">
        <input type="hidden" name="acao" value="salvar_notas">
        <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
        <input type="hidden" name="aba" value="notas">
        <input type="hidden" name="aval" value="<?= $avaliacao['id'] ?>">

        <div class="lista">
          <div class="linha cabecalho">
            <span></span><span class="info">Aluno</span>
            <span class="col">Média geral</span><span class="col">Nota</span>
          </div>
          <?php foreach ($alunos as $a):
            $media = $medias[$a['id']] ?? null; ?>
            <div class="linha">
              <span class="avatar"><?= e(mb_strtoupper(mb_substr($a['nome'], 0, 1))) ?></span>
              <span class="info"><strong><?= e($a['nome']) ?></strong></span>
              <span class="col media <?= ($media !== null && $media < 6) ? 'baixa' : '' ?>">
                <?= $media !== null ? e(fmtNota($media)) : '—' ?>
              </span>
              <!-- type="number": aceita de 0 a 10 com uma casa decimal. Vazio = sem nota. -->
              <span class="col">
                <input class="nota" type="number" min="0" max="10" step="0.1"
                       name="nota[<?= $a['id'] ?>]" placeholder="—"
                       value="<?= isset($notasDaAval[$a['id']]) ? e(number_format((float)$notasDaAval[$a['id']], 1, '.', '')) : '' ?>"
                       aria-label="Nota de <?= e($a['nome']) ?>">
              </span>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="salvar">Salvar notas</button>
      </form>

      <form method="post" class="excluir" onsubmit="return confirm('Excluir esta avaliação e todas as notas dela?')">
        <input type="hidden" name="acao" value="del_avaliacao">
        <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
        <input type="hidden" name="aval" value="<?= $avaliacao['id'] ?>">
        <button class="link">Excluir esta avaliação</button>
      </form>
    <?php else: ?>
      <p class="vazio">Nenhuma avaliação ainda. Crie a primeira abaixo.</p>
    <?php endif; ?>

    <!-- Criar nova avaliação -->
    <form method="post" class="nova-aval">
      <h3>Nova avaliação</h3>
      <input type="hidden" name="acao" value="add_avaliacao">
      <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
      <input type="hidden" name="aba" value="notas">
      <div class="tres">
        <label>Disciplina <input name="disciplina" placeholder="Matemática" required></label>
        <label>Título <input name="titulo" placeholder="Prova 1" required></label>
        <label>Data <input type="date" name="data_aval" value="<?= date('Y-m-d') ?>" required></label>
      </div>
      <button>Criar avaliação</button>
    </form>
  <?php endif; ?>

  </section>
  <?php endif; ?>
</main>
</body>
</html>
