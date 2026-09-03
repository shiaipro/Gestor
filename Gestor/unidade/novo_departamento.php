<?php
require_once '../config.php';

if (!estaLogado() || !getUnidadeId()) {
    header('Location: ../login.php');
    exit;
}

$unidade_id = getUnidadeId();
$mensagem   = '';
$erro       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $cor  = $_POST['cor']  ?? '#3b82f6';

    if ($nome) {
        try {
            $stmt = $pdo->prepare("INSERT INTO departamentos (unidade_id, nome, cor) VALUES (?, ?, ?)");
            $stmt->execute([$unidade_id, strtoupper($nome), $cor]);
            $mensagem = "Departamento criado com sucesso!";
            header("refresh:2;url=departamentos.php");
        } catch (PDOException $e) {
            $erro = "Erro ao criar departamento: " . $e->getMessage();
        }
    } else {
        $erro = "O nome do departamento é obrigatório.";
    }
}

$custom_title = "Novo Departamento";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">Novo Departamento</h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">Crie uma nova categoria para organizar sua equipe e processos.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="departamentos.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid var(--primary-green); margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i><?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px 25px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i><?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">
            
            <!-- Coluna Principal -->
            <div class="dashboard-container" style="padding: 40px;">
                <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                    <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Dados do Departamento</h2>
                </div>

                <div style="display: grid; gap: 25px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nome do Departamento</label>
                        <input type="text" name="nome" required placeholder="EX: FINANCEIRO" 
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark); text-transform: uppercase;">
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Cor de Identificação</label>
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <input type="color" name="cor" value="#3b82f6" 
                                style="width: 60px; height: 60px; border: 1px solid var(--border-color); cursor: pointer; padding: 0; background: none;">
                            <span style="color: var(--text-muted); font-size: var(--fs-sm); font-weight: 700;">Essa cor será utilizada em relatórios e banners.</span>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">
                             <i class="fa-solid fa-layer-group" style="margin-right: 10px;"></i>CRIAR DEPARTAMENTO
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin: 0 0 25px 0; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Organização</h3>
                    <p style="font-size: var(--fs-sm); color: var(--text-muted); line-height: 1.6; font-weight: 500; margin: 0;">Departamentos ajudam a segmentar permissões de acesso e filtros em relatórios operacionais.</p>
                </div>

                <div class="dashboard-container" style="padding: 30px; background: #08153a; border: none;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin: 0 0 15px 0;">Dica SHIAI</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic; margin: 0;">A cor escolhida facilita a identificação visual rápida de cada setor no dashboard mestre.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<?php include 'footer.php'; ?>