<?php
/*
 * ============================================================
 *  config.php  -  CONEXÃO COM O BANCO DE DADOS
 * ============================================================
 *  Todo arquivo PHP começa com "<?php". É isso que avisa ao servidor:
 *  "daqui pra frente é código PHP, não texto comum".
 *
 *  Este arquivo faz uma única coisa: abrir a "ponte" entre o PHP
 *  e o MySQL (o banco de dados). Os outros arquivos usam essa ponte.
 *
 *  Sobre comentários em PHP (o PHP ignora tudo isso):
 *    // comentário de uma linha
 *    barra-asterisco ... asterisco-barra = comentário de várias linhas
 *    (como este bloco inteiro)
 */

/*
 * "const" cria uma constante: um valor com nome que nunca muda.
 * Aqui guardamos os dados de acesso ao MySQL.
 * Se o seu MySQL tiver outra senha, é AQUI que você troca.
 */
const DB_HOST = 'localhost';          // onde o MySQL está (no seu próprio computador)
const DB_NOME = 'secretaria_escolar'; // nome do banco criado pelo banco.sql
const DB_USER = 'root';               // usuário do MySQL (padrão do XAMPP)
const DB_PASS = '';                   // senha do MySQL (no XAMPP é vazia)

/*
 * try / catch = "tente fazer isso; se der erro, faça aquilo".
 * Conectar ao banco pode falhar (senha errada, MySQL desligado...),
 * então usamos try/catch para mostrar uma mensagem amigável.
 */
try {
    /*
     * PDO é a ferramenta do PHP para conversar com bancos de dados.
     * "new PDO(...)" cria a conexão e guarda na variável $pdo.
     * Em PHP, TODA variável começa com o símbolo $.
     */
    $pdo = new PDO(
        // "DSN": endereço do banco. O ponto (.) junta textos.
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NOME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            // Se algum comando SQL falhar, o PHP avisa com um erro (em vez de ficar calado).
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Cada linha do banco volta como lista com nomes: $linha['nome'], $linha['email']...
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $ex) {
    // exit() para tudo e mostra a mensagem na tela.
    exit('Não foi possível conectar ao banco. Importe o banco.sql e confira o config.php.');
}
