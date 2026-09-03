<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// Auto-Migração para adicionar coluna grade_id em loja_pedido_itens e academia_id em loja_pedidos
try {
    $pdo->query("SELECT grade_id FROM loja_pedido_itens LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE loja_pedido_itens ADD COLUMN grade_id INT NULL AFTER produto_id");
}
try {
    $pdo->query("SELECT academia_id FROM loja_pedidos LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE loja_pedidos ADD COLUMN academia_id INT NULL AFTER aluno_id");
}
try {
    $pdo->query("SELECT visitante_nome FROM loja_pedidos LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE loja_pedidos ADD COLUMN visitante_nome VARCHAR(255) NULL AFTER academia_id");
    $pdo->exec("ALTER TABLE loja_pedidos ADD COLUMN visitante_telefone VARCHAR(20) NULL AFTER visitante_nome");
}

$erro = '';
$sucesso = '';

// Processar Envio do Formulário de Pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fazer_pedido'])) {
    $cliente_id_post = $_POST['cliente_id'] ?? '';
    $aluno_id = null;
    $academia_id = null;
    
    if (strpos($cliente_id_post, 'aluno_') === 0) {
        $aluno_id = (int)str_replace('aluno_', '', $cliente_id_post);
    } elseif (strpos($cliente_id_post, 'academia_') === 0) {
        $academia_id = (int)str_replace('academia_', '', $cliente_id_post);
    } elseif (is_numeric($cliente_id_post) && $cliente_id_post > 0) {
        $aluno_id = (int)$cliente_id_post;
    }
    // Se 'visitante' ou vazio: aluno_id e academia_id ficam null
    $metodo_pagamento = $_POST['metodo_pagamento'] ?? 'dinheiro';
    $status = $_POST['status'] ?? 'pago';
    $desconto = !empty($_POST['desconto']) ? (float)str_replace(',', '.', $_POST['desconto']) : 0.00;
    $notas_cliente = $_POST['notas_cliente'] ?? '';
    $notas_internas = $_POST['notas_internas'] ?? '';
    
    // Dados Visitante
    $visitante_nome = null;
    $visitante_telefone = null;
    if (!$aluno_id && !$academia_id) {
        $visitante_nome = $_POST['visitante_nome'] ?? null;
        $visitante_telefone = $_POST['visitante_telefone'] ?? null;
    }
    
    $itens_post = $_POST['itens'] ?? [];
    
    if (empty($itens_post)) {
        $erro = "Selecione pelo menos um item para o pedido.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // 1. Calcular Totais
            $total_itens = 0.00;
            $items_to_insert = [];
            
            foreach ($itens_post as $it) {
                $produto_id = (int)$it['produto_id'];
                $grade_id = !empty($it['grade_id']) ? (int)$it['grade_id'] : null;
                $quantidade = (int)$it['quantidade'];
                
                if ($quantidade <= 0) {
                    throw new Exception("A quantidade do produto deve ser maior que zero.");
                }
                
                // Buscar preço do produto
                $stmt = $pdo->prepare("SELECT preco, preco_promocional, nome, estoque FROM cms_produtos WHERE id = ? AND unidade_id = ?");
                $stmt->execute([$produto_id, $unidade_id]);
                $prod = $stmt->fetch();
                
                if (!$prod) {
                    throw new Exception("Produto não encontrado.");
                }
                
                $preco_unitario = ($prod['preco_promocional'] > 0) ? $prod['preco_promocional'] : $prod['preco'];
                
                // Verificar estoque da variação ou do produto
                if ($grade_id) {
                    $stmt_g = $pdo->prepare("SELECT id, estoque, tamanho, cor_nome FROM cms_produtos_grade WHERE id = ? AND produto_id = ?");
                    $stmt_g->execute([$grade_id, $produto_id]);
                    $grade = $stmt_g->fetch();
                    
                    if (!$grade) {
                        throw new Exception("Variação do produto '{$prod['nome']}' não encontrada.");
                    }
                    if ($grade['estoque'] < $quantidade) {
                        $desc_var = ($grade['tamanho'] ? "Tamanho: {$grade['tamanho']} " : "") . ($grade['cor_nome'] ? "Cor: {$grade['cor_nome']}" : "");
                        throw new Exception("Estoque insuficiente para a variação ({$desc_var}) do produto '{$prod['nome']}'. Disponível: {$grade['estoque']}");
                    }
                } else {
                    if ($prod['estoque'] < $quantidade) {
                        throw new Exception("Estoque insuficiente para o produto '{$prod['nome']}'. Disponível: {$prod['estoque']}");
                    }
                }
                
                $subtotal = $preco_unitario * $quantidade;
                $total_itens += $subtotal;
                
                $items_to_insert[] = [
                    'produto_id' => $produto_id,
                    'grade_id' => $grade_id,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $preco_unitario,
                    'subtotal' => $subtotal
                ];
            }
            
            $total_pedido = max(0.00, $total_itens - $desconto);
            
            // 2. Inserir Pedido
            $stmt_ped = $pdo->prepare("
                INSERT INTO loja_pedidos (unidade_id, aluno_id, academia_id, visitante_nome, visitante_telefone, total, desconto, status, metodo_pagamento, notas_cliente, notas_internas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_ped->execute([
                $unidade_id,
                $aluno_id,
                $academia_id,
                $visitante_nome,
                $visitante_telefone,
                $total_pedido,
                $desconto,
                $status,
                $metodo_pagamento,
                $notas_cliente,
                $notas_internas
            ]);
            $pedido_id = $pdo->lastInsertId();
            
            // 3. Inserir itens e decrementar estoque
            $stmt_item = $pdo->prepare("
                INSERT INTO loja_pedido_itens (pedido_id, produto_id, grade_id, quantidade, preco_unitario, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($items_to_insert as $item) {
                $stmt_item->execute([
                    $pedido_id,
                    $item['produto_id'],
                    $item['grade_id'],
                    $item['quantidade'],
                    $item['preco_unitario'],
                    $item['subtotal']
                ]);
                
                // Decrementar estoque
                if ($item['grade_id']) {
                    $pdo->prepare("UPDATE cms_produtos_grade SET estoque = estoque - ? WHERE id = ?")
                        ->execute([$item['quantidade'], $item['grade_id']]);
                        
                    // Também atualiza o total consolidado de estoque no produto
                    $pdo->prepare("UPDATE cms_produtos SET estoque = estoque - ? WHERE id = ?")
                        ->execute([$item['quantidade'], $item['produto_id']]);
                } else {
                    $pdo->prepare("UPDATE cms_produtos SET estoque = estoque - ? WHERE id = ?")
                        ->execute([$item['quantidade'], $item['produto_id']]);
                }
            }
            
            $pdo->commit();
            header("Location: loja_pedidos.php?sucesso=" . urlencode("Pedido #$pedido_id criado com sucesso!"));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "Erro ao criar pedido: " . $e->getMessage();
        }
    }
}

// Buscar alunos para dropdown
$stmt_alunos = $pdo->prepare("SELECT id, nome_completo FROM alunos WHERE unidade_id = ? ORDER BY nome_completo ASC");
$stmt_alunos->execute([$unidade_id]);
$alunos = $stmt_alunos->fetchAll();

// Buscar academias para dropdown
$stmt_academias = $pdo->prepare("SELECT id, nome FROM academias WHERE unidade_id = ? ORDER BY nome ASC");
$stmt_academias->execute([$unidade_id]);
$academias = $stmt_academias->fetchAll();

// Buscar produtos ativos e suas variações
$stmt_prod = $pdo->prepare("SELECT id, nome, preco, preco_promocional, estoque FROM cms_produtos WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
$stmt_prod->execute([$unidade_id]);
$produtos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

// Buscar variações
$stmt_variants = $pdo->prepare("
    SELECT g.* 
    FROM cms_produtos_grade g
    JOIN cms_produtos p ON g.produto_id = p.id
    WHERE p.unidade_id = ? AND p.status = 'ativo'
    ORDER BY g.id ASC
");
$stmt_variants->execute([$unidade_id]);
$variants = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);

$custom_title = "Novo Pedido Manual";
include 'header.php';
?>

<style>
    .order-container {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }
    @media (max-width: 1024px) {
        .order-container {
            grid-template-columns: 1fr;
        }
    }
    .item-row {
        background: #f8fafc;
        border: 1px solid var(--border-color);
        padding: 20px;
        margin-bottom: 15px;
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .item-row-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px dashed var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 5px;
    }
    .remove-btn {
        background: none;
        border: none;
        color: #ef4444;
        cursor: pointer;
        font-weight: 800;
        font-size: var(--fs-sm);
        display: flex;
        align-items: center;
        gap: 5px;
    }
</style>

<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Fazer Pedido
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Registre uma venda manual de produtos física ou internamente pelo Shiaipro.
            </p>
        </div>
        <div>
            <a href="loja_pedidos.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar para Pedidos
            </a>
        </div>
    </div>

    <?php if ($erro): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px 25px; border-left: 4px solid #991b1b; margin-bottom: 30px; font-weight: 700; font-size: var(--fs-base);">
            <i class="fa-solid fa-circle-xmark" style="margin-right: 10px;"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="form-pedido">
        <input type="hidden" name="fazer_pedido" value="1">
        
        <div class="order-container">
            
            <!-- Coluna Principal (Itens do Pedido) -->
            <div>
                <div class="dashboard-container" style="padding: 30px; margin-bottom: 30px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 1.15rem; font-weight: 900; color: var(--text-dark); text-transform: uppercase; margin: 0;">Itens da Venda</h3>
                        <button type="button" class="btn-sq" style="width: auto; height: 35px; font-size: var(--fs-xs); padding: 0 15px;" onclick="addItemRow()">
                            <i class="fa-solid fa-plus" style="margin-right: 5px;"></i> Adicionar Item
                        </button>
                    </div>

                    <div id="itens-container">
                        <!-- Linhas de itens serão injetadas por JS -->
                    </div>
                </div>

                <div class="dashboard-container" style="padding: 30px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                        <h3 style="font-size: 1.15rem; font-weight: 900; color: var(--text-dark); text-transform: uppercase; margin: 0;">Notas & Observações</h3>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Notas do Cliente</label>
                            <textarea name="notas_cliente" rows="3" class="form-control" style="resize: vertical; font-size: var(--fs-sm);" placeholder="Ex: Entregar na próxima aula de Kimono"></textarea>
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Notas Internas</label>
                            <textarea name="notas_internas" rows="3" class="form-control" style="resize: vertical; font-size: var(--fs-sm);" placeholder="Ex: Pago em dinheiro para o professor"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Lateral (Informações de Pagamento e Cliente) -->
            <div>
                <div class="dashboard-container" style="padding: 30px; position: sticky; top: 20px;">
                    <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 25px;">
                        <h3 style="font-size: 1.15rem; font-weight: 900; color: var(--text-dark); text-transform: uppercase; margin: 0;">Dados do Pedido</h3>
                    </div>

                    <!-- Seleção do Aluno / Academia -->
                    <div style="margin-bottom: 20px;">
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Aluno / Academia / Visitante</label>
                        <select name="cliente_id" id="cliente_id" class="form-control" style="font-size: var(--fs-sm);" onchange="toggleVisitanteFields()">
                            <option value="">-- Selecione o Cliente --</option>
                            <option value="visitante">🏷️ Visitante (digitar dados)</option>
                            <optgroup label="Alunos">
                                <?php foreach ($alunos as $a): ?>
                                    <option value="aluno_<?php echo $a['id']; ?>">
                                        <?php echo htmlspecialchars($a['nome_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Academias">
                                <?php foreach ($academias as $ac): ?>
                                    <option value="academia_<?php echo $ac['id']; ?>">
                                        <?php echo htmlspecialchars($ac['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Campos do Visitante -->
                    <div id="visitante_fields" style="display:none; margin-bottom: 25px; padding: 15px; background: #fff; border: 1px dashed var(--border-color); border-radius: 4px;">
                        <div style="margin-bottom: 15px;">
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-dark); margin-bottom:8px; text-transform:uppercase;">Nome do Visitante / Cliente</label>
                            <input type="text" name="visitante_nome" class="form-control" style="font-size: var(--fs-sm);" placeholder="Ex: João da Silva">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-dark); margin-bottom:8px; text-transform:uppercase;">Telefone / WhatsApp</label>
                            <input type="text" name="visitante_telefone" class="form-control" style="font-size: var(--fs-sm);" placeholder="(00) 00000-0000">
                        </div>
                    </div>

                    <!-- Método de Pagamento -->
                    <div style="margin-bottom: 20px;">
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Forma de Pagamento</label>
                        <select name="metodo_pagamento" class="form-control" style="font-size: var(--fs-sm);">
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="PIX">PIX</option>
                            <option value="Cartão de Crédito">Cartão de Crédito</option>
                            <option value="Cartão de Débito">Cartão de Débito</option>
                            <option value="Boleto">Boleto Bancário</option>
                        </select>
                    </div>

                    <!-- Status do Pedido -->
                    <div style="margin-bottom: 20px;">
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Status</label>
                        <select name="status" class="form-control" style="font-size: var(--fs-sm);">
                            <option value="pago" selected>Pago / Entregue</option>
                            <option value="pendente">Aguardando Pagamento</option>
                            <option value="enviado">Enviado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>

                    <!-- Desconto -->
                    <div style="margin-bottom: 25px;">
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Desconto (R$)</label>
                        <input type="text" name="desconto" id="desconto" class="form-control" style="font-size: var(--fs-sm);" placeholder="0,00" oninput="recalcTotal()">
                    </div>

                    <!-- Resumo Financeiro -->
                    <div style="background: #f8fafc; border: 1px solid var(--border-color); padding: 20px; margin-bottom: 25px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-muted);">
                            <span>Subtotal Itens:</span>
                            <span id="label-subtotal">R$ 0,00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; font-size: var(--fs-sm); font-weight: 700; color: #ef4444;">
                            <span>Desconto:</span>
                            <span id="label-desconto">R$ 0,00</span>
                        </div>
                        <div style="border-top: 1px dashed var(--border-color); padding-top: 15px; display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 900; color: var(--text-dark);">
                            <span>Total Geral:</span>
                            <span id="label-total">R$ 0,00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-sq" style="width: 100%; height: 50px; font-weight: 900; letter-spacing: 0.05em;">
                        FINALIZAR PEDIDO
                    </button>
                </div>
            </div>

        </div>
    </form>
</section>

<script>
const products = <?php echo json_encode($produtos); ?>;
const variants = <?php echo json_encode($variants); ?>;

let rowCount = 0;

function addItemRow() {
    rowCount++;
    const container = document.getElementById('itens-container');
    
    const row = document.createElement('div');
    row.className = 'item-row';
    row.id = `item-row-${rowCount}`;
    
    row.innerHTML = `
        <div class="item-row-header">
            <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase;">Item #${rowCount}</span>
            <button type="button" class="remove-btn" onclick="removeItemRow(${rowCount})">
                <i class="fa-solid fa-trash-can"></i> Remover
            </button>
        </div>
        <div style="display: grid; grid-template-columns: 2fr 2fr 1fr 1fr; gap: 15px; align-items: end;">
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Produto</label>
                <select name="itens[${rowCount}][produto_id]" class="form-control" style="font-size: var(--fs-sm);" onchange="onProductChange(${rowCount}, this.value)" required>
                    <option value="">-- Selecione o Produto --</option>
                    ${products.map(p => `<option value="${p.id}">${p.nome.toUpperCase()} - R$ ${parseFloat(p.preco_promocional > 0 ? p.preco_promocional : p.preco).toFixed(2).replace('.', ',')}</option>`).join('')}
                </select>
            </div>
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Variação / Grade</label>
                <select name="itens[${rowCount}][grade_id]" class="form-control" style="font-size: var(--fs-sm);" id="grade-select-${rowCount}" onchange="recalcTotal()" disabled>
                    <option value="">-- Produto sem variação --</option>
                </select>
            </div>
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Qtd</label>
                <input type="number" name="itens[${rowCount}][quantidade]" class="form-control" style="font-size: var(--fs-sm);" value="1" min="1" oninput="recalcTotal()" required>
            </div>
            <div>
                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Subtotal</label>
                <div style="height: 45px; display: flex; align-items: center; font-weight: 900; color: var(--text-dark); font-size: var(--fs-base);" id="subtotal-${rowCount}">R$ 0,00</div>
            </div>
        </div>
    `;
    
    container.appendChild(row);
}

function removeItemRow(id) {
    const row = document.getElementById(`item-row-${id}`);
    if (row) {
        row.remove();
        recalcTotal();
    }
}

function onProductChange(rowId, prodId) {
    const gradeSelect = document.getElementById(`grade-select-${rowId}`);
    gradeSelect.innerHTML = '';
    
    if (!prodId) {
        gradeSelect.innerHTML = '<option value="">-- Produto sem variação --</option>';
        gradeSelect.disabled = true;
        recalcTotal();
        return;
    }
    
    const prodVariants = variants.filter(v => v.produto_id == prodId);
    
    if (prodVariants.length > 0) {
        gradeSelect.disabled = false;
        gradeSelect.innerHTML = '<option value="">-- Escolha uma Grade --</option>';
        prodVariants.forEach(v => {
            let parts = [];
            if (v.idade) parts.push(`Idd: ${v.idade.toUpperCase()}`);
            if (v.genero) parts.push(`Gên: ${v.genero.toUpperCase()}`);
            if (v.tamanho) parts.push(`Tam: ${v.tamanho.toUpperCase()}`);
            if (v.altura) parts.push(`Alt: ${v.altura.toUpperCase()}`);
            if (v.cor_nome) parts.push(`Cor: ${v.cor_nome.toUpperCase()}`);
            
            let desc = parts.join(' | ');
            if (desc === '') desc = `Variação #${v.id}`;
            desc += ` — (Estoque: ${v.estoque})`;
            
            gradeSelect.innerHTML += `<option value="${v.id}" data-estoque="${v.estoque}">${desc}</option>`;
        });
    } else {
        gradeSelect.innerHTML = '<option value="">-- Sem variação para este produto --</option>';
        gradeSelect.disabled = true;
    }
    
    recalcTotal();
}

function recalcTotal() {
    let subtotal = 0;
    
    for (let i = 1; i <= rowCount; i++) {
        const row = document.getElementById(`item-row-${i}`);
        if (row) {
            const prodSelect = document.querySelector(`select[name="itens[${i}][produto_id]"]`);
            const qtdInput = document.querySelector(`input[name="itens[${i}][quantidade]"]`);
            
            if (prodSelect && prodSelect.value && qtdInput && qtdInput.value > 0) {
                const prod = products.find(p => p.id == prodSelect.value);
                if (prod) {
                    const price = prod.preco_promocional > 0 ? parseFloat(prod.preco_promocional) : parseFloat(prod.preco);
                    const qty = parseInt(qtdInput.value);
                    const itemTotal = price * qty;
                    subtotal += itemTotal;
                    const elSub = document.getElementById(`subtotal-${i}`);
                    if (elSub) elSub.innerHTML = `R$ ${itemTotal.toFixed(2).replace('.', ',')}`;
                }
            }
        }
    }
    
    let descontoVal = document.getElementById('desconto').value.replace(',', '.');
    let descAmount = parseFloat(descontoVal) || 0;
    let totalGeral = Math.max(0, subtotal - descAmount);
    
    document.getElementById('label-subtotal').innerHTML = `R$ ${subtotal.toFixed(2).replace('.', ',')}`;
    document.getElementById('label-desconto').innerHTML = `R$ ${descAmount.toFixed(2).replace('.', ',')}`;
    document.getElementById('label-total').innerHTML = `R$ ${totalGeral.toFixed(2).replace('.', ',')}`;
}

// Toggle Visitante Fields
function toggleVisitanteFields() {
    const select = document.getElementById('cliente_id');
    const fields = document.getElementById('visitante_fields');
    if (select.value === 'visitante') {
        fields.style.display = 'block';
    } else {
        fields.style.display = 'none';
        document.querySelector('input[name="visitante_nome"]').value = '';
        document.querySelector('input[name="visitante_telefone"]').value = '';
    }
}

// Initial setup
document.addEventListener('DOMContentLoaded', () => {
    addItemRow();
    // Esconder campos visitante no carregamento inicial
    document.getElementById('visitante_fields').style.display = 'none';
});
</script>

<?php include 'footer.php'; ?>
