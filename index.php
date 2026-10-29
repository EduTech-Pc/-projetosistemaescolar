<?php
/*
 * ============================================================
 *  index.php  -  A PÁGINA PRINCIPAL DO SISTEMA
 * ============================================================
 *  Duas partes:
 *    1) PHP (aqui em cima): recebe os formulários, valida, salva e busca dados.
 *    2) HTML (mais abaixo): desenha a página com os dados buscados.
 *  O PHP roda no SERVIDOR; o navegador só recebe o HTML pronto.
 */

// "require" copia aqui outro arquivo. Com isso ganhamos a variável $pdo (conexão com o banco).
require __DIR__ . '/auth.php';   // login e conexão com o banco
exigir(['dev', 'adm']);             // só dev e adm acessam a secretaria

/* ------------------------------------------------------------
 *  LISTAS FIXAS (fáceis de editar)
 * ------------------------------------------------------------ */

// Dias da semana: "código salvo no banco" => "nome por extenso".
$DIAS = ['Seg' => 'Segunda', 'Ter' => 'Terça', 'Qua' => 'Quarta', 'Qui' => 'Quinta',
         'Sex' => 'Sexta', 'Sáb' => 'Sábado', 'Dom' => 'Domingo'];

// Opções de parentesco do responsável. Para incluir outra, é só adicionar aqui.
$PARENTESCOS = ['Mãe', 'Pai', 'Avó', 'Avô', 'Tio(a)', 'Responsável legal'];

/* ------------------------------------------------------------
 *  FUNÇÕES AUXILIARES
 *  "function" cria um bloco de código com nome que podemos chamar várias vezes.
 * ------------------------------------------------------------ */

// (a função e() agora vem do auth.php)

// Devolve o que o usuário tinha digitado num campo (para não perder tudo se der erro).
function v($campo)
{
    return e($_POST[$campo] ?? '');
}

// Devolve ' selected' quando a opção é a que o usuário tinha escolhido antes do erro.
function sel($campo, $valor)
{
    return (($_POST[$campo] ?? '') == $valor) ? ' selected' : '';
}

// Remove tudo que não for número: "(11) 98765-4321" vira "11987654321".
function apenasDigitos($texto)
{
    return preg_replace('/\D/', '', (string)$texto);
}

// Confere se um CPF é válido (11 números e dígitos verificadores corretos).
function validarCpf($cpf)
{
    $cpf = apenasDigitos($cpf);
    // Precisa ter 11 dígitos e não pode ser tudo igual (ex: 111.111.111-11).
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    // Calcula o 1º e o 2º dígitos verificadores e compara com os informados.
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += $cpf[$i] * (($t + 1) - $i);
        }
        if ($cpf[$t] != ((10 * $soma) % 11) % 10) {
            return false;
        }
    }
    return true;
}

// Telefone válido = DDD + número, ou seja, 10 ou 11 dígitos.
function validarTel($tel)
{
    $n = strlen(apenasDigitos($tel));
    return $n === 10 || $n === 11;
}

// Formata para exibir: 52998224725 -> 529.982.247-25
function fmtCpf($d)
{
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
}

// Formata para exibir: 11987654321 -> (11) 98765-4321
function fmtTel($d)
{
    return strlen($d) === 11
        ? preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $d)
        : preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $d);
}

// Calcula a idade em anos a partir da data de nascimento (ano-mês-dia).
function calcIdade($data)
{
    return (new DateTime($data))->diff(new DateTime('today'))->y;
}

/* ------------------------------------------------------------
 *  PARTE 1: RECEBER O QUE O USUÁRIO ENVIOU
 * ------------------------------------------------------------ */
$erros = [];   // lista de mensagens de erro (começa vazia)

