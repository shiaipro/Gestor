<?php
require_once '../Gestor/config.php';

if (empty($_SESSION['portal_cpf'])) {
    header('Location: login.php');
    exit;
}

$cpf_sessao = $_SESSION['portal_cpf'];

$stmt = $pdo->prepare("
    SELECT a.id, a.unidade_id, a.academia_id, a.nome_completo, a.foto, a.faixa, a.data_nascimento, a.status,
           un.nome as unidade_nome,
           ac.nome as academia_nome,
           (SELECT GROUP_CONCAT(t.nome SEPARATOR ', ') FROM turma_alunos ta JOIN turmas t ON ta.turma_id = t.id WHERE ta.aluno_id = a.id) as turmas_nomes
    FROM alunos a
    LEFT JOIN unidades un ON a.unidade_id = un.id
    LEFT JOIN academias ac ON a.academia_id = ac.id
    WHERE REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
       OR REPLACE(REPLACE(REPLACE(a.responsavel_cpf, '.', ''), '-', ''), ' ', '') = ?
    ORDER BY a.nome_completo ASC
");
$stmt->execute([$cpf_sessao, $cpf_sessao]);
$alunos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Área do Aluno - SHIAI PRO</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="shortcut icon" href="/favicon/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../Gestor/assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../Gestor/assets/css/dashboard_v2.css?v=<?php echo time(); ?>">
</head>

<body>
    <section class="dashboard-section-sq" style="max-width: 960px; margin: 40px auto; padding: 0 20px;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <img src="../Gestor/assets/img/logo.png" alt="SHIAIPRO" style="height: 36px;">
                <h1 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0;">Área do Aluno</h1>
            </div>
            <a href="logout.php" class="btn-sq-outline" style="width: auto; padding: 10px 20px; font-size: var(--fs-sm);">
                <i class="fa-solid fa-right-from-bracket" style="margin-right: 8px;"></i> Sair
            </a>
        </div>

        <?php if (empty($alunos)): ?>
            <div class="dashboard-container" style="padding: 35px; text-align: center;">
                <p style="margin: 0; color: var(--text-muted); font-weight: 700;">Nenhum aluno encontrado para este CPF.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($alunos as $al): ?>
                    <a href="ficha.php?id=<?php echo (int)$al['id']; ?>" class="dashboard-container" style="padding: 25px 30px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; text-decoration: none; color: inherit; cursor: pointer;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #133080; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.1rem; flex-shrink: 0; overflow: hidden;">
                            <?php if (!empty($al['foto'])):
                                $foto_base = '../Gestor/uploads/u_' . (int)$al['unidade_id'];
                                if (!empty($al['academia_id'])) { $foto_base .= '/academias/a_' . (int)$al['academia_id']; }
                                $foto_url = $foto_base . '/alunos/' . $al['foto'];
                            ?>
                                <img src="<?php echo htmlspecialchars($foto_url); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <?php echo strtoupper(substr($al['nome_completo'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <div style="font-weight: 900; font-size: var(--fs-base); text-transform: uppercase;"><?php echo htmlspecialchars($al['nome_completo']); ?></div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 4px;">
                                Faixa: <?php echo htmlspecialchars($al['faixa'] ?: 'Sem faixa'); ?>
                                <?php if (!empty($al['unidade_nome'])): ?> • <?php echo htmlspecialchars($al['unidade_nome']); ?><?php endif; ?>
                                <?php if (!empty($al['academia_nome'])): ?> • <?php echo htmlspecialchars($al['academia_nome']); ?><?php endif; ?>
                                <?php if (!empty($al['turmas_nomes'])): ?> • Turma: <?php echo htmlspecialchars($al['turmas_nomes']); ?><?php endif; ?>
                            </div>
                        </div>
                        <span style="font-size: 10px; font-weight: 900; padding: 4px 10px; text-transform: uppercase; letter-spacing: 0.03em; background: <?php echo $al['status'] === 'ativo' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $al['status'] === 'ativo' ? '#166534' : '#991b1b'; ?>;">
                            <?php echo htmlspecialchars($al['status']); ?>
                        </span>
                        <i class="fa-solid fa-chevron-right" style="color: var(--text-muted);"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </section>

    <footer style="text-align: center; padding: 25px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600;">
        &copy; <?php echo date('Y'); ?> SHIAI PRO - Gestão Inteligente para Academias.
    </footer>
</body>

</html>
