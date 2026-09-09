<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('administrativo')) {
    header('Location: index.php?erro=sem_permissao');
    exit;
}

// Estatísticas de Backoffice
$stmt_equipe = $pdo->prepare("SELECT COUNT(*) FROM unidade_equipe WHERE unidade_id = ? AND status = 'ativo'");
$stmt_equipe->execute([$unidade_id]);
$total_equipe = $stmt_equipe->fetchColumn() ?: 0;

$stmt_planos = $pdo->prepare("SELECT COUNT(*) FROM planos WHERE unidade_id = ? AND ativo = 1");
$stmt_planos->execute([$unidade_id]);
$total_planos = $stmt_planos->fetchColumn() ?: 0;

$stmt_academias = $pdo->prepare("SELECT COUNT(*) FROM academias WHERE unidade_id = ? AND status = 'ativo'");
$stmt_academias->execute([$unidade_id]);
$total_academias = $stmt_academias->fetchColumn() ?: 0;

// Alunos ativos (exclui visitantes)
$stmt_alunos = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE unidade_id = ? AND status = 'ativo' AND tipo_cadastro != 'visitante'");
$stmt_alunos->execute([$unidade_id]);
$total_alunos = $stmt_alunos->fetchColumn() ?: 0;

// Turmas ativas
$stmt_turmas = $pdo->prepare("SELECT COUNT(*) FROM turmas WHERE unidade_id = ? AND status = 'ativo'");
$stmt_turmas->execute([$unidade_id]);
$total_turmas = $stmt_turmas->fetchColumn() ?: 0;

// Receita do mês (mensalidades + lançamentos avulsos pagos)
$mes_atual = date('n');
$ano_atual = date('Y');
$stmt_receita = $pdo->prepare("
    SELECT
        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? AND status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos WHERE unidade_id = ? AND status = 'pago' AND tipo = 'receita' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) as total
");
$stmt_receita->execute([$unidade_id, $mes_atual, $ano_atual, $unidade_id, $mes_atual, $ano_atual]);
$receita_mes = $stmt_receita->fetchColumn() ?: 0;

