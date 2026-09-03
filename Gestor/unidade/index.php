<?php
require_once '../config.php';

if (!estaLogado()) {
    header('Location: ../login.php');
    exit;
}

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

garantirColunaTipoCadastroAluno($pdo);

// Instrutor (marcado como instrutor de alguma turma, e não Super Admin) vê um painel
// enxuto com só as informações das próprias turmas — sem números financeiros/administrativos
// da unidade inteira.
$is_instrutor_restrito = restringirVisaoAsProprisTurmas();

if ($is_instrutor_restrito) {
    $stmt_minhas_turmas = $pdo->prepare("
        SELECT t.id, t.nome, t.horario, t.dias_semana,
        (SELECT COUNT(*) FROM turma_alunos ta JOIN alunos a ON ta.aluno_id = a.id WHERE ta.turma_id = t.id AND a.status = 'ativo' AND a.tipo_cadastro != 'visitante') as total_inscritos
        FROM turmas t
        INNER JOIN unidade_equipe ue ON ue.id = t.instrutor_equipe_id
        WHERE t.unidade_id = ? AND ue.usuario_id = ? AND t.status = 'ativo'
        ORDER BY t.horario ASC
    ");
    $stmt_minhas_turmas->execute([$unidade_id, $_SESSION['usuario_id']]);
    $minhas_turmas = $stmt_minhas_turmas->fetchAll();

    $total_minhas_turmas = count($minhas_turmas);
    $total_alunos_minhas_turmas = array_sum(array_column($minhas_turmas, 'total_inscritos'));

    $custom_title = "Minhas Turmas - SHIAIPRO";
    // Esconde a sidebar direita (Atividade Recente / Ações Rápidas) — mostra dados
    // financeiros e de matrícula da unidade inteira, fora do escopo do instrutor.
    $esconder_sidebar = true;
    include 'header.php';
    ?>
    <section class="dashboard-section-sq">
        <div style="margin-bottom: 40px;">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Minhas Turmas
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                PAINEL DO INSTRUTOR • <?php echo date('d M Y'); ?>
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 40px;">
            <div class="stat-card-square">
                <div class="label">Minhas Turmas</div>
                <div class="value"><?php echo str_pad($total_minhas_turmas, 2, '0', STR_PAD_LEFT); ?></div>
                <div style="font-size: var(--fs-sm); color: #3b82f6; margin-top: 10px; font-weight: 700;">
                    <i class="fa-solid fa-calendar-days"></i> TURMAS ATIVAS
                </div>
            </div>
            <div class="stat-card-square">
                <div class="label">Alunos nas Minhas Turmas</div>
                <div class="value"><?php echo str_pad($total_alunos_minhas_turmas, 2, '0', STR_PAD_LEFT); ?></div>
                <div style="font-size: var(--fs-sm); color: var(--primary-green); margin-top: 10px; font-weight: 700;">
                    <i class="fa-solid fa-user-group"></i> MATRICULADOS
                </div>
            </div>
        </div>

        <h2 style="font-size: 1.2rem; font-weight: 900; color: var(--text-dark); margin: 0 0 20px 0; letter-spacing: -0.03em; text-transform: uppercase;">
            Cronograma
        </h2>
        <div class="dashboard-container" style="padding: 0; overflow: hidden;">
            <?php if (empty($minhas_turmas)): ?>
                <div style="padding: 30px; text-align: center; color: var(--text-muted); font-weight: 700;">
                    Nenhuma turma vinculada ao seu usuário ainda.
                </div>
            <?php else: ?>
                <?php foreach ($minhas_turmas as $t): ?>
                    <a href="chamada.php?id=<?php echo (int) $t['id']; ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: inherit;">
                        <div>
                            <div style="font-weight: 900; text-transform: uppercase; font-size: var(--fs-sm);"><?php echo htmlspecialchars($t['nome']); ?></div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 4px;">
                                <?php echo htmlspecialchars($t['horario']); ?> · <?php echo htmlspecialchars(str_replace(',', ', ', strtoupper($t['dias_semana']))); ?>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);"><?php echo (int) $t['total_inscritos']; ?> ALUNOS</span>
                            <i class="fa-solid fa-calendar-check" style="color: var(--primary-gold);"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php
    include 'footer.php';
    exit;
}

