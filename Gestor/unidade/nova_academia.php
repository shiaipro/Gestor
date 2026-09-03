<?php
require_once '../config.php';

if (!estaLogado() || !getUnidadeId()) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('unidades_criar')) {
    header('Location: academias.php?erro=sem_permissao');
    exit;
}

$unidade_id = getUnidadeId();
$mensagem   = '';
$erro       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = $_POST['nome']        ?? '';
    $cnpj        = $_POST['cnpj']        ?? '';
    $endereco    = $_POST['endereco']    ?? '';
    $telefone    = $_POST['telefone']    ?? '';
    $whatsapp    = $_POST['whatsapp']    ?? '';
    $email       = $_POST['email']       ?? '';
    $valor       = !empty($_POST['valor']) ? (float)str_replace(['.', ','], ['', '.'], $_POST['valor']) : 0.00;
    $recorrencia = $_POST['recorrencia'] ?? '';
    $dia_vencimento = !empty($_POST['dia_vencimento']) ? (int)$_POST['dia_vencimento'] : null;
    $status      = $_POST['status']      ?? 'ativo';

    if ($nome) {
        try {
            $stmt = $pdo->prepare("INSERT INTO academias (unidade_id, nome, cnpj, endereco, telefone, whatsapp, email, valor, recorrencia, dia_vencimento, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $nome, $cnpj, $endereco, $telefone, $whatsapp, $email, $valor, $recorrencia, $dia_vencimento, $status]);
            $mensagem = "Academia criada com sucesso!";
            header("refresh:2;url=academias.php");
        } catch (PDOException $e) {
            $erro = "Erro ao criar academia: " . $e->getMessage();
        }
    } else {
        $erro = "O nome da academia é obrigatório.";
    }
}

$custom_title = "Nova Unidade";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">Nova Unidade</h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">Abertura de nova filial ou local de treino.</p>
        </div>

    
        <div style="display: flex; gap: 10px;">
            <a href="academias.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="alunos.php" class="tab-item-sq">Alunos</a>
    <a href="equipe.php" class="tab-item-sq">RH</a>
    <a href="academias.php" class="tab-item-sq active">Unidades</a>
    <a href="turmas.php" class="tab-item-sq">Turmas</a>
    <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
    <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
</div>

    

    <?php if ($mensagem): ?>
        <div style="background: #dcfce7; color: #166534; padding: 20px; border-left: 4px solid var(--primary-green); margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: 0.1em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">

            <!-- Coluna Principal -->
            <div class="dashboard-container" style="padding: 40px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                    <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Dados da Unidade</h2>
                </div>

                <div style="display: grid; gap: 25px;">
                    <div style="display: grid; grid-template-columns: 1fr 200px; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nome Oficial da Unidade</label>
                            <input type="text" name="nome" required placeholder="EX: SHIAI - UNIDADE CENTRO"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark);">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Status Operacional</label>
                            <select name="status" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="ativo">ATIVO</option>
                                <option value="inativo">INATIVO</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">CNPJ</label>
                            <input type="text" name="cnpj" placeholder="00.000.000/0000-00"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">E-mail Administrativo</label>
                            <input type="email" name="email" placeholder="unidade@exemplo.com"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                        </div>
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Endereço Completo</label>
                        <input type="text" name="endereco" placeholder="RUA, NÚMERO, BAIRRO, CIDADE - UF"
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Telefone (Fixo)</label>
                            <input type="text" name="telefone" placeholder="(00) 0000-0000"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">WhatsApp</label>
                            <input type="text" name="whatsapp" placeholder="(00) 00000-0000"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:600; color:var(--text-dark);">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px; padding: 20px; background: #f8fafc; border: 1px solid var(--border-color);">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Valor do Contrato (R$)</label>
                            <input type="text" name="valor" placeholder="0,00"
                                style="width:100%; height:50px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:900; color:var(--text-dark);">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Recorrência</label>
                            <select name="recorrencia" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fff; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="">Não cobrar</option>
                                <option value="Mensal">Mensal</option>
                                <option value="Trimestral">Trimestral</option>
                                <option value="Semestral">Semestral</option>
                                <option value="Anual">Anual</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Dia do Vencimento</label>
                            <input type="number" name="dia_vencimento" placeholder="Ex: 5" min="1" max="31"
                                style="width:100%; height:50px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:900; color:var(--text-dark);">
                        </div>
                    </div>

                    <div style="margin-top: 10px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">
                            <i class="fa-solid fa-save" style="margin-right: 10px;"></i>CONFIRMAR CADASTRO DE UNIDADE
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div style="display: flex; flex-direction: column; gap: 20px; position: sticky; top: 30px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin: 0 0 25px 0; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Gestão Estratégica</h3>
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin: 0 0 5px 0;">Identidade de Marca</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500; margin: 0;">O nome cadastrado aparecerá em todos os contratos e certificados gerados por esta filial.</p>
                        </div>
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin: 0 0 5px 0;">Geolocalização</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500; margin: 0;">O endereço deve ser preciso para que o APP do Aluno exiba a rota correta via GPS.</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-container" style="padding: 30px; background: #08153a; border: none;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin: 0 0 15px 0;">Dica Técnica</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic; margin: 0;">Padronize a nomenclatura das unidades para uma gestão multiclub eficiente e relatórios comerciais mais limpos.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>