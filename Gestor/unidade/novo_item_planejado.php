<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('planejamento_criar')) {
    header('Location: planejamento.php?erro=sem_permissao');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $data_limite = $_POST['data_limite'] ?? '';
    $prioridade = $_POST['prioridade'] ?? 'media';
    $status = $_POST['status'] ?? 'pendente';

    if ($titulo && $data_limite) {
        try {
            $stmt = $pdo->prepare("INSERT INTO unidade_planejamento (unidade_id, titulo, descricao, data_limite, prioridade, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $titulo, $descricao, $data_limite, $prioridade, $status]);
            $sucesso = "Objetivo cadastrado com sucesso!";
            header("refresh:1;url=planejamento.php");
        } catch (PDOException $e) {
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o título e a data limite.";
    }
}

$custom_title = "Novo Objetivo";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">Ajustar Planejamento</h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">Defina metas, objetivos e acompanhe o crescimento da sua unidade.</p>
        </div>

    
        <div style="display: flex; gap: 10px;">
            <a href="planejamento.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="alunos.php" class="tab-item-sq">Alunos</a>
    <a href="equipe.php" class="tab-item-sq">RH</a>
    <a href="academias.php" class="tab-item-sq">Unidades</a>
    <a href="turmas.php" class="tab-item-sq">Turmas</a>
    <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
    <a href="planejamento.php" class="tab-item-sq active">Planejamento</a>
</div>

    

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">
            
            <!-- Coluna Principal -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <?php if ($sucesso): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 20px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid var(--primary-green);">
                        <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $sucesso; ?>
                    </div>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 20px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid #ef4444;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <div class="dashboard-container" style="padding: 40px; display: grid; gap: 25px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Título do Objetivo / Meta</label>
                        <input type="text" name="titulo" required placeholder="EX: AUMENTAR FATURAMENTO EM 20%" 
                            style="width:100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700; color: var(--text-dark); text-transform: uppercase;">
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Descrição Detalhada & Plano de Ação</label>
                        <textarea name="descricao" placeholder="DESCREVA OS PASSOS NECESSÁRIOS..." 
                            style="width:100%; min-height: 150px; background: #fafafa; border: 1px solid var(--border-color); padding: 15px; font-size: var(--fs-base); font-weight: 600; color: var(--text-dark); resize: none;"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Data Limite</label>
                            <input type="date" name="data_limite" required 
                                style="width:100%; height: 50px; background: #fafafa; border: 1px solid var(--border-color); padding: 0 15px; font-size: var(--fs-base); font-weight: 700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Prioridade</label>
                            <select name="prioridade" style="width:100%; height: 50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="baixa">BAIXA</option>
                                <option value="media" selected>MÉDIA</option>
                                <option value="alta">ALTA</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Status Inicial</label>
                            <select name="status" style="width:100%; height: 50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="pendente">PENDENTE</option>
                                <option value="em_andamento">EM ANDAMENTO</option>
                                <option value="concluido">CONCLUÍDO</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">
                            <i class="fa-solid fa-save" style="margin-right: 10px;"></i>SALVAR OBJETIVO ESTRATÉGICO
                        </button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Crescimento & Metas</h3>
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Metas SMART</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Certifique-se de que seu objetivo seja Específico, Mensurável, Atingível, Relevante e com Prazo definido.</p>
                        </div>
                        <div>
                            <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 5px;">Priorização</h4>
                            <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500;">Use a Alta prioridade apenas para o que impacta diretamente o faturamento ou a segurança.</p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Foco Operacional</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">Este item aparecerá no seu Dashboard principal como um lembrete constante das suas metas operacionais.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>