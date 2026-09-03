<?php
require_once 'config.php';

if (!estaLogado()) {
    header('Location: login.php');
    exit;
}

if (ehMestre()) {
    header('Location: mestre/index.php');
} else {
    header('Location: unidade/index.php');
}
exit;