// Inadimplência (mensalidades vencidas/atrasadas de alunos ativos)
$stmt_inadimplencia = $pdo->prepare("
    SELECT COUNT(*) FROM mensalidades
    WHERE unidade_id = ? AND (status = 'atrasado' OR (status = 'pendente' AND data_vencimento < CURDATE()))
    AND aluno_id IN (SELECT id FROM alunos WHERE unidade_id = ? AND status != 'inativo')
");
$stmt_inadimplencia->execute([$unidade_id, $unidade_id]);
$total_inadimplentes = $stmt_inadimplencia->fetchColumn() ?: 0;

// Aniversariantes do mês
$stmt_aniversariantes = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE unidade_id = ? AND status = 'ativo' AND MONTH(data_nascimento) = ?");
$stmt_aniversariantes->execute([$unidade_id, $mes_atual]);
$total_aniversariantes = $stmt_aniversariantes->fetchColumn() ?: 0;

// Receita dos últimos 6 meses (para o gráfico de barras)
$stmt_receita_hist = $pdo->prepare("
    SELECT
        (SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE unidade_id = ? AND status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) +
        (SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos WHERE unidade_id = ? AND status = 'pago' AND tipo = 'receita' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?) as total
");
$historico_receita = [];
for ($i = 5; $i >= 0; $i--) {
    $ref = strtotime("-$i months", strtotime("$ano_atual-$mes_atual-01"));
    $m = (int) date('n', $ref);
    $a = (int) date('Y', $ref);
    $stmt_receita_hist->execute([$unidade_id, $m, $a, $unidade_id, $m, $a]);
    $historico_receita[] = ['mes' => $ref, 'valor' => (float) $stmt_receita_hist->fetchColumn()];
}
$maior_receita_historico = max(1, ...array_column($historico_receita, 'valor'));

// Distribuição de alunos ativos por faixa
$stmt_faixas = $pdo->prepare("
    SELECT COALESCE(NULLIF(faixa, ''), 'Não definida') as faixa, COUNT(*) as total
    FROM alunos
    WHERE unidade_id = ? AND status = 'ativo' AND tipo_cadastro != 'visitante'
    GROUP BY faixa
    ORDER BY total DESC
");
$stmt_faixas->execute([$unidade_id]);
$distribuicao_faixas = $stmt_faixas->fetchAll();
$maior_faixa = max(1, ...array_column($distribuicao_faixas, 'total'));

// Top inadimplentes (mensalidades em atraso, alunos ativos)
$stmt_top_inadimplentes = $pdo->prepare("
    SELECT a.id, a.nome_completo, a.faixa, m.valor, m.data_vencimento
    FROM mensalidades m
    INNER JOIN alunos a ON a.id = m.aluno_id
    WHERE m.unidade_id = ? AND a.status != 'inativo'
    AND (m.status = 'atrasado' OR (m.status = 'pendente' AND m.data_vencimento < CURDATE()))
    ORDER BY m.data_vencimento ASC
    LIMIT 6
");
$stmt_top_inadimplentes->execute([$unidade_id]);
$top_inadimplentes = $stmt_top_inadimplentes->fetchAll();

$progresso = 0;
$onboarding_completo = true;

$custom_title = "Administrativo - SHIAIPRO";
include 'header.php';
?>

<style>
    @media (max-width: 992px) {
        .cards-admin-grid { grid-template-columns: repeat(3, 1fr) !important; }
    }
    @media (max-width: 600px) {
        .cards-admin-grid { grid-template-columns: repeat(2, 1fr) !important; }
    }
</style>

<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Gestão Administrativa
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Configurações de estrutura e RH
            </p>
        </div>

    
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar Operações
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="administrativo_dashboard.php" class="tab-item-sq active">Dashboard</a>
        <a href="alunos.php" class="tab-item-sq">Alunos</a>
        <a href="equipe.php" class="tab-item-sq">RH</a>
        <a href="academias.php" class="tab-item-sq">Unidades</a>
        <a href="turmas.php" class="tab-item-sq">Turmas</a>
        <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
        <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
    </div>

    <!-- Cards de Estrutura -->
    <div class="cards-admin-grid" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 14px; margin-bottom: 26px;">
        <?php
        $cards = [
            [
                'label' => 'Alunos Ativos', 'valor' => $total_alunos, 'icone' => 'fa-user-group',
                'cor' => '#2563eb', 'bg' => '#eff6ff', 'link' => 'alunos.php', 'acao' => 'Ver alunos',
            ],
            [
                'label' => 'Corpo Docente', 'valor' => $total_equipe, 'icone' => 'fa-chalkboard-user',
                'cor' => '#7c3aed', 'bg' => '#f5f3ff', 'link' => 'equipe.php', 'acao' => 'Gerenciar RH',
            ],
            [
                'label' => 'Turmas Ativas', 'valor' => $total_turmas, 'icone' => 'fa-people-group',
                'cor' => '#0891b2', 'bg' => '#ecfeff', 'link' => 'turmas.php', 'acao' => 'Ver turmas',
            ],
            [
                'label' => 'Unidades Ativas', 'valor' => $total_academias, 'icone' => 'fa-building',
                'cor' => '#08153a', 'bg' => '#f1f5f9', 'link' => 'academias.php', 'acao' => 'Configurar pontos',
            ],
            [
                'label' => 'Planos Ativos', 'valor' => $total_planos, 'icone' => 'fa-tags',
                'cor' => '#15803d', 'bg' => '#f0fdf4', 'link' => 'planejamento.php', 'acao' => 'Ver planos',
            ],
            [
                'label' => 'Inadimplentes', 'valor' => $total_inadimplentes, 'icone' => 'fa-triangle-exclamation',
                'cor' => '#dc2626', 'bg' => '#fef2f2', 'link' => 'financeiro.php', 'acao' => 'Ver financeiro',
            ],
        ];
        foreach ($cards as $c):
        ?>
            <div class="dashboard-container" style="padding: 18px; background: #fff; position: relative; overflow: hidden;">
                <div style="width: 36px; height: 36px; background: <?php echo $c['bg']; ?>; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                    <i class="fa-solid <?php echo $c['icone']; ?>" style="color: <?php echo $c['cor']; ?>; font-size: 15px;"></i>
                </div>
                <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 6px;"><?php echo $c['label']; ?></div>
                <div style="font-size: 28px; font-weight: 900; color: #08153a; line-height: 1;"><?php echo str_pad($c['valor'], 2, '0', STR_PAD_LEFT); ?></div>
                <a href="<?php echo $c['link']; ?>" style="font-size: var(--fs-xs); font-weight: 900; color: <?php echo $c['cor']; ?>; text-decoration: none; display: block; margin-top: 10px; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo strtoupper($c['acao']); ?> →</a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Receita dos últimos 6 meses -->
    <div style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 16px; margin-bottom: 16px;">
        <div class="dashboard-container" style="padding: 20px; background: linear-gradient(135deg, #08153a, #133080); color: #fff;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; margin-bottom: 18px;">
                <div>
                    <div style="font-size: var(--fs-xs); font-weight: 900; color: #b8c4e8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px;">
                        <i class="fa-solid fa-sack-dollar" style="margin-right: 6px;"></i> Receita — Mês Atual (<?php echo date('m/Y'); ?>)
                    </div>
                    <div style="font-size: 26px; font-weight: 900; line-height: 1;">
                        R$ <?php echo number_format($receita_mes, 2, ',', '.'); ?>
                    </div>
                </div>
                <a href="financeiro.php" style="font-size: var(--fs-xs); font-weight: 900; color: #fff; opacity: 0.85; text-decoration: none; text-transform: uppercase; letter-spacing: 0.05em;">
                    VER FINANCEIRO →
                </a>
            </div>

            <!-- Mini gráfico de barras: últimos 6 meses -->
            <div style="display: flex; align-items: flex-end; gap: 10px; height: 70px; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px;">
                <?php foreach ($historico_receita as $h):
                    $altura = max(4, round(($h['valor'] / $maior_receita_historico) * 52));
                    $atual = (date('n-Y', $h['mes']) === date('n-Y'));
                ?>
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <div title="R$ <?php echo number_format($h['valor'], 2, ',', '.'); ?>"
                             style="width: 100%; max-width: 34px; height: <?php echo $altura; ?>px; background: <?php echo $atual ? '#ffb020' : 'rgba(255,255,255,0.35)'; ?>;"></div>
                        <div style="font-size: 10px; font-weight: 800; color: #b8c4e8; text-transform: uppercase;"><?php echo ucfirst(date('M', $h['mes'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dashboard-container" style="padding: 20px; background: #fff;">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 12px;">
                <i class="fa-solid fa-layer-group" style="margin-right: 6px; color: #7c3aed;"></i> Alunos por Faixa
            </div>
            <?php if (empty($distribuicao_faixas)): ?>
                <div style="color: var(--text-muted); font-size: var(--fs-sm); font-weight: 600;">Nenhum aluno ativo cadastrado.</div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach (array_slice($distribuicao_faixas, 0, 6) as $f):
                        $pct = round(($f['total'] / $maior_faixa) * 100);
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: var(--fs-xs); font-weight: 800; color: #08153a; margin-bottom: 4px; text-transform: uppercase;">
                                <span><?php echo htmlspecialchars($f['faixa']); ?></span>
                                <span><?php echo (int) $f['total']; ?></span>
                            </div>
                            <div style="background: #f1f5f9; height: 8px; width: 100%;">
                                <div style="background: #7c3aed; height: 8px; width: <?php echo $pct; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aniversariantes & Inadimplentes -->
    <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 16px; margin-bottom: 16px;">
        <div class="dashboard-container" style="padding: 20px; background: #fff;">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px;">
                <i class="fa-solid fa-cake-candles" style="margin-right: 6px; color: #d97706;"></i> Aniversariantes do Mês
            </div>
            <div style="font-size: 28px; font-weight: 900; color: #08153a; line-height: 1;">
                <?php echo str_pad($total_aniversariantes, 2, '0', STR_PAD_LEFT); ?>
            </div>
            <a href="alunos.php" style="font-size: var(--fs-xs); font-weight: 900; color: #d97706; text-decoration: none; display: inline-block; margin-top: 10px; text-transform: uppercase; letter-spacing: 0.05em;">
                VER LISTA →
            </a>
        </div>

        <div class="dashboard-container" style="padding: 0; background: #fff; overflow: hidden;">
            <div style="padding: 14px 18px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em;">
                    <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px; color: #dc2626;"></i> Mensalidades em Atraso
                </div>
                <a href="financeiro.php" style="font-size: var(--fs-xs); font-weight: 900; color: #dc2626; text-decoration: none; text-transform: uppercase;">Ver todas →</a>
            </div>
            <?php if (empty($top_inadimplentes)): ?>
                <div style="padding: 18px; text-align: center; color: var(--text-muted); font-weight: 700; font-size: var(--fs-sm);">
                    <i class="fa-solid fa-circle-check" style="color: #15803d; margin-right: 6px;"></i> Nenhuma pendência em atraso.
                </div>
            <?php else: ?>
                <?php foreach ($top_inadimplentes as $inad): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 18px; border-bottom: 1px solid var(--border-color);">
                        <div>
                            <div style="font-size: var(--fs-sm); font-weight: 800; color: #08153a;"><?php echo htmlspecialchars($inad['nome_completo']); ?></div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600;">Venceu em <?php echo date('d/m/Y', strtotime($inad['data_vencimento'])); ?></div>
                        </div>
                        <div style="font-size: var(--fs-sm); font-weight: 900; color: #dc2626;">R$ <?php echo number_format($inad['valor'], 2, ',', '.'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</section>

<?php include 'footer.php'; ?>