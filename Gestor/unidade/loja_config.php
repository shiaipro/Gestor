<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// ─── Criar tabelas de atributos ───────────────────────────────────────────────
$tabelas_sql = [
    'cms_loja_idades'   => "CREATE TABLE IF NOT EXISTS cms_loja_idades   (id INT AUTO_INCREMENT PRIMARY KEY, unidade_id INT NOT NULL, nome VARCHAR(100) NOT NULL, ordem INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'cms_loja_generos'  => "CREATE TABLE IF NOT EXISTS cms_loja_generos  (id INT AUTO_INCREMENT PRIMARY KEY, unidade_id INT NOT NULL, nome VARCHAR(100) NOT NULL, ordem INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'cms_loja_tamanhos' => "CREATE TABLE IF NOT EXISTS cms_loja_tamanhos (id INT AUTO_INCREMENT PRIMARY KEY, unidade_id INT NOT NULL, nome VARCHAR(100) NOT NULL, ordem INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'cms_loja_alturas'  => "CREATE TABLE IF NOT EXISTS cms_loja_alturas  (id INT AUTO_INCREMENT PRIMARY KEY, unidade_id INT NOT NULL, nome VARCHAR(100) NOT NULL, ordem INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'cms_loja_cores'    => "CREATE TABLE IF NOT EXISTS cms_loja_cores    (id INT AUTO_INCREMENT PRIMARY KEY, unidade_id INT NOT NULL, nome VARCHAR(100) NOT NULL, hex VARCHAR(7) DEFAULT '#ffffff', ordem INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($tabelas_sql as $tbl => $sql) {
    try { $pdo->query("SELECT id FROM $tbl LIMIT 1"); } catch (Exception $e) { $pdo->exec($sql); }
}

$active_tab  = $_GET['tab']  ?? 'geral';
$active_attr = $_GET['attr'] ?? 'idade';
$mensagem    = '';

// ─── Mapa de atributos ────────────────────────────────────────────────────────
$attr_map = [
    'idade'   => ['tabela' => 'cms_loja_idades',   'label' => 'Idade',   'icone' => 'fa-baby',          'tem_cor' => false, 'placeholder' => 'Ex: Adulto, Infantil, Juvenil...'],
    'genero'  => ['tabela' => 'cms_loja_generos',  'label' => 'Gênero',  'icone' => 'fa-venus-mars',    'tem_cor' => false, 'placeholder' => 'Ex: Todos, Masculino, Feminino, Unissex...'],
    'tamanho' => ['tabela' => 'cms_loja_tamanhos', 'label' => 'Tamanho', 'icone' => 'fa-ruler',         'tem_cor' => false, 'placeholder' => 'Ex: PP, P, M, G, GG, A1, A2, 38, 40...'],
    'altura'  => ['tabela' => 'cms_loja_alturas',  'label' => 'Altura',  'icone' => 'fa-arrows-up-down','tem_cor' => false, 'placeholder' => 'Ex: 160cm, 170cm, 180cm, Curto, Longo...'],
    'cor'     => ['tabela' => 'cms_loja_cores',    'label' => 'Cor',     'icone' => 'fa-palette',       'tem_cor' => true,  'placeholder' => 'Ex: Branco, Azul Marinho, Preto Fosco...'],
];

if (!isset($attr_map[$active_attr])) $active_attr = 'idade';
$cur        = $attr_map[$active_attr];
$cur_tabela = $cur['tabela'];

// ─── EXCLUIR atributo ─────────────────────────────────────────────────────────
if (isset($_GET['del_attr']) && $active_tab === 'grades') {
    $del_id = (int)$_GET['del_attr'];
    $pdo->prepare("DELETE FROM $cur_tabela WHERE id = ? AND unidade_id = ?")->execute([$del_id, $unidade_id]);
    header("Location: loja_config.php?tab=grades&attr=$active_attr&msg=excluido");
    exit;
}

// ─── SALVAR atributo ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_attr'])) {
    $attr_id = !empty($_POST['attr_id']) ? (int)$_POST['attr_id'] : null;
    $nome    = strtoupper(trim($_POST['attr_nome'] ?? ''));
    $hex     = $cur['tem_cor'] ? trim($_POST['attr_hex'] ?? '#ffffff') : null;

    if ($nome !== '') {
        if ($attr_id) {
            if ($cur['tem_cor']) {
                $pdo->prepare("UPDATE $cur_tabela SET nome=?, hex=? WHERE id=? AND unidade_id=?")->execute([$nome, $hex, $attr_id, $unidade_id]);
            } else {
                $pdo->prepare("UPDATE $cur_tabela SET nome=? WHERE id=? AND unidade_id=?")->execute([$nome, $attr_id, $unidade_id]);
            }
            $msg_txt = $cur['label'] . " atualizado com sucesso!";
        } else {
            if ($cur['tem_cor']) {
                $pdo->prepare("INSERT INTO $cur_tabela (unidade_id, nome, hex) VALUES (?,?,?)")->execute([$unidade_id, $nome, $hex]);
            } else {
                $pdo->prepare("INSERT INTO $cur_tabela (unidade_id, nome) VALUES (?,?)")->execute([$unidade_id, $nome]);
            }
            $msg_txt = $cur['label'] . " adicionado com sucesso!";
        }
    } else {
        $msg_txt = "Nome não pode estar vazio.";
    }
    header("Location: loja_config.php?tab=grades&attr=$active_attr&msg=sucesso&txt=" . urlencode($msg_txt));
    exit;
}

// ─── SALVAR Config Geral ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config_geral'])) {
    $pdo->prepare("UPDATE loja_config SET nome_loja=?, email_notificacao=?, moeda=?, metodo_pagamento_ativo=?, status_loja=? WHERE unidade_id=?")
        ->execute([$_POST['nome_loja'], $_POST['email_notificacao'], $_POST['moeda'], $_POST['metodo_pagamento'], $_POST['status_loja'] ?? 'aberta', $unidade_id]);
    header("Location: loja_config.php?tab=geral&msg=sucesso&txt=" . urlencode("Configurações atualizadas com sucesso!"));
    exit;
}

