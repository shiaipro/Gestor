<?php
require_once '../config.php';
include 'header.php';

$aluno_id = $_GET['aluno_id'] ?? null;
$unidade_id = getUnidadeId();

if (!$aluno_id) {
    header('Location: financeiro.php');
    exit;
}

// Buscar dados do aluno
$stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ? AND unidade_id = ?");
$stmt->execute([$aluno_id, $unidade_id]);
$aluno = $stmt->fetch();

if (!$aluno) {
    header('Location: financeiro.php');
    exit;
}

$mensagem = '';
$erro = '';

// Adicionar Nova Mensalidade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_mensalidade'])) {
    $valor = $_POST['valor'] ?? '';
    $vencimento = $_POST['vencimento'] ?? '';

    if ($valor && $vencimento) {
        try {
            $stmt = $pdo->prepare("INSERT INTO mensalidades (unidade_id, academia_id, aluno_id, valor, data_vencimento, status) VALUES (?, ?, ?, ?, ?, 'pendente')");
            $stmt->execute([$unidade_id, $aluno['academia_id'] ?? null, $aluno_id, $valor, $vencimento]);
            $mensagem = "Mensalidade gerada com sucesso!";
        } catch (PDOException $e) {
            $erro = "Erro ao gerar: " . $e->getMessage();
        }
    }
}

// Registrar Pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pagar_id'])) {
    $m_id = $_POST['pagar_id'];
    $forma = $_POST['forma_pagamento'] ?? 'pix';

    try {
        $stmt = $pdo->prepare("UPDATE mensalidades SET status = 'pago', data_pagamento = CURDATE(), forma_pagamento = ? WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$forma, $m_id, $unidade_id]);
        registrarComissoesMensalidade($m_id, $unidade_id);
        $mensagem = "Pagamento registrado com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao registrar pagamento: " . $e->getMessage();
    }
}

// Editar Mensalidade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_mensalidade'])) {
    $m_id = $_POST['mensalidade_id'];
    $valor = str_replace(['.', ','], ['', '.'], $_POST['valor']);
    $venc = $_POST['data_vencimento'];
    $aplicar_edicao = $_POST['aplicar_edicao'] ?? 'atual';

    try {
        // Vencimento original (antes da edição) serve de referência para localizar passadas/futuras
        $stmt_original = $pdo->prepare("SELECT data_vencimento FROM mensalidades WHERE id = ? AND unidade_id = ?");
        $stmt_original->execute([$m_id, $unidade_id]);
        $venc_original = $stmt_original->fetchColumn();

        $stmt = $pdo->prepare("UPDATE mensalidades SET valor = ?, data_vencimento = ? WHERE id = ? AND unidade_id = ?");
        $stmt->execute([$valor, $venc, $m_id, $unidade_id]);

        // Demais mensalidades do aluno (passadas/futuras) recebem apenas o Valor, preservando o
        // vencimento próprio de cada uma; mensalidades pagas nunca são alteradas
        if (($aplicar_edicao === 'passadas' || $aplicar_edicao === 'futuras') && $venc_original) {
            $operador = ($aplicar_edicao === 'passadas') ? '<=' : '>=';
            $stmt_serie = $pdo->prepare("UPDATE mensalidades SET valor = ? WHERE aluno_id = ? AND unidade_id = ? AND id != ? AND status != 'pago' AND data_vencimento $operador ?");
            $stmt_serie->execute([$valor, $aluno_id, $unidade_id, $m_id, $venc_original]);
        }

        $mensagem = "Mensalidade atualizada com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao atualizar: " . $e->getMessage();
    }
}

