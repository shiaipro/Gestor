<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
$evento_id = $_GET['id'] ?? 0;

if (!$evento_id) {
    die("Evento não encontrado.");
}

// Buscar o evento
$stmt = $pdo->prepare("SELECT eg.*, a.nome as academia_nome 
                       FROM eventos_graduacao eg 
                       LEFT JOIN academias a ON eg.academia_id = a.id 
                       WHERE eg.id = ? AND eg.unidade_id = ?");
$stmt->execute([$evento_id, $unidade_id]);
$evento = $stmt->fetch();

if (!$evento) {
    die("Acesso negado.");
}

garantirColunaTipoCadastroAluno($pdo);

// Buscar inscritos
$stmt_inscritos = $pdo->prepare("SELECT egi.*, a.nome_completo as aluno_nome, a.faixa as aluno_faixa, a.telefone, a.data_nascimento, a.tipo_cadastro,
                                       ac.nome as academia_nome,
                                       (SELECT GROUP_CONCAT(t.nome SEPARATOR ', ') FROM turma_alunos ta JOIN turmas t ON ta.turma_id = t.id WHERE ta.aluno_id = a.id) as turmas_nomes
                                FROM eventos_graduacao_inscricoes egi 
                                JOIN alunos a ON egi.aluno_id = a.id 
                                LEFT JOIN academias ac ON a.academia_id = ac.id
                                WHERE egi.evento_id = ? 
                                ORDER BY a.nome_completo ASC");
$stmt_inscritos->execute([$evento_id]);
$inscritos = $stmt_inscritos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lista de Exame - <?php echo htmlspecialchars($evento['titulo']); ?></title>
    <!-- FontAwesome required for the print icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #08153a;
            --success: #22c55e;
            --danger: #ef4444;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
            margin: 0;
            padding: 2.5rem;
            background: #fff;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .header h1 {
            margin: 0;
            font-size: 1.6rem;
            text-transform: uppercase;
            font-weight: 900;
            color: var(--primary);
            letter-spacing: -0.02em;
        }

        .header .info {
            text-align: right;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }

        .summary-box {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.2rem;
            background: #f8fafc;
            page-break-inside: avoid;
        }

        .summary-box h3 {
            margin: 0 0 0.5rem 0;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.05em;
        }

        .summary-box .value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--primary);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            margin-bottom: 3rem;
        }

        th {
            background: #f1f5f9;
            text-align: left;
            padding: 1rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border);
            font-weight: 800;
            letter-spacing: 0.05em;
        }

        td {
            padding: 1.1rem 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.95rem;
            vertical-align: middle;
        }

        .badge-status {
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-block;
        }
        .status-pago { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .status-pendente { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
            padding-top: 1.5rem;
        }

        @media print {
            body { padding: 0 !important; }
            .no-print { display: none !important; }
            @page { margin: 1.2cm; }
            .summary-box { border: 1px solid #ccc; background: transparent; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <div>
            <h1>Exame de Graduação</h1>
            <div style="font-weight: 800; font-size: 1.15rem; color: var(--text-muted); margin-top: 6px; text-transform: uppercase;">
                <?php echo htmlspecialchars($evento['titulo']); ?>
            </div>
        </div>
        <div class="info">
            <div style="font-size: 1.35rem; font-weight: 900; color: var(--primary);">
                <?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?>
            </div>
            <div style="margin-top: 6px; font-weight: 600;">
                Horário: <?php echo $evento['horario'] ? substr($evento['horario'], 0, 5) : '--:--'; ?><br>
                Gerado em: <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>
    </div>

    <!-- Metadados / Resumos rápidos -->
    <div class="summary-grid">
        <div class="summary-box">
            <h3>Total Inscritos</h3>
            <div class="value"><?php echo count($inscritos); ?> Alunos</div>
        </div>
        <div class="summary-box">
            <h3>Local do Exame</h3>
            <div class="value" style="font-size: 1.1rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($evento['local'] ?: 'Não definido'); ?></div>
        </div>
        <div class="summary-box">
            <h3>Academia</h3>
            <div class="value" style="font-size: 1.1rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($evento['academia_nome'] ?: 'Todas as academias'); ?></div>
        </div>
        <div class="summary-box">
            <h3>Status Evento</h3>
            <div class="value" style="font-size: 1.1rem; font-weight: 800; text-transform: uppercase; color: #133080;"><?php echo $evento['status']; ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">#</th>
                <th>Nome do Aluno</th>
                <th>Faixa Atual</th>
                <th>Nova Faixa / Graduação</th>
                <th style="width: 130px; text-align: center;">Pagamento</th>
                <th>Assinatura / Observações</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($inscritos as $insc): ?>
                <tr>
                    <td style="text-align: center; font-weight: 700; color: var(--text-muted);"><?php echo $i++; ?></td>
                    <td>
                        <strong style="font-size: 1.05rem; color: var(--text-main);"><?php echo strtoupper(htmlspecialchars($insc['aluno_nome'])); ?></strong>
                        <?php if (($insc['tipo_cadastro'] ?? 'fixo') === 'visitante'): ?>
                            <span style="background:#f59e0b; color:#fff; font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px; margin-left:6px;">VISITANTE</span>
                        <?php endif; ?>
                        <br>
                        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; margin-top: 4px; display: inline-block;">
                            Nasc: <?php echo $insc['data_nascimento'] ? date('d/m/Y', strtotime($insc['data_nascimento'])) : 'N/D'; ?> 
                            <?php if ($insc['telefone']): ?> | Tel: <?php echo htmlspecialchars($insc['telefone']); ?><?php endif; ?>
                            <?php if ($insc['academia_nome']): ?> | Local: <?php echo htmlspecialchars($insc['academia_nome']); ?><?php endif; ?>
                            <?php if ($insc['turmas_nomes']): ?> | Turma: <?php echo htmlspecialchars($insc['turmas_nomes']); ?><?php endif; ?>
                        </span>
                    </td>
                    <td style="font-weight: 600; color: var(--text-main); text-transform: uppercase;"><?php echo htmlspecialchars($insc['faixa_atual'] ?: $insc['aluno_faixa'] ?: '—'); ?></td>
                    <td>
                        <strong style="color: #133080; text-transform: uppercase;">
                            <?php 
                                $txt_pret = $insc['faixa_pretendida'] ?: '--';
                                if (!empty($insc['tamanho_faixa'])) {
                                    $txt_pret .= ' - TAM: ' . htmlspecialchars($insc['tamanho_faixa']);
                                }
                                echo $txt_pret;
                            ?>
                        </strong>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge-status <?php echo $insc['status_pagamento'] == 'pago' ? 'status-pago' : 'status-pendente'; ?>">
                            <?php echo $insc['status_pagamento'] == 'pago' ? 'Confirmado' : 'Pendente'; ?>
                        </span>
                    </td>
                    <td style="border-bottom: 1px solid #cbd5e1; width: 220px;"></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Gerado automaticamente pelo sistema SHIAI PRO em <?php echo date('d/m/Y H:i:s'); ?> • Assinatura do Professor Responsável: ___________________________</p>
    </div>

    <!-- Floating Print Button -->
    <div class="no-print" style="position: fixed; bottom: 2rem; right: 2rem;">
        <button onclick="window.print()"
            style="padding: 1rem 2rem; background: var(--primary); color: #fff; border: none; border-radius: 2rem; font-weight: 800; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.3); font-size: 0.9rem;">
            <i class="fa-solid fa-print" style="margin-right: 8px;"></i> IMPRIMIR RELATÓRIO
        </button>
    </div>

</body>
</html>