// ─── Mensagens via GET ────────────────────────────────────────────────────────
if (isset($_GET['msg'])) {
    $mensagem = $_GET['msg'] === 'excluido' ? "Item excluído com sucesso!" : (htmlspecialchars($_GET['txt'] ?? "Operação realizada!"));
}

// ─── Carregar dados ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM loja_config WHERE unidade_id = ?");
$stmt->execute([$unidade_id]);
$config = $stmt->fetch();

// Carregar itens do atributo atual
$stmt_list = $pdo->prepare("SELECT * FROM $cur_tabela WHERE unidade_id = ? ORDER BY ordem ASC, id ASC");
$stmt_list->execute([$unidade_id]);
$attr_items = $stmt_list->fetchAll();

// Contagens para badges
$counts = [];
foreach ($attr_map as $k => $info) {
    try {
        $c = $pdo->prepare("SELECT COUNT(*) FROM {$info['tabela']} WHERE unidade_id = ?");
        $c->execute([$unidade_id]);
        $counts[$k] = (int)$c->fetchColumn();
    } catch (Exception $e) { $counts[$k] = 0; }
}

// Item em edição
$edit_item = null;
if (isset($_GET['edit_attr'])) {
    $ei = $pdo->prepare("SELECT * FROM $cur_tabela WHERE id = ? AND unidade_id = ?");
    $ei->execute([(int)$_GET['edit_attr'], $unidade_id]);
    $edit_item = $ei->fetch();
}

$custom_title = "Configurações da Loja";
include 'header.php';
?>

