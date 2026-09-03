<?php
require_once '../config.php';

if (empty($_SESSION['portal_cpf'])) {
    header('Location: login.php');
    exit;
}

$cpf_sessao = $_SESSION['portal_cpf'];
$aluno_id = $_GET['id'] ?? 0;

// Ownership: só permite ver a ficha se o aluno estiver de fato vinculado ao CPF logado
// (evita acesso a ficha de terceiros trocando o id na URL).
$stmt = $pdo->prepare("
    SELECT a.*, un.nome as unidade_nome, ac.nome as academia_nome
    FROM alunos a
    LEFT JOIN unidades un ON a.unidade_id = un.id
    LEFT JOIN academias ac ON a.academia_id = ac.id
    WHERE a.id = ?
      AND (
            REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
            OR REPLACE(REPLACE(REPLACE(a.responsavel_cpf, '.', ''), '-', ''), ' ', '') = ?
          )
    LIMIT 1
");
$stmt->execute([$aluno_id, $cpf_sessao, $cpf_sessao]);
$aluno = $stmt->fetch();

if (!$aluno) {
    header('Location: index.php');
    exit;
}

$foto_url = null;
if (!empty($aluno['foto'])) {
    $foto_url = '../uploads/u_' . (int)$aluno['unidade_id'];
    if (!empty($aluno['academia_id'])) { $foto_url .= '/academias/a_' . (int)$aluno['academia_id']; }
    $foto_url .= '/alunos/' . $aluno['foto'];
}

// Turmas vinculadas
$stmt_turmas = $pdo->prepare("
    SELECT t.nome, t.horario
    FROM turma_alunos ta
    JOIN turmas t ON ta.turma_id = t.id
    WHERE ta.aluno_id = ?
    ORDER BY t.nome ASC
");
$stmt_turmas->execute([$aluno['id']]);
$turmas_aluno = $stmt_turmas->fetchAll();

// Financeiro (mensalidades)
$stmt_fin = $pdo->prepare("SELECT * FROM mensalidades WHERE aluno_id = ? ORDER BY data_vencimento DESC");
$stmt_fin->execute([$aluno['id']]);
$financeiro = $stmt_fin->fetchAll();

// Competições
$stmt_comp = $pdo->prepare("
    SELECT ci.*, c.nome as comp_nome, c.data_evento
    FROM competicao_inscricoes ci
    JOIN competicoes c ON ci.competicao_id = c.id
    WHERE ci.aluno_id = ?
    ORDER BY c.data_evento DESC
");
$stmt_comp->execute([$aluno['id']]);
$competicoes = $stmt_comp->fetchAll();

// Exames de faixa
$exames_faixa = [];
try {
    $stmt_ef = $pdo->prepare("
        SELECT ei.*, eg.titulo as evento_titulo, eg.data_evento, eg.local, eg.taxa
        FROM eventos_graduacao_inscricoes ei
        JOIN eventos_graduacao eg ON eg.id = ei.evento_id
        WHERE ei.aluno_id = ?
        ORDER BY eg.data_evento DESC
    ");
    $stmt_ef->execute([$aluno['id']]);
    $exames_faixa = $stmt_ef->fetchAll();
} catch (Exception $e) {
    $exames_faixa = [];
}

function campo($label, $valor) {
    echo '<div style="min-width: 200px; flex: 1;">';
    echo '<div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">' . htmlspecialchars($label) . '</div>';
    echo '<div style="font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark);">' . ($valor !== '' && $valor !== null ? htmlspecialchars($valor) : '—') . '</div>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Ficha do Aluno - SHIAI PRO</title>
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
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboard_v2.css?v=<?php echo time(); ?>">
    <style>
        .tab-btn-inner.active {
            background: #133080 !important;
            color: var(--primary-green) !important;
        }
        .tab-btn-inner:not(.active):hover {
            background: #eee;
        }
        .tab-content-aluno { display: none; }
        .tab-content-aluno.active { display: block; animation: fadeInSq 0.3s ease; }
        @keyframes fadeInSq { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>

<body>
    <section class="dashboard-section-sq" style="max-width: 1180px; margin: 40px auto; padding: 0 20px;">

        <div style="text-align: center; margin-bottom: 25px;">
            <img src="../assets/img/logo.png" alt="SHIAIPRO" style="height: 40px;">
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
            <a href="index.php" class="btn-sq-outline" style="width: auto; padding: 10px 20px; font-size: var(--fs-sm);">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
            <a href="logout.php" class="btn-sq-outline" style="width: auto; padding: 10px 20px; font-size: var(--fs-sm);">
                <i class="fa-solid fa-right-from-bracket" style="margin-right: 8px;"></i> Sair
            </a>
        </div>

        <!-- Cabeçalho da ficha -->
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 25px; background: #fff; padding: 30px; border: 1px solid var(--border-color); border-left: 10px solid var(--primary-green); flex-wrap: wrap;">
            <div style="width: 72px; height: 72px; border-radius: 50%; background: #133080; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.5rem; flex-shrink: 0; overflow: hidden;">
                <?php if ($foto_url): ?>
                    <img src="<?php echo htmlspecialchars($foto_url); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?php echo strtoupper(substr($aluno['nome_completo'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size: 1.2rem; font-weight: 900; text-transform: uppercase;"><?php echo htmlspecialchars($aluno['nome_completo']); ?></div>
                <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 4px; text-transform: uppercase;">
                    Faixa: <?php echo htmlspecialchars($aluno['faixa'] ?: 'Sem faixa'); ?>
                </div>
            </div>
            <span style="margin-left: auto; font-size: 10px; font-weight: 900; padding: 4px 10px; text-transform: uppercase; letter-spacing: 0.03em; background: <?php echo $aluno['status'] === 'ativo' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $aluno['status'] === 'ativo' ? '#166534' : '#991b1b'; ?>; height: fit-content;">
                <?php echo htmlspecialchars($aluno['status']); ?>
            </span>
        </div>

        <?php
            $mensalidades_abertas = array_filter($financeiro, fn($f) => ($f['status'] ?? '') !== 'pago');
            $exames_pendentes = array_filter($exames_faixa, fn($ef) => (float)($ef['taxa'] ?? 0) > 0 && ($ef['status_pagamento'] ?? '') !== 'pago');
        ?>
        <?php if (!empty($mensalidades_abertas) || !empty($exames_pendentes)): ?>
            <div style="background:#fff7ed; border:1px solid #fed7aa; border-left:6px solid #ea580c; padding:18px 20px; margin-bottom:25px; display:flex; flex-direction:column; gap:8px;">
                <?php if (!empty($mensalidades_abertas)): ?>
                    <div style="display:flex; align-items:center; gap:10px; font-size:var(--fs-sm); font-weight:800; color:#9a3412;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Você tem <?php echo count($mensalidades_abertas); ?> mensalidade<?php echo count($mensalidades_abertas) > 1 ? 's' : ''; ?> em aberto.
                        <a href="#" onclick="document.querySelector('[onclick*=financeiro]').click(); return false;" style="color:#9a3412; text-decoration:underline; font-weight:900;">Ver financeiro</a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($exames_pendentes)): ?>
                    <div style="display:flex; align-items:center; gap:10px; font-size:var(--fs-sm); font-weight:800; color:#9a3412;">
                        <i class="fa-solid fa-medal"></i>
                        Você tem <?php echo count($exames_pendentes); ?> taxa<?php echo count($exames_pendentes) > 1 ? 's' : ''; ?> de exame de faixa pendente<?php echo count($exames_pendentes) > 1 ? 's' : ''; ?>.
                        <a href="#" onclick="document.querySelector('[onclick*=exames_faixa]').click(); return false;" style="color:#9a3412; text-decoration:underline; font-weight:900;">Ver exames</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Abas -->
        <div style="display: flex; gap: 5px; margin-bottom: 25px; background: #fafafa; padding: 5px; border: 1px solid var(--border-color); flex-wrap: wrap;">
            <button class="tab-btn-inner active" onclick="openTab(event, 'dados')" style="flex: 1; min-width: 120px; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">DADOS</button>
            <button class="tab-btn-inner" onclick="openTab(event, 'turmas')" style="flex: 1; min-width: 120px; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">TURMAS</button>
            <button class="tab-btn-inner" onclick="openTab(event, 'financeiro')" style="flex: 1; min-width: 120px; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">FINANCEIRO</button>
            <button class="tab-btn-inner" onclick="openTab(event, 'competicoes')" style="flex: 1; min-width: 120px; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s;">COMPETIÇÕES</button>
            <button class="tab-btn-inner" onclick="openTab(event, 'exames_faixa')" style="flex: 1; min-width: 160px; padding: 15px; border: none; background: none; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fa-solid fa-medal" style="font-size:11px;"></i>EXAME DE FAIXAS</button>
        </div>

        <!-- TAB DADOS -->
        <div id="dados" class="tab-content-aluno active">
            <div class="dashboard-container" style="padding: 30px; margin-bottom: 25px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Dados Pessoais</h2>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
                    <?php
                        campo('CPF', $aluno['cpf']);
                        campo('RG', $aluno['rg']);
                        campo('Data de Nascimento', $aluno['data_nascimento'] ? date('d/m/Y', strtotime($aluno['data_nascimento'])) : '');
                        campo('Gênero', $aluno['genero']);
                    ?>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <?php
                        campo('Telefone', $aluno['telefone']);
                        campo('E-mail', $aluno['email_pessoal']);
                        campo('Unidade', $aluno['unidade_nome']);
                        campo('Academia', $aluno['academia_nome']);
                    ?>
                </div>
            </div>

            <div class="dashboard-container" style="padding: 30px; margin-bottom: 25px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Endereço</h2>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
                    <?php
                        campo('CEP', $aluno['cep']);
                        campo('Endereço', $aluno['endereco']);
                        campo('Número', $aluno['endereco_numero']);
                        campo('Complemento', $aluno['endereco_complemento']);
                    ?>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <?php
                        campo('Bairro', $aluno['bairro']);
                        campo('Cidade', $aluno['cidade']);
                        campo('Estado', $aluno['estado']);
                    ?>
                </div>
            </div>

            <?php if (!empty($aluno['responsavel_nome']) || !empty($aluno['responsavel_cpf'])): ?>
            <div class="dashboard-container" style="padding: 30px; margin-bottom: 25px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Responsável</h2>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
                    <?php
                        campo('Nome', $aluno['responsavel_nome']);
                        campo('Parentesco', $aluno['responsavel_parentesco']);
                        campo('CPF', $aluno['responsavel_cpf']);
                    ?>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <?php
                        campo('Telefone', $aluno['responsavel_telefone']);
                        campo('E-mail', $aluno['responsavel_email']);
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="dashboard-container" style="padding: 30px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Graduação</h2>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <?php
                        campo('Faixa Atual', $aluno['faixa']);
                        campo('Última Graduação', $aluno['data_graduacao'] ? date('d/m/Y', strtotime($aluno['data_graduacao'])) : '');
                    ?>
                </div>
            </div>
        </div>

        <!-- TAB TURMAS -->
        <div id="turmas" class="tab-content-aluno">
            <div class="dashboard-container" style="padding: 30px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Turmas Vinculadas</h2>
                </div>
                <?php if (empty($turmas_aluno)): ?>
                    <p style="color: var(--text-muted); font-size: var(--fs-sm); font-weight: 700;">Nenhuma turma vinculada.</p>
                <?php else: ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap:15px;">
                        <?php foreach ($turmas_aluno as $t): ?>
                            <div style="padding: 20px; background: #fafafa; border: 1px solid var(--border-color);">
                                <span style="font-size: var(--fs-sm); font-weight: 900; color: var(--text-dark); display: block; text-transform: uppercase;"><?php echo htmlspecialchars($t['nome']); ?></span>
                                <span style="font-size: var(--fs-xs); font-weight: 700; color: var(--primary-green);"><?php echo $t['horario'] ? date('H:i', strtotime($t['horario'])) : ''; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB FINANCEIRO -->
        <div id="financeiro" class="tab-content-aluno">
            <div class="dashboard-container" style="padding: 30px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Histórico Financeiro</h2>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #133080;">
                                <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Vencimento</th>
                                <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Valor</th>
                                <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Status</th>
                                <th style="padding: 15px 10px; text-align: right; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($financeiro)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:40px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Nenhum lançamento encontrado.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($financeiro as $f): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 800;"><?php echo date('d/m/y', strtotime($f['data_vencimento'])); ?></td>
                                    <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 900;">R$ <?php echo number_format($f['valor'], 2, ',', '.'); ?></td>
                                    <td style="padding: 15px 10px;">
                                        <span style="display: inline-block; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; background: <?php echo $f['status'] == 'pago' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $f['status'] == 'pago' ? '#166534' : '#991b1b'; ?>;">
                                            <?php echo htmlspecialchars($f['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px 10px; text-align: right;">
                                        <?php if ($f['status'] !== 'pago'): ?>
                                            <a href="pagar_pix.php?id=<?php echo (int)$f['id']; ?>" class="btn-sq" style="width: auto; padding: 8px 16px; font-size: var(--fs-xs); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                                <i class="fa-brands fa-pix"></i> PAGAR PIX
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB COMPETICOES -->
        <div id="competicoes" class="tab-content-aluno">
            <div class="dashboard-container" style="padding: 30px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Histórico de Competições</h2>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #133080;">
                                <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Evento</th>
                                <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Data</th>
                                <th style="padding: 15px 10px; text-align: left; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Categoria</th>
                                <th style="padding: 15px 10px; text-align: right; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-muted);">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($competicoes)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:40px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Nenhuma competição registrada.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($competicoes as $c): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 900; text-transform: uppercase;"><?php echo htmlspecialchars($c['comp_nome']); ?></td>
                                    <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 800; color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($c['data_evento'])); ?></td>
                                    <td style="padding: 15px 10px; font-size: var(--fs-sm); font-weight: 700; text-transform: uppercase;"><?php echo htmlspecialchars($c['categoria']); ?></td>
                                    <td style="padding: 15px 10px; text-align: right;">
                                        <span style="display: inline-block; padding: 4px 12px; font-size: var(--fs-xs); font-weight: 900; background: #133080; color: var(--primary-green); text-transform: uppercase;">
                                            <?php echo htmlspecialchars($c['resultado'] ?: 'Pendente'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB EXAMES DE FAIXA -->
        <div id="exames_faixa" class="tab-content-aluno">
            <div class="dashboard-container" style="padding: 30px;">
                <div style="border-left: 6px solid #6366f1; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1; color:#1e293b;">Exames de Faixa</h2>
                    <p style="font-size: var(--fs-xs); color: var(--text-muted); margin: 6px 0 0 0; font-weight:600;">Histórico de participações em exames de graduação.</p>
                </div>

                <?php if (empty($exames_faixa)): ?>
                    <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                        <i class="fa-solid fa-medal" style="font-size:3rem; opacity:0.1; display:block; margin-bottom:15px;"></i>
                        <p style="font-weight:800; text-transform:uppercase; font-size:var(--fs-sm);">Nenhum exame de faixa registrado.</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <?php foreach ($exames_faixa as $ef):
                            $taxa_val = (float)($ef['taxa'] ?? 0);
                            $res = $ef['resultado'] ?? '';
                        ?>
                            <div style="border:1px solid var(--border-color); border-radius:10px; padding:18px 20px; background:#fff;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
                                    <div>
                                        <div style="font-weight:900; font-size:var(--fs-sm); color:#1e293b;"><?php echo htmlspecialchars($ef['evento_titulo']); ?></div>
                                        <?php if ($ef['local']): ?>
                                            <div style="font-size:var(--fs-xs); color:var(--text-muted); margin-top:4px; font-weight:600;">
                                                <i class="fa-solid fa-location-dot" style="margin-right:4px;"></i><?php echo htmlspecialchars($ef['local']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align:right;">
                                        <?php
                                            if ($res === 'aprovado') {
                                                echo '<span style="display:inline-block; padding:4px 12px; background:#133080; color:#a3e635; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;"><i class="fa-solid fa-check" style="margin-right:5px;"></i>APROVADO</span>';
                                            } elseif ($res === 'reprovado') {
                                                echo '<span style="display:inline-block; padding:4px 12px; background:#fee2e2; color:#991b1b; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;"><i class="fa-solid fa-xmark" style="margin-right:5px;"></i>REPROVADO</span>';
                                            } else {
                                                echo '<span style="display:inline-block; padding:4px 12px; background:#f1f5f9; color:#64748b; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;">RESULTADO PENDENTE</span>';
                                            }
                                            if (($ef['nota'] ?? null) !== null) {
                                                echo '<div style="font-size:10px; font-weight:900; color:var(--text-muted); text-transform:uppercase; margin-top:5px;">Nota: <span style="color:#133080;">' . number_format($ef['nota'], 1, ',', '.') . '</span></div>';
                                            }
                                        ?>
                                    </div>
                                </div>

                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:14px; padding:14px 0; border-top:1px solid var(--border-color); border-bottom:1px solid var(--border-color); margin-bottom:14px;">
                                    <div>
                                        <div style="font-size:10px; font-weight:900; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Data</div>
                                        <div style="font-size:var(--fs-sm); font-weight:800;"><?php echo $ef['data_evento'] ? date('d/m/Y', strtotime($ef['data_evento'])) : '—'; ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size:10px; font-weight:900; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Graduação Atual</div>
                                        <div style="font-size:var(--fs-sm); font-weight:800; text-transform:uppercase;"><?php echo htmlspecialchars($ef['faixa_atual'] ?: $aluno['faixa'] ?: '—'); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size:10px; font-weight:900; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Faixa Pretendida</div>
                                        <div style="font-size:var(--fs-sm); font-weight:800; text-transform:uppercase; color:#4f46e5;"><?php echo htmlspecialchars($ef['faixa_pretendida'] ?: '—'); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size:10px; font-weight:900; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Tamanho</div>
                                        <div style="font-size:var(--fs-sm); font-weight:800;"><?php echo htmlspecialchars($ef['tamanho_faixa'] ?: '—'); ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size:10px; font-weight:900; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Taxa</div>
                                        <div style="font-size:var(--fs-sm); font-weight:800;"><?php echo $taxa_val > 0 ? 'R$ ' . number_format($taxa_val, 2, ',', '.') : '—'; ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size:10px; font-weight:900; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Status Pgto</div>
                                        <?php
                                            if ($taxa_val <= 0) {
                                                echo '<span style="font-size:var(--fs-xs); font-weight:700; color:var(--text-muted);">Isento</span>';
                                            } elseif (($ef['status_pagamento'] ?? '') === 'pago') {
                                                echo '<span style="display:inline-block; padding:4px 10px; background:#dcfce7; color:#166534; font-size:var(--fs-xs); font-weight:900;">PAGO</span>';
                                            } else {
                                                echo '<span style="display:inline-block; padding:4px 10px; background:#fee2e2; color:#991b1b; font-size:var(--fs-xs); font-weight:900;">PENDENTE</span>';
                                            }
                                        ?>
                                    </div>
                                </div>

                                <?php if ($taxa_val > 0 && ($ef['status_pagamento'] ?? '') !== 'pago'): ?>
                                    <a href="pagar_pix_exame.php?id=<?php echo (int)$ef['id']; ?>" class="btn-sq" style="width: auto; padding: 10px 18px; font-size: var(--fs-xs); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fa-brands fa-pix"></i> CONFIRMAR / PAGAR
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </section>

    <footer style="text-align: center; padding: 25px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600;">
        &copy; <?php echo date('Y'); ?> SHIAI PRO - Gestão Inteligente para Academias.
    </footer>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content-aluno");
            for (i = 0; i < tabcontent.length; i++) tabcontent[i].classList.remove("active");
            tablinks = document.getElementsByClassName("tab-btn-inner");
            for (i = 0; i < tablinks.length; i++) tablinks[i].classList.remove("active");
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>

</html>
