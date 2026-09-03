<?php
require_once '../config.php';

if (!ehMestre()) {
    header('Location: ../login.php');
    exit;
}

$mensagem_reset = null;

// Processar Ações (Ativar/Inativar/Resetar Senha / Acessar Tenant)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $id_uni = $_POST['unidade_id'] ?? null;
    if ($id_uni) {
        if ($_POST['acao'] === 'toggle_status') {
            $novo_status = $_POST['status_atual'] === 'ativo' ? 'inativo' : 'ativo';
            $pdo->prepare("UPDATE unidades SET status = ? WHERE id = ?")->execute([$novo_status, $id_uni]);
        } elseif ($_POST['acao'] === 'reset_senha') {
            $senha_padrao = password_hash("senpipe123", PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET senha = ? WHERE unidade_id = ? AND nivel = 'admin'")->execute([$senha_padrao, $id_uni]);
            $mensagem_reset = "Senha do administrador resetada para 'senpipe123'!";
        } elseif ($_POST['acao'] === 'acessar_tenant') {
            $_SESSION['unidade_id'] = $id_uni;
            header("Location: ../unidade/index.php");
            exit;
        }
    }
}

// Buscar todas as unidades com seus respectivos planos - Protegido
$unidades = [];
$erro_db = '';
try {
    $stmt = $pdo->query("SELECT u.*, p.nome as plano_nome 
                         FROM unidades u 
                         LEFT JOIN planos_mestre p ON u.plano_id = p.id 
                         ORDER BY u.criado_em DESC");
    $unidades = $stmt->fetchAll();
} catch (Exception $e) {
    $erro_db = "Aviso: Estrutura de banco de dados incompleta. Por favor, rode os scripts de atualização.";
}

include 'header.php';
?>

<div class="card" style="padding: 0; overflow: hidden; border: none; box-shadow: var(--shadow);">
    <?php if ($erro_db): ?>
        <div class="alert"
            style="background: #fee2e2; color: #991b1b; padding: 1.5rem; margin: 1.5rem; border-radius: 0.5rem; border: 1px solid #fecaca;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $erro_db; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($mensagem_reset)): ?>
        <div class="alert"
            style="background: #dcfce7; color: #166534; padding: 1rem; margin: 1.5rem; border-radius: 0.5rem; border: 1px solid #bbf7d0;">
            <i class="fa-solid fa-circle-check"></i>
            <?php echo $mensagem_reset; ?>
        </div>
    <?php endif; ?>

    <div
        style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: flex-end; align-items: center;">
        <a href="nova_unidade.php" class="btn btn-primary" style="padding: 0.6rem 1.25rem; font-size: 0.8rem;">
            <i class="fa-solid fa-plus"></i> Nova Academia
        </a>
    </div>

    <?php if (empty($unidades)): ?>
        <div style="text-align: center; color: var(--text-muted); padding: 4rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.2;">🏢</div>
            <p>Nenhuma academia cadastrada na rede SENPIPE.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; background: #f8f9fa; border-bottom: 1px solid var(--border);">
                        <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: #495057; font-weight: 700;">Academia
                        </th>
                        <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: #495057; font-weight: 700;">
                            Identificação (Slug)</th>
                        <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: #495057; font-weight: 700;">Plano Atual
                        </th>
                        <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: #495057; font-weight: 700;">Status</th>
                        <th
                            style="padding: 1rem 1.5rem; font-size: 0.75rem; color: #495057; font-weight: 700; text-align: right;">
                            Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unidades as $unidade): ?>
                        <tr style="border-bottom: 1px solid var(--border); transition: all 0.2s;"
                            onmouseover="this.style.background='#fbfcfe'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 1rem 1.5rem;">
                                <div style="font-weight: 700; color: var(--text); font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($unidade['nome']); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    Desde <?php echo date('d/m/Y', strtotime($unidade['criado_em'])); ?>
                                </div>
                            </td>
                            <td style="padding: 1rem 1.5rem;">
                                <code
                                    style="background: rgba(59, 130, 246, 0.05); color: #3b82f6; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">
                                                                                                                            /<?php echo htmlspecialchars($unidade['slug']); ?>
                                                                                                                        </code>
                            </td>
                            <td style="padding: 1rem 1.5rem;">
                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text);">
                                    <?php echo htmlspecialchars($unidade['plano_nome'] ?? 'Sem Plano'); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 1.5rem;">
                                <span
                                    class="badge <?php echo $unidade['status'] == 'ativo' ? 'badge-ativo' : 'badge-inativo'; ?>"
                                    style="font-size: 0.65rem;">
                                    <?php echo strtoupper($unidade['status']); ?>
                                </span>
                            </td>
                            <td
                                style="padding: 1rem 1.5rem; text-align: right; display: flex; justify-content: flex-end; gap: 8px;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="unidade_id" value="<?php echo $unidade['id']; ?>">
                                    <input type="hidden" name="status_atual" value="<?php echo $unidade['status']; ?>">
                                    <button type="submit" name="acao" value="toggle_status"
                                        class="btn <?php echo $unidade['status'] === 'ativo' ? 'btn-secondary' : 'btn-success'; ?>"
                                        style="padding: 0.35rem 0.75rem; font-size: 0.7rem; border-radius: 4px; width: 90px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;"
                                        title="<?php echo $unidade['status'] === 'ativo' ? 'Desativar' : 'Ativar'; ?>">
                                        <i
                                            class="fa-solid <?php echo $unidade['status'] === 'ativo' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        <?php echo $unidade['status'] === 'ativo' ? 'Inativar' : 'Ativar'; ?>
                                    </button>
                                </form>

                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Resetar senha do administrador para senpipe123?')">
                                    <input type="hidden" name="unidade_id" value="<?php echo $unidade['id']; ?>">
                                    <button type="submit" name="acao" value="reset_senha" class="btn btn-warning"
                                        style="padding: 0.35rem 0.75rem; font-size: 0.7rem; border-radius: 4px; color:#08153a;">
                                        <i class="fa-solid fa-key"></i> Reset
                                    </button>
                                </form>

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="unidade_id" value="<?php echo $unidade['id']; ?>">
                                    <button type="submit" name="acao" value="acessar_tenant" class="btn btn-info"
                                        style="padding: 0.35rem 0.75rem; font-size: 0.7rem; border-radius: 4px;">
                                        <i class="fa-solid fa-right-to-bracket"></i> Acessar
                                    </button>
                                </form>

                                <a href="editar_unidade.php?id=<?php echo $unidade['id']; ?>" class="btn btn-primary"
                                    style="padding: 0.35rem 0.75rem; font-size: 0.7rem; border-radius: 4px;">
                                    <i class="fa-solid fa-pen-to-square"></i> Editar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>