// $_SERVER['REQUEST_METHOD'] é "POST" quando um formulário foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // $_POST guarda tudo que veio do formulário. "??" = "se não existir, use o valor depois".
    $acao = $_POST['acao'] ?? '';
    $nome = trim($_POST['nome'] ?? '');   // trim() tira espaços do começo e do fim

    /* ---------- AÇÃO 1: cadastrar turma ---------- */
    if ($acao === 'add_turma') {

        if ($nome === '') {
            $erros[] = 'Informe o nome da turma.';
        }

        // Pega só os dias marcados que existem na lista $DIAS, na ordem da semana.
        // array_keys($DIAS) = ['Seg','Ter',...]; array_intersect fica só com os marcados.
        $dias = array_values(array_intersect(array_keys($DIAS), (array)($_POST['dias'] ?? [])));
        if (!$dias) {
            $erros[] = 'Marque pelo menos um dia da semana.';
        }

        $turno = in_array($_POST['turno'] ?? '', ['Manhã', 'Tarde', 'Noite']) ? $_POST['turno'] : 'Manhã';
        $cor = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['cor'] ?? '') ? $_POST['cor'] : '#3B4BDB';

        if (!$erros) {
            // Consulta preparada: os "?" são preenchidos em ordem pelo execute().
            // Isso impede que alguém "injete" comandos maliciosos no banco.
            // implode(',', $dias) junta a lista num texto: ['Seg','Qua'] vira 'Seg,Qua'.
            $pdo->prepare('INSERT INTO turmas (nome, turno, sala, dias, cor) VALUES (?, ?, ?, ?, ?)')
                ->execute([$nome, $turno, trim($_POST['sala'] ?? ''), implode(',', $dias), $cor]);
        }

    /* ---------- AÇÃO 2: cadastrar aluno ---------- */
    } elseif ($acao === 'add_aluno') {

        $nasc = $_POST['nascimento'] ?? '';
        $rg   = trim($_POST['rg'] ?? '');
        $cpf  = apenasDigitos($_POST['cpf'] ?? '');
        $tel1 = apenasDigitos($_POST['telefone1'] ?? '');
        $tel2 = apenasDigitos($_POST['telefone2'] ?? '');   // opcional
        $turmaId = (int)($_POST['turma_id'] ?? 0);          // (int) garante que é um número

        // --- Validações: cada problema encontrado vira uma mensagem na lista $erros ---
        if ($nome === '') {
            $erros[] = 'Informe o nome do aluno.';
        }

        // Confere se a data existe de verdade e não está no futuro.
        $dt = DateTime::createFromFormat('Y-m-d', $nasc);
        $dataOk = $dt && $dt->format('Y-m-d') === $nasc && $dt <= new DateTime('today');
        if (!$dataOk) {
            $erros[] = 'Informe uma data de nascimento válida.';
        }

        if ($rg === '') {
            $erros[] = 'Informe o RG.';
        }
        if (!validarCpf($cpf)) {
            $erros[] = 'O CPF do aluno é inválido.';
        }
        if (!validarTel($tel1)) {
            $erros[] = 'O telefone 1 precisa ter DDD + número.';
        }
        // O telefone 2 é opcional: só validamos se a pessoa digitou algo.
        if ($tel2 !== '' && !validarTel($tel2)) {
            $erros[] = 'O telefone 2 precisa ter DDD + número (ou deixe em branco).';
        }

        // A turma escolhida precisa existir.
        $existe = $pdo->prepare('SELECT COUNT(*) FROM turmas WHERE id = ?');
        $existe->execute([$turmaId]);
        if (!$existe->fetchColumn()) {
            $erros[] = 'Escolha uma turma (cadastre uma turma antes, se ainda não houver).';
        }

        // --- Responsável: obrigatório SOMENTE para menores de 18 anos ---
        $respNome = $respCpf = $respTel = $respParentesco = null;   // começam vazios (NULL)
        if ($dataOk && calcIdade($nasc) < 18) {
            $respNome       = trim($_POST['resp_nome'] ?? '');
            $respCpf        = apenasDigitos($_POST['resp_cpf'] ?? '');
            $respTel        = apenasDigitos($_POST['resp_telefone'] ?? '');
            $respParentesco = $_POST['resp_parentesco'] ?? '';

            if ($respNome === '') {
                $erros[] = 'Aluno menor de 18 anos: informe o nome do responsável.';
            }
            if (!validarCpf($respCpf)) {
                $erros[] = 'O CPF do responsável é inválido.';
            }
            if (!validarTel($respTel)) {
                $erros[] = 'O telefone do responsável precisa ter DDD + número.';
            }
            if (!in_array($respParentesco, $PARENTESCOS)) {
                $erros[] = 'Escolha o parentesco do responsável.';
            }
        }

        // Só salva se não houve nenhum erro.
        if (!$erros) {
            try {
                $pdo->prepare(
                    'INSERT INTO alunos (nome, nascimento, rg, cpf, telefone1, telefone2, turma_id,
                                         resp_nome, resp_cpf, resp_telefone, resp_parentesco)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $nome, $nasc, $rg, $cpf, $tel1, $tel2 ?: null, $turmaId,
                    $respNome, $respCpf, $respTel, $respParentesco,
                ]);
            } catch (PDOException $ex) {
                // Erro 1062 do MySQL = valor repetido numa coluna UNIQUE (nosso CPF).
                if (($ex->errorInfo[1] ?? 0) === 1062) {
                    $erros[] = 'Já existe um aluno cadastrado com este CPF.';
                } else {
                    throw $ex;   // outro tipo de erro: deixa o PHP mostrar
                }
            }
        }

    /* ---------- AÇÃO 3: remover aluno ---------- */
    } elseif ($acao === 'del_aluno') {
        $pdo->prepare('DELETE FROM alunos WHERE id = ?')->execute([(int)$_POST['id']]);

    /* ---------- AÇÃO 4: excluir turma (os alunos dela saem junto: ON DELETE CASCADE) ---------- */
    } elseif ($acao === 'del_turma') {
        $pdo->prepare('DELETE FROM turmas WHERE id = ?')->execute([(int)$_POST['id']]);
    }

    // Se deu tudo certo, abre a página de novo (evita cadastrar em dobro ao apertar F5).
    // Se houve erro, NÃO redirecionamos: a página aparece de novo com as mensagens e os dados digitados.
    if (!$erros) {
        header('Location: index.php');
        exit;
    }
}

