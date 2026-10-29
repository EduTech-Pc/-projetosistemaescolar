<?php
// Encerra a sessão e volta para o login.
require __DIR__ . '/auth.php';
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
