<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('alunos_visualizar')) {
    header('Location: administrativo_dashboard.php?erro=sem_permissao');
    exit;
}

garantirColunaTipoCadastroAluno($pdo);

// Buscar academias para o filtro
$stmt_acads = $pdo->prepare("SELECT id, nome FROM academias WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
$stmt_acads->execute([$unidade_id]);
$acads_filtro = $stmt_acads->fetchAll();

$academia_id_filtro = $_GET['academia_id'] ?? null;
$busca_filtro = $_GET['busca'] ?? '';
$status_filtro = $_GET['status'] ?? '';
$letra_filtro = strtoupper(trim($_GET['letra'] ?? ''));
if ($letra_filtro && (!preg_match('/^[A-Z]$/', $letra_filtro))) {
    $letra_filtro = '';
}

$where_academia = "";
$where_busca = "";
$where_status = "";
$where_letra = "";

$params = [$unidade_id];

if ($academia_id_filtro) {
    $where_academia = " AND a.academia_id = ? ";
    $params[] = $academia_id_filtro;
}

if ($busca_filtro) {
    $where_busca = " AND a.nome_completo LIKE ? ";
    $params[] = "%{$busca_filtro}%";
}

if ($status_filtro) {
    $where_status = " AND a.status = ? ";
    $params[] = $status_filtro;
}

if ($letra_filtro) {
    $where_letra = " AND a.nome_completo LIKE ? ";
    $params[] = "{$letra_filtro}%";
}

// Buscar todos os alunos e suas turmas
$stmt = $pdo->prepare("
    SELECT a.*, GROUP_CONCAT(t.nome SEPARATOR ', ') as turmas_nomes, ac.nome as academia_nome
    FROM alunos a
    LEFT JOIN turma_alunos ta ON a.id = ta.aluno_id
    LEFT JOIN turmas t ON ta.turma_id = t.id
    LEFT JOIN academias ac ON a.academia_id = ac.id
    WHERE a.unidade_id = ? $where_academia $where_busca $where_status $where_letra
    GROUP BY a.id
    ORDER BY a.nome_completo ASC
");
$stmt->execute($params);
$alunos = $stmt->fetchAll();
$total_alunos = count($alunos);

// Query string base (sem "letra") para montar os links do filtro alfabético preservando os demais filtros
$query_params_base = array_filter([
    'busca' => $busca_filtro,
    'academia_id' => $academia_id_filtro,
    'status' => $status_filtro,
], function ($v) { return $v !== null && $v !== ''; });

$custom_title = "Alunos & Atletas";
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
                Base de Alunos
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Consulte e gerencie a ficha cadastral de todos os alunos da unidade.
            </p>
            <div style="margin-top: 12px; display: inline-flex; align-items: center; gap: 8px; background: var(--primary-green); color: #fff; padding: 6px 16px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.05em;">
                <i class="fa-solid fa-users"></i>
                <?php echo $total_alunos; ?> <?php echo $total_alunos == 1 ? 'ALUNO CADASTRADO' : 'ALUNOS CADASTRADOS'; ?>
            </div>
        </div>

    
        <?php if (temPermissaoModulo('alunos_criar')): ?>
        <div style="display: flex; gap: 10px;">
            <a href="novo_aluno.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-user-plus" style="margin-right: 8px;"></i> Novo Aluno
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="alunos.php" class="tab-item-sq active">Alunos</a>
    <a href="equipe.php" class="tab-item-sq">RH</a>
    <a href="academias.php" class="tab-item-sq">Unidades</a>
    <a href="turmas.php" class="tab-item-sq">Turmas</a>
    <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
    <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
</div>

    

    <!-- Filtros -->
    <div style="margin-bottom: 30px;">
        <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" name="busca" value="<?php echo htmlspecialchars($busca_filtro); ?>" placeholder="Buscar por nome do aluno..." style="width: 100%; height: 45px; background: #fff; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase;">
            </div>

            <div style="width: 250px; position: relative;">
                <select name="academia_id" onchange="this.form.submit()" style="width: 100%; height: 45px; background: #fff; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                    <option value="">FILTRAR POR UNIDADE / ACADEMIA</option>
                    <?php foreach ($acads_filtro as $acad): ?>
                        <option value="<?php echo $acad['id']; ?>" <?php echo $academia_id_filtro == $acad['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($acad['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="width: 200px; position: relative;">
                <select name="status" onchange="this.form.submit()" style="width: 100%; height: 45px; background: #fff; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem;">
                    <option value="">TODOS OS STATUS</option>
                    <option value="ativo" <?php echo $status_filtro == 'ativo' ? 'selected' : ''; ?>>ATIVO</option>
                    <option value="inativo" <?php echo $status_filtro == 'inativo' ? 'selected' : ''; ?>>INATIVO</option>
                </select>
            </div>

            <button type="submit" class="btn-sq" style="width: auto; padding: 0 20px; height: 45px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-magnifying-glass"></i> Buscar
            </button>

            <?php if ($academia_id_filtro || $busca_filtro || $status_filtro || $letra_filtro): ?>
                <a href="alunos.php" class="btn-sq-light" style="width: 45px; height: 45px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Limpar Filtros">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Filtro por letra do alfabeto -->
    <div style="margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
        <a href="alunos.php?<?php echo htmlspecialchars(http_build_query($query_params_base)); ?>"
           style="padding: 6px 12px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; border: 1px solid var(--border-color); background: <?php echo $letra_filtro === '' ? 'var(--text-dark)' : '#fff'; ?>; color: <?php echo $letra_filtro === '' ? '#fff' : 'var(--text-dark)'; ?>; text-decoration: none;">
            Todos
        </a>
        <?php foreach (range('A', 'Z') as $letra): ?>
            <a href="alunos.php?<?php echo htmlspecialchars(http_build_query(array_merge($query_params_base, ['letra' => $letra]))); ?>"
               style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; border: 1px solid var(--border-color); background: <?php echo $letra_filtro === $letra ? 'var(--text-dark)' : '#fff'; ?>; color: <?php echo $letra_filtro === $letra ? '#fff' : 'var(--text-dark)'; ?>; text-decoration: none;">
                <?php echo $letra; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tabela Square -->
    <div class="dashboard-container" style="padding: 0;">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="table-sq" style="width: 100%; min-width: 720px;">
            <thead>
                <tr>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);">Atleta / Alunos</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);">Unidade & Turmas</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);">Graduação</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted);">Status</th>
                    <th style="padding: 20px 30px; text-transform: uppercase; font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-align: right;">Ficha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alunos as $aluno): ?>
                    <tr>
                        <td style="padding: 15px 30px;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <?php if ($aluno['foto']): ?>
                                    <img src="<?php echo getUploadURL('alunos', $aluno['foto'], $aluno['academia_id']); ?>" style="width: 40px; height: 40px; object-fit: cover; border: 1px solid var(--border-color);">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; background: #fafafa; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: #cbd5e1;">
                                        <i class="fa-solid fa-user" style="font-size: var(--fs-base);"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 900; color: var(--text-dark); font-size: var(--fs-base); text-transform: uppercase;">
                                        <?php echo htmlspecialchars($aluno['nome_completo']); ?>
                                        <?php if (($aluno['tipo_cadastro'] ?? 'fixo') === 'visitante'): ?>
                                            <span style="background:#f59e0b; color:#fff; font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px; margin-left:6px; vertical-align:middle;">VISITANTE</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 700;">
                                        <?php echo htmlspecialchars($aluno['telefone'] ?: 'SEM CONTATO'); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="font-weight: 800; color: var(--text-dark); font-size: var(--fs-sm); text-transform: uppercase;">
                                <?php echo htmlspecialchars($aluno['academia_nome'] ?: 'NÃO DEFINIDA'); ?>
                            </div>
                            <div style="font-size: var(--fs-xs); color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-top: 3px;">
                                <?php echo htmlspecialchars($aluno['turmas_nomes'] ?: 'NENHUMA TURMA'); ?>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 12px; height: 12px; border: 1px solid #ddd; background: <?php
                                    $f = strtolower($aluno['faixa']);
                                    echo (strpos($f, 'branca') !== false ? '#fff' :
                                         (strpos($f, 'azul') !== false ? '#3b82f6' :
                                         (strpos($f, 'roxa') !== false ? '#8b5cf6' :
                                         (strpos($f, 'marrom') !== false ? '#78350f' :
                                         (strpos($f, 'preta') !== false ? '#08153a' : '#fafafa')))));
                                ?>;"></div>
                                <span style="font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase;"><?php echo htmlspecialchars($aluno['faixa'] ?: 'N/A'); ?></span>
                            </div>
                        </td>
                        <td style="padding: 15px 30px;">
                            <span style="background: <?php echo $aluno['status'] == 'ativo' ? 'var(--primary-green)' : '#ef4444'; ?>; color: #fff; padding: 4px 10px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;">
                                <?php echo strtoupper($aluno['status']); ?>
                            </span>
                        </td>
                        <td style="padding: 15px 30px; text-align: right;">
                            <a href="editar_aluno.php?id=<?php echo $aluno['id']; ?>" class="btn-sq" style="width: auto; padding: 0 15px; height: 35px; display: inline-flex; align-items: center; justify-content: center; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; gap: 8px;">
                                <i class="fa-solid fa-address-card" style="font-size: var(--fs-sm);"></i> Gerenciar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($alunos)): ?>
                    <tr>
                        <td colspan="5" style="padding: 100px; text-align: center; color: var(--text-muted); font-weight: 600;">
                            <i class="fa-solid fa-users-slash" style="font-size: 48px; opacity: 0.1; display: block; margin-bottom: 20px;"></i>
                            Nenhum aluno cadastrado nesta unidade.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>