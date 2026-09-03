<?php
require_once '../config.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf_input = $_POST['cpf'] ?? '';
    $nascimento_input = $_POST['data_nascimento'] ?? '';
    $cpf_digits = preg_replace('/\D/', '', $cpf_input);

    if ($cpf_digits && $nascimento_input) {
        try {
            $stmt = $pdo->prepare("
                SELECT a.id,
                       CASE
                           WHEN REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ? THEN REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '')
                           ELSE REPLACE(REPLACE(REPLACE(a.responsavel_cpf, '.', ''), '-', ''), ' ', '')
                       END as cpf_dono
                FROM alunos a
                WHERE a.status = 'ativo'
                  AND a.data_nascimento = ?
                  AND (
                        REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
                        OR REPLACE(REPLACE(REPLACE(a.responsavel_cpf, '.', ''), '-', ''), ' ', '') = ?
                      )
                LIMIT 1
            ");
            $stmt->execute([$cpf_digits, $nascimento_input, $cpf_digits, $cpf_digits]);
            $encontrado = $stmt->fetch();

            if ($encontrado) {
                session_regenerate_id(true);
                $_SESSION['portal_cpf'] = $cpf_digits;
                header('Location: index.php');
                exit;
            } else {
                $erro = 'CPF ou data de nascimento não conferem com nenhum cadastro.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro no banco de dados: ' . $e->getMessage();
        }
    } else {
        $erro = 'Por favor, preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Área do Aluno - SHIAI PRO</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/favicon/site.webmanifest">
    <link rel="shortcut icon" href="/favicon/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/login.css?v=<?php echo time(); ?>">
</head>

<body>
    <div class="bg-glow glow-1"></div>
    <div class="bg-glow glow-2"></div>

    <div class="login-card">
        <div class="login-header">
            <a href="#" class="logo">
                <img src="../assets/img/logo.png" alt="SHIAIPRO">
            </a>
            <p>Área do Aluno / Responsável</p>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="cpf">CPF (seu ou do responsável)</label>
                <input type="text" id="cpf" name="cpf" class="form-control" placeholder="000.000.000-00" required
                    autofocus inputmode="numeric">
            </div>
            <div class="form-group">
                <label for="data_nascimento">Data de nascimento do aluno</label>
                <input type="date" id="data_nascimento" name="data_nascimento" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">
                Entrar <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left: 8px;"></i>
            </button>
        </form>
    </div>

    <footer style="text-align: center; padding: 25px 20px; font-size: 0.75rem; color: #94a3b8; font-weight: 600; position: relative; z-index: 1;">
        &copy; <?php echo date('Y'); ?> SHIAI PRO - Gestão Inteligente para Academias.
    </footer>
</body>

</html>