// Excluir Mensalidade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_mensalidade'])) {
    $m_id = $_POST['mensalidade_id'];
    $escopo = $_POST['excluir_escopo'] ?? 'esta';

    try {
        if ($escopo === 'todos') {
            // A mensalidade selecionada é sempre excluída; as demais só se ainda não estiverem pagas
            $pdo->prepare("DELETE FROM mensalidades WHERE unidade_id = ? AND aluno_id = ? AND (id = ? OR status != 'pago')")
                ->execute([$unidade_id, $aluno_id, $m_id]);
        } elseif ($escopo === 'futuros') {
            $stmt_original = $pdo->prepare("SELECT data_vencimento FROM mensalidades WHERE id = ? AND unidade_id = ?");
            $stmt_original->execute([$m_id, $unidade_id]);
            $venc_original = $stmt_original->fetchColumn();
            $pdo->prepare("DELETE FROM mensalidades WHERE unidade_id = ? AND aluno_id = ? AND (id = ? OR (status != 'pago' AND data_vencimento >= ?))")
                ->execute([$unidade_id, $aluno_id, $m_id, $venc_original]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM mensalidades WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$m_id, $unidade_id]);
        }
        $mensagem = "Mensalidade excluída com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao excluir: " . $e->getMessage();
    }
}

// Buscar Histórico
$stmt = $pdo->prepare("SELECT * FROM mensalidades WHERE aluno_id = ? ORDER BY data_vencimento DESC");
$stmt->execute([$aluno_id]);
$mensalidades = $stmt->fetchAll();
?>


