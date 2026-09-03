<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('rh_criar')) {
    header('Location: equipe.php?erro=sem_permissao');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $funcao = $_POST['funcao'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $email = $_POST['email'] ?? '';
    $status = $_POST['status'] ?? 'ativo';

    $acesso_sistema = isset($_POST['acesso_sistema']) ? 1 : 0;
    $login_email = $_POST['login_email'] ?? '';
    $login_senha = $_POST['login_senha'] ?? '';
    // Não existe mais categoria de "nível de acesso" — as permissões são configuradas
    // por usuário, individualmente, em Configurações → Permissões de Acesso.
    $nivel_acesso = 'colaborador';

    // Upload de Foto
    $foto_path = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $novo_nome = "membro_" . time() . "_" . rand(1000, 9999) . "." . $ext;
        
        $path = getUploadPath('equipe');
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $path . "/" . $novo_nome)) {
            $foto_path = $novo_nome;
        }
    }

    if ($nome && $funcao) {
        try {
            $pdo->beginTransaction();

            $usuario_id = null;
            if ($acesso_sistema) {
                if (!$login_email || !$login_senha) {
                    throw new Exception("Para liberar o acesso, informe e-mail e senha.");
                }

                // Verificar se e-mail já existe
                $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt_check->execute([$login_email]);
                if ($stmt_check->fetch()) {
                    throw new Exception("Este e-mail de login já está em uso.");
                }

                $senha_hash = password_hash($login_senha, PASSWORD_DEFAULT);
                $stmt_user = $pdo->prepare("INSERT INTO usuarios (unidade_id, nome, email, senha, nivel, status) VALUES (?, ?, ?, ?, ?, 'ativo')");
                $stmt_user->execute([$unidade_id, $nome, $login_email, $senha_hash, $nivel_acesso]);
                $usuario_id = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("INSERT INTO unidade_equipe (unidade_id, usuario_id, nome, foto, funcao, nivel_acesso, telefone, email, status, acesso_sistema) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unidade_id, $usuario_id, $nome, $foto_path, $funcao, $nivel_acesso, $telefone, $email, $status, $acesso_sistema]);
            
            $pdo->commit();
            $sucesso = "Colaborador cadastrado com sucesso!";
            header("refresh:1;url=equipe.php");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o nome e a função.";
    }
}

$custom_title = "Novo Colaborador";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Novo Colaborador
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Adicionar novo membro à equipe da unidade
            </p>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <a href="equipe.php" class="btn-sq-outline" style="width: auto; padding: 12px 25px;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Voltar
            </a>
        </div>
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
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nome Completo</label>
                        <input type="text" name="nome" required placeholder="EX: JOÃO DA SILVA"
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark);">
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Função / Cargo</label>
                        <input type="text" name="funcao" required placeholder="EX: INSTRUTOR PRINCIPAL"
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark);">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">WhatsApp / Telefone</label>
                            <input type="text" name="telefone" placeholder="(00) 00000-0000"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">E-mail Corporativo</label>
                            <input type="email" name="email" placeholder="email@shiaipro.com"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Status Operacional</label>
                            <select name="status" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="ativo">ATIVO / EM OPERAÇÃO</option>
                                <option value="inativo">INATIVO / AFASTADO</option>
                            </select>
                        </div>
                    </div>

                    <!-- CONFIGURAÇÕES DE ACESSO -->
                    <div style="background: #f1f5f9; padding: 30px; border: 1px solid var(--border-color); margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin: 0; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                                <i class="fa-solid fa-shield-halved" style="color: #08153a;"></i> Configurações de Acesso ao Sistema
                            </h3>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; padding: 6px 12px; border: 1px solid var(--border-color);">
                                <input type="checkbox" name="acesso_sistema" id="acesso_sistema" value="1" style="width: 18px; height: 18px;">
                                <span style="font-size: 10px; font-weight: 900; color: var(--text-dark);">LIBERAR ACESSO</span>
                            </label>
                        </div>

                        <div id="campos_acesso" style="display: none; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">E-mail de Login</label>
                                <input type="email" name="login_email" id="login_email" placeholder="email@exemplo.com"
                                    style="width:100%; height:45px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-sm); font-weight:700;">
                            </div>
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Senha de Acesso</label>
                                <input type="password" name="login_senha" placeholder="••••••••"
                                    style="width:100%; height:45px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-sm); font-weight:700;">
                            </div>
                            <p style="grid-column: span 2; font-size: var(--fs-xs); color: var(--text-muted); margin-top: 5px; font-weight: 600;">
                                As permissões de acesso por módulo são configuradas depois, em Configurações → Permissões de Acesso.
                            </p>
                        </div>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base); background: #08153a; color: #fff; display: flex; align-items: center; justify-content: center; gap: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                             <i class="fa-solid fa-save"></i> SALVAR REGISTRO DE COLABORADOR
                        </button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin: 0 0 25px 0; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Foto do Perfil</h3>
                    
                    <div style="width: 100%; aspect-ratio: 1; background: #fff; border: 2px dashed var(--border-color); display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; cursor: pointer; transition: all 0.3s ease;" id="drop-zone">
                        <div id="upload-placeholder" style="text-align: center; padding: 20px;">
                            <i class="fa-solid fa-camera" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px;"></i>
                            <p style="font-size: var(--fs-xs); font-weight: 900; color: #94a3b8; text-transform: uppercase; line-height: 1.4; letter-spacing: 0.05em; margin: 0;">Clique para escolher foto</p>
                        </div>
                        <img id="preview" style="display: none; width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0;">
                        <input type="file" name="foto" id="foto-input" accept="image/*" style="opacity: 0; position: absolute; inset: 0; cursor: pointer;">
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Importante</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">A foto será utilizada para o crachá digital e identificação do instrutor no app do aluno.</p>
                </div>
            </div>
        </div>
    </form>
</section>

<script>
    const fotoInput = document.getElementById('foto-input');
    const preview = document.getElementById('preview');
    const placeholder = document.getElementById('upload-placeholder');
    const dropZone = document.getElementById('drop-zone');

    fotoInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (re) => {
                preview.src = re.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
                dropZone.style.border = '2px solid var(--primary-green)';
            };
            reader.readAsDataURL(file);
        }
    });

    // Toggle Campos de Acesso
    const acessoCheckbox = document.getElementById('acesso_sistema');
    const camposAcesso = document.getElementById('campos_acesso');
    const emailCorp = document.querySelector('input[name="email"]');
    const emailLogin = document.getElementById('login_email');

    acessoCheckbox.addEventListener('change', function() {
        camposAcesso.style.display = this.checked ? 'grid' : 'none';
        if (this.checked && emailCorp.value && !emailLogin.value) {
            emailLogin.value = emailCorp.value;
        }
    });

    emailCorp.addEventListener('input', function() {
        if (acessoCheckbox.checked && !emailLogin.value) {
            emailLogin.value = this.value;
        }
    });
</script>

<?php include 'footer.php'; ?>