<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('alunos_criar')) {
    header('Location: alunos.php?erro=sem_permissao');
    exit;
}

// Auto-migration: Garantir coluna complemento
try {
    $pdo->query("SELECT complemento FROM alunos LIMIT 1");
} catch (Exception $e) {
    $pdo->query("ALTER TABLE alunos ADD complemento VARCHAR(150) AFTER academia_id");
}

garantirColunaTipoCadastroAluno($pdo);

$mensagem = '';
$erro = '';

// Buscar academias disponíveis
$stmt_acads = $pdo->prepare("SELECT id, nome FROM academias WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
$stmt_acads->execute([$unidade_id]);
$academias = $stmt_acads->fetchAll();

// Buscar todas as turmas da unidade
$stmt_turmas = $pdo->prepare("SELECT id, nome, preco_mensal, horario, academia_id FROM turmas WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
$stmt_turmas->execute([$unidade_id]);
$todas_turmas = $stmt_turmas->fetchAll();

// Buscar faixas da unidade
$stmt_grads = $pdo->prepare("SELECT nome, grau FROM unidade_graduacoes WHERE unidade_id = ? ORDER BY ordem ASC, nome ASC");
$stmt_grads->execute([$unidade_id]);
$graduacoes_db = $stmt_grads->fetchAll(PDO::FETCH_ASSOC);

if (empty($graduacoes_db)) {
    $graduacoes_list = [];
    foreach (['Branca', 'Cinza', 'Amarela', 'Laranja', 'Verde', 'Azul', 'Roxa', 'Marrom', 'Preta'] as $f) {
        $graduacoes_list[] = ['nome' => $f, 'grau' => ''];
    }
} else {
    $graduacoes_list = $graduacoes_db;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_completo      = strtoupper(trim($_POST['nome_completo'] ?? ''));
    $email_pessoal      = $_POST['email_pessoal'] ?? '';
    $cpf                = $_POST['cpf'] ?? '';
    $rg                 = $_POST['rg'] ?? '';
    $data_nascimento    = $_POST['data_nascimento'] ?: null;
    $genero             = $_POST['genero'] ?? 'masculino';
    $telefone           = $_POST['telefone'] ?? '';
    $responsavel_nome   = $_POST['responsavel_nome'] ?? '';
    $responsavel_cpf    = $_POST['responsavel_cpf'] ?? '';
    $responsavel_parentesco = $_POST['responsavel_parentesco'] ?? '';
    $responsavel_telefone = $_POST['responsavel_telefone'] ?? '';
    $responsavel_email  = $_POST['responsavel_email'] ?? '';
    $cep                = $_POST['cep'] ?? '';
    $endereco           = $_POST['endereco'] ?? '';
    $endereco_numero    = $_POST['endereco_numero'] ?? '';
    $endereco_complemento = $_POST['endereco_complemento'] ?? '';
    $bairro             = $_POST['bairro'] ?? '';
    $cidade             = $_POST['cidade'] ?? '';
    $estado             = $_POST['estado'] ?? '';
    $faixa              = $_POST['faixa'] ?? '';
    $academia_id        = $_POST['academia_id'] ?: null;
    $complemento        = $_POST['complemento'] ?? '';
    $data_graduacao     = $_POST['data_graduacao'] ?: null;
    $status             = $_POST['status'] ?? 'ativo';
    $tipo_cadastro      = in_array($_POST['tipo_cadastro'] ?? '', ['fixo', 'visitante'], true) ? $_POST['tipo_cadastro'] : 'fixo';
    $turma_id           = $_POST['turma_id'] ?: null;

    if (!$nome_completo) {
        $erro = "O nome do aluno é obrigatório.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO alunos
                (unidade_id, nome_completo, email_pessoal, cpf, rg, data_nascimento, genero, telefone,
                 cep, endereco, endereco_numero, endereco_complemento, bairro, cidade, estado,
                 responsavel_nome, responsavel_cpf, responsavel_parentesco, responsavel_telefone, responsavel_email,
                 faixa, academia_id, complemento, data_graduacao, status, tipo_cadastro)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $unidade_id, $nome_completo, $email_pessoal, $cpf, $rg, $data_nascimento, $genero, $telefone,
                $cep, $endereco, $endereco_numero, $endereco_complemento, $bairro, $cidade, $estado,
                $responsavel_nome, $responsavel_cpf, $responsavel_parentesco, $responsavel_telefone, $responsavel_email,
                $faixa, $academia_id, $complemento, $data_graduacao, $status, $tipo_cadastro
            ]);
            $novo_id = $pdo->lastInsertId();

            // Vincular turma, se informada
            if ($turma_id) {
                $pdo->prepare("INSERT INTO turma_alunos (aluno_id, turma_id) VALUES (?, ?)")->execute([$novo_id, $turma_id]);
            }

            header("Location: editar_aluno.php?id={$novo_id}&novo=1");
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao cadastrar aluno: " . $e->getMessage();
        }
    }
}

