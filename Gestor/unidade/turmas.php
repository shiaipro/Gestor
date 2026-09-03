<?php
require_once '../config.php';

if (!moduloAtivo('turmas')) {
    include 'header.php';
    echo "<div class='dashboard-container' style='padding:40px; text-align:center;'>
            <i class='fa-solid fa-lock' style='font-size:48px; opacity:0.1; margin-bottom:20px;'></i>
            <h2 style='font-weight:900; text-transform:uppercase;'>Acesso Negado</h2>
            <p style='color:var(--text-muted);'>O módulo de Turmas não está ativo para o seu plano.</p>
          </div>";
    include 'footer.php';
    exit;
}

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('turmas_visualizar')) {
    header('Location: administrativo_dashboard.php?erro=sem_permissao');
    exit;
}

// Buscar academias para o filtro
$stmt_acads = $pdo->prepare("SELECT id, nome FROM academias WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
$stmt_acads->execute([$unidade_id]);
$acads_filtro = $stmt_acads->fetchAll();

$academia_id_filtro = $_GET['academia_id'] ?? null;
$where_academia = "";
$params = [$unidade_id];

if ($academia_id_filtro) {
    $where_academia = " AND t.academia_id = ? ";
    $params[] = $academia_id_filtro;
}

// Se o usuário logado está marcado como instrutor de alguma turma, filtrar apenas as suas
$where_instrutor = "";
$is_instrutor = restringirVisaoAsProprisTurmas();
if ($is_instrutor) {
    $where_instrutor = " AND t.instrutor_equipe_id IN (SELECT id FROM unidade_equipe WHERE unidade_id = ? AND usuario_id = ?) ";
    $params[] = $unidade_id;
    $params[] = $_SESSION['usuario_id'];
}

// Buscar todas as turmas desta unidade com contagem de alunos
$stmt = $pdo->prepare("
    SELECT t.*, ac.nome as academia_nome,
    (SELECT COUNT(*) FROM turma_alunos ta JOIN alunos a ON ta.aluno_id = a.id WHERE ta.turma_id = t.id AND a.status = 'ativo') as total_inscritos
    FROM turmas t 
    LEFT JOIN academias ac ON t.academia_id = ac.id
    WHERE t.unidade_id = ? $where_academia $where_instrutor
    ORDER BY t.horario ASC
");
$stmt->execute($params);
$turmas = $stmt->fetchAll();

// Buscar slug da unidade para links de inscrição
$stmt_slug = $pdo->prepare("SELECT slug FROM unidades WHERE id = ?");
$stmt_slug->execute([$unidade_id]);
$unidade_slug = $stmt_slug->fetchColumn();

$dias_map = [
    'seg' => 'SEG',
    'ter' => 'TER',
    'qua' => 'QUA',
    'qui' => 'QUI',
    'sex' => 'SEX',
    'sab' => 'SAB',
    'dom' => 'DOM'
];

$custom_title = "Gestão de Turmas";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'sem_permissao'): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> ACESSO NEGADO: Você não tem permissão para realizar esta ação.
        </div>
    <?php endif; ?>

    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Cronograma de Aulas
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gestão de horários, locais e instrutores
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <?php if (temPermissaoModulo('chamadas_visualizar')): ?>
            <a href="relatorio_chamadas.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-chart-line" style="margin-right: 8px;"></i> Relatório de Chamadas
            </a>
            <?php endif; ?>
            <?php if ($unidade_slug): ?>
            <button type="button" onclick="copiarInscricao('https://shiaipro.com.br/<?php echo $unidade_slug; ?>/inscricao')" class="btn-sq-outline" style="width: auto; padding: 12px 25px;" title="Link único onde o aluno escolhe a turma">
                <i class="fa-solid fa-link" style="margin-right: 8px;"></i> Link Geral de Inscrição
            </button>
            <?php endif; ?>
            <?php if (temPermissaoModulo('turmas_criar')): ?>
            <a href="nova_turma.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Nova Turma
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navegação por Abas -->
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

    

    <!-- Filtros e Busca -->
    <div class="dashboard-container" style="padding: 20px 25px; margin-bottom: 25px; background: #fafafa;">
        <form method="GET" style="display: flex; gap: 20px; align-items: center; justify-content: flex-end;">
            <div style="flex: 1;">
                <h3 style="margin: 0; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; color: var(--text-dark);">
                    <i class="fa-solid fa-filter" style="margin-right: 8px; color: var(--primary-green);"></i> Filtrar Turmas
                </h3>
            </div>
            <div style="width: 320px; display: flex; gap: 10px;">
                <select name="academia_id" class="form-control" onchange="this.form.submit()" 
                    style="height: 50px; font-size: var(--fs-xs); font-weight: 800; border-radius: 0; border: 2px solid var(--border-color); text-transform: uppercase; letter-spacing: 0.05em;">
                    <option value="">TODAS AS ACADEMIAS / LOCAIS</option>
                    <?php foreach ($acads_filtro as $acad): ?>
                        <option value="<?php echo $acad['id']; ?>" <?php echo $academia_id_filtro == $acad['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(strtoupper($acad['nome'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($academia_id_filtro): ?>
                    <a href="turmas.php" class="btn-sq-light" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; padding: 0;" title="Limpar Filtro">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Área de Conteúdo Principal -->
    <div class="dashboard-container" style="padding: 0;">
        <div style="padding: 20px 30px; border-bottom: 1px solid var(--border-color); background: #fff;">
            <h3 style="margin: 0; font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.1em;">Cronograma Cadastrado</h3>
        </div>

        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Turma / Local</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Cronograma</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Investimento</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); letter-spacing: 0.1em;">Matrículas</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right; letter-spacing: 0.1em;">Config</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($turmas as $turma): ?>
                    <tr>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;">
                                <?php echo htmlspecialchars($turma['nome']); ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700; margin-top: 5px; text-transform: uppercase; display: flex; align-items: center; flex-wrap: wrap;">
                                <span style="display: inline-flex; align-items: center;"><i class="fa-solid fa-user-tie" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($turma['instrutor'] ?: 'NÃO DEFINIDO'); ?></span>
                                <span style="margin: 0 10px; opacity: 0.2;">|</span>
                                <span style="display: inline-flex; align-items: center;"><i class="fa-solid fa-location-dot" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($turma['academia_nome'] ?: 'NÃO DEFINIDO'); ?></span>
                                <?php if (!empty($turma['instrucoes_pdf'])): ?>
                                <span style="margin: 0 10px; opacity: 0.2;">|</span>
                                <a href="<?php echo getUploadURL('documentos', $turma['instrucoes_pdf']); ?>" target="_blank" style="text-decoration: none; color: #ef4444; font-weight: 900; font-size: var(--fs-xs); display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-file-pdf"></i> PLANO DE AULA
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); margin-bottom: 10px;">
                                <?php echo date('H:i', strtotime($turma['horario'])); ?>
                            </div>
                            <div style="display: flex; gap: 5px;">
                                <?php
                                $dias_selecionados = explode(',', $turma['dias_semana']);
                                foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $dia_cod):
                                    $ativo = in_array($dia_cod, $dias_selecionados);
                                    if ($ativo):
                                    ?>
                                        <span style="font-size: var(--fs-xs); font-weight: 900; padding: 3px 8px; background: #08153a; color: #fff; letter-spacing: 0.05em;">
                                            <?php echo $dias_map[$dia_cod]; ?>
                                        </span>
                                    <?php endif; endforeach; ?>
                            </div>
                        </td>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base);">
                                R$ <?php echo number_format($turma['preco_mensal'], 2, ',', '.'); ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-top: 3px;">
                                <?php echo $turma['frequencia']; ?>
                            </div>
                        </td>
                        <td style="padding: 20px 30px;">
                            <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base);">
                                <?php echo $turma['total_inscritos']; ?> / <?php echo $turma['capacidade_max'] ?: '∞'; ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-top: 3px;">Vagas Inscritas</div>
                        </td>
                         <td style="padding: 20px 30px; text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                <?php if (!empty($turma['uuid_inscricao'])): ?>
                                <?php
                                    if ($unidade_slug) {
                                        $link_insc = 'https://shiaipro.com.br/' . $unidade_slug . '/inscricao/' . $turma['uuid_inscricao'];
                                    } else {
                                        $link_insc = 'https://shiaipro.com.br/Gestor/inscricao.php?t=' . $turma['uuid_inscricao'];
                                    }
                                ?>
                                <button onclick="copiarInscricao('<?php echo $link_insc; ?>')" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Copiar Link de Inscrição">
                                    <i class="fa-solid fa-link"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (temPermissaoModulo('chamadas_visualizar')): ?>
                                <a href="chamada.php?turma_id=<?php echo $turma['id']; ?>" class="btn-sq" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; background: var(--primary-green); color: #08153a; border: none;" title="Fazer Chamada">
                                    <i class="fa-solid fa-clipboard-user"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (temPermissaoModulo('turmas_editar')): ?>
                                <a href="editar_turma.php?id=<?php echo $turma['id']; ?>" class="btn-sq-light" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-gear"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($turmas)): ?>
                    <tr><td colspan="5" style="padding: 80px; text-align: center; color: var(--text-muted); font-weight: 900;">NENHUMA TURMA ENCONTRADA.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<script>
function copiarInscricao(link) {
    navigator.clipboard.writeText(link).then(function() {
        alert("Link de inscrição copiado para a área de transferência!");
    }, function() {
        // Fallback simples
        var tempInput = document.createElement("input");
        tempInput.value = link;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
        alert("Link de inscrição copiado!");
    });
}
</script>

<?php include 'footer.php'; ?>