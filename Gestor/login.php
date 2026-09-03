<?php
require_once 'config.php';

// Garantir que a tabela de logs existe
$pdo->exec("CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    nome VARCHAR(255) DEFAULT NULL,
    nivel VARCHAR(50) DEFAULT NULL,
    unidade_id INT DEFAULT NULL,
    status ENUM('sucesso','falha') NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    INDEX idx_email (email)
)");

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if ($email && $senha) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, un.status as unidade_status 
                FROM usuarios u 
                LEFT JOIN unidades un ON u.unidade_id = un.id 
                WHERE u.email = ? AND u.status = 'ativo'
            ");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                // Se for usuário de academia, checar status da academia
                if ($usuario['nivel'] !== 'mestre' && $usuario['unidade_status'] !== 'ativo') {
                    $erro = 'Acesso bloqueado: Esta academia está inativa ou suspensa. Contate o administrador.';
                    $usuario = false; // Forçar falha no login
                }
            }

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['nivel'] = $usuario['nivel'];
                $_SESSION['unidade_id'] = $usuario['unidade_id'];

                // Registrar log de SUCESSO
                $pdo->prepare("INSERT INTO login_logs (usuario_id, email, nome, nivel, unidade_id, status, ip, user_agent) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$usuario['id'], $email, $usuario['nome'], $usuario['nivel'], $usuario['unidade_id'], 'sucesso', $ip, $ua]);

                if ($usuario['nivel'] === 'mestre') {
                    header('Location: mestre/index.php');
                } else {
                    header('Location: unidade/index.php');
                }
                exit;
            } else {
                // Registrar log de FALHA
                $pdo->prepare("INSERT INTO login_logs (usuario_id, email, status, ip, user_agent) VALUES (?,?,?,?,?)")
                    ->execute([null, $email, 'falha', $ip, $ua]);
                $erro = 'E-mail ou senha inválidos.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro no banco de dados: ' . $e->getMessage();
        }
    } else {
        $erro = 'Por favor, preencha todos os campos.';;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - SHIAI PRO</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/favicon/site.webmanifest">
    <link rel="shortcut icon" href="/favicon/favicon.ico">

    <!-- Fonts: Rubik -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom Login Styles -->
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo time(); ?>">
</head>

<body>
    <!-- Background Glows -->
    <div class="bg-glow glow-1"></div>
    <div class="bg-glow glow-2"></div>

    <div class="login-card">
        <div class="login-header">
            <a href="#" class="logo">
                <img src="assets/img/logo.png" alt="SHIAIPRO">
            </a>
            <p>Acesse o painel do seu sistema</p>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i>
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="seu@email.com" required
                    autofocus>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary">
                Entrar no Sistema <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left: 8px;"></i>
            </button>
        </form>
    </div>
</body>

</html>