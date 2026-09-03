<?php
include '../config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$tipo = $_GET['tipo'] ?? '';
$cat_id = filter_input(INPUT_GET, 'cat_id', FILTER_SANITIZE_NUMBER_INT);

// Verificar login básico através das funções do config.php
if (!estaLogado()) {
    die("Acesso negado. Por favor, faça login.");
}

if (!$id) {
    die("Competição não selecionada.");
}

// Dados da Competição ou Competição Oficial
$dados = null;
$is_oficial = ($tipo == 'competicao_oficial');

if ($is_oficial) {
    $comp = $pdo->prepare("SELECT * FROM competicoes_oficiais WHERE id = ?");
    $comp->execute([$id]);
    $dados = $comp->fetch();
} else {
    $comp = $pdo->prepare("SELECT * FROM competicoes WHERE id = ?");
    $comp->execute([$id]);
    $dados = $comp->fetch();
}

if (!$dados) {
    die("Evento não encontrado.");
}

// Academia/Unidade dono do evento
$unidade_id_evento = $dados['unidade_id'];
$un = $pdo->prepare("SELECT nome FROM unidades WHERE id = ?");
$un->execute([$unidade_id_evento]);
$unidade_nome = $un->fetchColumn();

// Dados da Categoria (se houver e não for oficial)
$cat_nome = "Todas as Categorias";
if (!$is_oficial && $cat_id) {
    $cat = $pdo->prepare("SELECT nome FROM competicao_categorias WHERE id = ?");
    $cat->execute([$cat_id]);
    $cat_nome = $cat->fetchColumn();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Imprimir - <?php echo htmlspecialchars($dados['nome']); ?></title>
    <style>
        :root {
            --primary: #EB0000;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
            margin: 0;
            padding: 2rem;
            background: #fff;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            text-transform: uppercase;
            font-weight: 900;
        }

        .header .info {
            text-align: right;
        }

        .badge {
            background: #f1f5f9;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        th {
            background: #f8fafc;
            text-align: left;
            padding: 0.75rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border);
        }

        td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }

        /* Crachás */
        .cracha-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15mm;
            padding: 10mm;
        }

        .cracha-card {
            border: 2px solid var(--text-main);
            border-radius: 8px;
            padding: 15mm 5mm;
            text-align: center;
            height: 100mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            background: #fff;
        }

        .cracha-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 10mm;
            background: var(--primary);
            border-radius: 6px 6px 0 0;
        }

        .cracha-atleta {
            font-size: 1.8rem;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 3.5rem;
            line-height: 1.2;
            color: #08153a;
        }

        .cracha-info {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-top: auto;
        }

        .cracha-categoria {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .cracha-qrcode {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
        }

        .cracha-qrcode img {
            width: 80px;
            height: 80px;
            border: 1px solid var(--border);
            padding: 5px;
            background: #fff;
        }

        /* Etiquetas (Labels) */
        .etiqueta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100mm, 1fr));
            gap: 5mm;
            padding: 5mm;
        }

        .etiqueta-card {
            border: 1px dashed #08153a;
            width: 100mm;
            height: 50mm;
            padding: 5mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            background: #fff;
            box-sizing: border-box;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .etiqueta-content {
            flex: 1;
            text-align: left;
        }

        .etiqueta-nome {
            font-size: 1.2rem;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            color: #08153a;
        }

        .etiqueta-info {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .etiqueta-qr {
            width: 35mm;
            display: flex;
            justify-content: flex-end;
        }

        .etiqueta-qr img {
            width: 30mm;
            height: 30mm;
        }

        /* Diplomas */
        .diploma-page {
            width: 297mm;
            height: 210mm;
            padding: 20mm;
            box-sizing: border-box;
            border: 10mm solid var(--primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: #fff;
            position: relative;
            page-break-after: always;
        }

        .diploma-title {
            font-size: 4rem;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--text-main);
            margin-bottom: 2rem;
        }

        .diploma-text {
            font-size: 1.55rem;
            line-height: 1.6;
            color: #334155;
            max-width: 80%;
        }

        .diploma-atleta {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary);
            margin: 1.5rem 0;
            text-decoration: underline;
        }

        .diploma-footer {
            margin-top: 4rem;
            display: flex;
            justify-content: space-between;
            width: 80%;
            
            padding-top: 2rem;
        }

        .assinatura-box {
            width: 200px;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 700;
        }

        @media print {
            .cracha-grid {
                padding: 0;
                gap: 5mm;
            }

            .cracha-card {
                page-break-inside: avoid;
            }

            .diploma-page {
                border-width: 5mm;
            }

            .etiqueta-card {
                border: 0.5px solid #eee;
                /* Light border for cutting guide but cleaner print */
            }

            body {
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            @page {
                margin: 1cm;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <div class="header">
        <div>
            <h1><?php echo htmlspecialchars($dados['nome']); ?></h1>
            <span class="badge"><?php echo htmlspecialchars($unidade_nome); ?></span>
        </div>
        <div class="info">
            <div style="font-weight: 800; font-size: 1.1rem; color: var(--primary);">
                <?php echo htmlspecialchars($cat_nome); ?>
            </div>
            <?php if ($is_oficial): ?>
                <div style="font-size: 0.85rem; font-weight: 600; margin-top: 5px;">
                    <i class="fa-solid fa-calendar-days"></i>
                    <?php
                    echo date('d/m/Y', strtotime($dados['data_inicio']));
                    if ($dados['data_fim'])
                        echo " até " . date('d/m/Y', strtotime($dados['data_fim']));
                    ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-main);">
                    <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($dados['localizacao']); ?>
                    • <i class="fa-solid fa-sitemap"></i> <?php echo htmlspecialchars($dados['organizacao']); ?>
                </div>
            <?php endif; ?>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">Relatório gerado em:
                <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>
    </div>

    <?php if ($tipo == 'competicao_oficial'): ?>
        <?php
        $stmt_atletas = $pdo->prepare("
            SELECT coa.*, a.nome_completo, a.faixa, a.data_nascimento, a.telefone, a.cpf, a.rg
            FROM competicao_oficial_atletas coa
            JOIN alunos a ON coa.aluno_id = a.id
            WHERE coa.competicao_id = ?
            ORDER BY a.nome_completo ASC
        ");
        $stmt_atletas->execute([$id]);
        $atletas = $stmt_atletas->fetchAll();
        ?>

        <div
            style="margin-bottom: 2rem; padding: 1.5rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid var(--primary);">
            <h3 style="margin-top: 0; font-size: 0.9rem; text-transform: uppercase;">Responsáveis pela Equipe</h3>
            <p style="margin-bottom: 0; font-size: 0.95rem; white-space: pre-line;">
                <?php echo htmlspecialchars($dados['responsaveis'] ?: 'Nenhum responsável listado.'); ?>
            </p>
        </div>

        <h3 style="font-size: 1.1rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            Lista de Atletas (Delegação)</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Atleta</th>
                    <th>Documentos</th>
                    <th>Faixa</th>
                    <th style="text-align: center;">Idade</th>
                    <th>Categoria na Competição</th>
                    <th>Contato</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($atletas as $idx => $at):
                    $date = new DateTime($at['data_nascimento']);
                    $now = new DateTime();
                    $age = $now->diff($date)->y;
                    ?>
                    <tr>
                        <td style="color: var(--text-muted); font-size: 0.7rem;"><?php echo $idx + 1; ?></td>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($at['nome_completo']); ?></td>
                        <td style="font-size: 0.75rem;">
                            <strong>CPF:</strong> <?php echo $at['cpf'] ?: '--'; ?><br>
                            <strong>RG:</strong> <?php echo $at['rg'] ?: '--'; ?>
                        </td>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($at['faixa']); ?></td>
                        <td style="text-align: center;"><?php echo $age; ?> anos</td>
                        <td>
                            <span style="font-weight: 600; color: var(--primary);">
                                <?php echo htmlspecialchars($at['categoria_disputada'] ?: '--'); ?>
                            </span>
                        </td>
                        <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($at['telefone'] ?: '--'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($atletas)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem;">Nenhum atleta cadastrado na delegação.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 4rem; display: flex; justify-content: space-between; page-break-inside: avoid;">
            <div
                style="width: 250px; text-align: center;  padding-top: 0.5rem; font-size: 0.8rem; font-weight: 700;">
                Assinatura do Responsável
            </div>
            <div
                style="width: 250px; text-align: center;  padding-top: 0.5rem; font-size: 0.8rem; font-weight: 700;">
                Carimbo da Unidade / Academia
            </div>
        </div>

    <?php elseif ($tipo == 'pesagem'): ?>
        <?php
        $sql = "SELECT i.*, 
                       a.nome_completo as aluno_nome, 
                       c.nome as cat_nome, c.peso_max
                FROM competicao_inscricoes i
                LEFT JOIN alunos a ON i.aluno_id = a.id
                LEFT JOIN competicao_categorias c ON i.categoria_id = c.id
                WHERE i.competicao_id = ?";

        $params = [$id];
        if ($cat_id) {
            $sql .= " AND i.categoria_id = ?";
            $params[] = $cat_id;
        }
        $sql .= " ORDER BY c.nome ASC, a.nome_completo ASC, i.nome_externo ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $inscritos = $stmt->fetchAll();
        ?>

        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Atleta</th>
                    <th>Academia / Equipe</th>
                    <th>Categoria</th>
                    <th style="text-align: center;">Limite (kg)</th>
                    <th style="text-align: center; width: 60px;">PESO</th>
                    <th style="text-align: center; width: 60px;">OK</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inscritos as $idx => $in):
                    $nome = $in['aluno_id'] ? $in['aluno_nome'] : $in['nome_externo'];
                    $equipe = $in['aluno_id'] ? $unidade_nome : $in['equipe_externa'];
                    ?>
                    <tr>
                        <td style="color: var(--text-muted); font-size: 0.7rem;"><?php echo $idx + 1; ?></td>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($nome); ?></td>
                        <td><?php echo htmlspecialchars($equipe); ?></td>
                        <td><?php echo htmlspecialchars($in['cat_nome'] ?: 'Sem Categoria'); ?></td>
                        <td style="text-align: center; font-weight: 600;"><?php echo $in['peso_max']; ?></td>
                        <td style="border: 1px solid var(--border);">&nbsp;</td>
                        <td style="border: 1px solid var(--border); text-align: center; font-size: 0.6rem;">[ &nbsp; ]</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($tipo == 'chaves'): ?>
        <?php
        if (!$cat_id) {
            echo "<p style='text-align:center; padding:5rem;'>Selecione uma categoria para visualizar as chaves.</p>";
        } else {
            // Buscar lutas
            $lutas = $pdo->prepare("
                SELECT l.*, 
                       i1.aluno_id as i1_aluno, i1.nome_externo as i1_ext, a1.nome_completo as a1_nome,
                       i2.aluno_id as i2_aluno, i2.nome_externo as i2_ext, a2.nome_completo as a2_nome
                FROM competicao_lutas l
                LEFT JOIN competicao_inscricoes i1 ON l.inscricao1_id = i1.id
                LEFT JOIN alunos a1 ON i1.aluno_id = a1.id
                LEFT JOIN competicao_inscricoes i2 ON l.inscricao2_id = i2.id
                LEFT JOIN alunos a2 ON i2.aluno_id = a2.id
                WHERE l.competicao_id = ? AND l.categoria_id = ?
                ORDER BY l.fase DESC, l.id ASC
            ");
            $lutas->execute([$id, $cat_id]);
            $rows = $lutas->fetchAll();

            $fases = [];
            foreach ($rows as $r) {
                $fases[$r['fase']][] = $r;
            }
            ksort($fases);
            $fases = array_reverse($fases, true);

            if (empty($fases)) {
                echo "<p style='text-align:center; padding:5rem;'>As chaves desta categoria ainda não foram geradas.</p>";
            } else {
                echo '<div class="chave-container">';
                foreach ($fases as $f => $lista) {
                    echo '<div class="fase-col">';
                    $title = $f == 1 ? 'Final' : ($f == 2 ? 'Semi-Final' : ($f == 4 ? 'Quartas' : "Oitavas ($f)"));
                    echo '<div class="fase-title">' . $title . '</div>';
                    foreach ($lista as $l) {
                        $p1 = $l['i1_aluno'] ? $l['a1_nome'] : ($l['i1_ext'] ?: '-- Vago --');
                        $p2 = $l['i2_aluno'] ? $l['a2_nome'] : ($l['i2_ext'] ?: '-- Vago --');
                        echo '<div class="luta-card">
                                <div class="luta-atleta">' . $p1 . '</div>
                                <div class="luta-atleta">' . $p2 . '</div>
                              </div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
        }
        ?>

    <?php elseif ($tipo == 'crachas' || $tipo == 'diplomas' || $tipo == 'etiquetas'): ?>
        <?php
        $insc_ids = $_GET['insc_ids'] ?? '';
        if (!$insc_ids) {
            die("Nenhum atleta selecionado.");
        }
        $ids_array = explode(',', $insc_ids);
        $placeholders = implode(',', array_fill(0, count($ids_array), '?'));

        $sql = "SELECT i.*, 
                       a.nome_completo as aluno_nome, 
                       c.nome as cat_nome, c.peso_max
                FROM competicao_inscricoes i
                LEFT JOIN alunos a ON i.aluno_id = a.id
                LEFT JOIN competicao_categorias c ON i.categoria_id = c.id
                WHERE i.id IN ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids_array);
        $atletas = $stmt->fetchAll();
        ?>

        <?php if ($tipo == 'crachas'): ?>
            <div class="cracha-grid">
                <?php foreach ($atletas as $at):
                    $nome = $at['aluno_id'] ? $at['aluno_nome'] : $at['nome_externo'];
                    $equipe = $at['aluno_id'] ? $unidade_nome : $at['equipe_externa'];
                    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode("https://senpipe.com.br/checkin?id=" . $at['id']);
                    ?>
                    <div class="cracha-card">
                        <div class="cracha-atleta"><?php echo htmlspecialchars($nome); ?></div>
                        <div class="cracha-info">
                            <div class="cracha-categoria"><?php echo htmlspecialchars($at['cat_nome'] ?: 'Sem Categoria'); ?></div>
                            <div class="cracha-equipe"><?php echo htmlspecialchars($equipe); ?></div>
                        </div>
                        <div class="cracha-qrcode">
                            <img src="<?php echo $qr_url; ?>" alt="QR Code">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($tipo == 'etiquetas'): ?>
            <div class="etiqueta-grid">
                <?php foreach ($atletas as $at):
                    $nome = $at['aluno_id'] ? $at['aluno_nome'] : $at['nome_externo'];
                    $equipe = $at['aluno_id'] ? $unidade_nome : $at['equipe_externa'];
                    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode("https://senpipe.com.br/checkin?id=" . $at['id']);
                    ?>
                    <div class="etiqueta-card">
                        <div class="etiqueta-content">
                            <div class="etiqueta-nome"><?php echo htmlspecialchars($nome); ?></div>
                            <div class="etiqueta-info">
                                <div><strong>Cat:</strong> <?php echo htmlspecialchars($at['cat_nome'] ?: 'Sem Categoria'); ?></div>
                                <div><strong>Equipe:</strong> <?php echo htmlspecialchars($equipe); ?></div>
                                <div style="margin-top:2mm; font-size:0.7rem; color:var(--primary); font-weight:800;">SENPIPE FIGHT
                                    SYSTEM</div>
                            </div>
                        </div>
                        <div class="etiqueta-qr">
                            <img src="<?php echo $qr_url; ?>" alt="QR Code">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <?php foreach ($atletas as $at):
                $nome = $at['aluno_id'] ? $at['aluno_nome'] : $at['nome_externo'];
                $equipe = $at['aluno_id'] ? $unidade_nome : $at['equipe_externa'];
                $resultado = $at['resultado'] ?: 'PARTICIPAÇÃO';
                $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode("https://senpipe.com.br/checkin?id=" . $at['id']);
                ?>
                <div class="diploma-page">
                    <div style="position: absolute; top: 20mm; right: 20mm; width: 30mm;">
                        <img src="<?php echo $qr_url; ?>" style="width: 100%; opacity: 0.8;">
                    </div>
                    <div class="diploma-title">Certificado</div>
                    <div class="diploma-text">
                        Certificamos que o atleta
                        <div class="diploma-atleta"><?php echo htmlspecialchars($nome); ?></div>
                        participou do evento <strong><?php echo htmlspecialchars($dados['nome']); ?></strong>,
                        conquistando o resultado de:
                        <div style="font-size: 2rem; font-weight: 800; margin: 1rem 0; color:var(--text-main);">
                            <?php echo strtoupper($resultado); ?>
                        </div>
                        na categoria <?php echo htmlspecialchars($at['cat_nome']); ?>.
                    </div>

                    <div class="diploma-footer">
                        <div class="assinatura-box">
                            <div style="border-bottom: 1px solid #08153a; margin-bottom: 0.5rem;"></div>
                            Organização
                        </div>
                        <div class="assinatura-box">
                            <div style="border-bottom: 1px solid #08153a; margin-bottom: 0.5rem;"></div>
                            Data: <?php echo date('d/m/Y', strtotime($dados['data_evento'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

    <style>
        /* Restoring Chaves CSS */
        .chave-container {
            display: flex;
            gap: 2rem;
            justify-content: center;
            padding: 2rem 0;
        }

        .fase-col {
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            gap: 1.5rem;
        }

        .fase-title {
            text-align: center;
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text-muted);
            margin-bottom: 1rem;
            text-transform: uppercase;
        }

        .luta-card {
            border: 1px solid #08153a;
            width: 180px;
            padding: 0.5rem;
            border-radius: 4px;
            background: #fff;
        }

        .luta-atleta {
            font-size: 0.75rem;
            padding: 0.25rem 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .luta-atleta:first-child {
            border-bottom: 1px solid #eee;
        }
    </style>

    <div class="no-print" style="position: fixed; bottom: 2rem; right: 2rem;">
        <button onclick="window.print()"
            style="padding: 1rem 2rem; background: var(--primary); color: #fff; border: none; border-radius: 2rem; font-weight: 800; cursor: pointer; box-shadow: 0 4px 15px rgba(235,0,0,0.3);">
            IMPRIMIR AGORA
        </button>
    </div>

</body>

</html>