<div class="rounded-main-wrapper">
    <!-- Header Page -->
    <div style="padding: 1rem 2rem; border-bottom: 1px solid var(--border); background: white;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <nav class="breadcrumb" style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700;">
                    <a href="index.php" style="text-decoration: none; color: inherit;">DASHBOARD</a> 
                    <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem; margin: 0 0.5rem; color: #cbd5e1;"></i> 
                    <a href="financeiro.php" style="text-decoration: none; color: inherit;">FINANCEIRO</a> 
                    <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem; margin: 0 0.5rem; color: #cbd5e1;"></i> 
                    <span>MENSALIDADES</span>
                </nav>
                <h1 style="font-size: 1.5rem; font-weight: 800; color: #08153a; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                   <i class="fa-solid fa-user-check" style="color:var(--primary);"></i>
                   <?php echo htmlspecialchars($aluno['nome_completo']); ?>
                </h1>
            </div>
            <div>
                <a href="financeiro.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 8px; padding: 0.7rem 1.25rem;">
                    <i class="fa-solid fa-arrow-left"></i> Voltar ao Financeiro
                </a>
            </div>
        </div>
    </div>

    <div style="padding: 2rem;">
        <?php if ($mensagem): ?>
            <div class="alert" style="background: #08153a; color: var(--primary); margin-bottom: 1.5rem; padding: 1.25rem; border-radius: 1rem; display: flex; align-items: center; gap: 10px; border-left: 4px solid var(--primary);">
                <i class="fa-solid fa-circle-check"></i>
                <span style="font-size: 0.9rem; font-weight: 600;"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert" style="background: #fee2e2; color: #991b1b; margin-bottom: 1.5rem; padding: 1.25rem; border-radius: 1rem; border-left: 4px solid #ef4444;">
                <span style="font-size: 0.9rem; font-weight: 600;"><?php echo $erro; ?></span>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start;">
            <!-- Historical Payments -->
            <div class="card" style="padding: 0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); background: #fafafa;">
                    <h2 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0;">Histórico de Mensalidades</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; background: white; border-bottom: 1px solid var(--border);">
                                <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Vencimento</th>
                                <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Valor</th>
                                <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Status</th>
                                <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Pagamento</th>
                                <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; text-align: right;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mensalidades)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">Nenhuma cobrança encontrada para este aluno.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($mensalidades as $m): ?>
                                <tr style="border-bottom: 1px solid var(--border); transition: all 0.2s;">
                                    <td style="padding: 1.25rem 1.5rem;">
                                        <div style="font-weight: 700; color: #08153a; font-size: 0.9rem;">
                                            <?php echo date('d/m/Y', strtotime($m['data_vencimento'])); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 1.25rem 1.5rem;">
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">
                                            R$ <?php echo number_format($m['valor'], 2, ',', '.'); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 1.25rem 1.5rem;">
                                        <?php
                                        $hoje = date('Y-m-d');
                                        if ($m['status'] === 'pago') {
                                            echo '<span class="badge" style="background:#dcfce7; color:#166534; font-size:0.65rem; font-weight:800; padding:4px 8px; border-radius:6px; border:1px solid #bbf7d0;">LIQUIDADO</span>';
                                        } elseif ($m['data_vencimento'] < $hoje) {
                                            echo '<span class="badge" style="background:#fee2e2; color:#991b1b; font-size:0.65rem; font-weight:800; padding:4px 8px; border-radius:6px; border:1px solid #fecaca;">ATRASADO</span>';
                                        } else {
                                            echo '<span class="badge" style="background:#fef3c7; color:#d97706; font-size:0.65rem; font-weight:800; padding:4px 8px; border-radius:6px; border:1px solid #fde68a;">PENDENTE</span>';
                                        }
                                        ?>
                                    </td>
                                    <td style="padding: 1.25rem 1.5rem;">
                                        <?php if ($m['status'] === 'pago'): ?>
                                            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">
                                                <i class="fa-solid fa-calendar-day" style="font-size:0.7rem; margin-right:4px;"></i> <?php echo date('d/m/Y', strtotime($m['data_pagamento'])); ?>
                                            </div>
                                            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; margin-top: 2px;">
                                                <i class="fa-solid fa-wallet" style="font-size:0.7rem; margin-right:4px;"></i> <?php echo $m['forma_pagamento']; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1; font-size: 0.8rem;">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1.25rem 1.5rem; text-align: right;">
                                        <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end;">
                                            <?php if ($m['status'] !== 'pago'): ?>
                                                <form method="POST" style="display: flex; gap: 0.4rem; align-items: center; margin: 0;">
                                                    <input type="hidden" name="pagar_id" value="<?php echo $m['id']; ?>">
                                                    <select name="forma_pagamento" class="form-control" style="padding: 0.35rem 0.5rem; font-size: 0.7rem; width: 85px; height: 32px !important; border-radius: 8px !important; background: #f8fafc;">
                                                        <option value="pix">PIX</option>
                                                        <option value="dinheiro">Dinheiro</option>
                                                        <option value="cartao">Cartão</option>
                                                        <option value="transferencia">Transf.</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary" style="padding: 0 0.75rem; font-size: 0.65rem; border-radius: 8px; font-weight: 800; background: var(--dark-main); min-height: 32px; border: none;">BAIXAR</button>
                                                </form>
                                                <button type="button" onclick="gerarPix(<?php echo $m['id']; ?>)" class="btn btn-secondary" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #059669;" title="Gerar PIX">
                                                    <i class="fa-brands fa-pix" style="font-size: 0.8rem;"></i>
                                                </button>
                                            <?php else: ?>
                                                <i class="fa-solid fa-circle-check" style="color: var(--primary); font-size: 1.15rem; margin-right: 10px;"></i>
                                            <?php endif; ?>

                                            <button onclick='abrirModalEditar(<?php echo json_encode($m); ?>)' class="btn btn-secondary" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b;" title="Editar">
                                                <i class="fa-solid fa-pen" style="font-size: 0.75rem;"></i>
                                            </button>
                                            
                                            <button onclick="abrirModalExcluir(<?php echo $m['id']; ?>)" class="btn btn-secondary" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #ef4444;" title="Excluir">
                                                <i class="fa-solid fa-trash" style="font-size: 0.75rem;"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- New Charge Sidebar -->
            <div class="card" style="padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border);">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom: 2rem;">
                    <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(163, 230, 53, 0.1); display:flex; align-items:center; justify-content:center; color: var(--primary);">
                        <i class="fa-solid fa-plus" style="font-size: 1.2rem;"></i>
                    </div>
                    <h2 style="font-size: 1.15rem; font-weight: 800; color: #08153a; margin: 0;">Nova Cobrança</h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="nova_mensalidade" value="1">
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem; display: block;">Valor da Mensalidade (R$)</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-51%); font-weight: 700; color: #08153a; font-size: 0.9rem;">R$</span>
                            <input type="number" step="0.01" name="valor" class="form-control" required placeholder="0,00" style="padding-left: 2.8rem !important; height: 50px !important; font-weight: 700; font-size: 1.1rem !important;">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem; display: block;">Data de Vencimento</label>
                        <input type="date" name="vencimento" class="form-control" required value="<?php echo date('Y-m-d'); ?>" style="height: 50px !important; font-weight: 600;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width:100%; padding: 1rem; font-weight: 800; font-size: 0.85rem; background: var(--dark-main); letter-spacing: 0.05em; border-radius: 14px;">GERAR COBRANÇA</button>
                </form>

                <div style="margin-top: 2rem; padding: 1.25rem; background: #f8fafc; border-radius: 12px; border: 1px dashed var(--border);">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0; line-height: 1.5;">
                        <i class="fa-solid fa-circle-info" style="margin-right: 4px; color: var(--primary);"></i>
                        A cobrança será gerada com o status <b>PENDENTE</b> e poderá ser baixada no histórico ao lado após o pagamento.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Mensalidade -->
