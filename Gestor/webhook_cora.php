<?php
// Endpoint público chamado pela Cora quando o status de um invoice muda.
// Cadastrar esta URL no painel do Cora de cada unidade (via config_cora.php > POST /endpoints).
require_once 'config.php';

http_response_code(200); // Sempre responde 200 para a Cora não ficar reenviando o evento.

// A Cora manda o evento nos headers (ex: webhook-event-type: invoice.paid), corpo pode vir vazio.
$headers = function_exists('getallheaders') ? getallheaders() : [];
$headers = array_change_key_case($headers, CASE_LOWER);

$eventType = $headers['webhook-event-type'] ?? '';
$invoiceId = $headers['webhook-resource-id'] ?? null;

if (!$invoiceId || strpos($eventType, 'invoice.') !== 0 || strpos($eventType, 'paid') === false) {
    echo json_encode(['ok' => true]);
    exit;
}

try {
    // 1) Mensalidades
    $stmt = $pdo->prepare("SELECT * FROM mensalidades WHERE cora_invoice_id = ? LIMIT 1");
    $stmt->execute([$invoiceId]);
    $mensalidade = $stmt->fetch();

    if ($mensalidade && $mensalidade['status'] !== 'pago') {
        // Nunca confia cegamente no webhook: reconsulta o invoice direto na API da Cora
        // usando o certificado da própria unidade antes de dar baixa, evitando chamadas forjadas.
        $cora = getCoraClientParaUnidade($pdo, $mensalidade['unidade_id']);
        if ($cora) {
            $invoice_real = $cora->getInvoice($invoiceId);
            $status_real = $invoice_real['status'] ?? '';

            if ($status_real === 'PAID') {
                $pdo->prepare("UPDATE mensalidades SET status = 'pago', data_pagamento = CURDATE(), forma_pagamento = 'pix' WHERE id = ? AND unidade_id = ?")
                    ->execute([$mensalidade['id'], $mensalidade['unidade_id']]);
                registrarComissoesMensalidade($mensalidade['id'], $mensalidade['unidade_id']);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // 2) Taxas de exame de faixa
    $stmt = $pdo->prepare("SELECT * FROM eventos_graduacao_inscricoes WHERE cora_invoice_id = ? LIMIT 1");
    $stmt->execute([$invoiceId]);
    $inscricao = $stmt->fetch();

    if ($inscricao && $inscricao['status_pagamento'] !== 'pago') {
        $stmt_evento = $pdo->prepare("SELECT unidade_id FROM eventos_graduacao WHERE id = ?");
        $stmt_evento->execute([$inscricao['evento_id']]);
        $unidade_id_evento = $stmt_evento->fetchColumn();

        $cora = $unidade_id_evento ? getCoraClientParaUnidade($pdo, $unidade_id_evento) : null;
        if ($cora) {
            $invoice_real = $cora->getInvoice($invoiceId);
            $status_real = $invoice_real['status'] ?? '';

            if ($status_real === 'PAID') {
                $pdo->prepare("UPDATE eventos_graduacao_inscricoes SET status_pagamento = 'pago' WHERE id = ?")
                    ->execute([$inscricao['id']]);
            }
        }
    }

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    // Não expõe detalhes à Cora; loga silenciosamente e responde 200 do mesmo jeito.
    echo json_encode(['ok' => true]);
}
