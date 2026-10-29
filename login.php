<?php
require __DIR__ . '/auth.php';

// Já está logado? Vai direto para o início.
if (usuario()) {
    header('Location: inicio.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
    $st->execute([strtolower(trim($_POST['email'] ?? ''))]);
    $u = $st->fetch();

    // password_verify compara a senha digitada com o "hash" guardado no banco
    // (a senha de verdade nunca é guardada).
    if ($u && password_verify($_POST['senha'] ?? '', $u['senha_hash'])) {
        session_regenerate_id(true);   // novo identificador de sessão (evita roubo de sessão)
        $_SESSION['usuario'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'papel' => $u['papel']];
        header('Location: inicio.php');
        exit;
    }
    // A mensagem é a mesma para e-mail errado ou senha errada (não revela quais e-mails existem).
    $erro = 'E-mail ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · Secretaria Escolar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="app.css">
</head>
<body class="login">
  <form method="post" class="painel login-card">
    <h1>Secretaria Escolar</h1>
    <p class="dica">Entre com o e-mail e a senha que a escola te passou.</p>
    <?php if ($erro): ?><div class="alerta" role="alert"><?= e($erro) ?></div><?php endif; ?>
    <label>E-mail <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Senha <input type="password" name="senha" required></label>
    <button>Entrar</button>
  </form>
</body>
</html>
