<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('chamadas_visualizar')) {
    header('Location: turmas.php?erro=sem_permissao');
    exit;
}

$turma_id = $_GET['turma_id'] ?? null;
$data_aula = $_GET['data'] ?? date('Y-m-d');

if (!$turma_id) {
    header('Location: turmas.php');
    exit;
}

// 1. Buscar dados da turma
$stmt_turma = $pdo->prepare("SELECT t.*, ac.nome as academia_nome FROM turmas t LEFT JOIN academias ac ON t.academia_id = ac.id WHERE t.id = ? AND t.unidade_id = ?");
$stmt_turma->execute([$turma_id, $unidade_id]);
$turma = $stmt_turma->fetch();

if (!$turma) {
    header('Location: turmas.php');
    exit;
}

// Proteger: instrutor só pode acessar suas próprias turmas
if (restringirVisaoAsProprisTurmas()) {
    $stmt_dono = $pdo->prepare("SELECT 1 FROM unidade_equipe WHERE id = ? AND unidade_id = ? AND usuario_id = ?");
    $stmt_dono->execute([$turma['instrutor_equipe_id'], $unidade_id, $_SESSION['usuario_id']]);
    if (!$stmt_dono->fetchColumn()) {
        header('Location: turmas.php?erro=sem_permissao');
        exit;
    }
}

