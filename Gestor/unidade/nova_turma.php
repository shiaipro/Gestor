<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('turmas_criar')) {
    header('Location: turmas.php?erro=sem_permissao');
    exit;
}

// Buscar academias para o select
$stmt_academias = $pdo->prepare("SELECT id, nome FROM academias WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
$stmt_academias->execute([$unidade_id]);
$academias_disponiveis = $stmt_academias->fetchAll();

// Buscar equipe do RH
try {
    $stmt_equipe = $pdo->prepare("SELECT id, nome, funcao, usuario_id FROM unidade_equipe WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
    $stmt_equipe->execute([$unidade_id]);
    $equipe_rh = $stmt_equipe->fetchAll();
} catch (Exception $e) {
    $equipe_rh = [];
}

// Garantir que a coluna instrucoes_pdf existe na tabela turmas
try {
    $pdo->query("SELECT instrucoes_pdf FROM turmas LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->query("ALTER TABLE turmas ADD COLUMN instrucoes_pdf VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $ex) {}
}

// Garantir que a coluna instrutor_equipe_id existe (vínculo por ID com unidade_equipe,
// usado para restringir a visão do instrutor às próprias turmas em vez de comparar nome)
try {
    $pdo->query("SELECT instrutor_equipe_id FROM turmas LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->query("ALTER TABLE turmas ADD COLUMN instrutor_equipe_id INT DEFAULT NULL");
    } catch (PDOException $ex) {}
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $instrutor_equipe_id = $_POST['instrutor_equipe_id'] ?: null;
    // Fallback: quando não há ninguém cadastrado no RH ainda, o formulário mostra um campo de texto livre
    $instrutor = trim($_POST['instrutor'] ?? '');
    if ($instrutor_equipe_id) {
        $stmt_nome_instrutor = $pdo->prepare("SELECT nome FROM unidade_equipe WHERE id = ? AND unidade_id = ?");
        $stmt_nome_instrutor->execute([$instrutor_equipe_id, $unidade_id]);
        $instrutor = $stmt_nome_instrutor->fetchColumn() ?: '';
    }
    $horario = $_POST['horario'] ?? '';
    $dias_semana_raw = $_POST['dias_semana'] ?? [];
    $capacidade_max = $_POST['capacidade_max'] ?: null;
    $frequencia = $_POST['frequencia'] ?? 'MENSAL';
    $descricao = $_POST['descricao'] ?? '';
    $academia_id = $_POST['academia_id'] ?? null;

    $comissao_prof_tipo = $_POST['comissao_prof_tipo'] ?? 'percentual';
    $comissao_prof_valor = $_POST['comissao_prof_valor'] ?: 0.00;
    $comissao_escola_tipo = $_POST['comissao_escola_tipo'] ?? 'percentual';
    $comissao_escola_valor = $_POST['comissao_escola_valor'] ?: 0.00;

    if ($nome && $horario && !empty($dias_semana_raw)) {
        try {
            $dias_semana = implode(',', $dias_semana_raw);

            // Gerar UUID v4 para link público de inscrição
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            $uuid_inscricao = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

            $instrucoes_pdf = null;
            if (isset($_FILES['instrucoes_pdf']) && $_FILES['instrucoes_pdf']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['instrucoes_pdf']['tmp_name'];
                $file_name = $_FILES['instrucoes_pdf']['name'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $novo_nome = 'turma_instrucoes_' . uniqid() . '.pdf';
                    $path = getUploadPath('documentos');
                    if ($path && move_uploaded_file($file_tmp, $path . '/' . $novo_nome)) {
                        $instrucoes_pdf = $novo_nome;
                    } else {
                        $erro = "Erro ao salvar o arquivo PDF.";
                    }
                } else {
                    $erro = "Apenas arquivos PDF são permitidos.";
                }
            }

            if (empty($erro)) {
                $stmt = $pdo->prepare("INSERT INTO turmas (unidade_id, academia_id, nome, instrutor, instrutor_equipe_id, horario, dias_semana, capacidade_max, preco_mensal, frequencia, descricao, status, uuid_inscricao, comissao_prof_tipo, comissao_prof_valor, comissao_escola_tipo, comissao_escola_valor, instrucoes_pdf) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo', ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $unidade_id,
                    $academia_id,
                    $nome,
                    $instrutor,
                    $instrutor_equipe_id,
                    $horario,
                    $dias_semana,
                    $capacidade_max,
                    $_POST['preco_mensal'] ?? 0,
                    $frequencia,
                    $descricao,
                    $uuid_inscricao,
                    $comissao_prof_tipo,
                    $comissao_prof_valor,
                    $comissao_escola_tipo,
                    $comissao_escola_valor,
                    $instrucoes_pdf
                ]);

                $sucesso = "Turma criada com sucesso!";
                header("refresh:2;url=turmas.php");
            }
        } catch (PDOException $e) {
            $erro = "Erro ao criar turma: " . $e->getMessage();
        }
    } else {
        $erro = "Nome, horário e pelo menos um dia da semana são obrigatórios.";
    }
}

$custom_title = "Nova Turma";
include 'header.php';
?>



<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Nova Turma
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Cadastre um novo horário e modalidade para sua unidade.
            </p>
        </div>

    
        
        <div style="display: flex; gap: 10px;">
            <a href="turmas.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
    </div>

    <div class="nav-tabs-sq" style="margin-bottom: 2rem;">
    <a href="administrativo_dashboard.php" class="tab-item-sq">Dashboard</a>
    <a href="alunos.php" class="tab-item-sq">Alunos</a>
    <a href="equipe.php" class="tab-item-sq">RH</a>
    <a href="academias.php" class="tab-item-sq">Unidades</a>
    <a href="turmas.php" class="tab-item-sq active">Turmas</a>
    <a href="financeiro.php" class="tab-item-sq">Financeiro</a>
    <a href="planejamento.php" class="tab-item-sq">Planejamento</a>
</div>

    

    <form method="POST" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">
            <div class="dashboard-container" style="padding: 40px;">
                <?php if ($sucesso): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 20px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid var(--primary-green); margin-bottom: 30px;">
                        <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i> <?php echo $sucesso; ?>
                    </div>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 20px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid #ef4444; margin-bottom: 30px;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i> <?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; gap: 25px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nome da Modalidade / Turma</label>
                        <input type="text" name="nome" required placeholder="EX: JIU JITSU ADULTO - NOGI"
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark);">
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Professor / Instrutor Principal</label>
                        <?php if (!empty($equipe_rh)): ?>
                        <select name="instrutor_equipe_id" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                            <option value="">— SELECIONAR INSTRUTOR —</option>
                            <?php foreach ($equipe_rh as $m): ?>
                            <option value="<?php echo (int) $m['id']; ?>">
                                <?php echo htmlspecialchars(strtoupper($m['nome'])); ?><?php if (!empty($m['funcao'])): ?> — <?php echo htmlspecialchars(strtoupper($m['funcao'])); ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" name="instrutor" style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark);" placeholder="NOME DO PROFESSOR RESPONSÁVEL">
                        <p style="font-size: var(--fs-xs); color:var(--text-muted); margin-top:6px; font-weight:600;">Nenhum membro cadastrado no <a href="equipe.php" style="color:var(--primary-green); font-weight:900; text-decoration: none;">RH</a>. Cadastre primeiro para selecionar aqui.</p>
                        <?php endif; ?>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Horário de Início</label>
                            <input type="time" name="horario" required
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">MATRICULAS - Vagas minimas</label>
                            <input type="number" name="capacidade_max" placeholder="VAGAS"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Unidade / Local</label>
                            <select name="academia_id" required style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <?php foreach ($academias_disponiveis as $acad): ?>
                                    <option value="<?php echo $acad['id']; ?>"><?php echo htmlspecialchars(strtoupper($acad['nome'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Valor Mensal (R$)</label>
                            <input type="number" step="0.01" name="preco_mensal" placeholder="0,00"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Frequência</label>
                            <select name="frequencia" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="MENSAL">MENSAL</option>
                                <option value="TRIMESTRAL">TRIMESTRAL</option>
                                <option value="SEMESTRAL">SEMESTRAL</option>
                                <option value="ANUAL">ANUAL</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Status Operacional</label>
                            <select name="status" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="ativo">ATIVO</option>
                                <option value="inativo">INATIVO</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Descrição Adicional (Website)</label>
                        <textarea name="descricao" rows="4" placeholder="BREVE DESCRIÇÃO PARA O WEBSITE..."
                            style="width:100%; background: #fafafa; border: 1px solid var(--border-color); padding: 15px; font-size: var(--fs-base); font-weight: 600; color: var(--text-dark); resize: none;"></textarea>
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Instruções de Aula (PDF)</label>
                        <input type="file" name="instrucoes_pdf" accept="application/pdf"
                            style="width:100%; background: #fafafa; border: 1px solid var(--border-color); padding: 10px 15px; font-size: var(--fs-base); font-weight: 700; color:var(--text-dark);">
                    </div>

                    <!-- Configurações de Comissionamento -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 25px; margin-top: 10px;">
                        <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.05em;">Comissionamento</h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 15px;">
                            <!-- Comissão Professor -->
                            <div style="background: #fafafa; padding: 20px; border: 1px solid var(--border-color);">
                                <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.05em;"><i class="fa-solid fa-user-tie" style="margin-right: 8px;"></i>Comissão do Professor</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <label style="display:block; font-size: 10px; font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Tipo</label>
                                        <select name="comissao_prof_tipo" style="width:100%; height:45px; border:1px solid var(--border-color); background:#fff; padding:0 10px; font-size: var(--fs-xs); font-weight:700;">
                                            <option value="percentual">PERCENTUAL (%)</option>
                                            <option value="fixo">VALOR FIXO (R$)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block; font-size: 10px; font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Valor / %</label>
                                        <input type="number" step="0.01" name="comissao_prof_valor" placeholder="0,00" value="0.00" style="width:100%; height:45px; border:1px solid var(--border-color); background:#fff; padding:0 10px; font-size: var(--fs-xs); font-weight:700;">
                                    </div>
                                </div>
                            </div>

                            <!-- Comissão Escola -->
                            <div style="background: #fafafa; padding: 20px; border: 1px solid var(--border-color);">
                                <h4 style="font-size: var(--fs-xs); font-weight: 900; color: #08153a; text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.05em;"><i class="fa-solid fa-building" style="margin-right: 8px;"></i>Comissão da Escola</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <label style="display:block; font-size: 10px; font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Tipo</label>
                                        <select name="comissao_escola_tipo" style="width:100%; height:45px; border:1px solid var(--border-color); background:#fff; padding:0 10px; font-size: var(--fs-xs); font-weight:700;">
                                            <option value="percentual">PERCENTUAL (%)</option>
                                            <option value="fixo">VALOR FIXO (R$)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block; font-size: 10px; font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Valor / %</label>
                                        <input type="number" step="0.01" name="comissao_escola_valor" placeholder="0,00" value="0.00" style="width:100%; height:45px; border:1px solid var(--border-color); background:#fff; padding:0 10px; font-size: var(--fs-xs); font-weight:700;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding-top: 30px; ">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base);">
                             <i class="fa-solid fa-save" style="margin-right: 10px;"></i>CRIAR NOVA TURMA
                        </button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Agenda Semanal</h3>
                    <div style="display: grid; gap: 10px;">
                        <?php
                        $dias = ['seg' => 'SEGUNDA', 'ter' => 'TERÇA', 'qua' => 'QUARTA', 'qui' => 'QUINTA', 'sex' => 'SEXTA', 'sab' => 'SÁBADO', 'dom' => 'DOMINGO'];
                        foreach ($dias as $val => $label): ?>
                            <label style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 12px 15px; border: 1px solid var(--border-color);">
                                <span style="font-size: var(--fs-xs); font-weight: 900; color: #08153a;"><?php echo $label; ?></span>
                                <input type="checkbox" name="dias_semana[]" value="<?php echo $val; ?>" style="width: 18px; height: 18px; accent-color: var(--primary-green);">
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Configuração</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">O nome da turma será exibido no website e aplicativo para os alunos.</p>
                </div>
            </div>
        </div>
    </form>
</section>


<?php include 'footer.php'; ?>