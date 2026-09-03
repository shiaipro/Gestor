<?php
require_once '../config.php';
include 'header.php';

$unidade_id = getUnidadeId();

// 1. Verificação / Migração Automática de Tabela
try {
    $pdo->query("SELECT 1 FROM departamentos LIMIT 1");
} catch (Exception $e) {
    // Tabela não existe, vamos criar
    $sql = "CREATE TABLE IF NOT EXISTS departamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        cor VARCHAR(20) DEFAULT '#3b82f6',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);

    // Popular departamentos padrão para ESTA unidade
    $depts = [
        ['ADMINISTRATIVO', '#64748b'],
        ['FINANCEIRO', '#22c55e'],
        ['JURÍDICO', '#ef4444'],
        ['MARKETING', '#a855f7'],
        ['COMERCIAL', '#f59e0b']
    ];
    $stmt_ins = $pdo->prepare("INSERT INTO departamentos (unidade_id, nome, cor) VALUES (?, ?, ?)");
    foreach ($depts as $d) {
        $stmt_ins->execute([$unidade_id, $d[0], $d[1]]);
    }
}

// Buscar Departamentos
$stmt = $pdo->prepare("SELECT * FROM departamentos WHERE unidade_id = ? ORDER BY nome ASC");
$stmt->execute([$unidade_id]);
$departamentos = $stmt->fetchAll();
?>

<div style="padding: 20px;">

    <div class="card"
        style="border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-radius: 1rem; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1.5px solid var(--border);">
                    <th
                        style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">
                        Área / Departamento</th>
                    <th
                        style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">
                        Cor de Identificação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departamentos as $d): ?>
                    <tr style="border-bottom: 1px solid var(--border); transition: background 0.2s;"
                        onmouseover="this.style.background='#fcfcfc'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 1.25rem 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div
                                    style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo $d['cor']; ?>;">
                                </div>
                                <span style="font-weight: 600; color: var(--text-main);">
                                    <?php echo htmlspecialchars($d['nome']); ?>
                                </span>
                            </div>
                        </td>
                        <td style="padding: 1.25rem 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div
                                    style="width: 24px; height: 24px; border-radius: 4px; background: <?php echo $d['cor']; ?>; border: 1px solid rgba(0,0,0,0.1);">
                                </div>
                                <code
                                    style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; color: #475569;">
                                            <?php echo strtoupper($d['cor']); ?>
                                        </code>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($departamentos)): ?>
                    <tr>
                        <td colspan="3" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            Nenhum departamento cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>