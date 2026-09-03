<?php
require_once '../config.php';
$back_link = "unidades.php";
include 'header.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: unidades.php');
    exit;
}

// Buscar Unidade
$stmt = $pdo->prepare("SELECT * FROM unidades WHERE id = ?");
$stmt->execute([$id]);
$unidade = $stmt->fetch();

if (!$unidade) {
    header('Location: unidades.php');
    exit;
}

// Buscar Planos do Mestre para o Select
$planos_stmt = $pdo->query("SELECT * FROM planos_mestre WHERE ativo = 1 ORDER BY nome ASC");
$planos_mestre = $planos_stmt->fetchAll();

// Buscar o Administrador (Super Admin) principal dessa unidade
$stmt_admin = $pdo->prepare("SELECT * FROM usuarios WHERE unidade_id = ? AND nivel = 'admin' ORDER BY id ASC LIMIT 1");
$stmt_admin->execute([$id]);
$admin_unidade = $stmt_admin->fetch();

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $status = $_POST['status'] ?? '';
    $plano_id = $_POST['plano_id'] ?? null;
    $email_contato = $_POST['email_contato'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $documento = $_POST['documento'] ?? '';
    $dia_vencimento = $_POST['dia_vencimento'] ?? 10;

    // Dados de Endereço
    $cep = $_POST['cep'] ?? '';
    $logradouro = $_POST['logradouro'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $complemento = $_POST['complemento'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $estado = $_POST['estado'] ?? '';
    // Módulos
    $modulos_selecionados = $_POST['modulos'] ?? [];
    $modulos_json = json_encode($modulos_selecionados);

    // Administrador (Super Admin) do painel da unidade
    $admin_nome = trim($_POST['admin_nome'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_senha = $_POST['admin_senha'] ?? ''; // opcional: em branco mantém a senha atual

    if ($nome && $status && $plano_id && $email_contato && $admin_nome && $admin_email) {
        try {
            $stmt = $pdo->prepare("UPDATE unidades SET
                nome = ?, status = ?, plano_id = ?, email_contato = ?, telefone = ?, documento = ?,
                dia_vencimento = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?, modulos = ?
                WHERE id = ?");
            $stmt->execute([
                $nome,
                $status,
                $plano_id,
                $email_contato,
                $telefone,
                $documento,
                $dia_vencimento,
                $cep,
                $logradouro,
                $numero,
                $complemento,
                $bairro,
                $cidade,
                $estado,
                $modulos_json,
                $id
            ]);

            // Atualizar (ou criar, se a unidade nunca teve) o Administrador do sistema
            if ($admin_unidade) {
                if ($admin_senha) {
                    $senha_hash = password_hash($admin_senha, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ? WHERE id = ?")
                        ->execute([$admin_nome, $admin_email, $senha_hash, $admin_unidade['id']]);
                } else {
                    $pdo->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?")
                        ->execute([$admin_nome, $admin_email, $admin_unidade['id']]);
                }
            } elseif ($admin_senha) {
                $senha_hash = password_hash($admin_senha, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (unidade_id, nome, email, senha, nivel, status) VALUES (?, ?, ?, ?, 'admin', 'ativo')")
                    ->execute([$id, $admin_nome, $admin_email, $senha_hash]);
            } else {
                $erro = "Esta unidade ainda não tem um Administrador. Defina uma senha para criar o acesso dele.";
            }

            if (!$erro) {
                $mensagem = "Unidade atualizada com sucesso!";
            }

            // Recarregar dados para o form
            $stmt = $pdo->prepare("SELECT * FROM unidades WHERE id = ?");
            $stmt->execute([$id]);
            $unidade = $stmt->fetch();

            $stmt_admin = $pdo->prepare("SELECT * FROM usuarios WHERE unidade_id = ? AND nivel = 'admin' ORDER BY id ASC LIMIT 1");
            $stmt_admin->execute([$id]);
            $admin_unidade = $stmt_admin->fetch();
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    } else {
        $erro = "Por favor, preencha todos os campos obrigatórios, inclusive nome e e-mail do administrador.";
    }
}
?>



<?php if ($mensagem): ?>
    <div class="alert"
        style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 0.5rem; border: 1px solid #bbf7d0; margin-bottom: 2rem; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-circle-check"></i>
        <strong><?php echo $mensagem; ?></strong>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert"
        style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem; border: 1px solid #fecaca; margin-bottom: 2rem; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-circle-exclamation"></i>
        <strong><?php echo $erro; ?></strong>
    </div>
<?php endif; ?>

<div class="card"
    style="border: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 2.5rem; background: #fff; border-radius: 1rem;">

    <form method="POST" action="">

        <!-- SEÇÃO 1: DADOS BÁSICOS -->
        <div style="margin-bottom: 2.5rem;">
            <h2
                style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fa-solid fa-building"></i> Identificação e Contato
            </h2>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">Nome da Academia</label>
                    <input type="text" name="nome" class="form-control-custom" required
                        value="<?php echo htmlspecialchars($unidade['nome']); ?>">
                </div>
                <div class="form-group">
                    <label class="label-custom">Status</label>
                    <select name="status" class="form-control-custom">
                        <option value="ativo" <?php echo $unidade['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo
                        </option>
                        <option value="inativo" <?php echo $unidade['status'] === 'inativo' ? 'selected' : ''; ?>>
                            Inativo</option>
                        <option value="suspenso" <?php echo $unidade['status'] === 'suspenso' ? 'selected' : ''; ?>>
                            Suspenso</option>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">CPF ou CNPJ</label>
                    <input type="text" name="documento" class="form-control-custom"
                        value="<?php echo htmlspecialchars($unidade['documento'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="label-custom">E-mail de Contato</label>
                    <input type="email" name="email_contato" class="form-control-custom" required
                        value="<?php echo htmlspecialchars($unidade['email_contato'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="label-custom">Telefone</label>
                    <input type="text" name="telefone" class="form-control-custom"
                        value="<?php echo htmlspecialchars($unidade['telefone'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- SEÇÃO 1B: ADMINISTRADOR DO SISTEMA -->
        <div style="margin-bottom: 2.5rem;">
            <h2
                style="font-size: 1.1rem; font-weight: 700; color: #db2777; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fa-solid fa-user-shield"></i> Administrador do Sistema (Super Admin da Unidade)
            </h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">Nome do Administrador</label>
                    <input type="text" name="admin_nome" class="form-control-custom" required
                        value="<?php echo htmlspecialchars($admin_unidade['nome'] ?? ''); ?>" placeholder="Ex: João da Silva">
                </div>
                <div class="form-group">
                    <label class="label-custom">E-mail do Administrador (Login)</label>
                    <input type="email" name="admin_email" class="form-control-custom" required
                        value="<?php echo htmlspecialchars($admin_unidade['email'] ?? ''); ?>" placeholder="joao@academia.com">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 1.5rem; margin-top: 1.5rem; align-items: end;">
                <div class="form-group">
                    <label class="label-custom">Nova Senha de Acesso</label>
                    <input type="text" name="admin_senha" id="admin_senha" class="form-control-custom"
                        placeholder="Deixe em branco para manter a senha atual">
                </div>
                <button type="button" onclick="gerarSenhaAdmin()" class="btn btn-secondary"
                    style="padding: 0.875rem 1.5rem; border-radius: 0.5rem; white-space: nowrap;">
                    <i class="fa-solid fa-shuffle" style="margin-right: 6px;"></i> Gerar Senha
                </button>
            </div>
            <?php if (!$admin_unidade): ?>
            <p style="margin: 1rem 0 0; font-size: 0.85rem; color: #b45309; background: #fffbeb; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #fef3c7;">
                <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i>
                Esta unidade ainda não tem um Administrador cadastrado. Defina uma senha acima para criar o acesso dele.
            </p>
            <?php endif; ?>
        </div>

        <!-- SEÇÃO 2: ENDEREÇO -->
        <div style="margin-bottom: 2.5rem;">
            <h2
                style="font-size: 1.1rem; font-weight: 700; color: #6366f1; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fa-solid fa-location-dot"></i> Localização
            </h2>
            <div style="display: grid; grid-template-columns: 1fr 3fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">CEP</label>
                    <input type="text" name="cep" id="cep" class="form-control-custom"
                        value="<?php echo htmlspecialchars($unidade['cep'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="label-custom">Logradouro</label>
                    <input type="text" name="logradouro" id="logradouro" class="form-control-custom"
                        value="<?php echo htmlspecialchars($unidade['logradouro'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="label-custom">Número</label>
                    <input type="text" name="numero" class="form-control-custom"
                        value="<?php echo htmlspecialchars($unidade['numero'] ?? ''); ?>">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control-custom"
                        value="<?php echo htmlspecialchars($unidade['bairro'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="label-custom">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control-custom"
                        value="<?php echo htmlspecialchars($unidade['cidade'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="label-custom">Estado (UF)</label>
                    <input type="text" name="estado" id="estado" class="form-control-custom" maxlength="2"
                        value="<?php echo htmlspecialchars($unidade['estado'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- SEÇÃO 3: PLANO E COBRANÇA -->
        <div style="margin-bottom: 2.5rem;">
            <h2
                style="font-size: 1.1rem; font-weight: 700; color: var(--success); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fa-solid fa-file-invoice-dollar"></i> Financeiro
            </h2>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">Alterar Plano de Venda</label>
                    <select name="plano_id" class="form-control-custom" required>
                        <option value="">Selecione um plano...</option>
                        <?php foreach ($planos_mestre as $pl): ?>
                            <option value="<?php echo $pl['id']; ?>" <?php echo $unidade['plano_id'] == $pl['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pl['nome']); ?> - R$
                                <?php echo number_format($pl['valor'], 2, ',', '.'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="label-custom">Dia de Vencimento</label>
                    <select name="dia_vencimento" class="form-control-custom">
                        <option value="5" <?php echo $unidade['dia_vencimento'] == 5 ? 'selected' : ''; ?>>Dia 05
                        </option>
                        <option value="10" <?php echo $unidade['dia_vencimento'] == 10 ? 'selected' : ''; ?>>Dia 10
                        </option>
                        <option value="15" <?php echo $unidade['dia_vencimento'] == 15 ? 'selected' : ''; ?>>Dia 15
                        </option>
                        <option value="20" <?php echo $unidade['dia_vencimento'] == 20 ? 'selected' : ''; ?>>Dia 20
                        </option>
                        <option value="25" <?php echo $unidade['dia_vencimento'] == 25 ? 'selected' : ''; ?>>Dia 25
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- SEÇÃO 4: MÓDULOS ATIVOS -->
        <div style="margin-bottom: 2.5rem;">
            <h2
                style="font-size: 1.1rem; font-weight: 700; color: #f59e0b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fa-solid fa-cubes"></i> Módulos Ativos
            </h2>
            <div
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; background: #fffbeb; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #fef3c7;">
                <?php
                $modulos_unidade = json_decode($unidade['modulos'] ?? '[]', true);
                if (!is_array($modulos_unidade))
                    $modulos_unidade = [];

                $modulos_disponiveis = [
                    'financeiro' => 'Financeiro Completo',
                    'alunos' => 'Gestão de Alunos',
                    'turmas' => 'Turmas e Horários',
                    'competicoes' => 'Organização de Eventos',
                    'graduacoes' => 'Troca de Faixas',
                    'config' => 'Configurações Avançadas'
                ];
                foreach ($modulos_disponiveis as $key => $label): ?>
                    <label
                        style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600; color: #92400e;">
                        <input type="checkbox" name="modulos[]" value="<?php echo $key; ?>" <?php echo in_array($key, $modulos_unidade) ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                        <?php echo $label; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div
            style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; border-top: 2px solid #f1f5f9; padding-top: 2rem;">
            <a href="unidades.php" class="btn btn-secondary"
                style="padding: 1rem 2rem; border-radius: 0.5rem;">Voltar</a>
            <button type="submit" class="btn btn-primary"
                style="padding: 1rem 3rem; font-weight: 700; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);">
                SALVAR ALTERAÇÕES
            </button>
        </div>
    </form>
</div>


<style>
    .label-custom {
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .form-control-custom {
        width: 100%;
        padding: 0.875rem;
        border-radius: 0.5rem;
        border: 1.5px solid var(--border);
        font-weight: 600;
        transition: border-color 0.2s;
    }

    .form-control-custom:focus {
        border-color: var(--primary);
        outline: none;
    }
</style>

<script>
    function gerarSenhaAdmin() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        let senha = '';
        for (let i = 0; i < 10; i++) {
            senha += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('admin_senha').value = senha;
    }

    // Busca de CEP (ViaCEP)
    document.getElementById('cep').addEventListener('blur', function () {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length === 8) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(res => res.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('logradouro').value = data.logradouro;
                        document.getElementById('bairro').value = data.bairro;
                        document.getElementById('cidade').value = data.localidade;
                        document.getElementById('estado').value = data.uf;
                    }
                });
        }
    });
</script>

<?php include 'footer.php'; ?>