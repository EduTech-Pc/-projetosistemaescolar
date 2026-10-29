<?php
/*
 * ============================================================
 *  auth.php  -  LOGIN E CONTROLE DE ACESSO
 * ============================================================
 *  Toda página protegida começa com:
 *      require __DIR__ . '/auth.php';
 *      exigir(['adm', 'dev']);     // lista de papéis que podem entrar
 *  Se a pessoa não estiver logada, é mandada para o login.
 *  Se estiver logada com um papel não permitido, vê "Acesso negado".
 */
require_once __DIR__ . '/config.php';   // traz $pdo (conexão com o banco)

// Sessão = memória do servidor sobre quem está logado (guardada por um cookie).
if (session_status() === PHP_SESSION_NONE) {
    // httponly: o JavaScript não lê o cookie; samesite: outros sites não conseguem usá-lo para enviar formulários.
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Papéis do sistema: "código no banco" => "nome na tela".
const PAPEIS = [
    'dev' => 'Desenvolvedor', 'adm' => 'Administrador', 'professor' => 'Professor',
    'aluno' => 'Aluno', 'responsavel' => 'Responsável',
];

// Protege a página de textos perigosos. Use sempre ao mostrar dados do banco/usuário.
function e($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

// Devolve os dados de quem está logado (id, nome, papel) ou null.
function usuario()
{
    return $_SESSION['usuario'] ?? null;
}

// Barra a entrada de quem não tem permissão.
function exigir(array $papeis)
{
    $u = usuario();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    if (!in_array($u['papel'], $papeis)) {
        http_response_code(403);
        exit('Acesso negado. <a href="inicio.php">Voltar ao início</a>');
    }
}

// Lista as turmas que o usuário logado pode ver:
//  - dev e adm: todas
//  - professor: as turmas ligadas a ele (tabela professor_turmas)
//  - aluno e responsável: as turmas dos alunos ligados a ele (tabela vinculos)
function turmasVisiveis(PDO $pdo)
{
    $u = usuario();
    if (in_array($u['papel'], ['dev', 'adm'])) {
        return $pdo->query('SELECT * FROM turmas ORDER BY nome')->fetchAll();
    }
    if ($u['papel'] === 'professor') {
        $sql = 'SELECT t.* FROM turmas t
                JOIN professor_turmas pt ON pt.turma_id = t.id
                WHERE pt.usuario_id = ? ORDER BY t.nome';
    } else {
        $sql = 'SELECT DISTINCT t.* FROM turmas t
                JOIN alunos a ON a.turma_id = t.id
                JOIN vinculos v ON v.aluno_id = a.id
                WHERE v.usuario_id = ? ORDER BY t.nome';
    }
    $st = $pdo->prepare($sql);
    $st->execute([$u['id']]);
    return $st->fetchAll();
}

// Quem pode publicar vídeos/atividades na área da turma.
function podePostar()
{
    return in_array(usuario()['papel'], ['dev', 'adm', 'professor']);
}

// Botões "Início" e "Sair" que aparecem no topo das páginas.
function barraUsuario()
{
    echo '<a class="btn-topo" href="inicio.php">Início</a>',
         '<a class="btn-topo sair" href="logout.php">Sair</a>';
}