// 2. Buscar alunos matriculados nesta turma
$stmt_alunos = $pdo->prepare("
    SELECT a.id, a.nome_completo, a.foto, a.faixa, a.data_graduacao
    FROM turma_alunos ta
    JOIN alunos a ON ta.aluno_id = a.id
    WHERE ta.turma_id = ? AND a.unidade_id = ? AND a.status = 'ativo'
    ORDER BY a.nome_completo ASC
");
$stmt_alunos->execute([$turma_id, $unidade_id]);
$alunos = $stmt_alunos->fetchAll();

// 2b. Alunos desta turma inscritos em algum exame de faixas com resultado ainda pendente
// (usado para exibir o botão "Fazer Exame", que abre o lançamento de aprovado/reprovado na hora).
$stmt_exames_pendentes = $pdo->prepare("
    SELECT ei.id as inscricao_id, ei.aluno_id, ei.faixa_pretendida, ei.tamanho_faixa, eg.titulo as evento_titulo
    FROM eventos_graduacao_inscricoes ei
    JOIN eventos_graduacao eg ON eg.id = ei.evento_id
    WHERE eg.unidade_id = ? AND eg.status = 'agendado' AND ei.resultado = 'pendente'
      AND ei.aluno_id IN (SELECT aluno_id FROM turma_alunos WHERE turma_id = ?)
");
$stmt_exames_pendentes->execute([$unidade_id, $turma_id]);
$exames_pendentes_por_aluno = [];
foreach ($stmt_exames_pendentes->fetchAll() as $ep) {
    $exames_pendentes_por_aluno[$ep['aluno_id']] = $ep;
}

// 2c. Checklist da prova (avaliações/tópicos/itens configurados em Configurações > Faixas)
// por graduação, para exibir no modal "Fazer Exame" o que o professor precisa avaliar
// naquela faixa. Uma faixa pode ter mais de uma avaliação (Prova 1, Prova 2...) — todas
// aparecem juntas no checklist do exame, agrupadas pelo nome da avaliação.
$checklist_por_faixa = [];
if (!empty($exames_pendentes_por_aluno)) {
    $stmt_grads_prova = $pdo->prepare("SELECT * FROM unidade_graduacoes WHERE unidade_id = ?");
    $stmt_grads_prova->execute([$unidade_id]);
    $graduacoes_prova = $stmt_grads_prova->fetchAll();

    if (!empty($graduacoes_prova)) {
        $ids_grad_prova = array_column($graduacoes_prova, 'id');
        $placeholders_grad = implode(',', array_fill(0, count($ids_grad_prova), '?'));

        $stmt_avals_prova = $pdo->prepare("SELECT * FROM unidade_graduacao_avaliacoes WHERE graduacao_id IN ($placeholders_grad) ORDER BY graduacao_id ASC, ordem ASC, id ASC");
        $stmt_avals_prova->execute($ids_grad_prova);
        $avaliacoes_prova_all = $stmt_avals_prova->fetchAll();

        $stmt_topicos_prova = $pdo->prepare("SELECT * FROM unidade_graduacao_checklist_topicos WHERE graduacao_id IN ($placeholders_grad) ORDER BY avaliacao_id ASC, ordem ASC, id ASC");
        $stmt_topicos_prova->execute($ids_grad_prova);
        $topicos_prova_all = $stmt_topicos_prova->fetchAll();

        $itens_por_topico_prova = [];
        if (!empty($topicos_prova_all)) {
            $ids_topicos_prova = array_column($topicos_prova_all, 'id');
            $placeholders_top = implode(',', array_fill(0, count($ids_topicos_prova), '?'));
            $stmt_itens_prova = $pdo->prepare("SELECT * FROM unidade_graduacao_checklist_itens WHERE topico_id IN ($placeholders_top) ORDER BY topico_id ASC, ordem ASC, id ASC");
            $stmt_itens_prova->execute($ids_topicos_prova);
            foreach ($stmt_itens_prova->fetchAll() as $item_prova) {
                $itens_por_topico_prova[$item_prova['topico_id']][] = $item_prova;
            }
        }

        $topicos_por_avaliacao_prova = [];
        foreach ($topicos_prova_all as $t_prova) {
            $t_prova['itens'] = $itens_por_topico_prova[$t_prova['id']] ?? [];
            $topicos_por_avaliacao_prova[$t_prova['avaliacao_id']][] = $t_prova;
        }

        foreach ($graduacoes_prova as $g_prova) {
            $nome_completo_grad_prova = $g_prova['nome'] . (!empty($g_prova['grau']) ? ' - ' . $g_prova['grau'] : '');
            $avaliacoes_desta_grad = [];
            foreach ($avaliacoes_prova_all as $av_prova) {
                if ($av_prova['graduacao_id'] == $g_prova['id']) {
                    $av_prova['topicos'] = $topicos_por_avaliacao_prova[$av_prova['id']] ?? [];
                    $avaliacoes_desta_grad[] = $av_prova;
                }
            }
            $checklist_por_faixa[$nome_completo_grad_prova] = $avaliacoes_desta_grad;
        }
    }
}

foreach ($alunos as &$aluno_exame) {
    $aluno_exame['exame_pendente'] = $exames_pendentes_por_aluno[$aluno_exame['id']] ?? null;
}
unset($aluno_exame);

// 3. Buscar presenças já registradas para esta data
// Auto-migration: Garantir coluna atualizado_em na tabela presencas
try {
    $pdo->query("SELECT atualizado_em FROM presencas LIMIT 1");
} catch (Exception $e) {
    $pdo->query("ALTER TABLE presencas ADD COLUMN atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

$stmt_presencas = $pdo->prepare("SELECT aluno_id, status, observacao, atualizado_em FROM presencas WHERE turma_id = ? AND data_aula = ?");
$stmt_presencas->execute([$turma_id, $data_aula]);
$presencas_db = $stmt_presencas->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

$custom_title = "Lista de Chamada";
include 'header.php';
?>

<style>
    .aluno-card-chamada {
        background: #fff;
        border: 1px solid var(--border-color);
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
        transition: all 0.2s ease;
    }
    .aluno-card-chamada:hover {
        border-color: #08153a;
        box-shadow: 5px 5px 0px rgba(0,0,0,0.05);
    }
    .status-btn {
        padding: 10px 20px;
        font-size: var(--fs-xs);
        font-weight: 900;
        text-transform: uppercase;
        border: 1px solid var(--border-color);
        background: #fafafa;
        cursor: pointer;
        transition: all 0.2s;
        letter-spacing: 0.05em;
    }
    .status-btn.active[data-status="presente"] { background: var(--primary-green); color: #08153a; border-color: var(--primary-green); }
    .status-btn.active[data-status="falta"] { background: #ef4444; color: #fff; border-color: #ef4444; }
    .status-btn.active[data-status="justificado"] { background: #3b82f6; color: #fff; border-color: #3b82f6; }
    
    .status-btn:not(.active):hover { background: #eee; }

    .chamada-layout-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        grid-template-areas: 
            "lista resumo"
            "lista instrucoes"
            "lista status";
        gap: 30px;
        align-items: start;
    }

    .lista-alunos-section { grid-area: lista; }
    .resumo-aula-section { grid-area: resumo; }
    .instrucoes-section { grid-area: instrucoes; }
    .save-status-section { grid-area: status; }

    @media (max-width: 1024px) {
        .chamada-layout-grid {
            grid-template-columns: 1fr;
            grid-template-areas: 
                "resumo"
                "lista"
                "instrucoes"
                "status";
        }
    }

    @media (max-width: 580px) {
        .aluno-card-chamada {
            flex-direction: column;
            align-items: stretch;
            gap: 15px;
            text-align: center;
        }
        .aluno-card-chamada > div:first-child {
            flex-direction: column;
            justify-content: center;
        }
        .aluno-card-chamada > div:last-child {
            display: flex;
            width: 100%;
        }
        .aluno-card-chamada .status-btn {
            flex: 1;
            padding: 12px 5px;
            font-size: 11px;
        }
    }
</style>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Lista de Chamada
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Aula: <strong><?php echo htmlspecialchars($turma['nome']); ?></strong> (<?php echo date('H:i', strtotime($turma['horario'])); ?>)
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px; align-items: center; width: 100%; max-width: 320px; justify-content: space-between; flex-wrap: wrap;">
            <input type="date" id="data_chamada" value="<?php echo $data_aula; ?>" onchange="window.location.href = 'chamada.php?turma_id=<?php echo $turma_id; ?>&data=' + this.value"
                style="height: 50px; border: 1px solid var(--border-color); padding: 0 15px; font-weight: 700; font-family: inherit; font-size: var(--fs-sm); flex: 1;">
            
            <a href="turmas.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px; display: inline-flex; align-items: center; justify-content: center; height: 50px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
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

    

    <div class="chamada-layout-grid">
        <!-- Lista de Alunos -->
        <div class="lista-alunos-section dashboard-container" style="padding: 30px;">
            <?php if (empty($alunos)): ?>
                <div style="padding: 60px; text-align: center; color: var(--text-muted);">
                    <i class="fa-solid fa-users-slash" style="font-size: 48px; opacity: 0.2; margin-bottom: 20px; display: block;"></i>
                    <p style="font-weight: 900; text-transform: uppercase;">Nenhum aluno matriculado nesta turma.</p>
                </div>
            <?php else: ?>
                <div id="lista-alunos-chamada">
                    <?php foreach ($alunos as $aluno): 
                        $presenca = $presencas_db[$aluno['id']] ?? null;
                        $status_atual = $presenca['status'] ?? '';
                    ?>
                        <div class="aluno-card-chamada" data-aluno-id="<?php echo $aluno['id']; ?>">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <img src="<?php echo $aluno['foto'] ? '../uploads/fotos/' . $aluno['foto'] : 'https://ui-avatars.com/api/?name=' . urlencode($aluno['nome_completo']) . '&background=000&color=a3e635'; ?>" 
                                    style="width: 50px; height: 50px; object-fit: cover; border: 1px solid var(--border-color);">
                                <div>
                                    <div style="font-weight: 900; text-transform: uppercase; color: var(--text-dark); font-size: var(--fs-base);"><?php echo htmlspecialchars($aluno['nome_completo']); ?></div>
                                    <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; text-transform: uppercase;"><?php echo htmlspecialchars($aluno['faixa']); ?></div>
                                    <?php if (!empty($presenca['atualizado_em'])): ?>
                                        <div style="font-size: 10px; color: #94a3b8; font-weight: 600; margin-top: 3px;">
                                            <i class="fa-regular fa-clock" style="margin-right: 3px;"></i>
                                            Registrado em: <?php echo date('d/m/Y \à\s H:i', strtotime($presenca['atualizado_em'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                <?php if (!empty($aluno['exame_pendente'])): ?>
                                    <button type="button" class="status-btn" style="background: #133080; color: #a3e635; border-color: #133080; display: inline-flex; align-items: center; gap: 6px;"
                                        onclick="abrirModalExame(<?php echo (int)$aluno['exame_pendente']['inscricao_id']; ?>, '<?php echo htmlspecialchars(addslashes($aluno['nome_completo'])); ?>', '<?php echo htmlspecialchars(addslashes($aluno['exame_pendente']['evento_titulo'])); ?>', '<?php echo htmlspecialchars(addslashes($aluno['exame_pendente']['faixa_pretendida'] ?: 'Não definida')); ?>', '<?php echo htmlspecialchars(addslashes($aluno['exame_pendente']['tamanho_faixa'] ?: '')); ?>')">
                                        <i class="fa-solid fa-medal"></i> FAZER EXAME
                                    </button>
                                    <template id="prova-<?php echo (int)$aluno['exame_pendente']['inscricao_id']; ?>">
                                        <?php
                                            $avaliacoes_prova_aluno = $checklist_por_faixa[$aluno['exame_pendente']['faixa_pretendida']] ?? [];
                                            if (empty($avaliacoes_prova_aluno)):
                                        ?>
                                            <p style="font-size: var(--fs-sm); color: var(--text-muted); font-weight: 600; text-align: center;">Nenhuma prova configurada para esta faixa em Configurações &gt; Faixas.</p>
                                        <?php else: foreach ($avaliacoes_prova_aluno as $avaliacao_prova): ?>
                                            <div style="margin-bottom: 20px; text-align: left; border-left: 3px solid #133080; padding-left: 12px;">
                                                <div style="font-size: var(--fs-sm); font-weight: 900; color: #4f46e5; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.05em;"><?php echo htmlspecialchars($avaliacao_prova['nome']); ?></div>
                                                <?php if (empty($avaliacao_prova['topicos'])): ?>
                                                    <p style="font-size: var(--fs-xs); color: var(--text-muted); margin: 0;">Sem tópicos cadastrados nesta avaliação.</p>
                                                <?php else: foreach ($avaliacao_prova['topicos'] as $topico_prova): ?>
                                                    <div style="margin-bottom: 15px;">
                                                        <div style="font-size: var(--fs-xs); font-weight: 900; color: #133080; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.05em;"><?php echo htmlspecialchars($topico_prova['nome']); ?></div>
                                                        <?php if (empty($topico_prova['itens'])): ?>
                                                            <p style="font-size: var(--fs-xs); color: var(--text-muted); margin: 0;">Sem itens cadastrados.</p>
                                                        <?php else: ?>
                                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                                <?php foreach ($topico_prova['itens'] as $item_prova): ?>
                                                                    <label style="display: flex; align-items: flex-start; gap: 8px; font-size: var(--fs-sm); color: var(--text-dark); font-weight: 600; cursor: pointer;">
                                                                        <input type="checkbox" class="prova-item-check" value="<?php echo (int)$item_prova['id']; ?>" onchange="atualizarNotaExame()" style="width: 16px; height: 16px; margin-top: 2px; accent-color: #133080; flex-shrink: 0;">
                                                                        <?php echo htmlspecialchars($item_prova['descricao']); ?>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; endif; ?>
                                            </div>
                                        <?php endforeach; endif; ?>
                                    </template>
                                <?php endif; ?>
                                <button class="status-btn <?php echo $status_atual == 'presente' ? 'active' : ''; ?>" data-status="presente" onclick="setPresenca(<?php echo $aluno['id']; ?>, 'presente')">Presente</button>
                                <button class="status-btn <?php echo $status_atual == 'falta' ? 'active' : ''; ?>" data-status="falta" onclick="setPresenca(<?php echo $aluno['id']; ?>, 'falta')">Falta</button>
                                <button class="status-btn <?php echo $status_atual == 'justificado' ? 'active' : ''; ?>" data-status="justificado" onclick="setPresenca(<?php echo $aluno['id']; ?>, 'justificado')">Justif.</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Resumo da Aula -->
        <div class="resumo-aula-section dashboard-container" style="padding: 30px; background: #08153a; color: #fff; border:none;">
            <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin: 0 0 15px 0; letter-spacing: 0.1em;">Resumo da Aula</h3>
            <div style="display: grid; gap: 15px;">
                <div style="padding-bottom:10px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <label style="font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 900; display: block;">Aula</label>
                    <span style="font-weight: 700;"><?php echo htmlspecialchars($turma['nome']); ?> (<?php echo date('H:i', strtotime($turma['horario'])); ?>)</span>
                </div>
                <div style="padding-bottom:10px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <label style="font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 900; display: block;">Local / Academia</label>
                    <span style="font-weight: 700;"><?php echo htmlspecialchars($turma['academia_nome'] ?: 'Não definido'); ?></span>
                </div>
                <div style="padding-bottom:10px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <label style="font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 900; display: block;">Instrutor Responsável</label>
                    <span style="font-weight: 700;"><?php echo htmlspecialchars($turma['instrutor'] ?: 'Não definido'); ?></span>
                </div>
                <?php
                // Dias da semana
                $mapa_dias = [
                    'seg' => 'SEG', 'ter' => 'TER', 'qua' => 'QUA',
                    'qui' => 'QUI', 'sex' => 'SEX', 'sab' => 'SÁB', 'dom' => 'DOM'
                ];
                $dias_turma = !empty($turma['dias_semana'])
                    ? array_filter(array_map('trim', explode(',', $turma['dias_semana'])))
                    : [];
                // Dia da semana atual (0=dom,1=seg...6=sab)
                $hoje_num = (int)date('w'); // 0=dom
                $mapa_hoje = [0=>'dom',1=>'seg',2=>'ter',3=>'qua',4=>'qui',5=>'sex',6=>'sab'];
                $hoje_key  = $mapa_hoje[$hoje_num] ?? '';
                ?>
                <?php if (!empty($dias_turma)): ?>
                <div style="padding-bottom:10px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <label style="font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 900; display: block; margin-bottom: 8px;">Dias da Semana</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <?php foreach ($mapa_dias as $key => $label): 
                            $ativo   = in_array($key, $dias_turma);
                            $is_hoje = ($key === $hoje_key) && $ativo;
                            if (!$ativo) continue;
                        ?>
                            <span style="
                                font-size: 10px; font-weight: 900; padding: 4px 10px;
                                background: <?php echo $is_hoje ? 'var(--primary-green)' : 'rgba(255,255,255,0.12)'; ?>;
                                color: <?php echo $is_hoje ? '#08153a' : '#cbd5e1'; ?>;
                                text-transform: uppercase; letter-spacing: 0.05em;
                                border: 1px solid <?php echo $is_hoje ? 'var(--primary-green)' : 'rgba(255,255,255,0.2)'; ?>;
                            ">
                                <?php echo $label; ?>
                                <?php if ($is_hoje): ?> <i class="fa-solid fa-circle-dot" style="font-size:8px;"></i><?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Instruções -->
        <div class="instrucoes-section dashboard-container" style="padding: 30px;">
            <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); text-transform: uppercase; margin-bottom: 20px; border-bottom: 2px solid #08153a; padding-bottom: 10px;">PLANO DE AULA</h3>
            
            <?php if (!empty($turma['instrucoes_pdf'])): ?>
                <div style="background: #f0fdf4; border: 1px dashed var(--primary-green); padding: 15px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                    <i class="fa-solid fa-file-pdf" style="font-size: 28px; color: #ef4444;"></i>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: #166534;">Orientação de Aula</h4>
                        <p style="margin: 3px 0 0 0; font-size: 11px; color: #374151; font-weight: 600;">Documento em PDF com as instruções desta turma.</p>
                    </div>
                    <a href="<?php echo getUploadURL('documentos', $turma['instrucoes_pdf']); ?>" target="_blank" class="btn-sq" style="height: auto; padding: 8px 15px; font-size: 11px; width: auto; background: var(--primary-green); border-color: var(--primary-green); text-decoration: none; color: #08153a; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-file-pdf"></i> ABRIR PDF
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!empty($turma['descricao'])): ?>
                <div style="margin-bottom: 20px; background: #fafafa; border: 1px solid var(--border-color); padding: 15px;">
                    <h4 style="margin: 0 0 8px 0; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-dark);">Descrição da Modalidade</h4>
                    <p style="margin: 0; font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.5; font-weight: 500;"><?php echo nl2br(htmlspecialchars($turma['descricao'])); ?></p>
                </div>
            <?php endif; ?>

            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500; margin: 0;">
                A presença é salva automaticamente ao clicar no status desejado. 
                <br><br>
                <strong>Presente:</strong> Aluno participou normalmente.
                <br>
                <strong>Falta:</strong> Aluno não compareceu.
                <br>
                <strong>Justificado:</strong> Falta com justificativa (médica, trabalho, etc).
            </p>
        </div>
        
        <div id="save-status" class="save-status-section" style="padding: 15px; text-align: center; font-weight: 900; text-transform: uppercase; font-size: var(--fs-xs); display: none;">
            Salvando alterações...
        </div>
    </div>
</section>

<!-- Modal Avaliar Exame -->
<div id="modal_exame" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
    <div class="dashboard-container" style="width:100%; max-width:460px; padding:2rem; text-align:center; position:relative;">
        <button onclick="document.getElementById('modal_exame').style.display='none'" style="position:absolute; top:1rem; right:1rem; border:none; background:none; cursor:pointer; font-size:1.2rem;">&times;</button>
        <i class="fa-solid fa-medal" style="font-size: 3rem; color: #133080; margin-bottom: 1rem;"></i>
        <h2 style="margin-bottom:0.3rem; font-size: 1.1rem; font-weight: 900; text-transform: uppercase;" id="modal_exame_aluno"></h2>
        <p style="color: var(--text-muted); margin-bottom: 1rem; font-size: var(--fs-sm); font-weight: 700;" id="modal_exame_evento"></p>

        <div style="background: #f0f0ff; border: 1px solid #c7d2fe; padding: 12px 15px; margin-bottom: 1.5rem;">
            <span style="display: block; font-size: 10px; font-weight: 900; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Faixa Pretendida</span>
            <span style="font-size: var(--fs-base); font-weight: 900; color: #1e293b; text-transform: uppercase;" id="modal_exame_faixa"></span>
        </div>

        <div style="text-align: left; margin-bottom: 1rem; max-height: 280px; overflow-y: auto; border: 1px solid var(--border-color); background: #fafafa; padding: 15px;">
            <div style="font-size: 10px; font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;"><i class="fa-solid fa-clipboard-list" style="margin-right: 6px;"></i>Prova da Faixa</div>
            <div id="modal_exame_prova"></div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; background: #08153a; padding: 12px 18px; margin-bottom: 1.5rem;">
            <span style="font-size: 10px; font-weight: 900; color: #a3e635; text-transform: uppercase; letter-spacing: 0.05em;">Nota Final</span>
            <span id="modal_exame_nota" style="font-size: 1.4rem; font-weight: 900; color: #fff;">0,0</span>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
            <button type="button" onclick="avaliarExame('reprovado')" class="status-btn" style="background:#fee2e2; color:#991b1b; border-color:#fecaca; padding: 15px;">
                <i class="fa-solid fa-xmark" style="margin-right:6px;"></i> REPROVADO
            </button>
            <button type="button" onclick="avaliarExame('aprovado')" class="status-btn" style="background:#dcfce7; color:#166534; border-color:#bbf7d0; padding: 15px;">
                <i class="fa-solid fa-check" style="margin-right:6px;"></i> APROVADO
            </button>
        </div>
        <p id="modal_exame_status" style="margin-top: 1rem; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase;"></p>
    </div>
</div>

<script>
let inscricaoExameAtual = null;

function abrirModalExame(inscricaoId, nomeAluno, tituloEvento, faixaPretendida, tamanhoFaixa) {
    inscricaoExameAtual = inscricaoId;
    document.getElementById('modal_exame_aluno').innerText = nomeAluno;
    document.getElementById('modal_exame_evento').innerText = tituloEvento;
    document.getElementById('modal_exame_faixa').innerText = faixaPretendida + (tamanhoFaixa ? ' - TAM: ' + tamanhoFaixa : '');
    document.getElementById('modal_exame_status').innerText = '';

    const provaTemplate = document.getElementById('prova-' + inscricaoId);
    document.getElementById('modal_exame_prova').innerHTML = provaTemplate ? provaTemplate.innerHTML : '';
    atualizarNotaExame();

    document.getElementById('modal_exame').style.display = 'flex';
}

function atualizarNotaExame() {
    const checks = document.querySelectorAll('#modal_exame_prova .prova-item-check');
    const total = checks.length;
    const marcados = document.querySelectorAll('#modal_exame_prova .prova-item-check:checked').length;
    const nota = total > 0 ? (marcados / total) * 10 : 0;
    document.getElementById('modal_exame_nota').innerText = nota.toFixed(1).replace('.', ',');
}

async function avaliarExame(resultado) {
    const statusEl = document.getElementById('modal_exame_status');
    statusEl.style.color = '#3b82f6';
    statusEl.innerText = 'Salvando...';

    const checks = document.querySelectorAll('#modal_exame_prova .prova-item-check');
    const total = checks.length;
    const marcados = Array.from(checks).filter(c => c.checked).map(c => c.value);
    const nota = total > 0 ? (marcados.length / total) * 10 : null;

    try {
        const response = await fetch('api_avaliar_exame.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ inscricao_id: inscricaoExameAtual, resultado: resultado, nota: nota, itens_marcados: marcados })
        });
        const result = await response.json();
        if (result.success) {
            statusEl.style.color = 'var(--primary-green)';
            statusEl.innerText = 'Resultado registrado!';
            setTimeout(() => { window.location.reload(); }, 900);
        } else {
            statusEl.style.color = '#ef4444';
            statusEl.innerText = result.message || 'Erro ao salvar.';
        }
    } catch (error) {
        statusEl.style.color = '#ef4444';
        statusEl.innerText = 'Erro de conexão.';
    }
}

async function setPresenca(alunoId, status) {
    const card = document.querySelector(`.aluno-card-chamada[data-aluno-id="${alunoId}"]`);
    const buttons = card.querySelectorAll('.status-btn');
    const saveStatus = document.getElementById('save-status');
    
    // Feedback visual imediato
    buttons.forEach(btn => btn.classList.remove('active'));
    card.querySelector(`.status-btn[data-status="${status}"]`).classList.add('active');
    
    saveStatus.style.display = 'block';
    saveStatus.style.color = '#3b82f6';
    saveStatus.innerText = 'Salvando...';

    try {
        const response = await fetch('api_chamada_salvar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                turma_id: <?php echo $turma_id; ?>,
                data_aula: '<?php echo $data_aula; ?>',
                aluno_id: alunoId,
                status: status
            })
        });

        const result = await response.json();
        if (result.success) {
            saveStatus.style.color = 'var(--primary-green)';
            saveStatus.innerText = 'Presença registrada!';
            setTimeout(() => { saveStatus.style.display = 'none'; }, 2000);
        } else {
            alert('Erro ao salvar: ' + result.message);
            saveStatus.style.color = '#ef4444';
            saveStatus.innerText = 'Erro ao salvar!';
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Erro na conexão com o servidor.');
        saveStatus.style.color = '#ef4444';
        saveStatus.innerText = 'Erro de conexão!';
    }
}
</script>

<?php include 'footer.php'; ?>
