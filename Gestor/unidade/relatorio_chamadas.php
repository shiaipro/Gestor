<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!moduloAtivo('turmas')) {
    header('Location: administrativo_dashboard.php');
    exit;
}

if (!temPermissaoModulo('chamadas_visualizar')) {
    header('Location: administrativo_dashboard.php?erro=sem_permissao');
    exit;
}

// Filtros
$turma_id_filtro    = $_GET['turma_id']    ?? null;
$academia_id_filtro = $_GET['academia_id'] ?? null;
$busca_nome         = trim($_GET['nome']   ?? '');

// Intervalo de datas
$hoje         = date('Y-m-d');
$primeiro_mes = date('Y-m-01');
$data_inicio  = $_GET['data_inicio'] ?? $primeiro_mes;
$data_fim     = $_GET['data_fim']    ?? $hoje;
// Garante ordem correta
if ($data_inicio > $data_fim) { [$data_inicio, $data_fim] = [$data_fim, $data_inicio]; }

// Instrutor só pode ver relatórios das suas próprias turmas
$is_instrutor = restringirVisaoAsProprisTurmas();

try {
    // Auto-migration: Garantir coluna atualizado_em na tabela presencas
    try { $pdo->query("SELECT atualizado_em FROM presencas LIMIT 1"); }
    catch (Exception $e) { $pdo->query("ALTER TABLE presencas ADD COLUMN atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); }

    // Buscar turmas para o filtro
    if ($is_instrutor) {
        $stmt_turmas = $pdo->prepare("SELECT id, nome, academia_id FROM turmas WHERE unidade_id = ? AND status = 'ativo' AND instrutor_equipe_id IN (SELECT id FROM unidade_equipe WHERE unidade_id = ? AND usuario_id = ?) ORDER BY nome ASC");
        $stmt_turmas->execute([$unidade_id, $unidade_id, $_SESSION['usuario_id']]);
    } else {
        $stmt_turmas = $pdo->prepare("SELECT id, nome, academia_id FROM turmas WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
        $stmt_turmas->execute([$unidade_id]);
    }
    $turmas_filtro = $stmt_turmas->fetchAll();

    // Se instrutor tentar filtrar por uma turma que não é sua, ignora o filtro inválido
    if ($is_instrutor && $turma_id_filtro && !in_array($turma_id_filtro, array_column($turmas_filtro, 'id'))) {
        $turma_id_filtro = null;
    }

    // Buscar academias para o filtro
    $stmt_acads = $pdo->prepare("SELECT id, nome FROM academias WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
    $stmt_acads->execute([$unidade_id]);
    $academias_filtro = $stmt_acads->fetchAll();

    // ---- Construção dinâmica dos WHERE clauses ----
    $where_extra = "";
    $params_base = ['unidade_id' => $unidade_id, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim];

    if ($turma_id_filtro) {
        $where_extra .= " AND p.turma_id = :turma_id ";
        $params_base['turma_id'] = $turma_id_filtro;
    }
    if ($academia_id_filtro) {
        $where_extra .= " AND EXISTS (SELECT 1 FROM turmas t2 WHERE t2.id = p.turma_id AND t2.academia_id = :academia_id) ";
        $params_base['academia_id'] = $academia_id_filtro;
    }
    if ($is_instrutor) {
        $where_extra .= " AND EXISTS (SELECT 1 FROM turmas t3 INNER JOIN unidade_equipe ue3 ON ue3.id = t3.instrutor_equipe_id WHERE t3.id = p.turma_id AND ue3.usuario_id = :instrutor_usuario_id) ";
        $params_base['instrutor_usuario_id'] = $_SESSION['usuario_id'];
    }

    // 1. Estatísticas Gerais do período
    $sql_stats = "
        SELECT 
            COUNT(DISTINCT p.data_aula) as total_aulas,
            COUNT(CASE WHEN p.status = 'presente' THEN 1 END) as total_presencas,
            COUNT(CASE WHEN p.status = 'falta'    THEN 1 END) as total_faltas,
            COUNT(CASE WHEN p.status = 'justificado' THEN 1 END) as total_justificados
        FROM presencas p
        WHERE p.unidade_id = :unidade_id
          AND p.data_aula BETWEEN :data_inicio AND :data_fim
        $where_extra
    ";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute($params_base);
    $stats = $stmt_stats->fetch();

    // 2. Lista de Alunos e Desempenho
    $where_nome = $busca_nome ? " AND a.nome_completo LIKE :nome " : "";
    $params_alunos = $params_base;
    if ($busca_nome) $params_alunos['nome'] = '%' . $busca_nome . '%';

    if ($turma_id_filtro) {
        $sql_alunos = "
            SELECT 
                a.id, a.nome_completo, a.faixa, a.status,
                ac.nome as academia_nome,
                COUNT(CASE WHEN p.status = 'presente'    THEN 1 END) as presencas,
                COUNT(CASE WHEN p.status = 'falta'       THEN 1 END) as faltas,
                COUNT(CASE WHEN p.status = 'justificado' THEN 1 END) as justificados,
                (SELECT COUNT(DISTINCT data_aula) FROM presencas
                    WHERE turma_id = :turma_id
                      AND data_aula BETWEEN :data_inicio AND :data_fim) as total_aulas_turma
            FROM turma_alunos ta
            JOIN alunos a ON ta.aluno_id = a.id
            LEFT JOIN academias ac ON a.academia_id = ac.id
            LEFT JOIN presencas p ON a.id = p.aluno_id
                AND p.turma_id = :turma_id
                AND p.data_aula BETWEEN :data_inicio AND :data_fim
            WHERE ta.turma_id = :turma_id AND a.unidade_id = :unidade_id
            $where_nome
            GROUP BY a.id, a.status, ac.nome
            ORDER BY a.nome_completo ASC
        ";
    } else {
        if ($is_instrutor) {
            $params_alunos['instrutor_usuario_id'] = $_SESSION['usuario_id'];
        }
        $sql_alunos = "
            SELECT
                a.id, a.nome_completo, a.faixa, a.status,
                ac.nome as academia_nome,
                COUNT(CASE WHEN p.status = 'presente'    THEN 1 END) as presencas,
                COUNT(CASE WHEN p.status = 'falta'       THEN 1 END) as faltas,
                COUNT(CASE WHEN p.status = 'justificado' THEN 1 END) as justificados,
                0 as total_aulas_turma
            FROM alunos a
            LEFT JOIN academias ac ON a.academia_id = ac.id
            LEFT JOIN presencas p ON a.id = p.aluno_id
                AND p.unidade_id = :unidade_id
                AND p.data_aula BETWEEN :data_inicio AND :data_fim
                " . ($academia_id_filtro ? "AND EXISTS (SELECT 1 FROM turmas t2 WHERE t2.id = p.turma_id AND t2.academia_id = :academia_id) " : "") . "
            WHERE a.unidade_id = :unidade_id
              AND (a.status = 'ativo' OR p.aluno_id IS NOT NULL)
              " . ($is_instrutor ? "AND EXISTS (SELECT 1 FROM turma_alunos ta2 JOIN turmas t4 ON t4.id = ta2.turma_id INNER JOIN unidade_equipe ue4 ON ue4.id = t4.instrutor_equipe_id WHERE ta2.aluno_id = a.id AND ue4.usuario_id = :instrutor_usuario_id) " : "") . "
            $where_nome
            GROUP BY a.id, a.status, ac.nome
            HAVING (presencas + faltas + justificados) > 0 OR a.status = 'ativo'
            ORDER BY a.nome_completo ASC
        ";
    }

    $stmt_alunos = $pdo->prepare($sql_alunos);
    $stmt_alunos->execute($params_alunos);
    $alunos_rel = $stmt_alunos->fetchAll();

    // 3. Buscar datas de falta de cada aluno no período
    $sql_datas_faltas = "
        SELECT p.aluno_id, p.data_aula, p.status, p.atualizado_em
        FROM presencas p
        WHERE p.unidade_id = :unidade_id
          AND p.data_aula BETWEEN :data_inicio AND :data_fim
          AND p.status IN ('falta', 'justificado')
        $where_extra
        ORDER BY p.aluno_id, p.data_aula ASC
    ";
    $stmt_datas = $pdo->prepare($sql_datas_faltas);
    $stmt_datas->execute($params_base);
    $datas_faltas_raw = $stmt_datas->fetchAll();

    $datas_por_aluno = [];
    foreach ($datas_faltas_raw as $df) {
        $datas_por_aluno[$df['aluno_id']][] = $df;
    }

} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}

$custom_title = "Relatório de Chamadas";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Relatório de Chamadas
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Acompanhamento de frequência e engajamento dos alunos.
            </p>
        </div>

    
        <div style="display: flex; gap: 10px;">
            <a href="imprimir_chamada.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="btn-sq" style="padding: 12px 25px; background: #08153a; border-color: #08153a;">
                <i class="fa-solid fa-file-pdf" style="margin-right: 8px;"></i> Exportar PDF
            </a>
        </div>
    </div>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <?php if (restringirVisaoAsProprisTurmas()): ?>
        <a href="index.php" class="tab-item-sq">Dashboard</a>
        <a href="turmas.php" class="tab-item-sq active">Turmas</a>
    <?php else: ?>
        <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="alunos.php" class="tab-item-sq">Alunos</a>
        <a href="equipe.php" class="tab-item-sq">RH</a>
        <a href="academias.php" class="tab-item-sq">Unidades</a>
        <a href="turmas.php" class="tab-item-sq active">Turmas</a>
        <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
        <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
    <?php endif; ?>
</div>

    

    <!-- Filtros -->
    <div class="dashboard-container" style="padding: 25px; margin-bottom: 40px; background: #fafafa;">
        <form method="GET" id="form-filtros">
            <!-- Linha 1: Escola → Turma → Nome (cascata) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 8px;">
                        <i class="fa-solid fa-building" style="margin-right:4px;"></i> Escola / Academia
                    </label>
                    <select id="sel-academia" name="academia_id" class="form-control" style="height: 50px; font-weight: 700;">
                        <option value="">TODAS AS ESCOLAS</option>
                        <?php foreach ($academias_filtro as $ac): ?>
                            <option value="<?php echo $ac['id']; ?>" <?php echo $academia_id_filtro == $ac['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ac['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 8px;">
                        <i class="fa-solid fa-users" style="margin-right:4px;"></i> Turma
                    </label>
                    <select id="sel-turma" name="turma_id" class="form-control" style="height: 50px; font-weight: 700;">
                        <option value="">TODAS AS TURMAS</option>
                        <?php foreach ($turmas_filtro as $tf): ?>
                            <option value="<?php echo $tf['id']; ?>"
                                    data-academia="<?php echo (int)$tf['academia_id']; ?>"
                                    <?php echo $turma_id_filtro == $tf['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tf['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 8px;">
                        <i class="fa-solid fa-magnifying-glass" style="margin-right:4px;"></i> Buscar Aluno
                    </label>
                    <input type="text" name="nome" class="form-control"
                           placeholder="Digite o nome do aluno..."
                           value="<?php echo htmlspecialchars($busca_nome); ?>"
                           style="height: 50px; font-weight: 700;">
                </div>
            </div>
            <!-- Linha 2: Período + Botões -->
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: flex-end;">
                <div>
                    <label style="font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 8px;">
                        <i class="fa-regular fa-calendar" style="margin-right:4px;"></i> Data Início
                    </label>
                    <input type="date" name="data_inicio" class="form-control"
                           value="<?php echo $data_inicio; ?>"
                           style="height: 50px; font-weight: 700;">
                </div>
                <div>
                    <label style="font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 8px;">
                        <i class="fa-regular fa-calendar-check" style="margin-right:4px;"></i> Data Fim
                    </label>
                    <input type="date" name="data_fim" class="form-control"
                           value="<?php echo $data_fim; ?>"
                           style="height: 50px; font-weight: 700;">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-sq" style="height: 50px; padding: 0 28px; background: #08153a; border-color: #08153a; white-space: nowrap;">
                        <i class="fa-solid fa-filter" style="margin-right:6px;"></i> Filtrar
                    </button>
                    <a href="relatorio_chamadas.php" class="btn-sq-light" style="height: 50px; padding: 0 20px; display:flex; align-items:center; white-space: nowrap;">
                        <i class="fa-solid fa-rotate-left" style="margin-right:6px;"></i> Limpar
                    </a>
                </div>
            </div>
        </form>

        <!-- Período ativo exibido -->
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color); font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <span><i class="fa-regular fa-clock" style="margin-right:4px;"></i>
                Período: 
                <strong style="color: var(--text-dark);"><?php echo date('d/m/Y', strtotime($data_inicio)); ?></strong>
                até
                <strong style="color: var(--text-dark);"><?php echo date('d/m/Y', strtotime($data_fim)); ?></strong>
            </span>
            <?php if ($busca_nome): ?>
                <span style="background:#e0f2fe; color:#0369a1; padding:2px 10px;"><i class="fa-solid fa-user" style="margin-right:4px;"></i><?php echo htmlspecialchars($busca_nome); ?></span>
            <?php endif; ?>
            <?php if ($turma_id_filtro): ?>
                <?php $t_nome = array_filter($turmas_filtro, fn($t) => $t['id'] == $turma_id_filtro); $t_nome = reset($t_nome); ?>
                <span style="background:#f0fdf4; color:#15803d; padding:2px 10px;"><i class="fa-solid fa-users" style="margin-right:4px;"></i><?php echo htmlspecialchars($t_nome['nome'] ?? ''); ?></span>
            <?php endif; ?>
            <?php if ($academia_id_filtro): ?>
                <?php $ac_nome = array_filter($academias_filtro, fn($ac) => $ac['id'] == $academia_id_filtro); $ac_nome = reset($ac_nome); ?>
                <span style="background:#fef9c3; color:#854d0e; padding:2px 10px;"><i class="fa-solid fa-building" style="margin-right:4px;"></i><?php echo htmlspecialchars($ac_nome['nome'] ?? ''); ?></span>
            <?php endif; ?>
        </div>
    </div>

<script>
(function() {
    const selAcademia = document.getElementById('sel-academia');
    const selTurma    = document.getElementById('sel-turma');
    if (!selAcademia || !selTurma) return;

    // Guarda todas as opções de turma (exceto a primeira "TODAS")
    const todasOpcoes = Array.from(selTurma.options).slice(1).map(opt => ({
        value:    opt.value,
        text:     opt.textContent.trim(),
        academia: opt.dataset.academia,
        selected: opt.selected
    }));

    function filtrarTurmas() {
        const acadId = selAcademia.value;

        // Limpa opções atuais mantendo só a primeira
        while (selTurma.options.length > 1) selTurma.remove(1);
        selTurma.options[0].textContent = acadId
            ? 'TODAS AS TURMAS DESTA ESCOLA'
            : 'TODAS AS TURMAS';

        const filtradas = acadId
            ? todasOpcoes.filter(o => o.academia === acadId)
            : todasOpcoes;

        filtradas.forEach(o => {
            const opt = new Option(o.text, o.value);
            opt.dataset.academia = o.academia;
            if (o.selected) opt.selected = true;
            selTurma.add(opt);
        });

        // Se a turma selecionada não pertence à escola escolhida, limpa
        const turmaSelecionada = todasOpcoes.find(o => o.selected);
        if (acadId && turmaSelecionada && turmaSelecionada.academia !== acadId) {
            selTurma.value = '';
        }

        // Feedback visual: desabilita se não há turmas
        selTurma.disabled = filtradas.length === 0;
        selTurma.style.opacity = filtradas.length === 0 ? '0.5' : '1';
    }

    selAcademia.addEventListener('change', filtrarTurmas);
    // Aplica ao carregar (para manter seleção via GET ao voltar da busca)
    filtrarTurmas();
})();
</script>

    <!-- Cards de Resumo -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card-square" style="padding: 25px; ">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;">Aulas Realizadas</div>
            <div style="font-size: 28px; font-weight: 900; color: #08153a;"><?php echo $stats['total_aulas']; ?></div>
            <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); margin-top: 5px;">NO PERÍODO SELECIONADO</div>
        </div>
        <div class="stat-card-square" style="padding: 25px; ">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;">Total de Presenças</div>
            <div style="font-size: 28px; font-weight: 900; color: var(--primary-green);"><?php echo $stats['total_presencas']; ?></div>
            <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); margin-top: 5px;">CONFIRMADAS</div>
        </div>
        <div class="stat-card-square" style="padding: 25px; ">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;">Total de Faltas</div>
            <div style="font-size: 28px; font-weight: 900; color: #ef4444;"><?php echo $stats['total_faltas']; ?></div>
            <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); margin-top: 5px;">SEM JUSTIFICATIVA</div>
        </div>
        <div class="stat-card-square" style="padding: 25px; ">
            <div style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;">Justificadas</div>
            <div style="font-size: 28px; font-weight: 900; color: #3b82f6;"><?php echo $stats['total_justificados']; ?></div>
            <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); margin-top: 5px;">FALTAS COM MOTIVO</div>
        </div>
    </div>

    <!-- Tabela de Detalhes -->
    <div class="dashboard-container" style="padding: 0;">
        <div style="padding: 20px 30px; border-bottom: 1px solid var(--border-color); background: #fafafa; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase;">Desempenho Individual por Aluno</h3>
        </div>
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: left;">Aluno</th>
                    <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: center;">Presenças</th>
                    <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: center;">Faltas</th>
                    <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: center;">Justif.</th>
                    <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: left;">Datas das Faltas</th>
                    <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: center;">Frequência</th>
                    <th style="padding: 15px 30px; text-transform: uppercase; font-size: 10px; font-weight: 900; color: var(--text-muted); text-align: right;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($alunos_rel as $aluno): 
                    $total_aluno = $aluno['presencas'] + $aluno['faltas'] + $aluno['justificados'];
                    $base_aulas = $turma_id_filtro ? $aluno['total_aulas_turma'] : $total_aluno;
                    $porcentagem = $base_aulas > 0 ? ($aluno['presencas'] / $base_aulas) * 100 : 0;
                    $classe_cor = $porcentagem >= 75 ? 'var(--primary-green)' : ($porcentagem >= 50 ? '#f59e0b' : '#ef4444');
                ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); text-transform: uppercase; font-size: var(--fs-sm);">
                                <?php echo htmlspecialchars($aluno['nome_completo']); ?>
                            </div>
                            <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                <?php echo htmlspecialchars($aluno['faixa']); ?>
                            </div>
                            <?php if (!empty($aluno['academia_nome'])): ?>
                                <div style="font-size: 10px; color: #6b7280; font-weight: 600; margin-top: 2px;">
                                    <i class="fa-solid fa-building" style="margin-right:3px; font-size:9px;"></i><?php echo htmlspecialchars($aluno['academia_nome']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px; text-align: center; font-weight: 900;"><?php echo $aluno['presencas']; ?></td>
                        <td style="padding: 15px 30px; text-align: center; font-weight: 900; color: #ef4444;"><?php echo $aluno['faltas']; ?></td>
                        <td style="padding: 15px 30px; text-align: center; font-weight: 900; color: #3b82f6;"><?php echo $aluno['justificados']; ?></td>
                        <td style="padding: 15px 30px;">
                            <?php 
                            $faltas_aluno = $datas_por_aluno[$aluno['id']] ?? [];
                            if (!empty($faltas_aluno)):
                            ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                    <?php foreach ($faltas_aluno as $fd): 
                                        $is_justif = $fd['status'] === 'justificado';
                                        $bg = $is_justif ? '#eff6ff' : '#fef2f2';
                                        $cor = $is_justif ? '#3b82f6' : '#ef4444';
                                        $data_fmt = date('d/m', strtotime($fd['data_aula']));
                                        $atualizado_fmt = !empty($fd['atualizado_em']) ? date('d/m H:i', strtotime($fd['atualizado_em'])) : '';
                                    ?>
                                        <span title="<?php echo $is_justif ? 'Justificada' : 'Falta'; ?><?php echo $atualizado_fmt ? ' — registrada em ' . $atualizado_fmt : ''; ?>" 
                                              style="font-size: 9px; font-weight: 900; padding: 3px 7px; background: <?php echo $bg; ?>; color: <?php echo $cor; ?>; white-space: nowrap; cursor: default;">
                                            <?php echo $data_fmt; ?>
                                            <?php if ($is_justif): ?><i class="fa-solid fa-check" style="margin-left:3px;"></i><?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="font-size: 10px; color: var(--text-muted); font-weight: 700;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 30px; text-align: center;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                                <div style="width: 100px; height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?php echo min(100, $porcentagem); ?>%; height: 100%; background: <?php echo $classe_cor; ?>;"></div>
                                </div>
                                <span style="font-weight: 900; font-size: var(--fs-xs); width: 40px;"><?php echo round($porcentagem); ?>%</span>
                            </div>
                        </td>
                        <td style="padding: 15px 30px; text-align: right;">
                            <?php if ($porcentagem >= 75): ?>
                                <span style="font-size: 9px; font-weight: 900; padding: 4px 8px; background: #f0fdf4; color: var(--primary-green); text-transform: uppercase; letter-spacing: 0.05em;">Excelente</span>
                            <?php elseif ($porcentagem >= 50): ?>
                                <span style="font-size: 9px; font-weight: 900; padding: 4px 8px; background: #fffbeb; color: #f59e0b; text-transform: uppercase; letter-spacing: 0.05em;">Regular</span>
                            <?php else: ?>
                                <span style="font-size: 9px; font-weight: 900; padding: 4px 8px; background: #fef2f2; color: #ef4444; text-transform: uppercase; letter-spacing: 0.05em;">Alerta</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($alunos_rel)): ?>
                    <tr>
                        <td colspan="7" style="padding: 60px; text-align: center; color: var(--text-muted); font-weight: 900; text-transform: uppercase;">
                            Nenhum registro de chamada encontrado para este período.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