/* ------------------------------------------------------------
 *  PARTE 2: BUSCAR OS DADOS PARA MOSTRAR NA PÁGINA
 * ------------------------------------------------------------ */
$turmas = $pdo->query('SELECT * FROM turmas ORDER BY nome')->fetchAll();

// Organiza os alunos por turma: $alunosPorTurma[3] = lista de alunos da turma 3.
// O "foreach" repete o bloco para cada item da lista; [] no final = "adicionar no fim".
$alunosPorTurma = [];
foreach ($pdo->query('SELECT * FROM alunos ORDER BY nome')->fetchAll() as $a) {
    $alunosPorTurma[$a['turma_id']][] = $a;
}
$totalAlunos = array_sum(array_map('count', $alunosPorTurma));

/*
 * AQUI TERMINA O PHP "PURO" E COMEÇA A PÁGINA (HTML).
 * Tudo abaixo é HTML comum, com pedaços de PHP quando precisamos de algo dinâmico.
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Secretaria Escolar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="app.css">
</head>
<body>

<!-- ======== TOPO ======== -->
<header class="topo">
  <div>
    <h1>Secretaria Escolar</h1>
    <p>Alunos, responsáveis e turmas em um só lugar.</p>
  </div>
  <div class="resumo">
    <!-- Os atalhos de PHP que mostram um valor na página são escritos com o sinal de igual. -->
    <span><strong><?= count($turmas) ?></strong> turmas</span>
    <span><strong><?= $totalAlunos ?></strong> alunos</span>
    <?php barraUsuario(); ?>
  </div>
</header>

<main class="conteudo">

  <?php
  // Se existir alguma mensagem de erro, mostra um aviso com a lista.
  if ($erros): ?>
    <div class="alerta" role="alert">
      <strong>Não foi possível salvar:</strong>
      <ul>
        <?php foreach ($erros as $erro): ?>
          <li><?= e($erro) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="layout">

    <!-- ======== COLUNA ESQUERDA: formulários ======== -->
    <aside class="forms">

      <!-- FORMULÁRIO DE ALUNO. method="post" envia para esta mesma página (parte 1 do PHP). -->
      <form method="post" class="painel">
        <h2>Novo aluno</h2>
        <!-- Campo invisível que diz ao PHP qual ação executar -->
        <input type="hidden" name="acao" value="add_aluno">

        <label>Nome completo
          <input name="nome" required value="<?= v('nome') ?>">
        </label>

        <label>Data de nascimento
          <input type="date" name="nascimento" id="nascimento" required value="<?= v('nascimento') ?>">
          <!-- O JavaScript lá embaixo escreve aqui a idade calculada -->
          <small id="idadeAviso" class="dica"></small>
        </label>

        <div class="duas">
          <label>RG <input name="rg" required value="<?= v('rg') ?>"></label>
          <label>CPF <input name="cpf" data-mask="cpf" inputmode="numeric" placeholder="000.000.000-00" required value="<?= v('cpf') ?>"></label>
        </div>

        <div class="duas">
          <label>Telefone 1 <input name="telefone1" data-mask="tel" inputmode="tel" placeholder="(00) 00000-0000" required value="<?= v('telefone1') ?>"></label>
          <label>Telefone 2 <small class="dica">opcional</small>
            <input name="telefone2" data-mask="tel" inputmode="tel" placeholder="(00) 00000-0000" value="<?= v('telefone2') ?>">
          </label>
        </div>

        <label>Turma
          <select name="turma_id" required>
            <?php foreach ($turmas as $t): ?>
              <option value="<?= $t['id'] ?>"<?= sel('turma_id', $t['id']) ?>><?= e($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <!--
          BLOCO DO RESPONSÁVEL
          O atributo "hidden" esconde o bloco. O JavaScript mostra quando a
          idade calculada for menor que 18. (O PHP também confere isso lá em cima,
          então não dá para burlar apenas mexendo na página.)
        -->
        <fieldset class="responsavel" id="responsavel" hidden>
          <legend>Dados do responsável</legend>
          <label>Nome do responsável
            <input name="resp_nome" value="<?= v('resp_nome') ?>">
          </label>
          <div class="duas">
            <label>CPF <input name="resp_cpf" data-mask="cpf" inputmode="numeric" placeholder="000.000.000-00" value="<?= v('resp_cpf') ?>"></label>
            <label>Telefone <input name="resp_telefone" data-mask="tel" inputmode="tel" placeholder="(00) 00000-0000" value="<?= v('resp_telefone') ?>"></label>
          </div>
          <label>Parentesco
            <select name="resp_parentesco">
              <option value="">Selecione</option>
              <?php foreach ($PARENTESCOS as $p): ?>
                <option<?= sel('resp_parentesco', $p) ?>><?= e($p) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </fieldset>

        <button>Cadastrar aluno</button>
      </form>

      <!-- FORMULÁRIO DE TURMA -->
      <form method="post" class="painel">
        <h2>Nova turma</h2>
        <input type="hidden" name="acao" value="add_turma">
        <label>Nome da turma <input name="nome" required></label>
        <div class="duas">
          <label>Turno
            <select name="turno"><option>Manhã</option><option>Tarde</option><option>Noite</option></select>
          </label>
          <label>Sala <input name="sala"></label>
        </div>

        <!-- Dias da semana: o "[]" em name="dias[]" faz o PHP receber uma LISTA com todos os marcados -->
        <fieldset class="dias-campo">
          <legend>Dias da semana</legend>
          <?php foreach ($DIAS as $codigo => $nomeDia): ?>
            <label class="chip" title="<?= e($nomeDia) ?>">
              <input type="checkbox" name="dias[]" value="<?= e($codigo) ?>">
              <span><?= e($codigo) ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <label>Cor da turma <input type="color" name="cor" value="#3B4BDB"></label>
        <button>Criar turma</button>
      </form>
    </aside>

    <!-- ======== COLUNA DIREITA: turmas e alunos ======== -->
    <section class="turmas">

      <?php if (!$turmas): ?>
        <p class="vazio">Nenhuma turma ainda. Crie a primeira no formulário ao lado.</p>
      <?php endif; ?>

      <?php
      // Um cartão para cada turma. $t é a turma atual; $lista são os alunos dela.
      // explode(',', ...) faz o contrário do implode: 'Seg,Qua' vira ['Seg','Qua'].
      foreach ($turmas as $t):
        $lista = $alunosPorTurma[$t['id']] ?? [];
        $diasDaTurma = explode(',', $t['dias']); ?>

        <!-- O atributo style abaixo manda a cor desta turma para o CSS usar na borda, no título e nos dias -->
        <article class="turma" style="--cor: <?= e($t['cor']) ?>">
          <div class="turma-topo">
            <div>
              <h2><?= e($t['nome']) ?></h2>
              <p>Sala <?= e($t['sala']) ?> · <?= e($t['turno']) ?> · <?= count($lista) ?> alunos</p>
            <a class="abrir" href="turma.php?id=<?= $t['id'] ?>">Abrir área da turma →</a>
            </div>
            <form method="post" onsubmit="return confirm('Excluir a turma e todos os seus alunos?')">
              <input type="hidden" name="acao" value="del_turma">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="link">Excluir turma</button>
            </form>
          </div>

          <!-- Faixa com os 7 dias da semana; os dias de aula da turma ficam pintados -->
          <div class="semana" aria-label="Dias de aula">
            <?php foreach ($DIAS as $codigo => $nomeDia): ?>
              <span class="<?= in_array($codigo, $diasDaTurma) ? 'on' : '' ?>" title="<?= e($nomeDia) ?>"><?= e($codigo) ?></span>
            <?php endforeach; ?>
          </div>

          <?php if ($lista): ?>
            <div class="alunos">
              <?php foreach ($lista as $a):
                $idade = calcIdade($a['nascimento']); ?>

                <!-- <details> é nativo do HTML: clicar no resumo abre/fecha os detalhes, sem JavaScript -->
                <details class="aluno">
                  <summary>
                    <span class="avatar"><?= e(mb_strtoupper(mb_substr($a['nome'], 0, 1))) ?></span>
                    <span class="info">
                      <strong><?= e($a['nome']) ?></strong>
                      <small><?= $idade ?> anos · <?= e(fmtTel($a['telefone1'])) ?></small>
                    </span>
                    <?php if ($idade < 18): ?><span class="etiqueta">Menor de idade</span><?php endif; ?>
                  </summary>

                  <!-- dl = lista de "termo: valor" -->
                  <dl class="dados">
                    <div><dt>Nascimento</dt><dd><?= e(date('d/m/Y', strtotime($a['nascimento']))) ?></dd></div>
                    <div><dt>RG</dt><dd><?= e($a['rg']) ?></dd></div>
                    <div><dt>CPF</dt><dd><?= e(fmtCpf($a['cpf'])) ?></dd></div>
                    <div><dt>Telefone 1</dt><dd><?= e(fmtTel($a['telefone1'])) ?></dd></div>
                    <?php if ($a['telefone2']): ?>
                      <div><dt>Telefone 2</dt><dd><?= e(fmtTel($a['telefone2'])) ?></dd></div>
                    <?php endif; ?>
                  </dl>

                  <?php if ($a['resp_nome']): /* só aparece para menores */ ?>
                    <div class="resp-dados">
                      <h3>Responsável</h3>
                      <dl class="dados">
                        <div><dt>Nome</dt><dd><?= e($a['resp_nome']) ?> (<?= e($a['resp_parentesco']) ?>)</dd></div>
                        <div><dt>CPF</dt><dd><?= e(fmtCpf($a['resp_cpf'])) ?></dd></div>
                        <div><dt>Telefone</dt><dd><?= e(fmtTel($a['resp_telefone'])) ?></dd></div>
                      </dl>
                    </div>
                  <?php endif; ?>

                  <form method="post" onsubmit="return confirm('Remover este aluno?')">
                    <input type="hidden" name="acao" value="del_aluno">
                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <button class="link">Remover aluno</button>
                  </form>
                </details>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="vazio">Sem alunos nesta turma. Cadastre o primeiro ao lado.</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>
  </div>
