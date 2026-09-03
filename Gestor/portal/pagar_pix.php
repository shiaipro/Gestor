<?php
require_once '../config.php';

if (empty($_SESSION['portal_cpf'])) {
    header('Location: login.php');
    exit;
}

$cpf_sessao = $_SESSION['portal_cpf'];
$mensalidade_id = $_GET['id'] ?? 0;

$erro = '';
$pix = null;
$mensalidade = null;

try {
    // Ownership: a mensalidade precisa pertencer a um aluno vinculado ao CPF logado.
    $stmt = $pdo->prepare("
        SELECT m.*, a.nome_completo, a.cpf, a.responsavel_cpf, a.responsavel_nome, a.email_pessoal, a.responsavel_email, a.unidade_id
        FROM mensalidades m
        JOIN alunos a ON m.aluno_id = a.id
        WHERE m.id = ?
          AND (
                REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
                OR REPLACE(REPLACE(REPLACE(a.responsavel_cpf, '.', ''), '-', ''), ' ', '') = ?
              )
        LIMIT 1
    ");
    $stmt->execute([$mensalidade_id, $cpf_sessao, $cpf_sessao]);
    $mensalidade = $stmt->fetch();

    if (!$mensalidade) {
        header('Location: index.php');
        exit;
    }

    if ($mensalidade['status'] !== 'pago') {
        $pix = gerarPixParaMensalidade($pdo, $mensalidade, $mensalidade);
    }
} catch (Throwable $e) {
    $erro = 'Não foi possível gerar o PIX: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Pagar com PIX - SHIAI PRO</title>
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
    <section class="dashboard-section-sq" style="max-width: 480px; margin: 40px auto; padding: 0 20px;">
        <div style="text-align: center; margin-bottom: 25px;">
            <img src="../assets/img/logo.png" alt="SHIAIPRO" style="height: 40px;">
        </div>
        <a href="<?php echo $mensalidade ? 'ficha.php?id=' . (int)$mensalidade['aluno_id'] : 'index.php'; ?>" class="btn-sq-outline" style="width: auto; padding: 10px 20px; font-size: var(--fs-sm); margin-bottom: 25px; display: inline-block;">
            <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
        </a>

        <div class="dashboard-container" style="padding: 35px; text-align: center;">
            <h1 style="font-size: 1rem; font-weight: 900; text-transform: uppercase; margin: 0 0 5px 0;">Pagamento via PIX</h1>
            <?php if ($mensalidade): ?>
                <p style="font-size: var(--fs-sm); color: var(--text-muted); font-weight: 700; margin: 0 0 25px 0;">
                    <?php echo htmlspecialchars($mensalidade['nome_completo']); ?> • Venc. <?php echo date('d/m/Y', strtotime($mensalidade['data_vencimento'])); ?>
                </p>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 15px 20px; font-weight: 700; font-size: var(--fs-sm);">
                    <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php elseif ($mensalidade['status'] === 'pago'): ?>
                <div style="padding: 30px 10px;">
                    <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: var(--primary-green);"></i>
                    <p style="font-weight: 900; text-transform: uppercase; margin-top: 15px;">Esta mensalidade já está paga.</p>
                </div>
            <?php else: ?>
                <div style="font-size: 1.6rem; font-weight: 900; color: var(--text-dark); margin-bottom: 20px;">
                    R$ <?php echo number_format($mensalidade['valor'], 2, ',', '.'); ?>
                </div>

                <?php if (!empty($pix['qrcode'])): ?>
                    <img src="data:image/png;base64,<?php echo $pix['qrcode']; ?>" alt="QR Code PIX" style="width: 220px; height: 220px; margin: 0 auto 20px auto; display: block; border: 1px solid var(--border-color); padding: 10px;">
                <?php else: ?>
                    <div id="qrcode-canvas" style="width: 220px; height: 220px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); padding: 10px;"></div>
                <?php endif; ?>

                <label style="display: block; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">PIX Copia e Cola</label>
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
                    Assim que o pagamento for confirmado pelo banco, a baixa é feita automaticamente — não é preciso avisar a academia.
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
