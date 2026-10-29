<?php
/* usuarios.php - cria e gerencia logins. Só para dev e adm. */
require __DIR__ . '/auth.php';
exigir(['dev', 'adm']);
$eu = usuario();

// O dev cria qualquer papel; o adm só cria professor, aluno e responsável.
$permitidos = $eu['papel'] === 'dev' ? array_keys(PAPEIS) : ['professor', 'aluno', 'responsavel'];
function podeGerir($papelAlvo)
{
    return usuario()['papel'] === 'dev' || in_array($papelAlvo, ['professor', 'aluno', 'responsavel']);
}

$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    /* ---------- CRIAR USUÁRIO ---------- */
    if ($acao === 'add_usuario') {
        $nome  = trim($_POST['nome'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $senha = $_POST['senha'] ?? '';
        $papel = $_POST['papel'] ?? '';
        $alunosIds = array_map('intval', (array)($_POST['alunos'] ?? []));   // para aluno/responsável
        $turmasIds = array_map('intval', (array)($_POST['turmas'] ?? []));   // para professor

        if ($nome === '') $erros[] = 'Informe o nome.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'Informe um e-mail válido.';
        if (strlen($senha) < 8) $erros[] = 'A senha precisa ter pelo menos 8 caracteres.';
        if (!in_array($papel, $permitidos)) $erros[] = 'Escolha um papel válido.';
        if (in_array($papel, ['aluno', 'responsavel']) && !$alunosIds) $erros[] = 'Marque o(s) aluno(s) ligado(s) a este login.';
        if ($papel === 'professor' && !$turmasIds) $erros[] = 'Marque a(s) turma(s) do professor.';

        if (!$erros) {
            try {
                $pdo->beginTransaction();
                // password_hash transforma a senha num código irreversível (é isso que fica no banco).
                $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, papel) VALUES (?, ?, ?, ?)')
                    ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $papel]);
                $uid = $pdo->lastInsertId();

                if (in_array($papel, ['aluno', 'responsavel'])) {
                    $ins = $pdo->prepare('INSERT INTO vinculos (usuario_id, aluno_id) VALUES (?, ?)');
                    foreach ($alunosIds as $id) $ins->execute([$uid, $id]);
                } elseif ($papel === 'professor') {
                    $ins = $pdo->prepare('INSERT INTO professor_turmas (usuario_id, turma_id) VALUES (?, ?)');
                    foreach ($turmasIds as $id) $ins->execute([$uid, $id]);
                }
                $pdo->commit();
                header('Location: usuarios.php?ok=' . urlencode('Usuário criado!'));
                exit;
            } catch (PDOException $ex) {
                $pdo->rollBack();   // desfaz tudo se algo falhou
                $erros[] = (($ex->errorInfo[1] ?? 0) === 1062)
                    ? 'Já existe um usuário com este e-mail.'
                    : 'Não foi possível salvar. Confira os vínculos escolhidos.';
            }
        }

    /* ---------- EXCLUIR / REDEFINIR SENHA ---------- */
    } elseif (in_array($acao, ['del_usuario', 'reset_senha'])) {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT papel FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        $alvo = $st->fetchColumn();

        if (!$alvo || !podeGerir($alvo)) {
            $erros[] = 'Você não tem permissão para alterar este usuário.';
        } elseif ($acao === 'del_usuario') {
            if ($id === $eu['id']) {
                $erros[] = 'Você não pode excluir o próprio login.';
            } else {
                $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
                header('Location: usuarios.php?ok=' . urlencode('Usuário excluído.'));
                exit;
            }
        } else {
            $nova = $_POST['nova_senha'] ?? '';
            if (strlen($nova) < 8) {
                $erros[] = 'A nova senha precisa ter pelo menos 8 caracteres.';
            } else {
                $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                    ->execute([password_hash($nova, PASSWORD_DEFAULT), $id]);
                header('Location: usuarios.php?ok=' . urlencode('Senha alterada.'));
                exit;
            }
        }
    }
}

$usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY FIELD(papel, "dev","adm","professor","responsavel","aluno"), nome')->fetchAll();
$listaAlunos = $pdo->query('SELECT a.id, a.nome, t.nome AS turma FROM alunos a JOIN turmas t ON t.id = a.turma_id ORDER BY a.nome')->fetchAll();
$listaTurmas = $pdo->query('SELECT id, nome FROM turmas ORDER BY nome')->fetchAll();
$ok = $_GET['ok'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Usuários</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="app.css">
</head>
<body>
<header class="topo">
  <div><h1>Usuários</h1><p>Crie logins para a equipe, os alunos e os responsáveis.</p></div>
  <div class="acoes"><?php barraUsuario(); ?></div>
</header>

<main class="conteudo">
  <?php if ($ok): ?><div class="sucesso" role="status"><?= e($ok) ?></div><?php endif; ?>
  <?php if ($erros): ?>
    <div class="alerta" role="alert"><strong>Não foi possível concluir:</strong>
      <ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="layout">
    <aside>
      <form method="post" class="painel">
        <h2>Novo usuário</h2>
        <input type="hidden" name="acao" value="add_usuario">
        <label>Nome <input name="nome" required value="<?= e($_POST['nome'] ?? '') ?>"></label>
        <label>E-mail (será o login) <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label>Senha provisória <input type="password" name="senha" minlength="8" required></label>
        <label>Papel
          <select name="papel" id="papel" required>
            <?php foreach ($permitidos as $p): ?><option value="<?= $p ?>"><?= e(PAPEIS[$p]) ?></option><?php endforeach; ?>
          </select>
        </label>

        <!-- Aparece para aluno e responsável: com quais alunos este login está ligado -->
        <fieldset class="vinculo" id="vinc-alunos" hidden>
          <legend>Aluno(s) ligado(s)</legend>
          <?php foreach ($listaAlunos as $a): ?>
            <label class="check"><input type="checkbox" name="alunos[]" value="<?= $a['id'] ?>"> <?= e($a['nome']) ?> <small>(<?= e($a['turma']) ?>)</small></label>
          <?php endforeach; ?>
          <?php if (!$listaAlunos): ?><small>Nenhum aluno cadastrado ainda.</small><?php endif; ?>
        </fieldset>

        <!-- Aparece para professor: quais turmas ele leciona -->
        <fieldset class="vinculo" id="vinc-turmas" hidden>
          <legend>Turma(s) do professor</legend>
          <?php foreach ($listaTurmas as $t): ?>
            <label class="check"><input type="checkbox" name="turmas[]" value="<?= $t['id'] ?>"> <?= e($t['nome']) ?></label>
          <?php endforeach; ?>
        </fieldset>

        <button>Criar usuário</button>
      </form>
    </aside>

    <section class="painel">
      <h2>Usuários cadastrados</h2>
      <div class="lista">
        <?php foreach ($usuarios as $us): ?>
          <div class="usuario">
            <span class="info"><strong><?= e($us['nome']) ?></strong><small><?= e($us['email']) ?></small></span>
            <span class="etiqueta papel-<?= e($us['papel']) ?>"><?= e(PAPEIS[$us['papel']]) ?></span>
            <?php if (podeGerir($us['papel'])): ?>
              <form method="post" class="mini">
                <input type="hidden" name="acao" value="reset_senha">
                <input type="hidden" name="id" value="<?= $us['id'] ?>">
                <input type="password" name="nova_senha" placeholder="Nova senha" minlength="8" required>
                <button class="link">Trocar senha</button>
              </form>
              <?php if ($us['id'] != $eu['id']): ?>
                <form method="post" onsubmit="return confirm('Excluir este usuário?')">
                  <input type="hidden" name="acao" value="del_usuario">
                  <input type="hidden" name="id" value="<?= $us['id'] ?>">
                  <button class="link">Excluir</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</main>

<script>
// Mostra a lista de alunos ou de turmas conforme o papel escolhido.
const papel = document.getElementById('papel');
function mostrarVinculo() {
  document.getElementById('vinc-alunos').hidden = !['aluno', 'responsavel'].includes(papel.value);
  document.getElementById('vinc-turmas').hidden = papel.value !== 'professor';
}
papel.addEventListener('change', mostrarVinculo);
mostrarVinculo();
</script>
</body>
</html>
