<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
if (!$unidade_id) {
    header('Location: ../login.php');
    exit;
}

if (!temPermissaoModulo('rh_editar')) {
    header('Location: equipe.php?erro=sem_permissao');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: equipe.php');
    exit;
}

// Buscar dados do membro com info de login
$stmt = $pdo->prepare("
    SELECT m.*, u.email as login_email, u.nivel as nivel_usuario 
    FROM unidade_equipe m 
    LEFT JOIN usuarios u ON m.usuario_id = u.id 
    WHERE m.id = ? AND m.unidade_id = ?
");
$stmt->execute([$id, $unidade_id]);
$membro = $stmt->fetch();

if (!$membro) {
    header('Location: equipe.php');
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
    $foto_path = $membro['foto'];
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

            $usuario_id = $membro['usuario_id'];

            if ($acesso_sistema) {
                if (!$login_email) {
                    throw new Exception("Informe o e-mail de login.");
                }

                if ($usuario_id) {
                    // Atualizar usuário existente
                    $stmt_up_user = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, nivel = ?, status = 'ativo' WHERE id = ?");
                    $stmt_up_user->execute([$nome, $login_email, $nivel_acesso, $usuario_id]);

                    // Se informou nova senha
                    if ($login_senha) {
                        $senha_hash = password_hash($login_senha, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$senha_hash, $usuario_id]);
                    }
                } else {
                    // Criar novo usuário
                    if (!$login_senha) {
                        throw new Exception("Defina uma senha inicial para o colaborador.");
                    }
                    
                    // Verificar e-mail duplicado
                    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt_check->execute([$login_email]);
                    if ($stmt_check->fetch()) {
                        throw new Exception("Este e-mail de login já está sendo usado por outro usuário.");
                    }

                    $senha_hash = password_hash($login_senha, PASSWORD_DEFAULT);
                    $stmt_new_user = $pdo->prepare("INSERT INTO usuarios (unidade_id, nome, email, senha, nivel, status) VALUES (?, ?, ?, ?, ?, 'ativo')");
                    $stmt_new_user->execute([$unidade_id, $nome, $login_email, $senha_hash, $nivel_acesso]);
                    $usuario_id = $pdo->lastInsertId();
                }
            } else {
                // Desativar acesso se existia
                if ($usuario_id) {
                    $pdo->prepare("UPDATE usuarios SET status = 'inativo' WHERE id = ?")->execute([$usuario_id]);
                }
            }

            $stmt = $pdo->prepare("UPDATE unidade_equipe SET nome = ?, usuario_id = ?, foto = ?, funcao = ?, nivel_acesso = ?, telefone = ?, email = ?, status = ?, acesso_sistema = ? WHERE id = ? AND unidade_id = ?");
            $stmt->execute([$nome, $usuario_id, $foto_path, $funcao, $nivel_acesso, $telefone, $email, $status, $acesso_sistema, $id, $unidade_id]);
            
            $pdo->commit();
            $sucesso = "Dados atualizados com sucesso!";
            header("refresh:1;url=equipe.php");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = "Erro ao atualizar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha o nome e a função.";
    }
}

$custom_title = "Editar Colaborador";
include 'header.php';
?>