<style>
    /* ── Abas principais ── */
    .tab-main-btn { padding:12px 22px; border:none; background:none; font-size:0.72rem; font-weight:900; text-transform:uppercase; letter-spacing:0.08em; cursor:pointer; border-bottom:3px solid transparent; color:var(--text-muted); transition:all 0.2s; }
    .tab-main-btn.active { border-bottom-color:#133080; color:#133080; }
    .tab-main-content { display:none; }
    .tab-main-content.active { display:block; }

    /* ── Sub-abas de atributos ── */
    .attr-tabs-nav { display:flex; gap:0; border-bottom:2px solid var(--border-color); margin-bottom:30px; overflow-x:auto; }
    .attr-tab-btn { display:inline-flex; align-items:center; gap:8px; padding:12px 20px; border:none; background:none; font-size:0.70rem; font-weight:900; text-transform:uppercase; letter-spacing:0.06em; cursor:pointer; border-bottom:3px solid transparent; color:var(--text-muted); transition:all 0.2s; white-space:nowrap; text-decoration:none; }
    .attr-tab-btn.active { border-bottom-color:var(--primary-green); color:#0f172a; background:#f0fdf4; }
    .attr-tab-btn:hover:not(.active) { color:#133080; border-bottom-color:#c7d2fe; background:#f8faff; }

    /* ── Badge de contagem ── */
    .attr-cnt { display:inline-block; background:#133080; color:var(--primary-green); font-size:0.58rem; font-weight:900; padding:2px 7px; border-radius:20px; min-width:22px; text-align:center; }
    .attr-cnt.zero { background:#e2e8f0; color:#94a3b8; }

    /* ── Linha de item ── */
    .item-row { display:flex; align-items:center; gap:14px; background:#fff; border:1px solid var(--border-color); padding:13px 20px; transition:all 0.2s; margin-bottom:0; }
    .item-row:hover { border-color:#133080; background:#f8faff; box-shadow:0 2px 8px rgba(19,48,128,0.07); transform:translateX(2px); }
    .item-row .item-ordem { width:28px; text-align:center; font-size:0.65rem; font-weight:900; color:#94a3b8; flex-shrink:0; }
    .item-row .item-nome { flex:1; font-size:var(--fs-sm); font-weight:800; text-transform:uppercase; color:var(--text-dark); }
    .item-row .item-hex  { font-size:0.65rem; font-weight:700; color:var(--text-muted); font-family:monospace; }
    .item-row .item-actions { display:flex; gap:6px; flex-shrink:0; }

    /* ── Swatch de cor ── */
    .cor-swatch { width:34px; height:34px; border-radius:6px; border:2px solid rgba(0,0,0,0.08); flex-shrink:0; box-shadow:0 1px 3px rgba(0,0,0,0.1); }

    /* ── Formulário de atributo ── */
    .attr-input { width:100%; height:50px; border:2px solid var(--border-color); padding:0 14px; font-weight:900; font-size:var(--fs-base); text-transform:uppercase; background:#fff; transition:border 0.2s; outline:none; }
    .attr-input:focus { border-color:#133080; box-shadow:0 0 0 3px rgba(19,48,128,0.08); }
</style>

<section class="dashboard-section-sq">

    <!-- Cabeçalho -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; flex-wrap:wrap; gap:20px;">
        <div>
            <h1 style="font-size:2rem; font-weight:900; color:var(--text-dark); margin:0; letter-spacing:-0.03em; text-transform:uppercase;">E-Commerce Config</h1>
            <p style="color:var(--text-muted); font-size:var(--fs-base); margin:6px 0 0 0; font-weight:500;">Configurações operacionais e atributos globais de variações.</p>
        </div>
    </div>

    <!-- Navegação principal da Loja -->
    <div class="nav-tabs-sq" style="margin-bottom:2rem;">
        <a href="loja_dashboard.php" class="tab-item-sq">Painel</a>
        <a href="loja_pedidos.php" class="tab-item-sq">Pedidos</a>
        <a href="catalogo_produtos.php" class="tab-item-sq">Produtos</a>
        <a href="marketing_produtos_categorias.php" class="tab-item-sq">Categorias</a>
        <a href="loja_cupons.php" class="tab-item-sq">Cupons</a>
        <a href="loja_relatorios.php" class="tab-item-sq">Relatórios</a>
        <a href="loja_config.php" class="tab-item-sq active">Configuração</a>
    </div>

    <?php if ($mensagem): ?>
        <div style="background:#E8F5E9; color:#2E7D32; padding:18px 25px; border:1px solid #C8E6C9; margin-bottom:25px; font-size:var(--fs-sm); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-circle-check" style="font-size:18px;"></i><?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <!-- Abas internas: Geral | Atributos -->
    <div style="display:flex; gap:0; border-bottom:2px solid var(--border-color); margin-bottom:30px;">
        <button class="tab-main-btn <?php echo $active_tab === 'geral' ? 'active' : ''; ?>" onclick="switchMainTab('geral')">
            <i class="fa-solid fa-sliders" style="margin-right:7px;"></i>Configurações Gerais
        </button>
        <button class="tab-main-btn <?php echo $active_tab === 'grades' ? 'active' : ''; ?>" onclick="switchMainTab('grades')">
            <i class="fa-solid fa-table-cells" style="margin-right:7px;"></i>Atributos de Variações
            <?php $total_attrs = array_sum($counts); if ($total_attrs > 0): ?>
                <span style="background:#133080; color:var(--primary-green); font-size:0.58rem; padding:2px 7px; border-radius:20px; margin-left:6px;"><?php echo $total_attrs; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ══════════ TAB CONFIGURAÇÕES GERAIS ══════════ -->
    <div id="tab-geral" class="tab-main-content <?php echo $active_tab === 'geral' ? 'active' : ''; ?>">
        <div class="dashboard-container" style="max-width:900px; padding:40px;">
            <form method="POST">
                <input type="hidden" name="salvar_config_geral" value="1">
                <div style="display:grid; gap:28px;">

                    <div>
                        <label style="display:block; font-size:var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nome da Vitrine Online</label>
                        <input type="text" name="nome_loja" value="<?php echo htmlspecialchars($config['nome_loja'] ?? ''); ?>" required
                               style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size:var(--fs-base); font-weight:700; text-transform:uppercase;">
                    </div>

                    <div>
                        <label style="display:block; font-size:var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">E-mail para Alertas de Pedidos</label>
                        <input type="email" name="email_notificacao" value="<?php echo htmlspecialchars($config['email_notificacao'] ?? ''); ?>" placeholder="comercial@shiaipro.com"
                               style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size:var(--fs-base); font-weight:700;">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px;">
                        <div>
                            <label style="display:block; font-size:var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Moeda Corrente</label>
                            <select name="moeda" style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size:var(--fs-base); font-weight:700;">
                                <option value="BRL" <?php echo ($config['moeda'] ?? '') == 'BRL' ? 'selected' : ''; ?>>REAL BRASILEIRO (R$)</option>
                                <option value="USD" <?php echo ($config['moeda'] ?? '') == 'USD' ? 'selected' : ''; ?>>DÓLAR AMERICANO (US$)</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Gateway de Checkout</label>
                            <select name="metodo_pagamento" style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size:var(--fs-base); font-weight:700;">
                                <option value="asaas" <?php echo ($config['metodo_pagamento_ativo'] ?? '') == 'asaas' ? 'selected' : ''; ?>>ASAAS (INTEGRAÇÃO DIRETA)</option>
                                <option value="pix"   <?php echo ($config['metodo_pagamento_ativo'] ?? '') == 'pix'   ? 'selected' : ''; ?>>PIX MANUAL (OFFLINE)</option>
                            </select>
                        </div>
                    </div>

                    <div style="background:#fafafa; border:1px solid var(--border-color); padding:30px;">
                        <label style="display:block; font-size:var(--fs-xs); font-weight:900; color:var(--text-dark); margin-bottom:15px; text-transform:uppercase; letter-spacing:0.05em;">Disponibilidade da Loja</label>
                        <div style="display:flex; gap:30px;">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:var(--fs-sm); font-weight:900; text-transform:uppercase;">
                                <input type="radio" name="status_loja" value="aberta"
                                    <?php echo ($config['status_loja'] ?? 'aberta') == 'aberta' ? 'checked' : ''; ?>
                                    style="width:18px; height:18px; accent-color:var(--primary-green);">
                                Disponível
                            </label>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:var(--fs-sm); font-weight:900; color:#ef4444; text-transform:uppercase;">
                                <input type="radio" name="status_loja" value="manutencao"
                                    <?php echo ($config['status_loja'] ?? '') == 'manutencao' ? 'checked' : ''; ?>
                                    style="width:18px; height:18px; accent-color:#ef4444;">
                                Em Manutenção
                            </label>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; padding-top:10px; border-top:1px solid var(--border-color);">
                        <button type="submit" class="btn-sq" style="height:50px; padding:0 50px; font-size:var(--fs-sm);">ATUALIZAR CONFIGURAÇÕES</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════ TAB ATRIBUTOS DE VARIAÇÕES ══════════ -->
    <div id="tab-grades" class="tab-main-content <?php echo $active_tab === 'grades' ? 'active' : ''; ?>">

        <!-- Descrição informativa -->
        <div style="background:linear-gradient(135deg, #0f172a 0%, #133080 100%); padding:20px 30px; margin-bottom:25px; display:flex; align-items:center; gap:20px;">
            <i class="fa-solid fa-circle-info" style="font-size:22px; color:var(--primary-green); flex-shrink:0;"></i>
            <div>
                <p style="color:#fff; font-size:var(--fs-sm); font-weight:700; margin:0; text-transform:uppercase; letter-spacing:0.04em;">Atributos Globais de Variações</p>
                <p style="color:#94a3b8; font-size:var(--fs-xs); margin:4px 0 0 0;">Configure as opções disponíveis para cada atributo. Elas aparecem como listas ao cadastrar variações de produtos.</p>
            </div>
        </div>

        <!-- Sub-abas de atributos (links reais para manter a página no attr correto) -->
        <div class="attr-tabs-nav">
            <?php foreach ($attr_map as $key => $info): ?>
            <a href="loja_config.php?tab=grades&attr=<?php echo $key; ?>"
               class="attr-tab-btn <?php echo $active_attr === $key ? 'active' : ''; ?>">
                <i class="fa-solid <?php echo $info['icone']; ?>"></i>
                <?php echo $info['label']; ?>
                <span class="attr-cnt <?php echo $counts[$key] == 0 ? 'zero' : ''; ?>"><?php echo $counts[$key]; ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Layout 2 colunas: Lista | Formulário -->
        <div style="display:grid; grid-template-columns:1fr 390px; gap:30px; align-items:start;">

            <!-- ── LISTA DE ITENS ── -->
            <div class="dashboard-container">
                <div style="padding:20px 30px; border-bottom:1px solid var(--border-color); background:#fafafa; display:flex; align-items:center; justify-content:space-between;">
                    <h3 style="margin:0; font-size:var(--fs-sm); font-weight:900; text-transform:uppercase; display:flex; align-items:center; gap:10px; color:var(--text-dark);">
                        <i class="fa-solid <?php echo $cur['icone']; ?>" style="color:#133080;"></i>
                        Opções de <?php echo $cur['label']; ?>
                    </h3>
                    <span style="font-size:0.65rem; font-weight:900; color:var(--text-muted); background:#e2e8f0; padding:4px 14px; text-transform:uppercase; letter-spacing:0.05em;">
                        <?php echo count($attr_items); ?> CADASTRADO<?php echo count($attr_items) !== 1 ? 'S' : ''; ?>
                    </span>
                </div>

                <div style="padding:25px 30px;">
                    <?php if (empty($attr_items)): ?>
                        <div style="text-align:center; padding:50px 20px;">
                            <i class="fa-solid <?php echo $cur['icone']; ?>" style="font-size:42px; color:#e2e8f0; display:block; margin-bottom:15px;"></i>
                            <p style="color:var(--text-muted); font-size:var(--fs-sm); font-weight:700; text-transform:uppercase; margin:0 0 6px 0;">
                                Nenhum(a) <?php echo $cur['label']; ?> cadastrado(a)
                            </p>
                            <p style="color:#94a3b8; font-size:var(--fs-xs); margin:0;">Use o formulário ao lado para adicionar a primeira opção.</p>
                        </div>
                    <?php else: ?>
                        <div style="display:grid; gap:8px;">
                            <?php foreach ($attr_items as $idx => $it): ?>
                            <div class="item-row">
                                <span class="item-ordem"><?php echo ($idx + 1); ?></span>

                                <?php if ($cur['tem_cor']): ?>
                                    <div class="cor-swatch" style="background:<?php echo htmlspecialchars($it['hex'] ?? '#fff'); ?>;"></div>
                                <?php endif; ?>

                                <div class="item-nome"><?php echo htmlspecialchars($it['nome']); ?></div>

                                <?php if ($cur['tem_cor'] && !empty($it['hex'])): ?>
                                    <span class="item-hex"><?php echo strtoupper($it['hex']); ?></span>
                                <?php endif; ?>

                                <div class="item-actions">
                                    <a href="loja_config.php?tab=grades&attr=<?php echo $active_attr; ?>&edit_attr=<?php echo $it['id']; ?>"
                                       class="btn-sq-light" style="width:32px; height:32px; padding:0; display:flex; align-items:center; justify-content:center;" title="Editar">
                                        <i class="fa-solid fa-edit" style="font-size:12px;"></i>
                                    </a>
                                    <a href="loja_config.php?tab=grades&attr=<?php echo $active_attr; ?>&del_attr=<?php echo $it['id']; ?>"
                                       onclick="return confirm('Excluir esta opção? Variações de produtos já salvas com ela não serão alteradas.')"
                                       style="width:32px; height:32px; padding:0; display:flex; align-items:center; justify-content:center; background:transparent; border:1px solid #ef4444; color:#ef4444; text-decoration:none;" title="Excluir">
                                        <i class="fa-solid fa-trash" style="font-size:12px;"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── FORMULÁRIO ── -->
            <div class="dashboard-container" style="padding:30px; position:sticky; top:30px;">
                <div style="border-left:5px solid #133080; padding-left:15px; margin-bottom:25px;">
                    <h3 style="margin:0 0 4px 0; font-size:var(--fs-base); font-weight:900; text-transform:uppercase; color:#133080;">
                        <?php echo $edit_item ? 'Editar ' . $cur['label'] : 'Nova Opção de ' . $cur['label']; ?>
                    </h3>
                    <p style="margin:0; font-size:var(--fs-xs); color:var(--text-muted);">
                        <?php echo $cur['placeholder']; ?>
                    </p>
                </div>

                <form method="POST">
                    <input type="hidden" name="salvar_attr" value="1">
                    <input type="hidden" name="attr_id" value="<?php echo $edit_item['id'] ?? ''; ?>">

                    <!-- Campo: Nome -->
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.04em;">
                            <i class="fa-solid <?php echo $cur['icone']; ?>" style="margin-right:5px;"></i>Nome
                        </label>
                        <input type="text" name="attr_nome" class="attr-input"
                               value="<?php echo htmlspecialchars($edit_item['nome'] ?? ''); ?>"
                               required placeholder="<?php echo $cur['placeholder']; ?>"
                               autofocus>
                    </div>

                    <!-- Campo: Cor Hex (somente para atributo "cor") -->
                    <?php if ($cur['tem_cor']): ?>
                    <div style="margin-bottom:22px;">
                        <label style="display:block; font-size:0.70rem; font-weight:700; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.04em;">
                            <i class="fa-solid fa-droplet" style="margin-right:5px;"></i>Cor (Hex)
                        </label>

                        <!-- Preview da cor selecionada -->
                        <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                            <div id="cor-preview" style="width:52px; height:52px; border-radius:8px; border:2px solid var(--border-color); background:<?php echo htmlspecialchars($edit_item['hex'] ?? '#133080'); ?>; flex-shrink:0; transition:background 0.2s;"></div>
                            <div>
                                <div id="cor-nome-preview" style="font-size:var(--fs-sm); font-weight:800; color:var(--text-dark); text-transform:uppercase;">
                                    <?php echo htmlspecialchars($edit_item['nome'] ?? 'Cor'); ?>
                                </div>
                                <div id="cor-hex-preview" style="font-size:0.68rem; font-weight:700; color:var(--text-muted); font-family:monospace;">
                                    <?php echo strtoupper($edit_item['hex'] ?? '#133080'); ?>
                                </div>
                            </div>
                        </div>

                        <input type="color" name="attr_hex" id="inp-hex"
                               value="<?php echo htmlspecialchars($edit_item['hex'] ?? '#133080'); ?>"
                               style="width:100%; height:52px; border:2px solid var(--border-color); padding:4px 6px; cursor:pointer; background:#fff;"
                               oninput="atualizarCor(this.value)">
                        <p style="font-size:0.65rem; color:var(--text-muted); margin:6px 0 0 0;">Clique para abrir o seletor de cores.</p>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn-sq" style="width:100%; height:52px; font-size:var(--fs-sm); margin-bottom:8px;">
                        <i class="fa-solid fa-<?php echo $edit_item ? 'save' : 'plus'; ?>" style="margin-right:8px;"></i>
                        <?php echo $edit_item ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR OPÇÃO'; ?>
                    </button>

                    <?php if ($edit_item): ?>
                    <a href="loja_config.php?tab=grades&attr=<?php echo $active_attr; ?>"
                       class="btn-sq-light" style="width:100%; height:42px; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:var(--fs-xs); font-weight:900; text-transform:uppercase;">
                        <i class="fa-solid fa-xmark" style="margin-right:6px;"></i>CANCELAR EDIÇÃO
                    </a>
                    <?php endif; ?>
                </form>

                <!-- Dica sobre o campo -->
                <div style="margin-top:20px; padding:15px; background:#f8fafc; border-left:3px solid var(--primary-green);">
                    <p style="font-size:0.68rem; color:var(--text-muted); margin:0; line-height:1.6;">
                        <i class="fa-solid fa-lightbulb" style="color:var(--primary-green); margin-right:5px;"></i>
                        As opções cadastradas aqui aparecem como <strong>listas de seleção</strong> ao adicionar variações em cada produto.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    // ── Alternar aba principal ────────────────────────────────────────────────
    function switchMainTab(tabName) {
        document.querySelectorAll('.tab-main-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-main-content').forEach(c => c.classList.remove('active'));
        const btn = [...document.querySelectorAll('.tab-main-btn')].find(b => b.getAttribute('onclick').includes(tabName));
        if (btn) btn.classList.add('active');
        const tab = document.getElementById('tab-' + tabName);
        if (tab) tab.classList.add('active');
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        if (tabName !== 'grades') url.searchParams.delete('attr');
        window.history.pushState({}, '', url);
    }

    // ── Preview da cor em tempo real ─────────────────────────────────────────
    function atualizarCor(hex) {
        const preview = document.getElementById('cor-preview');
        const hexText = document.getElementById('cor-hex-preview');
        const nomeInput = document.querySelector('input[name="attr_nome"]');
        const nomePreview = document.getElementById('cor-nome-preview');
        if (preview) preview.style.background = hex;
        if (hexText) hexText.textContent = hex.toUpperCase();
        if (nomeInput && nomePreview) nomePreview.textContent = nomeInput.value.toUpperCase() || 'COR';
    }

    // Atualizar nome no preview ao digitar
    document.addEventListener('DOMContentLoaded', function () {
        const nomeInput = document.querySelector('input[name="attr_nome"]');
        const nomePreview = document.getElementById('cor-nome-preview');
        if (nomeInput && nomePreview) {
            nomeInput.addEventListener('input', function () {
                nomePreview.textContent = this.value.toUpperCase() || 'COR';
            });
        }
    });
</script>

<?php include 'footer.php'; ?>