// Estatísticas Reais da Unidade
$stmt_alunos = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE unidade_id = ? AND status = 'ativo' AND tipo_cadastro != 'visitante'");
$stmt_alunos->execute([$unidade_id]);
$total_alunos = $stmt_alunos->fetchColumn() ?: 0;

$stmt_atraso = $pdo->prepare("SELECT COUNT(DISTINCT aluno_id) FROM mensalidades WHERE unidade_id = ? AND status = 'pendente' AND data_vencimento < CURDATE() AND aluno_id IN (SELECT id FROM alunos WHERE status != 'inativo')");
$stmt_atraso->execute([$unidade_id]);
$inadimplentes = $stmt_atraso->fetchColumn() ?: 0;

$stmt_turmas = $pdo->prepare("SELECT COUNT(*) FROM turmas WHERE unidade_id = ? AND status = 'ativo'");
$stmt_turmas->execute([$unidade_id]);
$total_turmas = $stmt_turmas->fetchColumn() ?: 0;

// Verificação de Onboarding (4 Passos)
$stmt_u = $pdo->prepare("SELECT logo, razao_social, smtp_host FROM unidades WHERE id = ?");
$stmt_u->execute([$unidade_id]);
$dados_u = $stmt_u->fetch();

// Asaas é configurado em uma tela própria (config_asaas.php), gravado em configuracoes_pagamento —
// não na coluna unidades.asaas_token (que ficou sem uso depois da tela de Integrações apontar pra lá).
$stmt_asaas_check = $pdo->prepare("SELECT 1 FROM configuracoes_pagamento WHERE unidade_id = ? AND gateway = 'asaas' AND api_key IS NOT NULL AND api_key != ''");
$stmt_asaas_check->execute([$unidade_id]);
$tem_asaas_configurado = (bool)$stmt_asaas_check->fetchColumn();

$stmt_acad = $pdo->prepare("SELECT COUNT(*) FROM academias WHERE unidade_id = ? AND status = 'ativo'");
$stmt_acad->execute([$unidade_id]);
$total_acad = $stmt_acad->fetchColumn() ?: 0;

$stmt_eq = $pdo->prepare("SELECT COUNT(*) FROM unidade_equipe WHERE unidade_id = ? AND status = 'ativo'");
$stmt_eq->execute([$unidade_id]);
$total_eq = $stmt_eq->fetchColumn() ?: 0;

$passos = [
    'config' => (!empty($dados_u['logo']) && !empty($dados_u['razao_social']) && ($tem_asaas_configurado || !empty($dados_u['smtp_host']))),
    'unidades' => ($total_acad > 0),
    'rh' => ($total_eq > 0),
    'turmas' => ($total_turmas > 0)
];

$concluidos = count(array_filter($passos));
$progresso = ($concluidos / count($passos)) * 100;
$onboarding_completo = ($progresso == 100);