</main>

<!--
  JAVASCRIPT: roda no navegador (não no servidor). Faz duas coisas:
  1) Mostra o bloco do responsável quando o aluno tem menos de 18 anos.
  2) Coloca máscaras de CPF e telefone enquanto a pessoa digita.
-->
<script>
// ---------- 1) Responsável aparece só para menores de 18 ----------
const campoNasc = document.getElementById('nascimento');
const blocoResp = document.getElementById('responsavel');
const aviso     = document.getElementById('idadeAviso');

function verificarIdade() {
  if (!campoNasc.value) { blocoResp.hidden = true; aviso.textContent = ''; return; }

  // O valor vem como "2010-03-14"; separamos em ano, mês e dia (números).
  const [ano, mes, dia] = campoNasc.value.split('-').map(Number);
  const hoje = new Date();
  let idade = hoje.getFullYear() - ano;
  // Se o aniversário deste ano ainda não chegou, tira 1 ano.
  if (hoje.getMonth() + 1 < mes || (hoje.getMonth() + 1 === mes && hoje.getDate() < dia)) idade--;

  const menor = idade < 18;
  blocoResp.hidden = !menor;                       // mostra ou esconde o bloco
  aviso.textContent = idade + ' anos' + (menor ? ' · preencha os dados do responsável' : '');
  // Campos do responsável só são obrigatórios quando o bloco está visível.
  blocoResp.querySelectorAll('input, select').forEach(c => c.required = menor);
}
campoNasc.addEventListener('input', verificarIdade);
verificarIdade();   // roda uma vez ao abrir a página (útil quando volta com erro)

// ---------- 2) Máscaras de CPF e telefone ----------
function mascara(texto, tipo) {
  const n = texto.replace(/\D/g, '');   // fica só com números
  if (tipo === 'cpf') {
    return n.slice(0, 11)
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }
  return n.slice(0, 11)
    .replace(/(\d{2})(\d)/, '($1) $2')
    .replace(/(\d{4,5})(\d{4})$/, '$1-$2');
}
document.querySelectorAll('[data-mask]').forEach(campo => {
  const aplicar = () => campo.value = mascara(campo.value, campo.dataset.mask);
  campo.addEventListener('input', aplicar);
  aplicar();
});
</script>
</body>
</html>
