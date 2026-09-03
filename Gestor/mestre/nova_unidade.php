<?php
require_once '../config.php';
$back_link = "unidades.php";
include 'header.php';

$mensagem = '';
$erro = '';

// Buscar Planos do Mestre para o Select - Protegido contra tabela inexistente
$planos_mestre = [];
try {
    $planos_stmt = $pdo->query("SELECT * FROM planos_mestre WHERE ativo = 1 ORDER BY nome ASC");
    $planos_mestre = $planos_stmt->fetchAll();
} catch (Exception $e) {
    // Tabela provavelmente não existe ainda
    $erro = "Aviso: Tabela de planos não encontrada. Por favor, rode os scripts de atualização.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $email_contato = $_POST['email_contato'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $documento = $_POST['documento'] ?? '';
    $plano_id = $_POST['plano_id'] ?? null;
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
    $admin_senha = $_POST['admin_senha'] ?? '';

    if ($nome && $slug && $email_contato && $plano_id && $admin_nome && $admin_email && $admin_senha) {
        try {
            $pdo->beginTransaction();

            // 1. Inserir Unidade (Academia)
            $stmt = $pdo->prepare("INSERT INTO unidades (nome, slug, plano_id, documento, dia_vencimento, telefone, email_contato, cep, logradouro, numero, complemento, bairro, cidade, estado, modulos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $nome,
                $slug,
                $plano_id,
                $documento,
                $dia_vencimento,
                $telefone,
                $email_contato,
                $cep,
                $logradouro,
                $numero,
                $complemento,
                $bairro,
                $cidade,
                $estado,
                $modulos_json
            ]);
            $unidade_id = $pdo->lastInsertId();

            // 2. Inserir Usuário Administrador (Super Admin do painel da unidade)
            $senha_hash = password_hash($admin_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (unidade_id, nome, email, senha, nivel) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->execute([$unidade_id, $admin_nome, $admin_email, $senha_hash]);

            // 3. Enviar E-mails de Boas-vindas (Protegido contra função desabilitada)
            $email_enviado = false;
            if (function_exists('mail')) {
                $assunto = "Bem-vindo ao SHIAI PRO - Sua Academia está ativa!";
                $corpo = "Olá $admin_nome,\n\nA academia $nome foi cadastrada com sucesso no sistema SHIAI PRO.\n\nAqui estão seus dados de acesso:\nURL: https://shiaipro.com.br/$slug\nLogin: $admin_email\nSenha: $admin_senha\n\nRecomendamos alterar sua senha após o primeiro acesso.\n\nEquipe SHIAI PRO";
                $headers = "From: contato@shiaipro.com.br";

                // Email para a Unidade
                @mail($admin_email, $assunto, $corpo, $headers);

                // Email para o Administrador Mestre
                $master_email = $_SESSION['usuario_email'] ?? 'admin@shiaipro.com.br';
                $assunto_master = "Nova Academia Cadastrada: $nome";
                $corpo_master = "Uma nova academia foi cadastrada no sistema.\n\nNome: $nome\nE-mail: $email_contato\nPlano: $plano_id\n\nAcesse o painel mestre para gerenciar.";
                @mail($master_email, $assunto_master, $corpo_master, $headers);
                $email_enviado = true;
            }

            $pdo->commit();
            $mensagem = "Academia cadastrada com sucesso!";
            if (!$email_enviado) {
                $mensagem .= " (Aviso: O servidor não suporta envio de e-mail, mas o cadastro foi realizado).";
            }
            $mensagem .= " Login do administrador: $admin_email";
            echo "<script>setTimeout(() => { window.location.href = 'unidades.php'; }, 5000);</script>";
        } catch (Throwable $e) { // Capturar qualquer erro (incluindo falta de colunas)
            if ($pdo->inTransaction())
                $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $erro = "Erro: Slug ou E-mail já estão em uso.";
            } else {
                $erro = "Erro ao cadastrar: " . $e->getMessage();
            }
        }
    } else {
        $erro = "Por favor, preencha nome, slug, e-mail, plano e os dados do administrador (nome, e-mail e senha).";
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
    <form method="POST" action="" id="formNovaUnidade">

        <!-- SEÇÃO 1: DADOS BÁSICOS -->
        <div style="margin-bottom: 2.5rem;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">Nome da Academia</label>
                    <input type="text" id="nome" name="nome" class="form-control-custom" required
                        placeholder="Ex: Gracie Barra Matriz">
                </div>
                <div class="form-group">
                    <label class="label-custom">Slug (URL)</label>
                    <div
                        style="display: flex; align-items: center; background: #f8fafc; border: 1.5px solid var(--border); border-radius: 0.5rem; padding-left: 0.875rem;">
                        <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">/</span>
                        <input type="text" id="slug" name="slug" class="form-control-custom"
                            style="border:none; background:transparent;" required placeholder="ex: gracie-barra">
                    </div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">CPF ou CNPJ</label>
                    <input type="text" name="documento" class="form-control-custom" placeholder="00.000.000/0000-00">
                </div>
                <div class="form-group">
                    <label class="label-custom">E-mail de Contato</label>
                    <input type="email" name="email_contato" class="form-control-custom" required
                        placeholder="contato@academia.com">
                </div>
                <div class="form-group">
                    <label class="label-custom">Telefone</label>
                    <input type="text" name="telefone" class="form-control-custom" placeholder="(00) 00000-0000">
                </div>
            </div>
        </div>

        <!-- SEÇÃO 1B: ADMINISTRADOR DO SISTEMA -->
        <div style="margin-bottom: 2.5rem;">
            <h2
                style="font-size: 1.25rem; font-weight: 700; color: #db2777; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fa-solid fa-user-shield"></i> Administrador do Sistema (Super Admin da Unidade)
            </h2>
            <p style="margin: 0 0 1.5rem; font-size: 0.85rem; color: var(--text-muted);">
                Esta será a primeira pessoa a acessar o painel dessa academia, com acesso total. Ela pode configurar as permissões dos demais colaboradores depois.
            </p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">Nome do Administrador</label>
                    <input type="text" name="admin_nome" class="form-control-custom" required
                        placeholder="Ex: João da Silva">
                </div>
                <div class="form-group">
                    <label class="label-custom">E-mail do Administrador (Login)</label>
                    <input type="email" name="admin_email" class="form-control-custom" required
                        placeholder="joao@academia.com">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 1.5rem; margin-top: 1.5rem; align-items: end;">
                <div class="form-group">
                    <label class="label-custom">Senha de Acesso</label>
                    <input type="text" name="admin_senha" id="admin_senha" class="form-control-custom" required
                        placeholder="Defina uma senha">
                </div>
                <button type="button" onclick="gerarSenhaAdmin()" class="btn btn-secondary"
                    style="padding: 0.875rem 1.5rem; border-radius: 0.5rem; white-space: nowrap;">
                    <i class="fa-solid fa-shuffle" style="margin-right: 6px;"></i> Gerar Senha
                </button>
            </div>
        </div>

        <!-- SEÇÃO 2: ENDEREÇO -->
        <div style="margin-bottom: 2.5rem;">
            <h2
                style="font-size: 1.25rem; font-weight: 700; color: #6366f1; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fa-solid fa-location-dot"></i> Endereço
            </h2>
            <div style="display: grid; grid-template-columns: 1fr 3fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">CEP</label>
                    <input type="text" name="cep" id="cep" class="form-control-custom" placeholder="00000-000">
                </div>
                <div class="form-group">
                    <label class="label-custom">Logradouro</label>
                    <input type="text" name="logradouro" id="logradouro" class="form-control-custom"
                        placeholder="Rua, Av...">
                </div>
                <div class="form-group">
                    <label class="label-custom">Número</label>
                    <input type="text" name="numero" class="form-control-custom" placeholder="123">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control-custom" placeholder="Centro">
                </div>
                <div class="form-group">
                    <label class="label-custom">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control-custom" placeholder="São Paulo">
                </div>
                <div class="form-group">
                    <label class="label-custom">Estado (UF)</label>
                    <input type="text" name="estado" id="estado" class="form-control-custom" maxlength="2"
                        placeholder="SP">
                </div>
            </div>
        </div>

        <!-- SEÇÃO 3: PLANO E COBRANÇA -->
        <div style="margin-bottom: 2.5rem;">
            <h2
                style="font-size: 1.25rem; font-weight: 700; color: var(--success); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fa-solid fa-file-invoice-dollar"></i> Plano da Academia
            </h2>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="label-custom">Selecione o Plano de Venda</label>
                    <select name="plano_id" class="form-control-custom" required>
                        <option value="">Selecione um plano...</option>
                        <?php foreach ($planos_mestre as $pl): ?>
                            <option value="<?php echo $pl['id']; ?>">
                                <?php echo htmlspecialchars($pl['nome']); ?> - R$
                                <?php echo number_format($pl['valor'], 2, ',', '.'); ?>
                                (<?php echo $pl['frequencia']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="label-custom">Dia de Vencimento</label>
                    <select name="dia_vencimento" class="form-control-custom">
                        <option value="5">Dia 05</option>
                        <option value="10" selected>Dia 10</option>
                        <option value="15">Dia 15</option>
                        <option value="20">Dia 20</option>
                        <option value="25">Dia 25</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 2.5rem;">
            <h2
                style="font-size: 1.25rem; font-weight: 700; color: #f59e0b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                <i class="fa-solid fa-cubes"></i> Módulos Ativos
            </h2>
            <div
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; background: #fffbeb; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #fef3c7;">
                <?php
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
                        <input type="checkbox" name="modulos[]" value="<?php echo $key; ?>" checked
                            style="width: 18px; height: 18px;">
                        <?php echo $label; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div
            style="display: flex; justify-content: flex-end; gap: 10px; border-top: 2px solid #f1f5f9; padding-top: 2rem;">
            <a href="unidades.php" class="btn btn-secondary"
                style="padding: 1rem 2rem; border-radius: 0.5rem;">Cancelar</a>
            <button type="submit" class="btn btn-primary"
                style="padding: 1rem 3rem; font-weight: 700; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);">
                FINALIZAR CADASTRO
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

    const nomeInput = document.getElementById('nome');
    const slugInput = document.getElementById('slug');

    nomeInput.addEventListener('input', function () {
        if (slugInput.value === '' || slugInput.dataset.auto === 'true') {
            slugInput.value = nomeInput.value
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, "")
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
            slugInput.dataset.auto = 'true';
        }
    });

    slugInput.addEventListener('input', function () {
        slugInput.dataset.auto = 'false';
    });

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