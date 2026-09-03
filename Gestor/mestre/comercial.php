<?php
require_once '../config.php';

// Processar Exclusão de Lead
if (isset($_POST['excluir_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM comercial_leads WHERE id = ?");
        $stmt->execute([$_POST['excluir_id']]);
        $mensagem = "Lead comercial removido com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao remover lead: " . $e->getMessage();
    }
}

// Processar Alteração de Status
if (isset($_POST['alterar_status_id']) && isset($_POST['novo_status'])) {
    try {
        $stmt = $pdo->prepare("UPDATE comercial_leads SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['novo_status'], $_POST['alterar_status_id']]);
        $mensagem = "Status do lead atualizado com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao atualizar status: " . $e->getMessage();
    }
}

// Buscar Todos os Leads com informações de Unidade
try {
    $stmt = $pdo->prepare("
        SELECT cl.*, un.nome as unidade_nome 
        FROM comercial_leads cl 
        LEFT JOIN unidades un ON cl.unidade_id = un.id 
        ORDER BY cl.criado_em DESC
    ");
    $stmt->execute();
    $leads = $stmt->fetchAll();
} catch (PDOException $e) {
    $leads = [];
    $erro = "Erro ao buscar leads: " . $e->getMessage();
}

// Contagens de Leads
$contagem = [
    'novo' => 0,
    'contatado' => 0,
    'interessado' => 0
];
foreach ($leads as $l) {
    $status = $l['status'] ?? 'novo';
    if (isset($contagem[$status])) {
        $contagem[$status]++;
    } else {
        $contagem[$status] = 1;
    }
}

$custom_title = "CRM Central";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                CRM Central & Expansão
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Visualize e gerencie todos os leads do pré-lançamento e de todas as unidades ativas da Shiai Pro.
            </p>
        </div>
    </div>

    <!-- Abas de Navegação -->
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="comercial.php" class="tab-item-sq active">Leads Registrados</a>
    </div>

    <!-- Feedbacks -->
    <?php if (isset($mensagem)): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid var(--primary-green); margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($erro)): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px 25px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <!-- Resumo dos Status -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card-square" style="padding: 25px; border-bottom: 4px solid #3b82f6;">
            <div style="color: #3b82f6; font-size: var(--fs-base); margin-bottom: 12px;">
                <i class="fa-solid fa-user-tag"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo $contagem['novo'] ?? 0; ?></div>
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-top: 12px; letter-spacing: 0.1em;">
                Novos Leads / Triagem
            </div>
        </div>

        <div class="stat-card-square" style="padding: 25px; border-bottom: 4px solid #f59e0b;">
            <div style="color: #f59e0b; font-size: var(--fs-base); margin-bottom: 12px;">
                <i class="fa-solid fa-comments"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo $contagem['contatado'] ?? 0; ?></div>
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-top: 12px; letter-spacing: 0.1em;">
                Em Contato
            </div>
        </div>

        <div class="stat-card-square" style="padding: 25px; border-bottom: 4px solid var(--primary-green);">
            <div style="color: var(--primary-green); font-size: var(--fs-base); margin-bottom: 12px;">
                <i class="fa-solid fa-face-smile"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo $contagem['interessado'] ?? 0; ?></div>
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-top: 12px; letter-spacing: 0.1em;">
                Muito Interessados
            </div>
        </div>

        <div class="stat-card-square" style="padding: 25px; border-bottom: 4px solid #8b5cf6;">
            <div style="color: #8b5cf6; font-size: var(--fs-base); margin-bottom: 12px;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo count($leads); ?></div>
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-top: 12px; letter-spacing: 0.1em;">
                Total Captado
            </div>
        </div>
    </div>

    <!-- Tabela de Leads -->
    <div class="dashboard-container" style="padding: 0; overflow: hidden; background: #fff; box-shadow: var(--shadow-sm);">
        <table class="table-sq" style="width: 100%;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border);">
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Lead / Academia</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Origem / Canal</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Notas de Interesse</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.05em;">Status / Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $l): 
                    $origem = $l['origem'] ?: 'DIRETO';
                    $status = $l['status'] ?? 'novo';
                    
                    // Configuração de cores para badges de status
                    $status_colors = [
                        'novo' => '#3b82f6',
                        'contatado' => '#f59e0b',
                        'interessado' => 'var(--primary-green)'
                    ];
                    $cor_badge = $status_colors[$status] ?? '#6b7280';
                    ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <!-- Informações Básicas -->
                        <td style="padding: 20px 30px; vertical-align: top;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: 15px; text-transform: uppercase;">
                                <?php echo htmlspecialchars($l['nome']); ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 5px;">
                                <i class="fa-solid fa-envelope" style="margin-right: 5px; opacity: 0.6;"></i> <?php echo htmlspecialchars($l['email']); ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 3px;">
                                <i class="fa-solid fa-phone" style="margin-right: 5px; opacity: 0.6;"></i> <?php echo htmlspecialchars($l['telefone']); ?>
                            </div>
                            <?php if ($l['unidade_nome']): ?>
                                <div style="font-size: 11px; color: var(--brand); font-weight: 800; text-transform: uppercase; margin-top: 8px;">
                                    <i class="fa-solid fa-building" style="margin-right: 4px;"></i> Unidade: <?php echo htmlspecialchars($l['unidade_nome']); ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Origem -->
                        <td style="padding: 20px 30px; vertical-align: top;">
                            <span style="background: #1f2937; color: #fff; padding: 4px 10px; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-radius: 2px;">
                                <?php echo htmlspecialchars($origem); ?>
                            </span>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500; margin-top: 8px;">
                                <?php echo date('d/m/Y H:i', strtotime($l['criado_em'])); ?>
                            </div>
                        </td>

                        <!-- Notas e Metadados -->
                        <td style="padding: 20px 30px; vertical-align: top; max-width: 320px;">
                            <p style="font-size: 13px; color: var(--text-dark); line-height: 1.5; font-weight: 600; margin: 0; white-space: pre-wrap;">
                                <?php echo htmlspecialchars($l['notas'] ?: 'Nenhuma observação informada.'); ?>
                            </p>
                        </td>

                        <!-- Alteração de Status e Exclusão -->
                        <td style="padding: 20px 30px; vertical-align: top;">
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <!-- Status atual -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $cor_badge; ?>;"></div>
                                    <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.05em;">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>

                                <!-- Form para alterar status -->
                                <form method="POST" style="display: flex; gap: 6px; align-items: center;">
                                    <input type="hidden" name="alterar_status_id" value="<?php echo $l['id']; ?>">
                                    <select name="novo_status" onchange="this.form.submit()" style="height: 32px; font-size: 11px; font-weight: 700; color: var(--text-dark); border: 1px solid var(--border-color); background: #fafafa; padding: 0 8px; outline: none; border-radius: 0;">
                                        <option value="" disabled selected>Alterar status...</option>
                                        <option value="novo">Novo Lead</option>
                                        <option value="contatado">Em Contato</option>
                                        <option value="interessado">Muito Interessado</option>
                                    </select>
                                </form>

                                <!-- Botão Excluir -->
                                <form method="POST" onsubmit="return confirm('Excluir este lead permanentemente do CRM?')">
                                    <input type="hidden" name="excluir_id" value="<?php echo $l['id']; ?>">
                                    <button type="submit" class="btn-sq-light" style="width: 100%; height: 32px; font-size: 11px; color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); background: transparent; display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 700;">
                                        <i class="fa-solid fa-trash-can"></i> Excluir Registro
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="4" style="padding: 100px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            <i class="fa-solid fa-users-viewfinder" style="font-size: 48px; opacity: 0.1; display: block; margin-bottom: 20px;"></i>
                            Nenhum lead registrado no CRM Central.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include 'footer.php'; ?>
