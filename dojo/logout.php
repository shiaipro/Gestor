<?php
require_once '../Gestor/config.php';
unset($_SESSION['portal_cpf']);
header('Location: login.php');
exit;