$custom_title = "Operações - SHIAIPRO";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho de Boas-vindas -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Operações da Unidade
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                PAINEL DE CONTROLE • <?php echo date('d M Y'); ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="alunos.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">LISTA DE ALUNOS</a>
            <a href="novo_aluno.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> NOVA MATRÍCULA
            </a>
        </div>
    </div>

    <?php if (!$onboarding_completo): ?>
    <!-- ONBOARDING WIDGET -->
    <div class="dashboard-container" style="padding: 25px; margin-bottom: 40px;  background: #fff;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="font-size: 14px; font-weight: 900; color: #133080; text-transform: uppercase; margin: 0;">Configuração Inicial Necessária</h2>
                <p style="color: var(--text-muted); font-size: 12px; margin: 3px 0 0 0; font-weight: 600;">Complete os pilares abaixo para liberar o sistema completo.</p>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 18px; font-weight: 900; color: #133080;"><?php echo round($progresso); ?>%</span>
                <div style="width: 150px; height: 6px; background: #f1f5f9; margin-top: 5px; border-radius: 10px; overflow: hidden;">
                    <div style="width: <?php echo $progresso; ?>%; height: 100%; background: var(--primary-gold); transition: width 0.5s ease;"></div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <!-- Passo 1 -->
            <a href="configuracoes.php" style="text-decoration: none; padding: 15px; background: <?php echo $passos['config'] ? '#f0fdf4' : '#fafafa'; ?>; border: 1px solid <?php echo $passos['config'] ? '#bbf7d0' : '#e2e8f0'; ?>; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid <?php echo $passos['config'] ? 'fa-circle-check' : 'fa-gear'; ?>" style="font-size: 18px; color: <?php echo $passos['config'] ? '#16a34a' : '#94a3b8'; ?>;"></i>
                <div>
                    <div style="font-size: 10px; font-weight: 900; color: #08153a; text-transform: uppercase;">Configurações</div>
                    <div style="font-size: 9px; color: #64748b; font-weight: 700;">Empresa & Integrações</div>
                </div>
            </a>

            <!-- Passo 2 -->
            <a href="academias.php" style="text-decoration: none; padding: 15px; background: <?php echo $passos['unidades'] ? '#f0fdf4' : '#fafafa'; ?>; border: 1px solid <?php echo $passos['unidades'] ? '#bbf7d0' : '#e2e8f0'; ?>; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid <?php echo $passos['unidades'] ? 'fa-circle-check' : 'fa-building'; ?>" style="font-size: 18px; color: <?php echo $passos['unidades'] ? '#16a34a' : '#94a3b8'; ?>;"></i>
                <div>
                    <div style="font-size: 10px; font-weight: 900; color: #08153a; text-transform: uppercase;">Unidades</div>
                    <div style="font-size: 9px; color: #64748b; font-weight: 700;">Academias Ativas</div>
                </div>
            </a>

            <!-- Passo 3 -->
            <a href="equipe.php" style="text-decoration: none; padding: 15px; background: <?php echo $passos['rh'] ? '#f0fdf4' : '#fafafa'; ?>; border: 1px solid <?php echo $passos['rh'] ? '#bbf7d0' : '#e2e8f0'; ?>; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid <?php echo $passos['rh'] ? 'fa-circle-check' : 'fa-user-tie'; ?>" style="font-size: 18px; color: <?php echo $passos['rh'] ? '#16a34a' : '#94a3b8'; ?>;"></i>
                <div>
                    <div style="font-size: 10px; font-weight: 900; color: #08153a; text-transform: uppercase;">Recursos Humanos</div>
                    <div style="font-size: 9px; color: #64748b; font-weight: 700;">Equipe & Professores</div>
                </div>
            </a>

            <!-- Passo 4 -->
            <a href="turmas.php" style="text-decoration: none; padding: 15px; background: <?php echo $passos['turmas'] ? '#f0fdf4' : '#fafafa'; ?>; border: 1px solid <?php echo $passos['turmas'] ? '#bbf7d0' : '#e2e8f0'; ?>; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid <?php echo $passos['turmas'] ? 'fa-circle-check' : 'fa-calendar-check'; ?>" style="font-size: 18px; color: <?php echo $passos['turmas'] ? '#16a34a' : '#94a3b8'; ?>;"></i>
                <div>
                    <div style="font-size: 10px; font-weight: 900; color: #08153a; text-transform: uppercase;">Turmas</div>
                    <div style="font-size: 9px; color: #64748b; font-weight: 700;">Horários & Planos</div>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Módulo de Estatísticas (Square Aesthetic) -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 40px;">
        <div class="stat-card-square">
            <div class="label">Membros Ativos</div>
            <div class="value"><?php echo str_pad($total_alunos, 2, '0', STR_PAD_LEFT); ?></div>
            <div style="font-size: var(--fs-sm); color: var(--primary-green); margin-top: 10px; font-weight: 700;">
                <i class="fa-solid fa-circle-check"></i> EM DIA COM A UNIDADE
            </div>
        </div>

        <div class="stat-card-square">
            <div class="label">Gestão de Turmas</div>
            <div class="value"><?php echo str_pad($total_turmas, 2, '0', STR_PAD_LEFT); ?></div>
            <div style="font-size: var(--fs-sm); color: #3b82f6; margin-top: 10px; font-weight: 700;">
                <i class="fa-solid fa-calendar-days"></i> HORÁRIOS CONFIGURADOS
            </div>
        </div>

        <div class="stat-card-square">
            <div class="label">Financeiro Crítico</div>
            <div class="value" style="color: #ef4444;"><?php echo str_pad($inadimplentes, 2, '0', STR_PAD_LEFT); ?></div>
            <div style="font-size: var(--fs-sm); color: #ef4444; margin-top: 10px; font-weight: 700;">
                <i class="fa-solid fa-triangle-exclamation"></i> PAGAMENTOS EM ATRASO
            </div>
        </div>
    </div>

    <!-- Acesso Rápido aos Módulos -->
    <h2 style="font-size: 1.2rem; font-weight: 900; color: var(--text-dark); margin: 0 0 20px 0; letter-spacing: -0.03em; text-transform: uppercase;">
        Acesso Rápido aos Módulos
    </h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <a href="alunos.php" class="dashboard-container" style="text-decoration: none; padding: 25px; display: flex; align-items: center; gap: 15px; background: #fff; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="width: 50px; height: 50px; background: #f0fdf4; border: 1px solid #bbf7d0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                <i class="fa-solid fa-users" style="font-size: 20px; color: #16a34a;"></i>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 900; color: #08153a; text-transform: uppercase;">Alunos & Alunos</div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600;">Gestão de matrículas e fichas</div>
            </div>
        </a>

        <a href="financeiro.php" class="dashboard-container" style="text-decoration: none; padding: 25px; display: flex; align-items: center; gap: 15px; background: #fff; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="width: 50px; height: 50px; background: #eff6ff; border: 1px solid #bfdbfe; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                <i class="fa-solid fa-sack-dollar" style="font-size: 20px; color: #2563eb;"></i>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 900; color: #08153a; text-transform: uppercase;">Financeiro</div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600;">Contas a receber e fluxo de caixa</div>
            </div>
        </a>

        <a href="turmas.php" class="dashboard-container" style="text-decoration: none; padding: 25px; display: flex; align-items: center; gap: 15px; background: #fff; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="width: 50px; height: 50px; background: #fdf4ff; border: 1px solid #fbcfe8; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                <i class="fa-solid fa-calendar-days" style="font-size: 20px; color: #db2777;"></i>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 900; color: #08153a; text-transform: uppercase;">Turmas & Horários</div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600;">Grade de aulas e controle</div>
            </div>
        </a>

        <a href="competicoes.php" class="dashboard-container" style="text-decoration: none; padding: 25px; display: flex; align-items: center; gap: 15px; background: #fff; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="width: 50px; height: 50px; background: #fffbeb; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                <i class="fa-solid fa-medal" style="font-size: 20px; color: #d97706;"></i>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 900; color: #08153a; text-transform: uppercase;">Competições</div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600;">Eventos, campeonatos e atletas</div>
            </div>
        </a>

        <a href="loja_dashboard.php" class="dashboard-container" style="text-decoration: none; padding: 25px; display: flex; align-items: center; gap: 15px; background: #fff; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="width: 50px; height: 50px; background: #fef2f2; border: 1px solid #fecaca; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                <i class="fa-solid fa-store" style="font-size: 20px; color: #dc2626;"></i>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 900; color: #08153a; text-transform: uppercase;">Loja Virtual</div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600;">Produtos, estoque e pedidos</div>
            </div>
        </a>

        <a href="configuracoes.php" class="dashboard-container" style="text-decoration: none; padding: 25px; display: flex; align-items: center; gap: 15px; background: #fff; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="width: 50px; height: 50px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                <i class="fa-solid fa-gear" style="font-size: 20px; color: #475569;"></i>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 900; color: #08153a; text-transform: uppercase;">Configurações</div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600;">Ajustes da empresa e integrações</div>
            </div>
        </a>
    </div>



</section>

<?php include 'footer.php'; ?>