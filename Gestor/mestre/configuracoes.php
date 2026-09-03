<?php
require_once '../config.php';

if (!ehMestre()) {
    header('Location: ../login.php');
    exit;
}

$mensagem = '';
$erro = '';

// Para o Mestre, configurações podem ser globais ou apenas do seu próprio perfil.
// Vamos implementar a edição do perfil do Mestre aqui também, ou configurações de sistema.

$usuario_id = $_SESSION['usuario_id'];

// Buscar dados atuais do mestre
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$mestre = $stmt->fetch();

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
            $_SESSION['usuario_nome'] = $nome; 
            $mensagem = "Configurações administrativas atualizadas!";
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $mestre = $stmt->fetch();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = $e->getMessage();
        }
    }
}

include 'header.php';
?>

<section class="dashboard-section-sq">
    <div class="section-header-sq">
        <h2 class="section-title-sq">Configurações (Mestre)</h2>
        <div class="back-bar-sq">
            <a href="index.php" class="btn-back-sq"><i class="fa-solid fa-arrow-left"></i> Voltar Operações</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert" style="background: #dcfce7; color: #166534; padding: 15px; border: 1px solid #bbf7d0; margin-bottom: 20px; font-weight: 700;">
            <i class="fa-solid fa-check-circle"></i> <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
        <!-- Lado Esquerdo: Perfil do Administrador -->
        <div style="background: white; border: 1px solid var(--border-color); padding: 30px;">
            <h3 style="font-size: 14px; font-weight: 800; margin-bottom: 20px; color: var(--brand);">Perfil do Administrador Mestre</h3>
            <form method="POST" action="">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Nome</label>
                    <input type="text" name="nome" value="<?php echo htmlspecialchars($mestre['nome']); ?>" class="form-control" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); font-weight: 600;" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">E-mail</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($mestre['email']); ?>" class="form-control" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); font-weight: 600;" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Nova Senha (opcional)</label>
                    <input type="password" name="senha_nova" class="form-control" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); font-weight: 600;">
                </div>
                <div class="form-group" style="margin-bottom: 30px;">
                    <label style="display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Confirmar Senha</label>
                    <input type="password" name="senha_confirma" class="form-control" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); font-weight: 600;">
                </div>
                <button type="submit" class="btn-sq" style="width: 100%;">SALVAR MEUS DADOS</button>
            </form>
        </div>

        <!-- Lado Direito: Info de Sistema -->
        <div style="background: var(--brand-bg); border: 1px solid var(--brand-light); padding: 30px;">
            <h3 style="font-size: 14px; font-weight: 800; margin-bottom: 20px; color: var(--brand);">Informações do Servidor</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div style="background: white; padding: 15px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--text-muted);">PHP VERSION</div>
                    <div style="font-size: 18px; font-weight: 900; color: var(--brand);"><?php echo phpversion(); ?></div>
                </div>
                <div style="background: white; padding: 15px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--text-muted);">SERVER OS</div>
                    <div style="font-size: 18px; font-weight: 900; color: var(--brand);"><?php echo PHP_OS; ?></div>
                </div>
                <div style="background: white; padding: 15px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--text-muted);">DATABASE</div>
                    <div style="font-size: 18px; font-weight: 900; color: var(--brand);">MySQL (PDO)</div>
                </div>
                <div style="background: white; padding: 15px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--text-muted);">TIMEZONE</div>
                    <div style="font-size: 18px; font-weight: 900; color: var(--brand);"><?php echo date_default_timezone_get(); ?></div>
                </div>
            </div>

            <div style="margin-top: 30px; padding: 20px; background: white; border-left: 5px solid var(--brand-gold);">
                <h4 style="font-size: 13px; font-weight: 800; margin-bottom: 10px;">Manutenção do Sistema</h4>
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 15px;">Use estas ferramentas para manter o banco de dados saudável.</p>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-sq-outline" style="font-size: 11px; padding: 8px 15px;">Limpar Cache</button>
                    <button class="btn-sq-outline" style="font-size: 11px; padding: 8px 15px;">Backup DB</button>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
