<?php
require_once '../config.php';

$unidade_id = getUnidadeId();

if (!moduloAtivo('competicoes')) {
    echo "<div class='alert alert-danger' style='margin:2rem; padding:1.5rem; background:#fee2e2; color:#991b1b; border-radius:0px;'>Acesso Negado: O módulo de Eventos não está ativo para o seu plano.</div>";
    include 'footer.php';
    exit;
}

// Auto-migração: coluna de vínculo quando um visitante é transformado em aluno
try {
    $pdo->query("SELECT aluno_id FROM eventos_visitantes LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE eventos_visitantes ADD COLUMN aluno_id INT DEFAULT NULL, ADD FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE SET NULL");
    } catch (Exception $ex) {}
}

$filtro_tipo = $_GET['tipo'] ?? '';
$busca = trim($_GET['busca'] ?? '');

$labels_tipo = ['exame' => 'Exame de Faixa', 'torneio' => 'Torneio', 'oficial' => 'Competição Oficial'];

$sql = "SELECT ev.*
        FROM eventos_visitantes ev
        WHERE ev.unidade_id = ?" .
        ($filtro_tipo ? " AND ev.evento_tipo = ?" : "") .
        ($busca ? " AND (ev.nome LIKE ? OR ev.cpf LIKE ?)" : "") .
        " ORDER BY ev.criado_em DESC";

$params = [$unidade_id];
if ($filtro_tipo) { $params[] = $filtro_tipo; }
if ($busca) { $params[] = '%' . $busca . '%'; $params[] = '%' . preg_replace('/\D/', '', $busca) . '%'; }

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visitantes = $stmt->fetchAll();

// Busca o nome/data do evento de origem de cada visitante (tabelas diferentes por tipo)
$tabelas_evento = [
    'exame'   => ['tabela' => 'eventos_graduacao', 'nome_col' => 'titulo', 'data_col' => 'data_evento'],
    'torneio' => ['tabela' => 'competicoes', 'nome_col' => 'nome', 'data_col' => 'data_evento'],
    'oficial' => ['tabela' => 'competicoes_oficiais', 'nome_col' => 'nome', 'data_col' => 'data_inicio'],
];
$cache_eventos = [];
foreach ($visitantes as &$v) {
    $tipo_cfg = $tabelas_evento[$v['evento_tipo']] ?? null;
    $chave = $v['evento_tipo'] . '_' . $v['evento_id'];
    if ($tipo_cfg && !isset($cache_eventos[$chave])) {
        $stmt_ev = $pdo->prepare("SELECT {$tipo_cfg['nome_col']} AS nome, {$tipo_cfg['data_col']} AS data_evento FROM {$tipo_cfg['tabela']} WHERE id = ?");
        $stmt_ev->execute([$v['evento_id']]);
        $cache_eventos[$chave] = $stmt_ev->fetch();
    }
    $v['evento_nome'] = $cache_eventos[$chave]['nome'] ?? '—';
    $v['evento_data'] = $cache_eventos[$chave]['data_evento'] ?? null;
}
unset($v);

$custom_title = "Visitantes";
include 'header.php';
?>

<div class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Visitantes
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Pessoas que se inscreveram em eventos pelo site sem serem alunas da unidade
            </p>
        </div>
    </div>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="eventos_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="competicoes_oficiais.php" class="tab-item-sq">Oficiais</a>
        <a href="competicoes.php" class="tab-item-sq">Torneios</a>
        <a href="faixas.php" class="tab-item-sq">Exames</a>
        <a href="eventos_visitantes.php" class="tab-item-sq active">Visitantes</a>
        <a href="delegacoes.php" class="tab-item-sq">Delegações</a>
        <a href="relatorios_eventos.php" class="tab-item-sq">Relatórios</a>
    </div>

    <form method="GET" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
        <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar por nome ou CPF..."
            style="flex:1; min-width:220px; padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
        <select name="tipo" style="padding:12px 16px; border:1px solid var(--border-color); font-size:var(--fs-sm);">
            <option value="">Todos os tipos</option>
            <?php foreach ($labels_tipo as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $filtro_tipo === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-sq" style="width:auto; padding:12px 25px;">Filtrar</button>
        <?php if ($filtro_tipo || $busca): ?>
            <a href="eventos_visitantes.php" class="btn-sq-light" style="width:auto; padding:12px 25px;">Limpar</a>
        <?php endif; ?>
    </form>

    <div style="background: white; border: 1px solid var(--border-color); padding: 0; margin-bottom: 30px;">
        <?php if (empty($visitantes)): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 5rem 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.1;"><i class="fa-solid fa-user-group"></i></div>
                <p style="font-weight: 600; font-size: 1rem;">Nenhum visitante encontrado.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Nome</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">CPF</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Contato</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Evento</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Inscrito em</th>
                            <th style="padding: 15px 20px; font-size: var(--fs-xs); color: var(--text-muted); font-weight: 800; text-transform: uppercase; text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitantes as $v): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 18px 20px;">
                                    <div style="font-weight: 800; color: var(--text-dark);"><?php echo htmlspecialchars($v['nome']); ?></div>
                                    <?php if (!empty($v['faixa'])): ?>
                                        <div style="font-size: var(--fs-xs); color: var(--text-muted);">Faixa <?php echo htmlspecialchars($v['faixa']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 18px 20px; font-size: var(--fs-sm);"><?php echo htmlspecialchars(preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $v['cpf'])); ?></td>
                                <td style="padding: 18px 20px; font-size: var(--fs-sm);">
                                    <div><?php echo htmlspecialchars($v['telefone']); ?></div>
                                    <?php if (!empty($v['email'])): ?><div style="color: var(--text-muted); font-size: var(--fs-xs);"><?php echo htmlspecialchars($v['email']); ?></div><?php endif; ?>
                                </td>
                                <td style="padding: 18px 20px; font-size: var(--fs-sm);">
                                    <div><?php echo htmlspecialchars($v['evento_nome']); ?></div>
                                    <div style="font-size: var(--fs-xs); color: var(--text-muted);">
                                        <?php echo $labels_tipo[$v['evento_tipo']] ?? $v['evento_tipo']; ?>
                                        <?php echo $v['evento_data'] ? ' · ' . date('d/m/Y', strtotime($v['evento_data'])) : ''; ?>
                                    </div>
                                </td>
                                <td style="padding: 18px 20px; font-size: var(--fs-sm);"><?php echo date('d/m/Y H:i', strtotime($v['criado_em'])); ?></td>
                                <td style="padding: 18px 20px; text-align: right;">
                                    <?php if (!empty($v['aluno_id'])): ?>
                                        <span class="badge-sq badge-ativo">Já é aluno</span>
                                    <?php else: ?>
                                        <form action="converter_visitante.php" method="POST" onsubmit="return confirm('Transformar este visitante em aluno da unidade?');" style="display:inline;">
                                            <input type="hidden" name="visitante_id" value="<?php echo (int) $v['id']; ?>">
                                            <button type="submit" class="btn-sq-light" style="width:auto; padding:8px 16px; font-size:var(--fs-xs);">
                                                <i class="fa-solid fa-user-plus" style="margin-right:6px;"></i> Transformar em aluno
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
