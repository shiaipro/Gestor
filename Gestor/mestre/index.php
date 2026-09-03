<?php
require_once '../config.php';

if (!ehMestre()) {
    header('Location: ../login.php');
    exit;
}

// Estatísticas
$total_unidades = $pdo->query("SELECT COUNT(*) FROM unidades")->fetchColumn();
$unidades = $pdo->query("SELECT id, nome FROM unidades LIMIT 5")->fetchAll();
$total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

include 'header.php';
?>

<section class="dashboard-section-sq">
    <h2 class="section-title-sq">Visão Geral Global</h2>

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px;">
        <!-- Total Academias -->
        <div class="stat-card-square">
            <div class="label">Total de Academias</div>
            <div class="value"><?php echo $total_unidades; ?></div>
            <div style="font-size: 12px; color: #22C55E; margin-top: 8px;"><i class="fa-solid fa-arrow-up"></i> 12% cresc.</div>
            <a href="unidades.php" class="link">Ver detalhes</a>
        </div>

        <!-- Receita -->
        <div class="stat-card-square">
            <div class="label">Receita Mensal</div>
            <div class="value">R$ 12.4K</div>
            <div style="font-size: 12px; color: #22C55E; margin-top: 8px;"><i class="fa-solid fa-arrow-up"></i> 8% este mês</div>
            <a href="faturas.php" class="link">Ver relatórios</a>
        </div>

        <!-- Planos -->
        <div class="stat-card-square">
            <div class="label">Planos Ativos</div>
            <div class="value">42</div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">Em 15 unidades</div>
            <a href="planos.php" class="link">Ver planos</a>
        </div>
    </div>

    <h2 class="section-title-sq">Faturas Recentes</h2>
    <div style="background: white; border: 1px solid var(--border-color); overflow: hidden;">
        <table class="table-sq">
            <thead>
                <tr>
                    <th>Academia</th>
                    <th>Vencimento</th>
                    <th>Valor</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unidades as $u): ?>
                <tr>
                    <td style="font-weight: 700;"><?php echo htmlspecialchars($u['nome']); ?></td>
                    <td>10/04/2024</td>
                    <td>R$ 299,00</td>
                    <td><span class="badge-sq" style="background: #DCFCE7; color: #15803D;">Pago</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($unidades)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: var(--text-muted);">Nenhuma unidade encontrada.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include 'footer.php'; ?>