<section class="dashboard-section-sq">
    <!-- Cabeçalho -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 20px;">
        <div style="">
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-dark); margin: 0; letter-spacing: -0.03em; text-transform: uppercase;">
                Perfil do Colaborador
            </h1>
            <p style="color: var(--text-muted); font-size: var(--fs-base); margin: 5px 0 0 0; font-weight: 500;">
                Editando: <strong><?php echo htmlspecialchars($membro['nome']); ?></strong>
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
                    <div style="background: #dcfce7; color: #166534; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid var(--primary-green);">
                        <i class="fa-solid fa-circle-check" style="margin-right: 10px;"></i><?php echo $sucesso; ?>
                    </div>
                <?php endif; ?>

                <?php if ($erro): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 20px; margin-bottom: 30px; font-size: var(--fs-xs); font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; border-left: 4px solid #ef4444;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right: 10px;"></i><?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; gap: 25px;">
                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Nome Completo</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($membro['nome']); ?>" required 
                            style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark); text-transform: uppercase;">
                    </div>

                    <div>
                        <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Função / Cargo</label>
                        <input type="text" name="funcao" value="<?php echo htmlspecialchars($membro['funcao']); ?>" required
                             style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700; color:var(--text-dark); text-transform: uppercase;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px;">
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">WhatsApp / Telefone</label>
                            <input type="text" name="telefone" value="<?php echo htmlspecialchars($membro['telefone']); ?>"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">E-mail Corporativo</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($membro['email']); ?>"
                                style="width:100%; height:50px; background:#fafafa; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-base); font-weight:700;">
                        </div>
                        <div>
                            <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing: 0.05em;">Status Operacional</label>
                            <select name="status" style="width:100%; height:50px; border:1px solid var(--border-color); background:#fafafa; padding:0 15px; font-size: var(--fs-sm); font-weight:700; color:var(--text-dark); appearance:none; background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 1rem center; background-size:1rem;">
                                <option value="ativo" <?php echo $membro['status'] == 'ativo' ? 'selected' : ''; ?>>ATIVO / EM OPERAÇÃO</option>
                                <option value="inativo" <?php echo $membro['status'] == 'inativo' ? 'selected' : ''; ?>>INATIVO / AFASTADO</option>
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
                                <input type="checkbox" name="acesso_sistema" id="acesso_sistema" value="1" <?php echo $membro['acesso_sistema'] ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                                <span style="font-size: 10px; font-weight: 900; color: var(--text-dark);">LIBERAR ACESSO</span>
                            </label>
                        </div>

                        <div id="campos_acesso" style="display: <?php echo $membro['acesso_sistema'] ? 'grid' : 'none'; ?>; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">E-mail de Login</label>
                                <input type="email" name="login_email" id="login_email" value="<?php echo htmlspecialchars($membro['login_email'] ?: $membro['email']); ?>" placeholder="email@exemplo.com"
                                    style="width:100%; height:45px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-sm); font-weight:700;">
                            </div>
                            <div>
                                <label style="display:block; font-size: var(--fs-xs); font-weight:900; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Nova Senha (opcional)</label>
                                <input type="password" name="login_senha" placeholder="Deixe em branco para manter"
                                    style="width:100%; height:45px; background:#fff; border:1px solid var(--border-color); padding:0 15px; font-size: var(--fs-sm); font-weight:700;">
                            </div>
                        </div>
                        <p style="font-size: var(--fs-xs); color: var(--text-muted); margin-top: 15px; font-weight: 600;">
                            As permissões de acesso por módulo são configuradas em Configurações → Permissões de Acesso.
                        </p>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn-sq" style="height: 60px; width: 100%; font-size: var(--fs-base); background: #08153a; color: #fff; display: flex; align-items: center; justify-content: center; gap: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em;">
                            <i class="fa-solid fa-save"></i> SALVAR ALTERAÇÕES NO COLABORADOR
                        </button>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="dashboard-container" style="padding: 30px; background: #fafafa;">
                    <h3 style="font-size: var(--fs-xs); font-weight: 900; color: var(--text-dark); margin: 0 0 25px 0; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid #08153a; padding-bottom: 10px;">Foto do Perfil</h3>
                    
                    <div style="width: 100%; aspect-ratio: 1; background: #fff; border: 2px dashed var(--border-color); display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; cursor: pointer; transition: all 0.3s ease;" id="drop-zone">
                        <?php if ($membro['foto']): ?>
                            <img id="preview" src="<?php echo getUploadURL('equipe', $membro['foto']); ?>" style="width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0;">
                        <?php else: ?>
                            <div id="upload-placeholder" style="text-align: center; padding: 20px;">
                                <i class="fa-solid fa-camera" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                <p style="font-size: var(--fs-xs); font-weight: 900; color: #94a3b8; text-transform: uppercase; line-height: 1.4; letter-spacing: 0.05em; margin: 0;">Clique para trocar foto</p>
                            </div>
                            <img id="preview" style="display: none; width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0;">
                        <?php endif; ?>
                        <input type="file" name="foto" id="foto-input" accept="image/*" style="opacity: 0; position: absolute; inset: 0; cursor: pointer;">
                    </div>
                </div>
                
                <div class="dashboard-container" style="padding: 30px; background: #08153a; color: #fff;">
                    <h4 style="font-size: var(--fs-xs); font-weight: 900; color: var(--primary-green); text-transform: uppercase; margin-bottom: 15px;">Identificação</h4>
                    <p style="font-size: var(--fs-sm); color: #94a3b8; line-height: 1.6; font-weight: 500; font-style: italic;">Manter os dados atualizados garante que o aluno consiga contatar o instrutor correto via aplicativo.</p>
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
