<?php
require_once '../config.php';
unset($_SESSION['portal_cpf']);
header('Location: login.php');
exit;