$select_style = "width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;";
$input_style = "width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark);";
$label_style = "display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;";

$custom_title = "Novo Aluno";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">Novo Aluno</h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">Preencha os dados para cadastrar um novo aluno na base de Alunos.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="alunos.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
        <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
        <a href="alunos.php" class="tab-item-sq active">Alunos</a>
        <a href="equipe.php" class="tab-item-sq">RH</a>
        <a href="academias.php" class="tab-item-sq">Unidades</a>
        <a href="turmas.php" class="tab-item-sq">Turmas</a>
        <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
        <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
    </div>

    <?php if ($mensagem): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px 25px; border-left: 4px solid var(--primary-green); margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px 25px; border-left: 4px solid #ef4444; margin-bottom: 30px; font-weight: 900; font-size: var(--fs-sm); text-transform: uppercase; letter-spacing: 0.05em;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <!-- SEÇÃO 1: DADOS PESSOAIS -->
        <div class="dashboard-container" style="padding: 40px; margin-bottom: 30px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Informações de Cadastro</h2>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
                <div>
                    <label style="<?php echo $label_style; ?>">Nome Completo *</label>
                    <input type="text" name="nome_completo" required value="<?php echo htmlspecialchars($_POST['nome_completo'] ?? ''); ?>"
                        placeholder="NOME COMPLETO DO ALUNO"
                        style="<?php echo $input_style; ?> text-transform: uppercase;">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">E-mail Pessoal</label>
                    <input type="email" name="email_pessoal" value="<?php echo htmlspecialchars($_POST['email_pessoal'] ?? ''); ?>"
                        style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Telefone / WhatsApp</label>
                    <input type="text" name="telefone" value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>"
                        placeholder="(00) 00000-0000" style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Data de Nascimento</label>
                    <input type="date" name="data_nascimento" value="<?php echo $_POST['data_nascimento'] ?? ''; ?>"
                        style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">CPF</label>
                    <input type="text" name="cpf" value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>"
                        placeholder="000.000.000-00" style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">RG</label>
                    <input type="text" name="rg" value="<?php echo htmlspecialchars($_POST['rg'] ?? ''); ?>"
                        style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Gênero</label>
                    <select name="genero" style="<?php echo $select_style; ?>">
                        <option value="masculino" <?php echo ($_POST['genero'] ?? '') == 'masculino' ? 'selected' : ''; ?>>MASCULINO</option>
                        <option value="feminino" <?php echo ($_POST['genero'] ?? '') == 'feminino' ? 'selected' : ''; ?>>FEMININO</option>
                        <option value="outro" <?php echo ($_POST['genero'] ?? '') == 'outro' ? 'selected' : ''; ?>>OUTRO</option>
                    </select>
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Status</label>
                    <select name="status" style="<?php echo $select_style; ?>">
                        <option value="ativo" <?php echo ($_POST['status'] ?? 'ativo') == 'ativo' ? 'selected' : ''; ?>>ATIVO</option>
                        <option value="inativo" <?php echo ($_POST['status'] ?? '') == 'inativo' ? 'selected' : ''; ?>>INATIVO</option>
                    </select>
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Tipo de Cadastro</label>
                    <select name="tipo_cadastro" style="<?php echo $select_style; ?>">
                        <option value="fixo" <?php echo ($_POST['tipo_cadastro'] ?? 'fixo') == 'fixo' ? 'selected' : ''; ?>>ALUNO FIXO</option>
                        <option value="visitante" <?php echo ($_POST['tipo_cadastro'] ?? '') == 'visitante' ? 'selected' : ''; ?>>VISITANTE (SÓ EXAME)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- SEÇÃO 2: RESPONSÁVEL -->
        <div class="dashboard-container" style="padding: 40px; margin-bottom: 30px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Responsável</h2>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
                <div>
                    <label style="<?php echo $label_style; ?>">Nome do Responsável</label>
                    <input type="text" name="responsavel_nome" value="<?php echo htmlspecialchars($_POST['responsavel_nome'] ?? ''); ?>"
                        style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Parentesco</label>
                    <input type="text" name="responsavel_parentesco" value="<?php echo htmlspecialchars($_POST['responsavel_parentesco'] ?? ''); ?>"
                        placeholder="Ex: Pai, Mãe, Avó..." style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">CPF do Responsável</label>
                    <input type="text" name="responsavel_cpf" value="<?php echo htmlspecialchars($_POST['responsavel_cpf'] ?? ''); ?>"
                        placeholder="000.000.000-00" style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Telefone do Responsável</label>
                    <input type="text" name="responsavel_telefone" value="<?php echo htmlspecialchars($_POST['responsavel_telefone'] ?? ''); ?>"
                        placeholder="(00) 00000-0000" style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">E-mail do Responsável</label>
                    <input type="email" name="responsavel_email" value="<?php echo htmlspecialchars($_POST['responsavel_email'] ?? ''); ?>"
                        style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Complemento <span style="font-weight:500; text-transform:none; font-size: 0.7rem;">(ex: turma, série, ano escolar)</span></label>
                    <input type="text" name="complemento" value="<?php echo htmlspecialchars($_POST['complemento'] ?? ''); ?>"
                        placeholder="Ex: 5º Ano A, 2ª Série B..." style="<?php echo $input_style; ?>">
                </div>
            </div>
        </div>

        <!-- SEÇÃO 3: ACADEMIA & GRADUAÇÃO -->
        <div class="dashboard-container" style="padding: 40px; margin-bottom: 30px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Academia & Graduação</h2>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
                <div>
                    <label style="<?php echo $label_style; ?>">Academia / Unidade</label>
                    <select name="academia_id" id="academia_id" onchange="filtrarTurmas()" style="<?php echo $select_style; ?>">
                        <option value="">— Selecionar Academia —</option>
                        <?php foreach ($academias as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo ($_POST['academia_id'] ?? '') == $a['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Turma</label>
                    <select name="turma_id" id="turma_id" style="<?php echo $select_style; ?>">
                        <option value="">— Selecionar Turma —</option>
                        <?php foreach ($todas_turmas as $t): ?>
                            <option value="<?php echo $t['id']; ?>" data-academia="<?php echo $t['academia_id']; ?>" <?php echo ($_POST['turma_id'] ?? '') == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['nome']); ?>
                                <?php if ($t['horario']): ?> — <?php echo htmlspecialchars($t['horario']); ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Faixa</label>
                    <select name="faixa" style="<?php echo $select_style; ?>">
                        <option value="">— Selecionar Faixa —</option>
                        <?php foreach ($graduacoes_list as $g_opt): 
                            $display_val = $g_opt['nome'];
                            if (!empty($g_opt['grau'])) {
                                $display_val .= ' - ' . $g_opt['grau'];
                            }
                        ?>
                            <option value="<?php echo htmlspecialchars($display_val); ?>" <?php echo ($_POST['faixa'] ?? '') == $display_val ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(strtoupper($display_val)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Data de Graduação</label>
                    <input type="date" name="data_graduacao" value="<?php echo $_POST['data_graduacao'] ?? ''; ?>"
                        style="<?php echo $input_style; ?>">
                </div>
            </div>
        </div>

        <!-- SEÇÃO 4: ENDEREÇO -->
        <div class="dashboard-container" style="padding: 40px; margin-bottom: 30px;">
            <div style="border-left: 6px solid #08153a; padding-left: 15px; margin-bottom: 30px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; text-transform: uppercase; margin: 0; line-height: 1;">Endereço & Localização</h2>
            </div>
            <div style="display:grid; grid-template-columns: 180px 1fr; gap:25px; margin-bottom:25px;">
                <div>
                    <label style="<?php echo $label_style; ?>">CEP</label>
                    <input type="text" name="cep" id="cep" value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>"
                        onblur="buscarCEP(this.value)" placeholder="00000-000" style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Logradouro</label>
                    <input type="text" name="endereco" id="endereco" value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>"
                        style="<?php echo $input_style; ?>">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 100px 1fr 1fr; gap:25px; margin-bottom:25px;">
                <div>
                    <label style="<?php echo $label_style; ?>">Nº</label>
                    <input type="text" name="endereco_numero" value="<?php echo htmlspecialchars($_POST['endereco_numero'] ?? ''); ?>"
                        style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Complemento</label>
                    <input type="text" name="endereco_complemento" value="<?php echo htmlspecialchars($_POST['endereco_complemento'] ?? ''); ?>"
                        style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">Bairro</label>
                    <input type="text" name="bairro" id="bairro" value="<?php echo htmlspecialchars($_POST['bairro'] ?? ''); ?>"
                        style="<?php echo $input_style; ?>">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 100px; gap:25px;">
                <div>
                    <label style="<?php echo $label_style; ?>">Cidade</label>
                    <input type="text" name="cidade" id="cidade" value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>"
                        style="<?php echo $input_style; ?>">
                </div>
                <div>
                    <label style="<?php echo $label_style; ?>">UF</label>
                    <input type="text" name="estado" id="estado" value="<?php echo htmlspecialchars($_POST['estado'] ?? ''); ?>"
                        maxlength="2" style="<?php echo $input_style; ?>">
                </div>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn-sq" style="width:100%; padding: 20px; font-size: var(--fs-base);">
                <i class="fa-solid fa-user-plus" style="margin-right: 10px;"></i> CADASTRAR ALUNO
            </button>
        </div>
    </form>
</section>

<script>
// Filtrar turmas por academia
function filtrarTurmas() {
    const academiaId = document.getElementById('academia_id').value;
    const selectTurma = document.getElementById('turma_id');
    if (!selectTurma) return;
    const options = selectTurma.querySelectorAll('option');
    options.forEach(opt => {
        if (!opt.value) return;
        const optAcademia = opt.getAttribute('data-academia');
        if (!academiaId || optAcademia == academiaId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    selectTurma.value = '';
}

// Buscar CEP via ViaCEP
function buscarCEP(cep) {
    cep = cep.replace(/\D/g, '');
    if (cep.length !== 8) return;
    fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(r => r.json())
        .then(d => {
            if (!d.erro) {
                document.getElementById('endereco').value = d.logradouro || '';
                document.getElementById('bairro').value = d.bairro || '';
                document.getElementById('cidade').value = d.localidade || '';
                document.getElementById('estado').value = d.uf || '';
            }
        }).catch(() => {});
}

// Inicializar filtro ao carregar
document.addEventListener('DOMContentLoaded', function() {
    filtrarTurmas();
});
</script>

<?php include 'footer.php'; ?>
