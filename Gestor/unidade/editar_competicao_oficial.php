<?php
require_once '../config.php';

$id = $_GET['id'] ?? null;
$unidade_id = getUnidadeId();

if (!$id) {
    header('Location: competicoes_oficiais.php');
    exit;
}

// 1. Buscar dados da competição
$stmt = $pdo->prepare("SELECT * FROM competicoes_oficiais WHERE id = ? AND unidade_id = ?");
$stmt->execute([$id, $unidade_id]);
$comp = $stmt->fetch();

if (!$comp) {
    header('Location: competicoes_oficiais.php');
    exit;
}

$mensagem = '';
$erro = '';

// 2. Handlers AJAX
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    try {
        if ($_POST['ajax_action'] === 'add_atleta') {
            $aluno_id = $_POST['aluno_id'];
            $categoria = $_POST['categoria'] ?? '';

            // Verificar se o atleta já está na lista
            $check = $pdo->prepare("SELECT id FROM competicao_oficial_atletas WHERE competicao_id = ? AND aluno_id = ?");
            $check->execute([$id, $aluno_id]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Atleta já está na lista.']);
            } else {
                $ins = $pdo->prepare("INSERT INTO competicao_oficial_atletas (competicao_id, aluno_id, categoria_disputada) VALUES (?, ?, ?)");
                $ins->execute([$id, $aluno_id, $categoria]);
                echo json_encode(['success' => true]);
            }
        } elseif ($_POST['ajax_action'] === 'remove_atleta') {
            $at_id = $_POST['atleta_ref_id']; // Este é o ID em competicao_oficial_atletas
            $del = $pdo->prepare("DELETE FROM competicao_oficial_atletas WHERE id = ? AND competicao_id = ?");
            $del->execute([$at_id, $id]);
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 3. Handlers POST (Normal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_info'])) {
    $nome = $_POST['nome'] ?? '';
    $data_inicio = $_POST['data_inicio'] ?? '';
    $data_fim = $_POST['data_fim'] ?: null;
    $localizacao = $_POST['localizacao'] ?? '';
    $organizacao = $_POST['organizacao'] ?? '';
    $responsaveis = $_POST['responsaveis'] ?? '';
    $status = $_POST['status'] ?? 'aberto';

    try {
        $upd = $pdo->prepare("UPDATE competicoes_oficiais SET nome = ?, data_inicio = ?, data_fim = ?, localizacao = ?, organizacao = ?, responsaveis = ?, status = ? WHERE id = ?");
        $upd->execute([$nome, $data_inicio, $data_fim, $localizacao, $organizacao, $responsaveis, $status, $id]);
        $mensagem = "Informações atualizadas com sucesso!";
        // Recarregar dados da competição
        $stmt->execute([$id, $unidade_id]);
        $comp = $stmt->fetch();
    } catch (PDOException $e) {
        $erro = "Erro ao atualizar: " . $e->getMessage();
    }
}

// 4. Buscar Atletas
$atletas_delegacao = $pdo->prepare("
    SELECT coa.*, a.nome_completo, a.faixa, a.data_nascimento, a.cpf, a.rg
    FROM competicao_oficial_atletas coa
    JOIN alunos a ON coa.aluno_id = a.id
    WHERE coa.competicao_id = ?
    ORDER BY a.nome_completo ASC
");
$atletas_delegacao->execute([$id]);
$atletas = $atletas_delegacao->fetchAll();

$todos_alunos = $pdo->prepare("SELECT id, nome_completo, faixa FROM alunos WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome_completo ASC");
$todos_alunos->execute([$unidade_id]);
$alunos_select = $todos_alunos->fetchAll();

$custom_title = "Gerenciar Delegação: " . $comp['nome'];
$custom_shortcuts = [
    ['label' => 'NOVO OFICIAL', 'link' => 'nova_competicao_oficial.php', 'icon' => 'fa-solid fa-trophy', 'class' => 'btn-sq'],
    ['label' => 'NOVO TORNEIO', 'link' => 'nova_competicao.php', 'icon' => 'fa-solid fa-plus-circle', 'class' => 'btn-sq'],
    ['label' => 'NOVO EXAME', 'link' => 'novo_evento_faixa.php', 'icon' => 'fa-solid fa-medal', 'class' => 'btn-sq-outline']
];
include 'header.php';
?>

<style>
    /* Custom tweaks for this page */
    .tab-trigger {
        padding: 12px 25px;
        background: #f3f4f6;
        color: var(--text-muted);
        text-decoration: none;
        font-size: var(--fs-base);
        font-weight: 600;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }

    .tab-trigger:hover {
        background: #e5e7eb;
        color: var(--text-dark);
    }

    .tab-trigger.active {
        background: #08153a;
        color: var(--primary-green);
    }

    .tab-pane {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .tab-pane.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>


<div style="padding: 1.5rem;">
    <h2 class="section-title-sq" style="margin-bottom: 2.5rem;"><?php echo htmlspecialchars($comp['nome']); ?></h2>

    <!-- Main Content Area (Full Width) -->
    <div>
        <div class="nav-tabs-sq" style="margin-bottom: 3rem;">
            <button class="tab-trigger active" onclick="switchTab(event, 'tab-atletas')">
                <i class="fa-solid fa-users"></i> Atletas (Delegação)
            </button>
            <button class="tab-trigger" onclick="switchTab(event, 'tab-info')">
                <i class="fa-solid fa-gears"></i> Informações do Evento
            </button>
            <button class="tab-trigger" onclick="switchTab(event, 'tab-docs')">
                <i class="fa-solid fa-file-pdf"></i> Documentação e PDF
            </button>
        </div>


            <!-- ATLETAS TAB -->
            <div id="tab-atletas" class="tab-pane active">
                <div class="card" style="border-radius: 0; border: 1px solid #e2e8f0; background: white; padding: 2.5rem; box-shadow: var(--shadow-sm);">
                    <div style="margin-bottom: 2.5rem;">
                        <h3 class="section-title-sq" style="margin:0;">Atletas na Delegação</h3>
                    </div>
                    <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;" id="table-atletas">
                                <thead>
                                    <tr style="text-align: left; border-bottom: 2px solid #e2e8f0; background: #f8fafc;">
                                        <th style="padding: 1.25rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #1e293b; font-weight: 900; letter-spacing: 0.05em;">Atleta</th>
                                        <th style="padding: 1.25rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #1e293b; font-weight: 900; letter-spacing: 0.05em;">Documentação</th>
                                        <th style="padding: 1.25rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #1e293b; font-weight: 900; letter-spacing: 0.05em;">Faixa / Idade</th>
                                        <th style="padding: 1.25rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #1e293b; font-weight: 900; letter-spacing: 0.05em;">Categoria Disp.</th>
                                        <th style="padding: 1.25rem 1rem; font-size: 0.7rem; text-transform: uppercase; color: #1e293b; font-weight: 900; letter-spacing: 0.05em; text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($atletas as $at):
                                        $date = new DateTime($at['data_nascimento']);
                                        $now = new DateTime();
                                        $age = $now->diff($date)->y;
                                        ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: all 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'" id="row-atleta-<?php echo $at['id']; ?>">
                                            <td style="padding: 1.25rem 1rem;">
                                                <div style="font-weight: 800; color: #08153a; font-size: 1rem; text-transform: uppercase;">
                                                    <?php echo htmlspecialchars($at['nome_completo']); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1.25rem 1rem;">
                                                <div style="font-size: 0.8rem; color: #64748b;">
                                                    <i class="fa-solid fa-id-card" style="margin-right: 5px; opacity: 0.7;"></i>
                                                    <strong>CPF:</strong> <?php echo $at['cpf'] ?: '--'; ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                                                    <i class="fa-solid fa-passport" style="margin-right: 5px; opacity: 0.7;"></i>
                                                    <strong>RG:</strong> <?php echo $at['rg'] ?: '--'; ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1.25rem 1rem;">
                                                <div style="font-size: 0.9rem; font-weight: 700; color: #1e293b;">
                                                    <?php echo $at['faixa']; ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #64748b;">
                                                    <?php echo $age; ?> anos
                                                </div>
                                            </td>
                                            <td style="padding: 1.25rem 1rem;">
                                                <span class="badge" style="background: rgba(163, 230, 53, 0.08); color: #4a5d23; border: 1px solid rgba(163, 230, 53, 0.2); padding: 0.5rem 0.8rem; border-radius: 0; font-size: 0.75rem; font-weight: 700;">
                                                    <?php echo htmlspecialchars($at['categoria_disputada'] ?: 'Não definida'); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1.25rem 1rem; text-align: right;">
                                                <button onclick="removeAtleta(<?php echo $at['id']; ?>)" class="btn" style="background: rgba(239, 68, 68, 0.08); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.15); padding: 0.6rem; border-radius: 0; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; width: 35px; height: 35px;">
                                                    <i class="fa-solid fa-trash-can" style="font-size: 0.8rem;"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($atletas)): ?>
                                        <tr id="empty-row">
                                            <td colspan="5" style="text-align: center; padding: 5rem 2rem; color: #94a3b8;">
                                                <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.1;"><i class="fa-solid fa-user-ninja"></i></div>
                                                <p style="font-weight: 600;">Nenhum atleta adicionado à delegação.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Formulário Adicionar (Clean Card) -->
                    <div style="background: #f8fafc; padding: 2.5rem; border: 1px solid #e2e8f0; margin-top: 3rem;">
                        <h3 class="section-title-sq">Adicionar à Delegação</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: end;">
                            <div class="form-group">
                                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; display: block; letter-spacing: 0.05em;">Selecione o Aluno</label>
                                <select id="select-aluno" class="form-control" style="height: 3.5rem; border-radius: 0; background: #fff; border: 1px solid #e2e8f0; width: 100%;">
                                    <option value="">-- Buscar Atleta --</option>
                                    <?php foreach ($alunos_select as $al): ?>
                                        <option value="<?php echo $al['id']; ?>">
                                            <?php echo htmlspecialchars($al['nome_completo']); ?> (<?php echo $al['faixa']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; display: block; letter-spacing: 0.05em;">Categoria / Divisão</label>
                                <input type="text" id="atleta-categoria" class="form-control" placeholder="Ex: Master 1 / Azul / Pesado" style="height: 3.5rem; border-radius: 0; background: #fff; border: 1px solid #e2e8f0; width: 100%;">
                            </div>
                        </div>
                        <button onclick="addAtleta()" class="btn-sq" style="width: 100%; margin-top: 2rem;">
                            <i class="fa-solid fa-save"></i> VINCULAR ATLETA À DELEGAÇÃO
                        </button>
                    </div>
                </div>
            </div>

            <!-- INFO TAB -->
            <div id="tab-info" class="tab-pane">
                <div class="card" style="border-radius: 0; border: 1px solid #e2e8f0; background: white; padding: 2.5rem; box-shadow: var(--shadow-sm);">
                    <form method="POST">
                        <input type="hidden" name="salvar_info" value="1">
                        <div style="padding: 0 0 1.5rem 0; border-bottom: 1px solid #f1f5f9; margin-bottom: 2.5rem;">
                            <h3 class="section-title-sq" style="margin:0;">
                                <i class="fa-solid fa-pen-to-square" style="color: #08153a;"></i> Editar Informações do Evento
                            </h3>
                        </div>

                        <div class="form-group" style="margin-bottom: 2rem;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Nome do Evento</label>
                            <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($comp['nome']); ?>" required 
                                style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                            <div class="form-group">
                                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data de Início</label>
                                <input type="date" name="data_inicio" class="form-control" value="<?php echo $comp['data_inicio']; ?>" required 
                                    style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data de Término</label>
                                <input type="date" name="data_fim" class="form-control" value="<?php echo $comp['data_fim']; ?>" 
                                    style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                            <div class="form-group">
                                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Localização / Cidade</label>
                                <input type="text" name="localizacao" class="form-control" value="<?php echo htmlspecialchars($comp['localizacao']); ?>" 
                                    style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Organização / Federação</label>
                                <input type="text" name="organizacao" class="form-control" value="<?php echo htmlspecialchars($comp['organizacao']); ?>" 
                                    style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 2rem;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Responsáveis pela Equipe</label>
                            <textarea name="responsaveis" class="form-control" rows="4" 
                                style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; min-height: 100px; border-radius: 0;"><?php echo htmlspecialchars($comp['responsaveis']); ?></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem; align-items: end;">
                            <div class="form-group">
                                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Status Atual</label>
                                <select name="status" class="form-control" style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; font-weight: 700; border-radius: 0;">
                                    <option value="aberto" <?php echo $comp['status'] === 'aberto' ? 'selected' : ''; ?>>ABERTO</option>
                                    <option value="finalizado" <?php echo $comp['status'] === 'finalizado' ? 'selected' : ''; ?>>FINALIZADO</option>
                                </select>
                            </div>
                             <button type="submit" class="btn-sq" style="height: 3.5rem; width: 100%;">
                                <i class="fa-solid fa-save"></i> SALVAR ALTERAÇÕES
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- DOCS TAB -->
            <div id="tab-docs" class="tab-pane">
                <div class="card" style="border-radius: 0; border: 1px solid #e2e8f0; background: white; padding: 3rem; box-shadow: var(--shadow-sm); text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.15;">📄</div>
                    <h3 class="section-title-sq" style="border-left:none; padding-left:0; text-align:center; justify-content:center; display:flex;">Exportação de Delegação</h3>
                    <p style="color: #64748b; max-width: 500px; margin: 0 auto 2.5rem; line-height: 1.6;">
                        Gere a listagem oficial da delegação em formato PDF para apresentação em federações, controle de viagens ou conferência rápida no dia do evento.
                    </p>

                    <div style="display: flex; justify-content: center; gap: 1rem;">
                        <a href="imprimir.php?id=<?php echo $id; ?>&tipo=competicao_oficial" target="_blank" class="btn-sq" style="padding: 1rem 2.5rem; font-size: 1rem; text-decoration: none;">
                            <i class="fa-solid fa-file-pdf"></i> GERAR PDF DA DELEGAÇÃO
                        </a>
                    </div>
                </div>
        </div>
    </div>

    <script>
        function switchTab(evt, tabId) {
            const triggers = document.querySelectorAll('.tab-trigger');
            const panes = document.querySelectorAll('.tab-pane');

            triggers.forEach(t => t.classList.remove('active'));
            panes.forEach(p => p.classList.remove('active'));

            evt.currentTarget.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        async function addAtleta() {
            const alunoId = document.getElementById('select-aluno').value;
            const categoria = document.getElementById('atleta-categoria').value;

            if (!alunoId) {
                alert('Selecione um atleta!');
                return;
            }

            const formData = new FormData();
            formData.append('ajax_action', 'add_atleta');
            formData.append('aluno_id', alunoId);
            formData.append('categoria', categoria);

            try {
                const resp = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const res = await resp.json();
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.message);
                }
            } catch (e) {
                alert('Erro de conexão ao adicionar atleta.');
            }
        }

        async function removeAtleta(refId) {
            if (!confirm('Remover este atleta da delegação?')) return;

            const formData = new FormData();
            formData.append('ajax_action', 'remove_atleta');
            formData.append('atleta_ref_id', refId);

            try {
                const resp = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const res = await resp.json();
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.message);
                }
            } catch (e) {
                alert('Erro de conexão ao remover atleta.');
            }
        }
    </script>

    <?php include 'footer.php'; ?>