<div id="modal_editar" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:400px; padding:2rem; position:relative;">
        <button onclick="document.getElementById('modal_editar').style.display='none'" style="position:absolute; top:1rem; right:1rem; border:none; background:none; cursor:pointer; font-size:1.2rem;">&times;</button>
        <h2 style="margin-bottom:1.5rem;">Editar Mensalidade</h2>
        <form method="POST">
            <input type="hidden" name="editar_mensalidade" value="1">
            <input type="hidden" name="mensalidade_id" id="edit_id">
            
            <div class="form-group">
                <label>Valor (R$)</label>
                <input type="text" name="valor" id="edit_valor" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Data de Vencimento</label>
                <input type="date" name="data_vencimento" id="edit_vencimento" class="form-control" required>
            </div>

            <div style="margin: 1rem 0; padding: 1rem; background: #f8fafc; border-radius: 10px; border: 1px solid var(--border);">
                <p style="font-size: 0.7rem; font-weight: 800; margin-bottom: 0.5rem; text-transform: uppercase; color: var(--text-muted);">Aplicar o Valor também em:</p>
                <label style="display:flex; align-items:center; gap:8px; font-size: 0.75rem; cursor:pointer;"><input type="radio" name="aplicar_edicao" value="atual" checked> Apenas esta mensalidade (atual)</label>
                <label style="display:flex; align-items:center; gap:8px; font-size: 0.75rem; cursor:pointer; margin-top:8px;"><input type="radio" name="aplicar_edicao" value="passadas"> Esta e as pendentes anteriores</label>
                <label style="display:flex; align-items:center; gap:8px; font-size: 0.75rem; cursor:pointer; margin-top:8px;"><input type="radio" name="aplicar_edicao" value="futuras"> Esta e as pendentes futuras</label>
                <p style="font-size: 0.65rem; color: var(--text-muted); margin: 0.5rem 0 0;">Mensalidades já pagas nunca são alteradas.</p>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding: 0.8rem; background: var(--dark-main); border:none; font-weight:700;">SALVAR ALTERAÇÕES</button>
        </form>
    </div>
</div>

<!-- Modal Excluir Mensalidade -->
<div id="modal_excluir" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:400px; padding:2rem; text-align:center; position:relative;">
        <button onclick="document.getElementById('modal_excluir').style.display='none'" style="position:absolute; top:1rem; right:1rem; border:none; background:none; cursor:pointer; font-size:1.2rem;">&times;</button>
        <i class="fa-solid fa-trash-can" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"></i>
        <h2 style="margin-bottom:0.5rem;">Excluir Mensalidade?</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Esta ação removerá permanentemente o registro de cobrança.</p>
        
        <form method="POST">
            <input type="hidden" name="excluir_mensalidade" value="1">
            <input type="hidden" name="mensalidade_id" id="delete_id">
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 10px; border: 1px solid var(--border); text-align: left;">
                <label style="display:flex; align-items:center; gap:8px; font-size: 0.75rem; cursor:pointer;"><input type="radio" name="excluir_escopo" value="esta" checked> Excluir apenas esta</label>
                <label style="display:flex; align-items:center; gap:8px; font-size: 0.75rem; cursor:pointer; margin-top:8px;"><input type="radio" name="excluir_escopo" value="futuros"> Esta e as pendentes futuras</label>
                <label style="display:flex; align-items:center; gap:8px; font-size: 0.75rem; cursor:pointer; margin-top:8px;"><input type="radio" name="excluir_escopo" value="todos"> Todas as pendentes do aluno</label>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                <button type="button" onclick="document.getElementById('modal_excluir').style.display='none'" class="btn btn-secondary" style="padding: 0.8rem;">CANCELAR</button>
                <button type="submit" class="btn btn-danger" style="padding: 0.8rem; background: #ef4444; border:none; font-weight:700;">CONFIRMAR EXCLUSÃO</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal PIX -->
