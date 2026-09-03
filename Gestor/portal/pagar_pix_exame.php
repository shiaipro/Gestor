<?php
require_once '../config.php';

if (empty($_SESSION['portal_cpf'])) {
    header('Location: login.php');
    exit;
}

$cpf_sessao = $_SESSION['portal_cpf'];
$inscricao_id = $_GET['id'] ?? 0;

$erro = '';
$pix = null;
$inscricao = null;

try {
    // Ownership: a inscrição no exame precisa pertencer a um aluno vinculado ao CPF logado.
    $stmt = $pdo->prepare("
        SELECT ei.*,
               eg.titulo as evento_titulo, eg.descricao as evento_descricao, eg.data_evento, eg.horario as evento_horario,
               eg.local as evento_local, eg.status as evento_status, eg.inscricoes_inicio, eg.inscricoes_fim,
               eg.avaliacoes_inicio, eg.avaliacoes_fim, eg.unidade_id,
               a.id as aluno_id, a.nome_completo, a.cpf, a.responsavel_cpf, a.responsavel_nome, a.email_pessoal, a.responsavel_email
        FROM eventos_graduacao_inscricoes ei
        JOIN eventos_graduacao eg ON eg.id = ei.evento_id
        JOIN alunos a ON ei.aluno_id = a.id
        WHERE ei.id = ?
          AND (
                REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
                OR REPLACE(REPLACE(REPLACE(a.responsavel_cpf, '.', ''), '-', ''), ' ', '') = ?
              )
        LIMIT 1
    ");
    $stmt->execute([$inscricao_id, $cpf_sessao, $cpf_sessao]);
    $inscricao = $stmt->fetch();

    if (!$inscricao) {
        header('Location: index.php');
        exit;
    }

    if ($inscricao['status_pagamento'] !== 'pago' && (float)$inscricao['valor_pago'] > 0) {
        $pix = gerarPixParaInscricaoExame($pdo, $inscricao, $inscricao);
    }
} catch (Throwable $e) {
    $erro = 'Não foi possível gerar o PIX: ' . $e->getMessage();
}

function campoExame($label, $valor) {
    if ($valor === '' || $valor === null) return;
    echo '<div style="min-width: 150px; flex: 1;">';
    echo '<div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">' . htmlspecialchars($label) . '</div>';
    echo '<div style="font-size: var(--fs-sm); font-weight: 700; color: var(--text-dark);">' . htmlspecialchars($valor) . '</div>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Ficha do Exame - SHIAI PRO</title>
    <link rel="shortcut icon" href="/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboard_v2.css?v=<?php echo time(); ?>">
    <script src="../assets/js/qrcode.min.js"></script>
</head>

<body>
    <section class="dashboard-section-sq" style="max-width: 640px; margin: 40px auto; padding: 0 20px;">
        <div style="text-align: center; margin-bottom: 25px;">
            <img src="../assets/img/logo.png" alt="SHIAIPRO" style="height: 40px;">
        </div>
        <a href="<?php echo $inscricao ? 'ficha.php?id=' . (int)$inscricao['aluno_id'] : 'index.php'; ?>" class="btn-sq-outline" style="width: auto; padding: 10px 20px; font-size: var(--fs-sm); margin-bottom: 25px; display: inline-block;">
            <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
        </a>

        <?php if ($erro): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 15px 20px; font-weight: 700; font-size: var(--fs-sm); margin-bottom: 25px;">
                <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <?php if ($inscricao): ?>
            <!-- Ficha do Evento -->
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 25px; background: #fff; padding: 30px; border: 1px solid var(--border-color); border-left: 10px solid #4f46e5; flex-wrap: wrap;">
                <div style="width: 56px; height: 56px; background: #133080; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fa-solid fa-medal" style="color: #a3e635; font-size: 1.4rem;"></i>
                </div>
                <div>
                    <span style="font-size: var(--fs-xs); font-weight: 900; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 3px;">Exame de Faixas</span>
                    <div style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase;"><?php echo htmlspecialchars($inscricao['evento_titulo']); ?></div>
                    <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 4px;">
                        <?php echo $inscricao['data_evento'] ? date('d/m/Y', strtotime($inscricao['data_evento'])) : '—'; ?>
                        <?php if ($inscricao['evento_horario']): ?> às <?php echo date('H:i', strtotime($inscricao['evento_horario'])); ?><?php endif; ?>
                    </div>
                </div>
                <span style="margin-left: auto; font-size: 10px; font-weight: 900; padding: 4px 10px; text-transform: uppercase; letter-spacing: 0.03em; background: #f1f5f9; color: #64748b; height: fit-content;">
                    <?php echo htmlspecialchars($inscricao['evento_status'] ?: '—'); ?>
                </span>
            </div>

            <div class="dashboard-container" style="padding: 30px; margin-bottom: 25px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Dados do Evento</h2>
                </div>
                <?php if (!empty($inscricao['evento_descricao'])): ?>
                    <p style="font-size: var(--fs-sm); color: var(--text-dark); font-weight: 600; margin: 0 0 20px 0;"><?php echo nl2br(htmlspecialchars($inscricao['evento_descricao'])); ?></p>
                <?php endif; ?>
                <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
                    <?php
                        campoExame('Local', $inscricao['evento_local']);
                        campoExame('Inscrições', $inscricao['inscricoes_inicio'] ? date('d/m/Y', strtotime($inscricao['inscricoes_inicio'])) . ($inscricao['inscricoes_fim'] ? ' a ' . date('d/m/Y', strtotime($inscricao['inscricoes_fim'])) : '') : '');
                        campoExame('Avaliações', $inscricao['avaliacoes_inicio'] ? date('d/m/Y', strtotime($inscricao['avaliacoes_inicio'])) . ($inscricao['avaliacoes_fim'] ? ' a ' . date('d/m/Y', strtotime($inscricao['avaliacoes_fim'])) : '') : '');
                    ?>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <?php
                        campoExame('Faixa Atual', $inscricao['faixa_atual']);
                        campoExame('Faixa Pretendida', $inscricao['faixa_pretendida']);
                        campoExame('Tamanho da Faixa', $inscricao['tamanho_faixa']);
                    ?>
                </div>
            </div>

            <div class="dashboard-container" style="padding: 30px; margin-bottom: 25px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Avaliação</h2>
                </div>
                <?php
                    $res = $inscricao['resultado'] ?? '';
                    if ($res === 'aprovado') {
                        echo '<span style="display:inline-block; padding:6px 14px; background:#133080; color:#a3e635; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;"><i class="fa-solid fa-check" style="margin-right:5px;"></i>APROVADO</span>';
                    } elseif ($res === 'reprovado') {
                        echo '<span style="display:inline-block; padding:6px 14px; background:#fee2e2; color:#991b1b; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;"><i class="fa-solid fa-xmark" style="margin-right:5px;"></i>REPROVADO</span>';
                    } else {
                        echo '<span style="display:inline-block; padding:6px 14px; background:#f1f5f9; color:#64748b; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;">RESULTADO PENDENTE</span>';
                    }
                    if (($inscricao['nota'] ?? null) !== null) {
                        echo '<div style="font-size:var(--fs-xs); font-weight:900; color:var(--text-muted); text-transform:uppercase; margin-top:10px;">Nota: <span style="color:#133080; font-size:var(--fs-base);">' . number_format($inscricao['nota'], 1, ',', '.') . '</span></div>';
                    }
                ?>
            </div>

            <!-- Pagamento -->
            <div class="dashboard-container" style="padding: 35px; text-align: center;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px; text-align: left;">
                    <h2 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Pagamento da Taxa</h2>
                </div>

                <?php if ((float)$inscricao['valor_pago'] <= 0): ?>
                    <div style="padding: 20px 10px;">
                        <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: var(--primary-green);"></i>
                        <p style="font-weight: 900; text-transform: uppercase; margin-top: 15px;">Este exame é isento de taxa.</p>
                    </div>
                <?php elseif ($inscricao['status_pagamento'] === 'pago'): ?>
                    <div style="padding: 20px 10px;">
                        <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: var(--primary-green);"></i>
                        <p style="font-weight: 900; text-transform: uppercase; margin-top: 15px;">Taxa já paga. Inscrição confirmada!</p>
                    </div>
                <?php elseif (!$erro): ?>
                    <div style="font-size: 1.6rem; font-weight: 900; color: var(--text-dark); margin-bottom: 20px;">
                        R$ <?php echo number_format($inscricao['valor_pago'], 2, ',', '.'); ?>
                    </div>

                    <?php if (!empty($pix['qrcode'])): ?>
                        <img src="data:image/png;base64,<?php echo $pix['qrcode']; ?>" alt="QR Code PIX" style="width: 220px; height: 220px; margin: 0 auto 20px auto; display: block; border: 1px solid var(--border-color); padding: 10px;">
                    <?php else: ?>
                        <div id="qrcode-canvas" style="width: 220px; height: 220px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); padding: 10px;"></div>
                    <?php endif; ?>

                    <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; text-align: left;">PIX Copia e Cola</label>
                    <textarea id="pix-payload" readonly style="width: 100%; height: 90px; background: #fafafa; border: 1px solid var(--border-color); padding: 12px; font-size: 0.7rem; font-family: monospace; resize: none;"><?php echo htmlspecialchars($pix['payload']); ?></textarea>
                    <button type="button" class="btn-sq" style="width: 100%; margin-top: 15px;" onclick="copiarPix()">
                        <i class="fa-solid fa-copy" style="margin-right: 8px;"></i> Copiar Código
                    </button>

                    <?php if (!empty($pix['boleto_url'])): ?>
                        <a href="<?php echo htmlspecialchars($pix['boleto_url']); ?>" target="_blank" class="btn-sq-outline" style="width: 100%; margin-top: 10px; text-decoration: none;">
                            <i class="fa-solid fa-barcode" style="margin-right: 8px;"></i> Ver Boleto
                        </a>
                    <?php endif; ?>

                    <p style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600; margin-top: 20px;">
                        Assim que o pagamento for confirmado pelo banco, a inscrição é confirmada automaticamente — não é preciso avisar a academia.
                    </p>

                    <?php if (empty($pix['qrcode']) && !empty($pix['payload'])): ?>
                        <script>
                            new QRCode(document.getElementById('qrcode-canvas'), {
                                text: <?php echo json_encode($pix['payload']); ?>,
                                width: 200,
                                height: 200
                            });
                        </script>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <footer style="text-align: center; padding: 25px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600;">
        &copy; <?php echo date('Y'); ?> SHIAI PRO - Gestão Inteligente para Academias.
    </footer>

    <script>
        function copiarPix() {
            const el = document.getElementById('pix-payload');
            el.select();
            navigator.clipboard.writeText(el.value);
        }
    </script>
</body>

</html>
