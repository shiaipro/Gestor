<?php
require_once '../config.php';

if (!estaLogado()) {
    header('Location: ../login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$erro = '';

// Buscar dados atuais do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha_nova = $_POST['senha_nova'] ?? '';
    $senha_confirma = $_POST['senha_confirma'] ?? '';

    if ($nome && $email) {
        try {
            $pdo->beginTransaction();

            // Atualizar Nome e E-mail
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $usuario_id]);

            // Atualizar Foto se enviada
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $novo_nome = "perfil_" . $usuario_id . "_" . time() . "." . $ext;
                $destino = "../uploads/perfil/" . $novo_nome;

                if (!is_dir('../uploads/perfil/')) {
                    mkdir('../uploads/perfil/', 0777, true);
                }

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                    // Tentar atualizar coluna 'foto'. Se não existir, o catch tratará.
                    try {
                        $stmt = $pdo->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
                        $stmt->execute([$novo_nome, $usuario_id]);
                    } catch (Exception $e) {
                        // Provavelmente a coluna 'foto' não existe. Vamos tentar criá-la.
                        $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto VARCHAR(255) NULL AFTER nivel");
                        $stmt = $pdo->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
                        $stmt->execute([$novo_nome, $usuario_id]);
                    }
                }
            }

            // Atualizar Senha se fornecida
            if (!empty($senha_nova)) {
                if ($senha_nova === $senha_confirma) {
                    $senha_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                    $stmt->execute([$senha_hash, $usuario_id]);
                } else {
                    throw new Exception("As senhas não coincidem.");
                }
            }

            $pdo->commit();
            $_SESSION['usuario_nome'] = $nome; // Atualizar nome na sessão
            $mensagem = "Perfil atualizado com sucesso!";
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = $e->getMessage();
        }
    } else {
        $erro = "Nome e E-mail são obrigatórios.";
    }
}

include 'header.php';
?>

<section class="dashboard-section-sq">
    <div class="section-header-sq">
        <h2 class="section-title-sq">Meu Perfil</h2>
        <div class="back-bar-sq">
            <a href="index.php" class="btn-back-sq"><i class="fa-solid fa-arrow-left"></i> Voltar Operações</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert" style="background: #dcfce7; color: #166534; padding: 15px; border: 1px solid #bbf7d0; margin-bottom: 20px; font-weight: 700; border-radius: 0 !important;">
            <i class="fa-solid fa-check-circle"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert" style="background: #fee2e2; color: #991b1b; padding: 15px; border: 1px solid #fecaca; margin-bottom: 20px; font-weight: 700; border-radius: 0 !important;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 40px; align-items: start;">
            
            <!-- Lado Esquerdo: Foto de Perfil -->
            <div style="background: white; border: 1px solid var(--border-color); padding: 30px; text-align: center;">
                <h3 style="font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--brand); margin-bottom: 20px; letter-spacing: 1px;">Foto de Perfil</h3>
                
                <div id="drop-zone" style="width: 100%; aspect-ratio: 1; background: #f8fafc; border: 2px dashed var(--border-color); display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition); position: relative; overflow: hidden;">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="../uploads/perfil/<?php echo $usuario['foto']; ?>" id="img-preview" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fa-solid fa-camera" style="font-size: 40px; color: var(--border-color); margin-bottom: 15px;"></i>
                        <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Arraste ou Clique</span>
                        <img src="" id="img-preview" style="width: 100%; height: 100%; object-fit: cover; display: none; position: absolute; top: 0; left: 0;">
                    <?php endif; ?>
                    <input type="file" name="foto" id="file-input" style="display: none;" accept="image/*">
                </div>
                
                <p style="font-size: 10px; color: var(--text-muted); margin-top: 15px; font-weight: 600;">JPG, PNG ou WEBP. Máximo 2MB.</p>
                <button type="button" onclick="document.getElementById('file-input').click()" class="btn-sq-outline" style="width: 100%; margin-top: 10px; font-size: 10px;">SELECIONAR ARQUIVO</button>
            </div>

            <!-- Lado Direito: Dados e Senha -->
            <div style="display: grid; gap: 30px;">
                <div style="background: white; border: 1px solid var(--border-color); padding: 40px;">
                    <h3 style="font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--brand); margin-bottom: 30px; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;">Informações Pessoais</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label style="display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px;">Nome Completo</label>
                            <input type="text" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" class="form-control" style="width: 100%; padding: 15px; border: 1px solid var(--border-color); font-weight: 700; background: #f8fafc;" required>
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px;">E-mail de Acesso</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" class="form-control" style="width: 100%; padding: 15px; border: 1px solid var(--border-color); font-weight: 700; background: #f8fafc;" required>
                        </div>
                    </div>
                </div>

                <div style="background: white; border: 1px solid var(--border-color); padding: 40px;">
                    <h3 style="font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--brand); margin-bottom: 30px; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;">Alterar Senha de Acesso</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label style="display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px;">Nova Senha</label>
                            <input type="password" name="senha_nova" placeholder="••••••••" class="form-control" style="width: 100%; padding: 15px; border: 1px solid var(--border-color); font-weight: 700; background: #f8fafc;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px;">Confirmar Nova Senha</label>
                            <input type="password" name="senha_confirma" placeholder="••••••••" class="form-control" style="width: 100%; padding: 15px; border: 1px solid var(--border-color); font-weight: 700; background: #f8fafc;">
                        </div>
                    </div>
                    <p style="font-size: 11px; color: var(--text-muted); margin-top: 15px; font-weight: 600; font-style: italic;">* Deixe os campos de senha vazios se não desejar alterá-la.</p>
                </div>

                <button type="submit" class="btn-sq" style="padding: 20px; font-size: 13px; letter-spacing: 1px;">SALVAR TODAS AS ALTERAÇÕES</button>
            </div>
        </div>
    </form>
</section>

<script>
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const imgPreview = document.getElementById('img-preview');

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--brand)';
        dropZone.style.background = 'var(--brand-bg)';
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = 'var(--border-color)';
        dropZone.style.background = '#f8fafc';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--border-color)';
        dropZone.style.background = '#f8fafc';
        
        const files = e.dataTransfer.files;
        if (files.length) {
            fileInput.files = files;
            handlePreview(files[0]);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            handlePreview(fileInput.files[0]);
        }
    });

    function handlePreview(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            imgPreview.src = e.target.result;
            imgPreview.style.display = 'block';
            if(dropZone.querySelector('i')) dropZone.querySelector('i').style.display = 'none';
            if(dropZone.querySelector('span')) dropZone.querySelector('span').style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
</script>

<?php include 'footer.php'; ?>
