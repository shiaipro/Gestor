<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('financeiro_visualizar')) {
    echo "Acesso negado.";
    exit;
}

$id = $_GET['id'] ?? null;
$origem = $_GET['origem'] ?? null;

if (!$id || !$origem) {
    echo "Parâmetros inválidos.";
    exit;
}

$recibo = null;

if ($origem === 'mensalidade') {
    $stmt = $pdo->prepare("
        SELECT m.*, a.nome_completo as cliente_nome, a.cpf as cliente_doc, a.telefone as cliente_tel, 
               u.razao_social as empresa_nome, u.logo as empresa_logo,
               'Pagamento de Mensalidade' as descricao_item
        FROM mensalidades m 
        JOIN alunos a ON m.aluno_id = a.id 
        JOIN unidades u ON m.unidade_id = u.id 
        WHERE m.id = ? AND m.unidade_id = ?
    ");
    $stmt->execute([$id, $unidade_id]);
    $recibo = $stmt->fetch();
} elseif ($origem === 'lancamento') {
    $stmt = $pdo->prepare("
        SELECT l.*, l.descricao as descricao_item, 
               u.razao_social as empresa_nome, u.logo as empresa_logo
        FROM financeiro_lancamentos l 
        JOIN unidades u ON l.unidade_id = u.id 
        WHERE l.id = ? AND l.unidade_id = ?
    ");
    $stmt->execute([$id, $unidade_id]);
    $recibo = $stmt->fetch();
    if ($recibo) {
        $recibo['cliente_nome'] = 'Consumidor Final';
        $recibo['cliente_doc'] = '';
        $recibo['cliente_tel'] = '';
    }
}

if (!$recibo || $recibo['status'] !== 'pago') {
    echo "Recibo não encontrado ou lançamento não está pago.";
    exit;
}

$valor_formatado = number_format($recibo['valor'], 2, ',', '.');
$data_pagamento = date('d/m/Y', strtotime($recibo['data_pagamento']));
$empresa_nome = htmlspecialchars($recibo['empresa_nome'] ?: 'Nossa Empresa');
$cliente_nome = htmlspecialchars($recibo['cliente_nome'] ?: 'Consumidor Final');

// Preparar mensagem para o WhatsApp
$wa_text = urlencode("Olá $cliente_nome, segue o recibo referente a {$recibo['descricao_item']} no valor de R$ $valor_formatado pago em $data_pagamento. Agradecemos a preferência! - $empresa_nome");
$wa_link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $recibo['cliente_tel']) . "?text=" . $wa_text;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo - <?php echo str_pad($id, 5, '0', STR_PAD_LEFT); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; margin: 0; padding: 20px; color: #0f172a; }
        .receipt-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); border-top: 8px solid #08153a; }
        .receipt-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; }
        .receipt-header img { max-height: 60px; }
        .receipt-title { font-size: 24px; font-weight: 900; color: #08153a; text-transform: uppercase; margin: 0; }
        .receipt-subtitle { font-size: 14px; color: #64748b; font-weight: 500; margin: 5px 0 0 0; }
        
        .receipt-body { margin-bottom: 40px; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 15px; }
        .receipt-label { font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 12px; }
        .receipt-value { font-weight: 700; color: #0f172a; }
        
        .receipt-total-box { background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; text-align: center; margin-bottom: 40px; }
        .receipt-total-label { font-size: 14px; font-weight: 900; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .receipt-total-value { font-size: 36px; font-weight: 900; color: #16a34a; }
        
        .receipt-footer { text-align: center; font-size: 13px; color: #94a3b8; font-weight: 500; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        
        .action-buttons { max-width: 800px; margin: 20px auto; display: flex; gap: 15px; justify-content: center; }
        .btn-action { display: inline-flex; align-items: center; gap: 8px; padding: 12px 25px; font-size: 14px; font-weight: 900; text-transform: uppercase; border-radius: 5px; cursor: pointer; text-decoration: none; border: none; transition: opacity 0.2s; }
        .btn-print { background: #08153a; color: #fff; }
        .btn-whatsapp { background: #25D366; color: #fff; }
        .btn-action:hover { opacity: 0.9; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .receipt-container { box-shadow: none; border-top: none; padding: 20px 0; }
            .action-buttons { display: none; }
        }
    </style>
</head>
<body>

    <div class="action-buttons">
        <button onclick="window.print()" class="btn-action btn-print"><i class="fa-solid fa-print"></i> Imprimir / Gerar PDF</button>
        <?php if (!empty($recibo['cliente_tel'])): ?>
            <a href="<?php echo $wa_link; ?>" target="_blank" class="btn-action btn-whatsapp"><i class="fa-brands fa-whatsapp"></i> Enviar WhatsApp</a>
        <?php endif; ?>
    </div>

    <div class="receipt-container">
        <div class="receipt-header">
            <div>
                <?php if ($recibo['empresa_logo']): ?>
                    <img src="<?php echo getUploadURL('logos', $recibo['empresa_logo']); ?>" alt="Logo">
                <?php else: ?>
                    <div style="font-size: 20px; font-weight: 900; color: #08153a; text-transform: uppercase;"><?php echo $empresa_nome; ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align: right;">
                <h1 class="receipt-title">Recibo</h1>
                <p class="receipt-subtitle">Nº <?php echo str_pad($id, 5, '0', STR_PAD_LEFT); ?>-<?php echo strtoupper(substr($origem, 0, 3)); ?></p>
            </div>
        </div>
        
        <div class="receipt-body">
            <div class="receipt-row">
                <div>
                    <div class="receipt-label">Recebemos de</div>
                    <div class="receipt-value"><?php echo $cliente_nome; ?></div>
                    <?php if (!empty($recibo['cliente_doc'])): ?>
                    <div style="font-size: 12px; color: #64748b; margin-top: 3px;">CPF: <?php echo htmlspecialchars($recibo['cliente_doc']); ?></div>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <div class="receipt-label">Data de Pagamento</div>
                    <div class="receipt-value"><?php echo $data_pagamento; ?></div>
                </div>
            </div>
            
            <hr style="border: 0; border-top: 1px dashed #cbd5e1; margin: 20px 0;">
            
            <div class="receipt-row">
                <div>
                    <div class="receipt-label">Referente a</div>
                    <div class="receipt-value" style="font-size: 16px;"><?php echo htmlspecialchars($recibo['descricao_item']); ?></div>
                </div>
                <div style="text-align: right;">
                    <div class="receipt-label">Forma de Pagamento</div>
                    <div class="receipt-value" style="text-transform: uppercase;"><?php echo htmlspecialchars($recibo['forma_pagamento'] ?? 'Dinheiro'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="receipt-total-box">
            <div class="receipt-total-label">A Importância de</div>
            <div class="receipt-total-value">R$ <?php echo $valor_formatado; ?></div>
        </div>
        
        <div class="receipt-footer">
            Para maior clareza, firmamos o presente recibo.<br>
            <strong><?php echo $empresa_nome; ?></strong>
        </div>
    </div>

</body>
</html>
