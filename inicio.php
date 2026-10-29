<?php
/* inicio.php - tela inicial depois do login. Mostra os atalhos do papel da pessoa e as turmas dela. */
require __DIR__ . '/auth.php';
exigir(array_keys(PAPEIS));   // qualquer pessoa logada

$u = usuario();
$turmas = turmasVisiveis($pdo);
$DIAS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Início</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="app.css">
</head>
<body>
<header class="topo">
  <div>
    <h1>Olá, <?= e(explode(' ', $u['nome'])[0]) ?></h1>
    <p>Você entrou como <?= e(PAPEIS[$u['papel']]) ?>.</p>
  </div>
  <div class="acoes"><a class="btn-topo sair" href="logout.php">Sair</a></div>
</header>

<main class="conteudo">
  <!-- Atalhos: cada um só aparece para quem tem permissão -->
  <div class="atalhos">
    <?php if (in_array($u['papel'], ['dev', 'adm'])): ?>
      <a class="painel atalho" href="index.php"><strong>Secretaria</strong><span>Alunos e turmas</span></a>
      <a class="painel atalho" href="usuarios.php"><strong>Usuários</strong><span>Logins e permissões</span></a>
    <?php endif; ?>
    <?php if (in_array($u['papel'], ['dev', 'adm', 'professor'])): ?>
      <a class="painel atalho" href="professor.php"><strong>Chamada e notas</strong><span>Área do professor</span></a>
    <?php endif; ?>
  </div>

  <h2 class="titulo-secao">Áreas das turmas</h2>
  <?php if (!$turmas): ?>
    <p class="painel">Nenhuma turma disponível para você ainda.</p>
  <?php endif; ?>
  <div class="grade-turmas">
    <?php foreach ($turmas as $t): $dias = explode(',', $t['dias']); ?>
      <a class="painel turma-card" style="--cor: <?= e($t['cor']) ?>" href="turma.php?id=<?= $t['id'] ?>">
        <h3><?= e($t['nome']) ?></h3>
        <p>Sala <?= e($t['sala']) ?> · <?= e($t['turno']) ?></p>
        <div class="semana">
          <?php foreach ($DIAS as $d): ?><span class="<?= in_array($d, $dias) ? 'on' : '' ?>"><?= $d ?></span><?php endforeach; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
