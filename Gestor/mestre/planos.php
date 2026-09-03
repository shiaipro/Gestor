<?php
require_once '../config.php';

// Verificar se é Mestre (config.php deve lidar com isso ou checar nível)
// Por enquanto seguindo o padrão de config.php e header.php

// Ações (Excluir)
if (isset($_GET['delete'])) {
    $planId = $_GET['delete'];
    $pdo->prepare("DELETE FROM planos_mestre WHERE id = ?")->execute([$planId]);
    header('Location: planos.php');
    exit;
}

$custom_title = "Planos de Venda (Mestre)";
include 'header.php';

// Listar Planos
$stmt = $pdo->query("SELECT * FROM planos_mestre ORDER BY ativo DESC, nome ASC");
$lista = $stmt->fetchAll();
?>



<div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom:1.5rem;">
    <a href="novo_plano.php" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Novo Plano de Venda
    </a>
</div>

<div class="card">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#f8fafc; text-align:left; border-bottom:1px solid var(--border);">
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Nome do Plano</th>
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Valor</th>
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Frequência</th>
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Limites (Staff / Alunos)</th>
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Status</th>
                <th style="padding:1rem; font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">
                    Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $p): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:1rem;">
                        <strong
                            style="display:block; font-size:0.95rem;"><?php echo htmlspecialchars($p['nome']); ?></strong>
                        <span
                            style="font-size:0.8rem; color:var(--text-muted);"><?php echo htmlspecialchars(mb_strimwidth($p['descricao'] ?? '', 0, 50, "...")); ?></span>
                    </td>
                    <td style="padding:1rem; font-weight:600;">R$
                        <?php echo number_format($p['valor'], 2, ',', '.'); ?>
                    </td>
                    <td style="padding:1rem;">
                        <span class="badge"
                            style="background:#eff6ff; color:#1d4ed8; font-size:0.75rem; text-transform:uppercase;">
                            <?php echo $p['frequencia']; ?>
                        </span>
                    </td>
                    <td style="padding:1rem;">
                        <span style="font-size:0.85rem; font-weight:600;">
                            <?php echo $p['limite_usuarios'] > 0 ? $p['limite_usuarios'] : '∞'; ?> /
                            <?php echo $p['limite_alunos'] > 0 ? $p['limite_alunos'] : '∞'; ?>
                        </span>
                    </td>
                    <td style="padding:1rem;">
                        <?php if ($p['ativo']): ?>
                            <span class="badge" style="background:#dcfce7; color:#166534;">Ativo</span>
                        <?php else: ?>
                            <span class="badge" style="background:#f1f5f9; color:#64748b;">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:1rem; display:flex; gap:5px;">
                        <a href="editar_plano.php?id=<?php echo $p['id']; ?>" class="btn btn-secondary"
                            style="padding:0.4rem 0.6rem;">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <a href="planos.php?delete=<?php echo $p['id']; ?>"
                            onclick="return confirm('Tem certeza que deseja excluir este plano?');" class="btn btn-danger"
                            style="padding:0.4rem 0.6rem; background:#fee2e2; color:#b91c1c; border:none;">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($lista)): ?>
                <tr>
                    <td colspan="5" style="padding:2rem; text-align:center; color:var(--text-muted);">Nenhum plano
                        cadastrado. Clique em "Novo Plano" para começar.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>



<?php include 'footer.php'; ?>