<div id="modal_pix" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:380px; padding:2rem; text-align:center; position:relative;">
        <button onclick="document.getElementById('modal_pix').style.display='none'" style="position:absolute; top:1rem; right:1rem; border:none; background:none; cursor:pointer; font-size:1.2rem;">&times;</button>
        <h2 style="margin-bottom: 1.5rem;"><i class="fa-brands fa-pix" style="color:#059669; margin-right:8px;"></i>Cobrança PIX</h2>
        <div id="pix_loading" style="padding: 2rem 0;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--text-muted);"></i>
        </div>
        <div id="pix_conteudo" style="display:none;">
            <img id="pix_qrcode" src="" alt="QR Code PIX" style="width: 200px; height: 200px; margin: 0 auto 1rem auto; display: block; border: 1px solid var(--border); padding: 8px;">
            <textarea id="pix_payload" readonly style="width: 100%; height: 80px; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 10px; font-size: 0.65rem; font-family: monospace; resize: none;"></textarea>
            <button type="button" class="btn btn-primary" style="width:100%; margin-top: 1rem; background: var(--dark-main); border:none; font-weight:700; padding: 0.75rem;" onclick="copiarPixAdmin()">
                <i class="fa-solid fa-copy" style="margin-right: 8px;"></i>Copiar Código
            </button>
        </div>
        <div id="pix_erro" style="display:none; background:#fee2e2; color:#991b1b; padding: 1rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem;"></div>
    </div>
</div>

<script>
function gerarPix(mensalidadeId) {
    document.getElementById('modal_pix').style.display = 'flex';
    document.getElementById('pix_loading').style.display = 'block';
    document.getElementById('pix_conteudo').style.display = 'none';
    document.getElementById('pix_erro').style.display = 'none';

    fetch('api_gerar_pix_mensalidade.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mensalidade_id: mensalidadeId })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('pix_loading').style.display = 'none';
        if (data.success) {
            document.getElementById('pix_qrcode').src = 'data:image/png;base64,' + data.qrcode;
            document.getElementById('pix_payload').value = data.payload;
            document.getElementById('pix_conteudo').style.display = 'block';
        } else {
            document.getElementById('pix_erro').textContent = data.message || 'Erro ao gerar PIX.';
            document.getElementById('pix_erro').style.display = 'block';
        }
    })
    .catch(() => {
        document.getElementById('pix_loading').style.display = 'none';
        document.getElementById('pix_erro').textContent = 'Erro de comunicação ao gerar PIX.';
        document.getElementById('pix_erro').style.display = 'block';
    });
}

function copiarPixAdmin() {
    const el = document.getElementById('pix_payload');
    el.select();
    navigator.clipboard.writeText(el.value);
}

function abrirModalEditar(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_valor').value = item.valor;
    document.getElementById('edit_vencimento').value = item.data_vencimento;
    document.querySelector('input[name="aplicar_edicao"][value="atual"]').checked = true;
    document.getElementById('modal_editar').style.display = 'flex';
}

function abrirModalExcluir(id) {
    document.getElementById('delete_id').value = id;
    document.querySelector('input[name="excluir_escopo"][value="esta"]').checked = true;
    document.getElementById('modal_excluir').style.display = 'flex';
}

function toggleRepeticoes(val) {
    const group = document.getElementById('group_repeticoes');
    if(group) group.style.display = (val === 'nenhuma') ? 'none' : 'block';
}
</script>

<?php include 'footer.php'; ?>