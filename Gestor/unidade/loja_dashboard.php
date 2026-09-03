<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

// 1. Auto-Migrações da Loja
try {
    // Tabela de Configurações da Loja
    $pdo->query("SELECT 1 FROM loja_config LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS loja_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        nome_loja VARCHAR(255),
        email_notificacao VARCHAR(255),
        moeda VARCHAR(10) DEFAULT 'BRL',
        metodo_pagamento_ativo VARCHAR(50) DEFAULT 'asaas',
        status_loja ENUM('aberta', 'manutencao') DEFAULT 'aberta',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

try {
    // Tabela de Cupons
    $pdo->query("SELECT 1 FROM loja_cupons LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS loja_cupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        codigo VARCHAR(50) NOT NULL,
        tipo ENUM('fixo', 'porcentagem') DEFAULT 'porcentagem',
        valor DECIMAL(10,2) NOT NULL,
        data_expiracao DATE,
        limite_uso INT DEFAULT 0,
        usos INT DEFAULT 0,
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

try {
    // Tabela de Pedidos
    $pdo->query("SELECT 1 FROM loja_pedidos LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS loja_pedidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        aluno_id INT,
        total DECIMAL(10,2) NOT NULL,
        desconto DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('pendente', 'pago', 'cancelado', 'enviado', 'entregue') DEFAULT 'pendente',
        metodo_pagamento VARCHAR(50),
        id_transacao_externa VARCHAR(255),
        notas_cliente TEXT,
        notas_internas TEXT,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

try {
    // Tabela de Itens do Pedido
    $pdo->query("SELECT 1 FROM loja_pedido_itens LIMIT 1");
} catch (Exception $e) {
    $sql = "CREATE TABLE IF NOT EXISTS loja_pedido_itens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id INT NOT NULL,
        produto_id INT NOT NULL,
        quantidade INT NOT NULL,
        preco_unitario DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (pedido_id) REFERENCES loja_pedidos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

// Buscar estatísticas básicas para o Painel
$hoje = date('Y-m-d');
$mes_atual = date('Y-m');

$stmt_vendas_total = $pdo->prepare("SELECT SUM(total) FROM loja_pedidos WHERE unidade_id = ? AND status = 'pago'");
$stmt_vendas_total->execute([$unidade_id]);
$vendas_total = $stmt_vendas_total->fetchColumn() ?: 0;

$stmt_pedidos_pendentes = $pdo->prepare("SELECT COUNT(*) FROM loja_pedidos WHERE unidade_id = ? AND status = 'pendente'");
$stmt_pedidos_pendentes->execute([$unidade_id]);
$pedidos_pendentes = $stmt_pedidos_pendentes->fetchColumn();

$stmt_ultimos_pedidos = $pdo->prepare("
    SELECT p.*, a.nome_completo as aluno_nome 
    FROM loja_pedidos p 
    LEFT JOIN alunos a ON p.aluno_id = a.id 
    WHERE p.unidade_id = ? 
    ORDER BY p.criado_em DESC LIMIT 5
");
$stmt_ultimos_pedidos->execute([$unidade_id]);
$ultimos_pedidos = $stmt_ultimos_pedidos->fetchAll();

$custom_title = "Painel da Loja";
include 'header.php';
?>


<style>
    .dashboard-container {
        padding: 2rem !important;
        background: #f8fafc;
        min-height: 100%;
    }

    .greet-header h1 {
        font-size: 2rem;
        font-weight: 800;
        color: var(--dark-main);
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
    }

    .greet-header p {
        color: var(--text-muted);
        font-size: 1rem;
        margin-bottom: 2.5rem;
    }

    .metrics-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }

    .metric-card {
        background: #fff;
        padding: 1.5rem;
        border-radius: 1.25rem;
        border: 1px solid var(--border);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s;
    }

    .metric-card:hover {
        transform: translateY(-4px);
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 2rem;
    }

    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    .premium-card {
        background: #fff;
        padding: 1.5rem;
        border-radius: 1.5rem;
        border: 1px solid var(--border);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
        margin-bottom: 1.5rem;
    }

    .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .widget-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--dark-main);
        margin: 0;
    }

    /* Mini Calendar Modernized */
    .calendar-mini {
        padding: 0.5rem;
    }

    .calendar-grid-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        text-align: center;
        margin-bottom: 1rem;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--text-muted);
        letter-spacing: 0.05em;
    }

    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 8px;
    }

    .cal-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 600;
        border-radius: 10px;
        color: var(--text-main);
        cursor: pointer;
        transition: all 0.2s;
    }

    .cal-day:hover {
        background: #f1f5f9;
        color: #08153a;
    }

    .day-active {
        background: var(--primary) !important;
        color: var(--dark-main) !important;
        box-shadow: 0 4px 12px rgba(163, 230, 53, 0.3);
    }

    /* Schedule List Modernized */
    .schedule-list {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .schedule-item {
        display: flex;
        gap: 1rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        position: relative;
    }

    .schedule-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .schedule-time {
        min-width: 65px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #08153a;
        text-align: right;
    }

    .schedule-info h4 {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--dark-main);
        margin: 0 0 0.25rem 0;
    }

    .schedule-info p {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin: 0;
    }

    .progress-bar-container {
        height: 8px;
        background: #f1f5f9;
        border-radius: 10px;
        overflow: hidden;
        margin: 0.5rem 0;
    }

    .progress-fill {
        height: 100%;
        background: var(--primary);
        border-radius: 10px;
    }
</style>



<section class="dashboard-section-sq">
    <!-- Cabeçalho Premium -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Loja & E-commerce
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Gerencie seus produtos, estoque e vendas online da unidade.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="editar_produto.php" class="btn-sq-light" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> Novo Produto
            </a>
            <a href="loja_pedidos.php" class="btn-sq" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-cart-shopping" style="margin-right: 8px;"></i> Ver Pedidos
            </a>
        </div>
    </div>

    <!-- Navegação por Abas -->
<div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="loja_dashboard.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_dashboard.php') ? 'active' : ''; ?>">Painel</a>
    <a href="loja_pedidos.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_pedidos.php') ? 'active' : ''; ?>">Pedidos</a>
    <a href="catalogo_produtos.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'catalogo_produtos.php') ? 'active' : ''; ?>">Produtos</a>
    <a href="marketing_produtos_categorias.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'marketing_produtos_categorias.php') ? 'active' : ''; ?>">Categorias</a>
    <a href="loja_cupons.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_cupons.php') ? 'active' : ''; ?>">Cupons</a>
    <a href="loja_relatorios.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_relatorios.php') ? 'active' : ''; ?>">Relatórios</a>
    <a href="loja_config.php" class="tab-item-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'loja_config.php') ? 'active' : ''; ?>">Configuração</a>
</div>

    

    <!-- Métricas em Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px;">
        <!-- Vendas -->
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Vendas Totais</span>
                <i class="fa-solid fa-chart-line" style="color: var(--primary-green); opacity: 0.5;"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;">R$ <?php echo number_format($vendas_total, 2, ',', '.'); ?></div>
            <div style="margin-top: 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--primary-green); display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-arrow-up"></i>
                <span>+15.5% vs mês passado</span>
            </div>
        </div>

        <!-- Pedidos -->
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Pedidos Pendentes</span>
                <i class="fa-solid fa-box-open" style="color: #f59e0b; opacity: 0.5;"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;"><?php echo $pedidos_pendentes; ?></div>
            <div style="margin-top: 15px; font-size: var(--fs-sm); font-weight: 700; color: #f59e0b; display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-clock"></i>
                <span>Aguardando ação</span>
            </div>
        </div>

        <!-- Lucro -->
        <div class="stat-card-square">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <span style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Lucro Estimado</span>
                <i class="fa-solid fa-wallet" style="color: var(--primary-green); opacity: 0.5;"></i>
            </div>
            <div style="font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1;">R$ <?php echo number_format($vendas_total * 0.82, 2, ',', '.'); ?></div>
            <div style="margin-top: 15px; font-size: var(--fs-sm); font-weight: 700; color: var(--text-muted);">Margem líquida de 82%</div>
        </div>
    </div>

</section>

<?php include 'footer.php'; ?>