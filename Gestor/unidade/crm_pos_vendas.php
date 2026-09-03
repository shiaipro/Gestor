<?php
require_once '../config.php';
include 'header.php';

$unidade_id = getUnidadeId();

// 1. Auto-Migração para CRM Pós-Vendas
try {
    $pdo->query("SELECT 1 FROM comercial_pos_vendas LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS comercial_pos_vendas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        aluno_id INT NOT NULL,
        data_contato DATE NOT NULL,
        proximo_contato DATE,
        feedback TEXT,
        status ENUM('satisfeito', 'neutro', 'insatisfeito', 'em_risco') DEFAULT 'satisfeito',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
        FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

// Processar Exclusão
if (isset($_POST['excluir_id'])) {
    $stmt = $pdo->prepare("DELETE FROM comercial_pos_vendas WHERE id = ? AND unidade_id = ?");
    $stmt->execute([$_POST['excluir_id'], $unidade_id]);
    $mensagem = "Registro de pós-venda removido!";
}

// Buscar Registros de Pós-Vendas com dados do Aluno
$stmt = $pdo->prepare("
    SELECT pv.*, a.nome_completo as aluno_nome, a.foto as aluno_foto 
    FROM comercial_pos_vendas pv
    JOIN alunos a ON pv.aluno_id = a.id
    WHERE pv.unidade_id = ? 
    ORDER BY pv.data_contato DESC
");
$stmt->execute([$unidade_id]);
$registros = $stmt->fetchAll();
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Pós-Venda & Retenção
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Monitore a satisfação dos alunos e realize o acompanhamento proativo.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="relatorio_satisfacao.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-chart-line" style="margin-right: 8px;"></i> Relatórios
            </a>
            <a href="novo_pos_venda.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-heart-circle-plus" style="margin-right: 8px;"></i> Novo Registro
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="comercial_dashboard.php" class="tab-item-sq">Início</a>
    <a href="crm_vendas.php" class="tab-item-sq">Vendas</a>
    <a href="crm_pos_vendas.php" class="tab-item-sq active">Pós Vendas</a>
    <a href="marketing_leads.php" class="tab-item-sq">CRM</a>
    <a href="mensagens.php" class="tab-item-sq">Mensagens</a>
    <a href="marketing_campanhas.php?menu=comercial" class="tab-item-sq">Funis de Vendas</a>
</div>

    

    <?php if (isset($mensagem)): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid #166534; margin-bottom: 30px; font-weight: 700; font-size: var(--fs-base); display: flex; align-items: center; gap: 12px;">
            <i class="fa-solid fa-circle-check"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Aluno / Identificação</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Último Contato</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Índice Satisfação</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.1em;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $r):
                    $cor_status = [
                        'satisfeito' => 'var(--primary-green)',
                        'neutro' => '#f59e0b',
                        'insatisfeito' => '#ef4444',
                        'em_risco' => '#08153a'
                    ][$r['status']];
                    ?>
                    <tr>
                        <td style="padding: 20px 30px;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div style="width: 45px; height: 45px; background: #08153a; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: var(--fs-sm); border: 1px solid #08153a;">
                                    <?php echo strtoupper(substr($r['aluno_nome'], 0, 1) . substr(explode(' ', $r['aluno_nome'])[1] ?? '', 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;"><?php echo htmlspecialchars($r['aluno_nome']); ?></div>
                                    <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700;">ALUNO ATIVO</div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-sm);">
                                <?php echo date('d/m/Y', strtotime($r['data_contato'])); ?>
                            </div>
                            <?php if ($r['proximo_contato']): ?>
                                <div style="font-size: var(--fs-xs); color: #1d4ed8; font-weight: 700; text-transform: uppercase; margin-top: 3px;">
                                    RETORNO: <?php echo date('d/m/Y', strtotime($r['proximo_contato'])); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 20px 30px;">
                            <span style="background: <?php echo $cor_status; ?>; color: #fff; padding: 5px 12px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">
                                <?php echo str_replace('_', ' ', $r['status']); ?>
                            </span>
                        </td>
                        <td style="padding: 20px 30px; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                <a href="editar_pos_venda.php?id=<?php echo $r['id']; ?>" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-edit"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('ATENÇÃO: Excluir este registro permanentemente?')">
                                    <input type="hidden" name="excluir_id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; color: #ef4444;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($registros)): ?>
                    <tr><td colspan="4" style="padding: 80px; text-align: center; color: var(--text-muted); font-weight: 600;">NENHUM REGISTRO DE PÓS-VENDA.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</section>

<?php include 'footer.php'; ?>