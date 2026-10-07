<?php
require_once '../Gestor/config.php';
require_once __DIR__ . '/inc_exame_shared.php';

$tipo = $_GET['tipo'] ?? '';
$id   = (int) ($_GET['id'] ?? 0);

$configs = [
    'exame' => [
        'tabela'    => 'eventos_graduacao',
        'sql'       => "SELECT eg.id, eg.titulo AS nome, eg.descricao, eg.data_evento, eg.horario, eg.local,
                                eg.taxa, eg.taxa_lote2, eg.lote2_data_inicio,
                                eg.inscricoes_inicio, eg.inscricoes_fim, eg.avaliacoes_inicio, eg.avaliacoes_fim,
                                eg.avaliacoes_numero_tentativas, eg.todas_turmas,
                                eg.requisito_pagamento_obrigatorio, eg.requisito_frequencia_minima, eg.requisito_carencia_ativo,
                                u.id AS unidade_id, u.nome AS unidade_nome, u.cidade, u.estado
                         FROM eventos_graduacao eg
                         JOIN unidades u ON u.id = eg.unidade_id
                         WHERE eg.id = ? AND eg.status = 'agendado' AND u.status = 'ativo'",
        'label'     => 'Exame de Faixa',
        'icone'     => 'fa-user-ninja',
        'desc_pad'  => 'Exame de graduação de faixa aberto aos alunos da unidade.',
    ],
    'torneio' => [
        'tabela'    => 'competicoes',
        'sql'       => "SELECT c.id, c.nome, c.descricao, c.data_evento, c.localizacao, c.fotos,
                                u.id AS unidade_id, u.nome AS unidade_nome, u.cidade, u.estado
                         FROM competicoes c
                         JOIN unidades u ON u.id = c.unidade_id
                         WHERE c.id = ? AND c.status = 'aberto' AND u.status = 'ativo'",
        'label'     => 'Torneio',
        'icone'     => 'fa-trophy',
        'desc_pad'  => 'Torneio interno organizado pela unidade, aberto aos alunos e convidados.',
    ],
    'oficial' => [
        'tabela'    => 'competicoes_oficiais',
        'sql'       => "SELECT co.id, co.nome, co.data_inicio, co.data_fim, co.localizacao, co.organizacao, co.responsaveis,
                                u.id AS unidade_id, u.nome AS unidade_nome, u.cidade, u.estado
                         FROM competicoes_oficiais co
                         JOIN unidades u ON u.id = co.unidade_id
                         WHERE co.id = ? AND co.status = 'aberto' AND u.status = 'ativo'",
        'label'     => 'Competição Oficial',
        'icone'     => 'fa-medal',
        'desc_pad'  => 'Competição do calendário oficial.',
    ],
];

if (!$id || !isset($configs[$tipo])) {
    http_response_code(404);
}

$cfg = $configs[$tipo] ?? null;
$evento = null;

if ($cfg) {
    $stmt = $pdo->prepare($cfg['sql']);
    $stmt->execute([$id]);
    $evento = $stmt->fetch();
}

if (!$evento) {
    http_response_code(404);
}

$meses_pt = ['01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril', '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'];

if ($evento) {
    $data_evento = $tipo === 'oficial' ? $evento['data_inicio'] : $evento['data_evento'];
    $dia  = date('d', strtotime($data_evento));
    $mes  = $meses_pt[date('m', strtotime($data_evento))];
    $ano  = date('Y', strtotime($data_evento));
    $data_fmt = date('d/m/Y', strtotime($data_evento));

    $local = '';
    if ($tipo === 'exame') $local = $evento['local'];
    if ($tipo === 'torneio') $local = $evento['localizacao'];
    if ($tipo === 'oficial') $local = $evento['localizacao'];
    if (!$local) {
        $local = trim(($evento['cidade'] ?? '') . ($evento['estado'] ? ' - ' . $evento['estado'] : ''));
    }

    $descricao = (in_array($tipo, ['exame', 'torneio'], true) && trim((string) ($evento['descricao'] ?? '')) !== '')
        ? $evento['descricao']
        : (($tipo === 'oficial' && !empty($evento['organizacao']))
            ? ('Competição oficial organizada por ' . $evento['organizacao'] . '.')
            : $cfg['desc_pad']);

    $titulo = $evento['nome'];

    // Torneio: usa a primeira foto cadastrada em Ajustes (editar_competicao.php) como capa do banner
    $banner_url = null;
    if ($tipo === 'torneio') {
        $fotos_evt = json_decode($evento['fotos'] ?? '[]', true);
        if (is_array($fotos_evt) && !empty($fotos_evt[0])) {
            $banner_url = '../Gestor/uploads/competicoes/' . rawurlencode(basename($fotos_evt[0]));
        }
    }

    // ── Torneio: delegações visitantes convidadas + categorias configuradas ──
    // (só faz sentido perguntar isso quando o CPF não pertence a nenhum aluno cadastrado)
    $delegacoes_torneio = [];
    $categorias_torneio = [];
    $turmas_torneio = [];
    if ($tipo === 'torneio') {
        $stmt_deleg = $pdo->prepare(
            "SELECT dv.id, dv.nome
             FROM competicao_convidados cc
             JOIN delegacoes_visitantes dv ON dv.id = cc.delegacao_id
             WHERE cc.competicao_id = ?
             ORDER BY dv.nome ASC"
        );
        try {
            $stmt_deleg->execute([$evento['id']]);
            $delegacoes_torneio = $stmt_deleg->fetchAll();
        } catch (PDOException $e) {
            $delegacoes_torneio = [];
        }

        // Turmas / equipes / professores cadastrados em cada delegação convidada
        // (Gestor > Delegações > editar). Alimentam o campo "Turma / Professor" do visitante.
        $turmas_delegacoes = [];
        if ($delegacoes_torneio) {
            $ids_deleg = array_map('intval', array_column($delegacoes_torneio, 'id'));
            $marcadores = implode(',', array_fill(0, count($ids_deleg), '?'));
            try {
                $stmt_td = $pdo->prepare("SELECT id, delegacao_id, nome FROM delegacoes_turmas WHERE delegacao_id IN ($marcadores) ORDER BY nome ASC");
                $stmt_td->execute($ids_deleg);
                foreach ($stmt_td->fetchAll() as $td) {
                    $turmas_delegacoes[(int) $td['delegacao_id']][] = ['id' => (int) $td['id'], 'nome' => $td['nome']];
                }
            } catch (PDOException $e) {
                $turmas_delegacoes = [];
            }
        }

        $stmt_cat = $pdo->prepare("SELECT cc.id, cc.nome, cc.sexo, cc.ano_nascimento_min, cc.ano_nascimento_max, cc.limite_atletas, cc.faixas, cc.peso_max,
                (SELECT COUNT(*) FROM competicao_inscricoes ci WHERE ci.categoria_id = cc.id AND ci.status_pagamento = 'pago') as total_pagos
             FROM competicao_categorias cc WHERE cc.competicao_id = ?");
        try {
            $stmt_cat->execute([$evento['id']]);
            $categorias_torneio = $stmt_cat->fetchAll();
            usort($categorias_torneio, function ($a, $b) {
                return strnatcasecmp($a['nome'], $b['nome']);
            });
        } catch (PDOException $e) {
            $categorias_torneio = [];
        }

        // Faixas/graduações da unidade (Configurações > Faixas e Graduações) — o inscrito escolhe a dele
        $faixas_unidade = [];
        try {
            // Só o nome da faixa (o "grau" cadastrado é um número de ordem, não interessa ao inscrito)
            $stmt_grads = $pdo->prepare("SELECT nome FROM unidade_graduacoes WHERE unidade_id = ? ORDER BY ordem ASC, nome ASC");
            $stmt_grads->execute([$evento['unidade_id']]);
            foreach ($stmt_grads->fetchAll() as $g) {
                $nome_faixa = trim($g['nome']);
                if ($nome_faixa !== '' && !in_array($nome_faixa, $faixas_unidade, true)) {
                    $faixas_unidade[] = $nome_faixa;
                }
            }
        } catch (PDOException $e) {
            $faixas_unidade = [];
        }
        if (!$faixas_unidade) {
            $faixas_unidade = ['Branca', 'Cinza', 'Amarela', 'Laranja', 'Verde', 'Azul', 'Roxa', 'Marrom', 'Preta'];
        }

        $stmt_turmas_torneio = $pdo->prepare("SELECT id, nome, horario FROM turmas WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
        try {
            $stmt_turmas_torneio->execute([$evento['unidade_id']]);
            $turmas_torneio = $stmt_turmas_torneio->fetchAll();
        } catch (PDOException $e) {
            $turmas_torneio = [];
        }
    }

    $ok  = isset($_GET['ok']);
    $erro = isset($_GET['erro']);

    // ── Regras de turmas e valores (somente para Exame de Faixa) ──
    $turmas_evento = [];
    $preco_unico = null;
    $lote_unico_label = null;
    $turmas_visitante_options = [];

    if ($tipo === 'exame') {
        $turmas_visitante_options = turmasLiberadasParaExame($pdo, $evento);
        $hoje = date('Y-m-d');

        if (empty($evento['todas_turmas'])) {
            // Cadastro por turma: cada turma participante tem suas próprias datas/valores
            $stmt_turmas = $pdo->prepare(
                "SELECT t.id, t.nome, a.nome AS academia_nome,
                        egd.data_evento, egd.inscricoes_inicio, egd.inscricoes_fim,
                        egd.avaliacoes_inicio, egd.avaliacoes_fim, egd.local_entrega, egd.horario_entrega,
                        egd.valor_lote1, egd.valor_lote1_data_limite, egd.valor_lote2, egd.valor_lote2_data_limite,
                        egd.avaliacoes_numero_tentativas
                 FROM eventos_graduacao_turmas egt
                 JOIN turmas t ON t.id = egt.turma_id
                 LEFT JOIN academias a ON a.id = t.academia_id
                 LEFT JOIN eventos_graduacao_datas_turma egd ON egd.evento_id = egt.evento_id AND egd.turma_id = egt.turma_id
                 WHERE egt.evento_id = ?
                 ORDER BY a.nome ASC, t.nome ASC"
            );
            $stmt_turmas->execute([$evento['id']]);
            foreach ($stmt_turmas->fetchAll() as $t) {
                $lote1_expirado = !empty($t['valor_lote1_data_limite']) && $hoje > $t['valor_lote1_data_limite'];
                if ($lote1_expirado && $t['valor_lote2'] !== null) {
                    $preco_atual = (float) $t['valor_lote2'];
                    $lote_label = '2º lote';
                } elseif ($t['valor_lote1'] !== null) {
                    $preco_atual = (float) $t['valor_lote1'];
                    $lote_label = !empty($t['valor_lote1_data_limite']) ? '1º lote' : null;
                } else {
                    $preco_atual = 0.00;
                    $lote_label = null;
                }
                $turmas_evento[] = [
                    'id'          => $t['id'],
                    'nome'        => $t['nome'] . ($t['academia_nome'] ? ' · ' . $t['academia_nome'] : ''),
                    'data_evento' => $t['data_evento'],
                    'local'       => $t['local_entrega'],
                    'horario'     => $t['horario_entrega'],
                    'preco'       => $preco_atual,
                    'lote_label'  => $lote_label,
                ];
            }
        } else {
            // Cadastro único: mesmas regras/valores para todas as turmas da unidade
            $lote2_vigente = !empty($evento['lote2_data_inicio']) && $hoje >= $evento['lote2_data_inicio'];
            if ($lote2_vigente && $evento['taxa_lote2'] !== null && $evento['taxa_lote2'] !== '') {
                $preco_unico = (float) $evento['taxa_lote2'];
                $lote_unico_label = '2º lote';
            } else {
                $preco_unico = (float) $evento['taxa'];
                $lote_unico_label = !empty($evento['lote2_data_inicio']) ? '1º lote' : null;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-7TR2SN75QF"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-7TR2SN75QF');
  </script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $evento ? htmlspecialchars($titulo) . ' | Eventos SHIAI PRO' : 'Evento não encontrado | SHIAI PRO'; ?></title>
  <meta name="robots" content="<?php echo $evento ? 'index, follow' : 'noindex, nofollow'; ?>">

  <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
  <link rel="manifest" href="/favicon/site.webmanifest">
  <link rel="shortcut icon" href="/favicon/favicon.ico">

  <link rel="stylesheet" href="../Tema/css/reset.css" />
  <link rel="stylesheet" href="../Tema/css/style.css" />
  <link rel="stylesheet" href="../Tema/css/animations.css" />

  <link
    href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <style>
    .evento-hero {
      padding: 150px 0 60px;
      background: var(--brand-bg);
    }

    .evento-hero__breadcrumb {
      font-size: 13px;
      color: var(--gray-500);
      margin-bottom: 16px;
    }

    .evento-hero__breadcrumb a {
      color: var(--brand);
      text-decoration: none;
      font-weight: 600;
    }

    .evento-hero__tag {
      display: inline-block;
      background: var(--brand-muted);
      color: var(--brand-darker);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      padding: 5px 12px;
      border-radius: 999px;
      margin-bottom: 16px;
    }

    .evento-hero h1 {
      font-family: 'Sora', sans-serif;
      font-size: 38px;
      font-weight: 800;
      color: var(--brand-darker);
      line-height: 1.2;
      max-width: 720px;
    }

    .evento-detalhe {
      padding: 60px 0 100px;
    }

    .evento-detalhe__grid {
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 50px;
      align-items: start;
    }

    .evento-detalhe__banner {
      height: 220px;
      border-radius: var(--r-md);
      background: linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 32px;
      position: relative;
      overflow: hidden;
    }

    .evento-detalhe__banner i {
      font-size: 110px;
      color: rgba(255, 255, 255, .18);
    }

    .evento-detalhe__banner--foto {
      height: auto;
      min-height: 220px;
      max-height: 420px;
      background: var(--gray-100, #f1f5f9);
    }

    .evento-detalhe__banner--foto img {
      width: 100%;
      max-height: 420px;
      object-fit: cover;
      display: block;
    }

    .evento-detalhe__banner .data-badge {
      position: absolute;
      top: 20px;
      left: 20px;
      background: #fff;
      border-radius: 10px;
      padding: 10px 16px;
      text-align: center;
      box-shadow: 0 8px 20px rgba(0, 0, 0, .18);
    }

    .evento-detalhe__banner .data-badge strong {
      display: block;
      font-size: 24px;
      font-weight: 800;
      color: var(--brand-darker);
      line-height: 1;
    }

    .evento-detalhe__banner .data-badge span {
      display: block;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--gray-500);
    }

    .evento-detalhe__desc h2 {
      font-family: 'Sora', sans-serif;
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .evento-detalhe__desc p {
      color: var(--gray-500);
      line-height: 1.7;
      font-size: 15px;
      margin-bottom: 32px;
    }

    .evento-meta-list {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 18px;
      margin-bottom: 32px;
    }

    .evento-meta-item {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      background: var(--gray-50);
      border: 1px solid var(--border);
      border-radius: var(--r-md);
      padding: 16px;
    }

    .evento-meta-item i {
      color: var(--brand);
      font-size: 18px;
      margin-top: 2px;
    }

    .evento-meta-item strong {
      display: block;
      font-size: 13px;
      color: var(--gray-400);
      text-transform: uppercase;
      letter-spacing: .4px;
      margin-bottom: 2px;
    }

    .evento-meta-item span {
      font-size: 14px;
      font-weight: 600;
      color: var(--text, #111);
    }

    /* Card de inscrição */
    .evento-form-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--r-md);
      padding: 28px;
      position: sticky;
      top: 100px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .06);
    }

    .evento-form-card h3 {
      font-family: 'Sora', sans-serif;
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .evento-form-card p.sub {
      font-size: 13px;
      color: var(--gray-500);
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 14px;
    }

    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 6px;
      color: var(--text, #111);
    }

    .form-group input {
      width: 100%;
      padding: 11px 14px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      font-family: 'Inter', sans-serif;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--brand);
    }

    .evento-form-card .btn {
      width: 100%;
      justify-content: center;
      margin-top: 6px;
    }

    .form-alert {
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 16px;
    }

    .form-alert--ok {
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0;
    }

    .form-alert--erro {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }

    .cpf-nao-encontrado {
      margin-bottom: 16px;
    }

    .cpf-nao-encontrado__texto {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
      border-radius: 8px;
      padding: 12px 14px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 12px;
    }

    .btn-inscrever-aluno {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      background: var(--brand-darker, #111);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 13px 14px;
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
      text-transform: uppercase;
      letter-spacing: .4px;
      cursor: pointer;
      transition: opacity .15s ease;
    }

    .btn-inscrever-aluno:hover {
      opacity: .88;
    }

    .link-visitante {
      display: block;
      text-align: center;
      margin-top: 12px;
      font-size: 13px;
      color: var(--gray-500);
      text-decoration: underline;
    }

    .evento-turmas {
      margin-bottom: 32px;
    }

    .evento-turmas h2 {
      font-family: 'Sora', sans-serif;
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .tabela-turmas {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }

    .tabela-turmas th {
      text-align: left;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .4px;
      color: var(--gray-400);
      padding: 10px 14px;
      border-bottom: 2px solid var(--border);
      white-space: nowrap;
    }

    .tabela-turmas td {
      padding: 12px 14px;
      border-bottom: 1px solid var(--border);
      color: var(--text, #111);
      white-space: nowrap;
    }

    .tabela-turmas tr:last-child td {
      border-bottom: none;
    }

    .lote-tag {
      display: inline-block;
      background: var(--brand-muted);
      color: var(--brand-darker);
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 999px;
      margin-left: 6px;
    }

    .form-group select {
      width: 100%;
      padding: 11px 14px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      font-family: 'Inter', sans-serif;
      background: #fff;
    }

    .form-group select:focus {
      outline: none;
      border-color: var(--brand);
    }

    .evento-form-card__preco {
      background: var(--brand-bg);
      border-radius: 8px;
      padding: 12px 14px;
      font-size: 13px;
      font-weight: 600;
      color: var(--brand-darker);
      margin-bottom: 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .evento-form-card__preco strong {
      font-size: 16px;
    }

    .lista-alunos {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 8px;
    }

    .aluno-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      transition: border-color .15s ease, background .15s ease;
    }

    .aluno-card:hover {
      border-color: var(--brand);
      background: var(--brand-bg);
    }

    .aluno-card.is-disabled {
      opacity: .5;
      cursor: not-allowed;
    }

    .aluno-card__avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: var(--brand-muted);
      color: var(--brand-darker);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 14px;
      overflow: hidden;
      flex-shrink: 0;
    }

    .aluno-card__avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .aluno-card__info strong {
      display: block;
      font-size: 14px;
      color: var(--text, #111);
    }

    .aluno-card__info span {
      font-size: 12px;
      color: var(--gray-500);
    }

    .ficha-aluno {
      background: var(--gray-50);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 16px;
      margin-bottom: 16px;
      font-size: 14px;
    }

    .ficha-aluno__linha {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      border-bottom: 1px solid var(--border);
    }

    .ficha-aluno__linha:last-child {
      border-bottom: none;
    }

    .ficha-aluno__linha span:first-child {
      color: var(--gray-500);
    }

    .ficha-aluno__linha span:last-child {
      font-weight: 700;
      color: var(--text, #111);
    }

    .evento-nao-encontrado {
      padding: 180px 0 120px;
      text-align: center;
    }

    .evento-nao-encontrado i {
      font-size: 48px;
      color: var(--gray-400);
      margin-bottom: 20px;
      display: block;
    }

    .evento-nao-encontrado h1 {
      font-family: 'Sora', sans-serif;
      font-size: 26px;
      margin-bottom: 12px;
    }

    .evento-nao-encontrado p {
      color: var(--gray-500);
      margin-bottom: 24px;
    }

    @media (max-width: 900px) {
      .evento-detalhe__grid {
        grid-template-columns: 1fr;
      }

      .evento-form-card {
        position: static;
      }
    }

    @media (max-width: 560px) {
      .evento-meta-list {
        grid-template-columns: 1fr;
      }

      .evento-hero h1 {
        font-size: 28px;
      }
    }
  </style>
</head>

<body>

  <!-- HEADER -->
  <header class="header" id="header">
    <div class="container header__inner">
      <a href="/index.html" class="logo">
        <img src="../Gestor/assets/img/logotipos/10.png" alt="SHIAIPRO Logo" style="height: 40px; width: auto;">
      </a>
      <nav class="nav" id="nav">
        <a href="/index.html#features" class="nav__link">Funcionalidades</a>
        <a href="/index.html#plans" class="nav__link">Planos</a>
        <a href="index.php" class="nav__link">Eventos</a>
        <a href="/index.html#faq" class="nav__link">FAQ</a>
      </nav>
      <div class="header__actions">
        <a href="/Gestor" class="btn btn--dark-outline" target="_blank">Entrar</a>
        <a href="https://wa.me/5545988128964" class="btn btn--dark" target="_blank">Teste grátis</a>
      </div>
      <button class="hamburger" id="hamburger" aria-label="Abrir menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <?php if (!$evento): ?>

    <section class="evento-nao-encontrado">
      <div class="container">
        <i class="fa-regular fa-calendar-xmark"></i>
        <h1>Evento não encontrado</h1>
        <p>Esse evento pode ter sido encerrado ou o link está incorreto.</p>
        <a href="index.php" class="btn btn--dark">Ver todos os eventos</a>
      </div>
    </section>

  <?php else: ?>

    <!-- HERO -->
    <section class="evento-hero">
      <div class="container">
        <div class="evento-hero__breadcrumb">
          <a href="index.php">Eventos</a> / <?php echo htmlspecialchars($cfg['label']); ?>
        </div>
        <div class="evento-hero__tag"><?php echo htmlspecialchars($cfg['label']); ?></div>
        <h1><?php echo htmlspecialchars($titulo); ?></h1>
      </div>
    </section>

    <!-- DETALHE -->
    <section class="evento-detalhe">
      <div class="container">
        <div class="evento-detalhe__grid">

          <div class="evento-detalhe__col">
            <div class="evento-detalhe__banner<?php echo $banner_url ? ' evento-detalhe__banner--foto' : ''; ?>">
              <?php if ($banner_url): ?>
                <img src="<?php echo htmlspecialchars($banner_url); ?>" alt="<?php echo htmlspecialchars($titulo); ?>">
              <?php endif; ?>
              <div class="data-badge">
                <strong><?php echo htmlspecialchars($dia); ?></strong>
                <span><?php echo htmlspecialchars($mes); ?></span>
              </div>
              <?php if (!$banner_url): ?>
                <i class="fa-solid <?php echo htmlspecialchars($cfg['icone']); ?>"></i>
              <?php endif; ?>
            </div>

            <div class="evento-meta-list">
              <div class="evento-meta-item">
                <i class="fa-regular fa-calendar"></i>
                <div>
                  <strong>Data</strong>
                  <span><?php echo htmlspecialchars($data_fmt); ?></span>
                </div>
              </div>
              <?php if ($tipo === 'exame' && !empty($evento['horario'])): ?>
                <div class="evento-meta-item">
                  <i class="fa-regular fa-clock"></i>
                  <div>
                    <strong>Horário</strong>
                    <span><?php echo htmlspecialchars(substr($evento['horario'], 0, 5)); ?></span>
                  </div>
                </div>
              <?php endif; ?>
              <?php if ($tipo === 'oficial' && !empty($evento['data_fim']) && $evento['data_fim'] !== $evento['data_inicio']): ?>
                <div class="evento-meta-item">
                  <i class="fa-regular fa-calendar-check"></i>
                  <div>
                    <strong>Encerramento</strong>
                    <span><?php echo htmlspecialchars(date('d/m/Y', strtotime($evento['data_fim']))); ?></span>
                  </div>
                </div>
              <?php endif; ?>
              <?php if (!empty($local)): ?>
                <div class="evento-meta-item">
                  <i class="fa-solid fa-location-dot"></i>
                  <div>
                    <strong>Local</strong>
                    <span><?php echo htmlspecialchars($local); ?></span>
                  </div>
                </div>
              <?php endif; ?>
              <div class="evento-meta-item">
                <i class="fa-solid fa-building"></i>
                <div>
                  <strong>Organização</strong>
                  <span><?php echo htmlspecialchars($evento['unidade_nome']); ?></span>
                </div>
              </div>
              <?php if ($tipo === 'exame' && !empty($evento['todas_turmas']) && $preco_unico !== null && $preco_unico > 0): ?>
                <div class="evento-meta-item">
                  <i class="fa-solid fa-tag"></i>
                  <div>
                    <strong>Taxa de inscrição<?php echo $lote_unico_label ? ' (' . $lote_unico_label . ')' : ''; ?></strong>
                    <span>R$ <?php echo number_format($preco_unico, 2, ',', '.'); ?></span>
                  </div>
                </div>
              <?php endif; ?>
              <?php if ($tipo === 'exame' && !empty($evento['inscricoes_inicio']) && !empty($evento['inscricoes_fim'])): ?>
                <div class="evento-meta-item">
                  <i class="fa-regular fa-calendar-plus"></i>
                  <div>
                    <strong>Período de inscrições</strong>
                    <span><?php echo date('d/m/Y', strtotime($evento['inscricoes_inicio'])); ?> a <?php echo date('d/m/Y', strtotime($evento['inscricoes_fim'])); ?></span>
                  </div>
                </div>
              <?php endif; ?>
              <?php if ($tipo === 'exame' && !empty($evento['avaliacoes_numero_tentativas'])): ?>
                <div class="evento-meta-item">
                  <i class="fa-solid fa-rotate"></i>
                  <div>
                    <strong>Tentativas de avaliação</strong>
                    <span><?php echo (int) $evento['avaliacoes_numero_tentativas']; ?></span>
                  </div>
                </div>
              <?php endif; ?>
              <?php if ($tipo === 'oficial' && !empty($evento['responsaveis'])): ?>
                <div class="evento-meta-item">
                  <i class="fa-solid fa-user-tie"></i>
                  <div>
                    <strong>Responsáveis</strong>
                    <span><?php echo htmlspecialchars($evento['responsaveis']); ?></span>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <div class="evento-detalhe__desc">
              <h2>Sobre o evento</h2>
              <p><?php echo nl2br(htmlspecialchars($descricao)); ?></p>
            </div>

            <?php if ($tipo === 'exame' && empty($evento['todas_turmas']) && !empty($turmas_evento)): ?>
              <div class="evento-turmas">
                <h2>Turmas e valores</h2>
                <p style="color:var(--gray-500); font-size:14px; margin-bottom:16px;">
                  Cada turma pode ter data de entrega, local e valor de inscrição próprios. Confira abaixo.</p>
                <div style="overflow-x:auto;">
                  <table class="tabela-turmas">
                    <thead>
                      <tr>
                        <th>Turma</th>
                        <th>Data</th>
                        <th>Local</th>
                        <th>Valor</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($turmas_evento as $t): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($t['nome']); ?></td>
                          <td><?php echo !empty($t['data_evento']) ? date('d/m/Y', strtotime($t['data_evento'])) : '—'; ?></td>
                          <td><?php echo !empty($t['local']) ? htmlspecialchars($t['local']) : '—'; ?></td>
                          <td>
                            <?php if ($t['preco'] > 0): ?>
                              R$ <?php echo number_format($t['preco'], 2, ',', '.'); ?>
                              <?php if ($t['lote_label']): ?><span class="lote-tag"><?php echo htmlspecialchars($t['lote_label']); ?></span><?php endif; ?>
                            <?php else: ?>
                              Gratuito
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- CARD DE INSCRIÇÃO -->
          <aside class="evento-form-card">

            <?php if ($ok): ?>
              <div class="form-alert form-alert--ok">
                <i class="fa-solid fa-circle-check"></i> Inscrição enviada com sucesso! Em breve entraremos em contato.
              </div>
            <?php elseif ($erro): ?>
              <div class="form-alert form-alert--erro">
                <i class="fa-solid fa-circle-exclamation"></i> Preencha todos os campos corretamente e tente novamente.
              </div>
            <?php elseif (isset($_GET['ja_inscrito'])): ?>
              <div class="form-alert form-alert--erro">
                <i class="fa-solid fa-circle-exclamation"></i> Este atleta já está inscrito neste evento com esse CPF de responsável.
              </div>
            <?php elseif (isset($_GET['categoria_lotada'])): ?>
              <div class="form-alert form-alert--erro">
                <i class="fa-solid fa-circle-exclamation"></i> Essa categoria já atingiu o limite de atletas inscritos.
              </div>
            <?php endif; ?>
            <script>
              // A mensagem acima vem de parâmetros na URL (?ok=1, ?erro=1...). Remove-os logo depois
              // de exibir, para que atualizar a página não repita a mensagem de uma inscrição anterior.
              (function () {
                if (!window.history || !history.replaceState) return;
                var url = new URL(window.location.href);
                var tinha = false;
                ['ok', 'erro', 'ja_inscrito', 'categoria_lotada'].forEach(function (p) {
                  if (url.searchParams.has(p)) { url.searchParams.delete(p); tinha = true; }
                });
                if (tinha) history.replaceState(null, '', url.pathname + url.search + url.hash);
              })();
            </script>

            <?php if ($tipo === 'exame'): ?>

              <!-- WIZARD: CPF do responsável -> alunos vinculados -> ficha + confirmação -->
              <div id="wizardExame" data-evento-id="<?php echo (int) $evento['id']; ?>">

                <div id="passoCpf">
                  <h3>Inscreva-se neste exame</h3>
                  <p class="sub">Digite o CPF do responsável (ou do próprio aluno) para localizar os alunos vinculados.</p>
                  <div class="form-group">
                    <label for="cpfBusca">CPF do responsável</label>
                    <input type="text" id="cpfBusca" placeholder="000.000.000-00" inputmode="numeric" maxlength="14" autocomplete="off">
                  </div>
                  <div id="cpfMsg" class="form-alert form-alert--erro" style="display:none;"></div>
                  <button type="button" class="btn btn--dark btn--lg" onclick="buscarCpf()">
                    Buscar alunos
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                  </button>
                </div>

                <div id="passoVisitanteExame" style="display:none;"></div>

                <div id="passoAlunos" style="display:none;">
                  <h3>Selecione o aluno</h3>
                  <p class="sub">Alunos encontrados para este CPF nesta unidade.</p>
                  <div id="listaAlunos" class="lista-alunos"></div>
                  <button type="button" class="btn btn--dark-outline" onclick="voltarPasso('passoCpf')" style="width:100%; justify-content:center; margin-top:8px;">
                    Voltar
                  </button>
                </div>

                <div id="passoFicha" style="display:none;">
                  <h3>Confirme a inscrição</h3>
                  <div id="fichaAluno" class="ficha-aluno"></div>

                  <form action="inscrever_exame.php" method="POST" id="formInscricaoExame">
                    <input type="hidden" name="evento_id" value="<?php echo (int) $evento['id']; ?>">
                    <input type="hidden" name="aluno_id" id="fichaAlunoId">

                    <div class="form-group">
                      <label for="tamanho_faixa">Tamanho da faixa (ex: M3, A2)</label>
                      <input type="text" id="tamanho_faixa" name="tamanho_faixa" placeholder="Ex: M3, A2">
                    </div>

                    <button type="submit" class="btn btn--dark btn--lg">
                      Confirmar inscrição
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                      </svg>
                    </button>
                  </form>
                  <button type="button" class="btn btn--dark-outline" onclick="voltarPasso('passoAlunos')" style="width:100%; justify-content:center; margin-top:8px;">
                    Voltar
                  </button>
                </div>

              </div>

            <?php else: ?>

              <!-- WIZARD: CPF -> ficha simples de visitante (inscrição válida só para este evento) -->
              <div id="wizardVisitante" data-tipo="<?php echo htmlspecialchars($tipo); ?>" data-evento-id="<?php echo (int) $evento['id']; ?>">

                <div id="passoVisitanteCpf">
                  <h3>Inscreva-se neste evento</h3>
                  <p class="sub">Digite o CPF do responsável (ou do próprio aluno) para começar. Esse cadastro vale apenas para este evento.</p>
                  <div class="form-group">
                    <label for="cpfVisitante">CPF do responsável</label>
                    <input type="text" id="cpfVisitante" placeholder="000.000.000-00" inputmode="numeric" maxlength="14" autocomplete="off">
                  </div>
                  <div id="cpfVisitanteMsg" class="form-alert form-alert--erro" style="display:none;"></div>
                  <button type="button" class="btn btn--dark btn--lg" onclick="verificarCpfVisitante()">
                    Continuar
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                  </button>
                </div>

                <div id="passoVisitanteForm" style="display:none;"></div>
              </div>

            <?php endif; ?>
          </aside>

        </div>
      </div>
    </section>

  <?php endif; ?>

  <!-- FOOTER -->
  <footer class="footer" id="footer">
    <div class="container footer__inner">
      <div class="footer__top">
        <div class="footer__brand">
          <a href="/index.html" class="logo logo--light">
            <img src="https://shiaipro.com.br/Gestor/assets/img/logotipos/10.png" alt="SHIAIPRO Logo"
              style="height: 40px; width: auto;">
          </a>
          <p>O sistema de gestão completo para academias de judô e jiu-jitsu do Brasil.</p>
        </div>
        <div class="footer__cols">
          <div class="footer__col">
            <h4>Produto</h4>
            <a href="/produto.html#funcionalidades">Funcionalidades</a>
            <a href="/produto.html#precos">Preços</a>
          </div>
          <div class="footer__col">
            <h4>Recursos</h4>
            <a href="index.php">Eventos</a>
          </div>
          <div class="footer__col">
            <h4>Empresa</h4>
            <a href="/sobre-nos.html#sobre">Sobre nós</a>
            <a href="/sobre-nos.html#contato">Contato</a>
          </div>
          <div class="footer__col">
            <h4>Legal</h4>
            <a href="/legal.html#termos">Termos de Uso</a>
            <a href="/legal.html#privacidade">Privacidade</a>
          </div>
        </div>
      </div>
      <div class="footer__bottom">
        <p class="footer__copy">© 2025 SHIAI PRO · Feito com <i class="fa-solid fa-user-ninja"></i> no Brasil</p>
        <p class="footer__cnpj">R RAMIREZ | 45.949.568/0001-91</p>
      </div>
    </div>
  </footer>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
  <script src="../Tema/js/main.js"></script>

  <script>
    function voltarPasso(idAlvo) {
      ['passoCpf', 'passoAlunos', 'passoFicha', 'passoVisitanteExame'].forEach(function (id) {
        document.getElementById(id).style.display = (id === idAlvo) ? 'block' : 'none';
      });
    }

    function mascaraCpf(input) {
      var v = input.value.replace(/\D/g, '').slice(0, 11);
      v = v.replace(/(\d{3})(\d)/, '$1.$2');
      v = v.replace(/(\d{3})(\d)/, '$1.$2');
      v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      input.value = v;
    }

    document.addEventListener('DOMContentLoaded', function () {
      var cpfInput = document.getElementById('cpfBusca');
      if (!cpfInput) return;

      cpfInput.addEventListener('input', function () { mascaraCpf(cpfInput); });

      // Se o pai voltou do cadastro de novo aluno, reaproveita o CPF já digitado e busca de novo.
      var cpfSalvo = sessionStorage.getItem('shiaipro_cpf_evento');
      if (cpfSalvo) {
        sessionStorage.removeItem('shiaipro_cpf_evento');
        cpfInput.value = cpfSalvo;
        buscarCpf();
      }
    });

    function buscarCpf() {
      var wizard = document.getElementById('wizardExame');
      var eventoId = wizard.getAttribute('data-evento-id');
      var cpf = document.getElementById('cpfBusca').value;
      var msg = document.getElementById('cpfMsg');
      msg.style.display = 'none';
      msg.className = 'form-alert form-alert--erro';

      var digits = cpf.replace(/\D/g, '');
      if (!cpfValido(digits)) {
        msg.textContent = digits.length !== 11 ? 'Digite os 11 números do CPF.' : 'CPF inválido. Confira os números digitados.';
        msg.style.display = 'block';
        return;
      }

      var fd = new FormData();
      fd.append('cpf', cpf);
      fd.append('evento_id', eventoId);

      fetch('api_buscar_cpf.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            if (data.nao_encontrado) {
              var cpfLimpo = cpf.replace(/'/g, '');
              var html = '<div class="cpf-nao-encontrado__texto"><i class="fa-solid fa-circle-exclamation"></i> ' +
                escapeHtml(data.message || 'Nenhum aluno encontrado com esse CPF.') + '</div>' +
                '<button type="button" class="btn-inscrever-aluno" onclick="mostrarVisitanteExame(\'' + cpfLimpo + '\')">' +
                'Inscrever aluno' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7" /></svg>' +
                '</button>';

              msg.className = 'cpf-nao-encontrado';
              msg.innerHTML = html;
              msg.style.display = 'block';
              return;
            }
            msg.className = 'form-alert form-alert--erro';
            msg.textContent = data.message || 'Nenhum aluno encontrado.';
            msg.style.display = 'block';
            return;
          }
          renderAlunos(data.alunos, eventoId);
          voltarPasso('passoAlunos');
        })
        .catch(function () {
          msg.textContent = 'Erro ao buscar. Tente novamente.';
          msg.style.display = 'block';
        });
    }

    // Valida o CPF pelos dígitos verificadores (mesma regra de inc_cpf.php)
    function cpfValido(digits) {
      if (!/^\d{11}$/.test(digits) || /^(\d)\1{10}$/.test(digits)) return false;
      for (var t = 9; t < 11; t++) {
        var soma = 0;
        for (var i = 0; i < t; i++) soma += parseInt(digits.charAt(i), 10) * ((t + 1) - i);
        if (parseInt(digits.charAt(t), 10) !== ((10 * soma) % 11) % 10) return false;
      }
      return true;
    }

    function escapeHtml(str) {
      var div = document.createElement('div');
      div.textContent = str == null ? '' : str;
      return div.innerHTML;
    }

    function iniciais(nome) {
      var partes = nome.trim().split(' ');
      var ini = partes[0].charAt(0);
      if (partes.length > 1) ini += partes[partes.length - 1].charAt(0);
      return ini.toUpperCase();
    }

    function renderAlunos(alunos, eventoId) {
      var lista = document.getElementById('listaAlunos');
      lista.innerHTML = '';
      alunos.forEach(function (al) {
        var card = document.createElement('div');
        card.className = 'aluno-card' + (al.ja_inscrito ? ' is-disabled' : '');

        var avatar = al.foto
          ? '<img src="' + escapeHtml(al.foto) + '" alt="">'
          : escapeHtml(iniciais(al.nome));

        card.innerHTML =
          '<div class="aluno-card__avatar">' + avatar + '</div>' +
          '<div class="aluno-card__info">' +
          '<strong>' + escapeHtml(al.nome) + '</strong>' +
          '<span>Faixa ' + escapeHtml(al.faixa) + (al.turmas ? ' · ' + escapeHtml(al.turmas) : '') + (al.ja_inscrito ? ' · Já inscrito' : '') + '</span>' +
          '</div>';

        if (!al.ja_inscrito) {
          card.addEventListener('click', function () { carregarFicha(al.id, eventoId); });
        }
        lista.appendChild(card);
      });
    }

    function carregarFicha(alunoId, eventoId) {
      var fd = new FormData();
      fd.append('aluno_id', alunoId);
      fd.append('evento_id', eventoId);

      fetch('api_ficha_aluno.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            alert(data.message || 'Não foi possível carregar a ficha do aluno.');
            return;
          }
          var al = data.aluno;
          var valorTexto = data.valor > 0
            ? 'R$ ' + data.valor.toFixed(2).replace('.', ',') + (data.lote_label ? ' (' + data.lote_label + ')' : '')
            : 'Gratuito';

          document.getElementById('fichaAluno').innerHTML =
            '<div class="ficha-aluno__linha"><span>Aluno</span><span>' + escapeHtml(al.nome) + '</span></div>' +
            '<div class="ficha-aluno__linha"><span>Faixa atual</span><span>' + escapeHtml(al.faixa_atual) + '</span></div>' +
            '<div class="ficha-aluno__linha"><span>Faixa pretendida</span><span>' + escapeHtml(al.faixa_pretendida) + '</span></div>' +
            (al.turma ? '<div class="ficha-aluno__linha"><span>Turma</span><span>' + escapeHtml(al.turma) + '</span></div>' : '') +
            '<div class="ficha-aluno__linha"><span>Unidade</span><span>' + escapeHtml(al.unidade_nome) + '</span></div>' +
            '<div class="ficha-aluno__linha"><span>Valor da inscrição</span><span>' + escapeHtml(valorTexto) + '</span></div>';

          document.getElementById('fichaAlunoId').value = al.id;
          voltarPasso('passoFicha');
        })
        .catch(function () {
          alert('Erro ao carregar a ficha do aluno.');
        });
    }

    // ── Visitante (inscrição válida só para o evento escolhido, não vira aluno da unidade) ──

    var DELEGACOES_TORNEIO = <?php echo json_encode(array_map(function ($d) {
        return ['id' => (int) $d['id'], 'nome' => $d['nome']];
    }, $delegacoes_torneio ?? [])); ?>;
    var CATEGORIAS_TORNEIO = <?php echo json_encode(array_map(function ($c) {
        $vagas = $c['limite_atletas'] ? max(0, (int) $c['limite_atletas'] - (int) $c['total_pagos']) : null;
        $sexo_labels = ['masculino' => 'Masc', 'feminino' => 'Fem', 'unissex' => 'Masc/Fem'];
        return [
            'id' => (int) $c['id'],
            'nome' => $c['nome'],
            'sexo' => $sexo_labels[$c['sexo']] ?? $c['sexo'],
            'ano_min' => $c['ano_nascimento_min'],
            'ano_max' => $c['ano_nascimento_max'],
            'vagas' => $vagas,
            'faixas' => trim((string) ($c['faixas'] ?? '')),
            'peso_max' => $c['peso_max'] !== null && $c['peso_max'] !== '' ? (float) $c['peso_max'] : null,
        ];
    }, $categorias_torneio ?? [])); ?>;
    var FAIXAS_UNIDADE = <?php echo json_encode(array_values($faixas_unidade ?? [])); ?>;
    var TURMAS_DELEGACOES = <?php echo json_encode((object) ($turmas_delegacoes ?? [])); ?>;
    var TURMAS_TORNEIO = <?php echo json_encode(array_map(function ($t) {
        return ['id' => (int) $t['id'], 'nome' => $t['nome'] . ($t['horario'] ? ' - ' . substr($t['horario'], 0, 5) : '')];
    }, $turmas_torneio ?? [])); ?>;

    // ── Categoria em cascata: 1) categoria (nome/sexo/anos + vagas) → 2) faixa → 3) peso ──
    // Cada linha de competicao_categorias é uma combinação nome+sexo+anos+faixa+peso; o select
    // final (#visCategoria / #torCategoria) recebe o id dessa linha, como antes.
    function grupoCategoriaKey(c) {
      return [c.nome, c.sexo, c.ano_min || '', c.ano_max || ''].join('|');
    }
    function somaVagas(lista) {
      if (lista.some(function (c) { return c.vagas === null; })) return null; // alguma sem limite
      return lista.reduce(function (t, c) { return t + c.vagas; }, 0);
    }
    function textoVagas(v) {
      if (v === null) return '';
      return v > 0 ? v + (v === 1 ? ' vaga disponível' : ' vagas disponíveis') : 'Esgotada';
    }
    // A categoria aceita a faixa do atleta? (faixas vazia/"livre" aceita todas; senão compara pelo nome da cor)
    function categoriaAceitaFaixa(c, faixaAtleta) {
      var f = (c.faixas || '').toLowerCase();
      if (!f || f.indexOf('livre') !== -1 || f.indexOf('todas') !== -1) return true;
      var cor = String(faixaAtleta || '').split(' - ')[0].trim().toLowerCase();
      return cor !== '' && f.indexOf(cor) !== -1;
    }
    function labelPesoCat(c) {
      return c.peso_max ? 'Até ' + String(c.peso_max).replace('.', ',') + ' kg' : 'Peso livre';
    }
    function opcoesSelect(itens, placeholder) {
      return '<option value="">' + placeholder + '</option>' + itens.map(function (it) {
        return '<option value="' + escapeHtml(String(it.valor)) + '"' + (it.desabilitado ? ' disabled' : '') + '>' + escapeHtml(it.texto) + '</option>';
      }).join('');
    }
    function selecionarUnicaOpcao(sel) {
      var validas = Array.prototype.filter.call(sel.options, function (o) { return o.value && !o.disabled; });
      if (validas.length === 1) {
        sel.value = validas[0].value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    // Monta os 3 selects. prefixo 'vis' (visitante) ou 'tor' (aluno); nomeFinal = atributo name do select final.
    function htmlCategoriaCascata(prefixo, nomeFinal, nomeFaixa, faixaAtual) {
      if (!CATEGORIAS_TORNEIO.length) return '';
      var grupos = {};
      var ordem = [];
      CATEGORIAS_TORNEIO.forEach(function (c) {
        var k = grupoCategoriaKey(c);
        if (!grupos[k]) { grupos[k] = []; ordem.push(k); }
        grupos[k].push(c);
      });
      var itens = ordem.map(function (k) {
        var base = grupos[k][0];
        var partes = [base.nome];
        if (base.sexo) partes.push(base.sexo);
        if (base.ano_min && base.ano_max) partes.push(base.ano_min + ' a ' + base.ano_max);
        else if (base.ano_min || base.ano_max) partes.push(base.ano_min || base.ano_max);
        var v = somaVagas(grupos[k]);
        if (v !== null) partes.push(textoVagas(v));
        return { valor: k, texto: partes.join(' - '), desabilitado: v === 0 };
      });
      return '<div class="form-group"><label for="' + prefixo + 'CatGrupo">Categoria do atleta</label>' +
          '<select id="' + prefixo + 'CatGrupo" data-cascata="' + prefixo + '" required>' + opcoesSelect(itens, 'Selecione a categoria do atleta') + '</select></div>' +
        '<div class="form-group" id="' + prefixo + 'CatFaixaGrupo" style="display:none;"><label for="' + prefixo + 'CatFaixa">Faixa do atleta</label>' +
          '<select id="' + prefixo + 'CatFaixa" data-cascata="' + prefixo + '"' + (nomeFaixa ? ' name="' + nomeFaixa + '"' : '') +
          ' data-faixa-atual="' + escapeHtml(faixaAtual || '') + '"></select></div>' +
        '<div class="form-group" id="' + prefixo + 'CategoriaGrupo" style="display:none;"><label for="' + prefixo + 'Categoria">Peso do atleta</label>' +
          '<select id="' + prefixo + 'Categoria"' + (nomeFinal ? ' name="' + nomeFinal + '"' : '') + ' required></select></div>';
    }

    document.addEventListener('change', function (ev) {
      var alvo = ev.target;
      var prefixo = alvo && alvo.getAttribute && alvo.getAttribute('data-cascata');
      if (!prefixo) return;
      var selGrupo = document.getElementById(prefixo + 'CatGrupo');
      var selFaixa = document.getElementById(prefixo + 'CatFaixa');
      var selPeso = document.getElementById(prefixo + 'Categoria');
      var boxFaixa = document.getElementById(prefixo + 'CatFaixaGrupo');
      var boxPeso = document.getElementById(prefixo + 'CategoriaGrupo');
      var doGrupo = CATEGORIAS_TORNEIO.filter(function (c) { return grupoCategoriaKey(c) === selGrupo.value; });

      if (alvo === selGrupo) {
        selPeso.innerHTML = '';
        boxPeso.style.display = 'none';
        if (!selGrupo.value) { boxFaixa.style.display = 'none'; selFaixa.innerHTML = ''; selFaixa.required = false; return; }
        // Faixa do cadastro pode vir como "Nome - 2" (nome + grau): compara só pelo nome
        var anterior = (selFaixa.value || selFaixa.getAttribute('data-faixa-atual') || '').replace(/\s+-\s+\d+\s*$/, '');
        var lista = FAIXAS_UNIDADE.slice();
        if (anterior && lista.indexOf(anterior) === -1) lista.unshift(anterior); // faixa do aluno fora da lista atual
        selFaixa.innerHTML = opcoesSelect(lista.map(function (f) { return { valor: f, texto: f }; }), 'Selecione a faixa do atleta');
        selFaixa.required = true;
        boxFaixa.style.display = '';
        if (anterior) {
          selFaixa.value = anterior;
          selFaixa.dispatchEvent(new Event('change', { bubbles: true }));
        }
        return;
      }

      if (alvo === selFaixa) {
        if (!selFaixa.value) { boxPeso.style.display = 'none'; selPeso.innerHTML = ''; return; }
        var pesos = doGrupo.filter(function (c) { return categoriaAceitaFaixa(c, selFaixa.value); });
        if (!pesos.length) pesos = doGrupo.slice(); // nenhuma bate pela cor: mostra todas da categoria
        pesos = pesos.sort(function (a, b) { return (a.peso_max || 9999) - (b.peso_max || 9999); });
        selPeso.innerHTML = opcoesSelect(pesos.map(function (c) {
          return { valor: c.id, texto: labelPesoCat(c) + (c.vagas !== null ? ' - ' + textoVagas(c.vagas) : ''), desabilitado: c.vagas === 0 };
        }), 'Selecione o peso do atleta');
        boxPeso.style.display = '';
        selecionarUnicaOpcao(selPeso);
      }
    });

    // Ao escolher a delegação, lista as turmas/professores cadastrados nela
    document.addEventListener('change', function (ev) {
      if (!ev.target || ev.target.id !== 'visDelegacao') return;
      var sel = document.getElementById('visTurmaDelegacao');
      if (!sel) return;
      var lista = TURMAS_DELEGACOES[ev.target.value] || [];
      if (!ev.target.value) {
        sel.innerHTML = '<option value="">Selecione primeiro a delegação</option>';
        sel.disabled = true;
        sel.required = false;
      } else if (!lista.length) {
        sel.innerHTML = '<option value="">Nenhuma turma/professor cadastrado nesta delegação</option>';
        sel.disabled = true;
        sel.required = false;
      } else {
        sel.innerHTML = '<option value="">Selecione a turma / professor</option>' + lista.map(function (t) {
          return '<option value="' + t.id + '">' + escapeHtml(t.nome) + '</option>';
        }).join('');
        sel.disabled = false;
        sel.required = true;
      }
    });

    function montarFormVisitante(tipo, eventoId, cpfDigits, incluirFaixa) {
      var cpfFormatado = cpfDigits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
      var faixaCampo = incluirFaixa
        ? '<div class="form-group"><label for="visFaixa">Faixa atual do atleta (se praticante)</label>' +
          '<input type="text" id="visFaixa" name="faixa" placeholder="Ex: Branca, Azul..."></div>'
        : '';

      var delegacaoCampo = '';
      var categoriaCampo = '';
      var turmaCampo = '';
      if (tipo === 'torneio') {
        if (DELEGACOES_TORNEIO.length) {
          var opcoesDeleg = DELEGACOES_TORNEIO.map(function (d) {
            return '<option value="' + d.id + '">' + escapeHtml(d.nome) + '</option>';
          }).join('');
          delegacaoCampo = '<div class="form-group"><label for="visDelegacao">Delegação / Academia</label>' +
            '<select id="visDelegacao" name="delegacao_id" required><option value="">Selecione sua delegação</option>' +
            opcoesDeleg + '</select></div>';
        }

        categoriaCampo = htmlCategoriaCascata('vis', 'categoria_id', 'faixa', '');

        if (DELEGACOES_TORNEIO.length) {
          // Turma / professor vem da delegação escolhida (preenchido no change de #visDelegacao)
          turmaCampo = '<div class="form-group" id="grupoTurmaDelegacao"><label for="visTurmaDelegacao">Turma / Professor</label>' +
            '<select id="visTurmaDelegacao" name="delegacao_turma_id" disabled><option value="">Selecione primeiro a delegação</option></select></div>';
        } else if (TURMAS_TORNEIO.length) {
          var opcoesTurmaTor = TURMAS_TORNEIO.map(function (t) {
            return '<option value="' + t.id + '">' + escapeHtml(t.nome) + '</option>';
          }).join('');
          turmaCampo = '<div class="form-group"><label for="visTurmaTorneio">Turma do atleta</label>' +
            '<select id="visTurmaTorneio" name="turma_id"><option value="">Selecione a turma do atleta</option>' +
            opcoesTurmaTor + '</select></div>';
        }
      }

      return (
        '<h3>Complete seu cadastro</h3>' +
        '<p class="sub">Essa inscrição vale apenas para este evento.</p>' +
        '<div class="form-group"><label>CPF do responsável</label><input type="text" value="' + escapeHtml(cpfFormatado) + '" disabled></div>' +
        '<form action="inscrever_visitante.php" method="POST">' +
        '<input type="hidden" name="tipo" value="' + escapeHtml(tipo) + '">' +
        '<input type="hidden" name="id" value="' + eventoId + '">' +
        '<input type="hidden" name="evento_id" value="' + eventoId + '">' +
        '<input type="hidden" name="cpf" value="' + escapeHtml(cpfDigits) + '">' +
        '<div class="form-group"><label for="visTelefone">WhatsApp do responsável</label>' +
        '<input type="tel" id="visTelefone" name="telefone" required placeholder="(45) 99999-9999"></div>' +
        '<div class="form-group"><label for="visEmail">E-mail do responsável</label>' +
        '<input type="email" id="visEmail" name="email" placeholder="seu@email.com"></div>' +
        '<div class="form-group"><label for="visNome">Nome completo do atleta</label>' +
        '<input type="text" id="visNome" name="nome" required placeholder="Nome do atleta"></div>' +
        '<div class="form-group"><label for="visNascimento">Ano de nascimento do atleta</label>' +
        '<input type="text" id="visNascimento" name="ano_nascimento" inputmode="numeric" maxlength="4" pattern="(19|20)[0-9]{2}" placeholder="Ex: 2019" required title="Informe o ano com 4 dígitos, ex: 2019" oninput="this.value=this.value.replace(/[^0-9]/g,\'\').slice(0,4)"></div>' +
        faixaCampo +
        delegacaoCampo +
        turmaCampo +
        categoriaCampo +
        '<button type="submit" class="btn btn--dark btn--lg">Confirmar inscrição' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7" /></svg>' +
        '</button>' +
        '</form>'
      );
    }

    // ── Visitante do exame (CPF não encontrado entre os alunos): cadastro completo de
    // aluno, marcado com o rótulo "visitante", numa turma real liberada para este exame ──
    var TURMAS_VISITANTE_EXAME = <?php echo json_encode(array_map(function ($t) {
        return ['id' => (int) $t['id'], 'nome' => $t['nome']];
    }, $turmas_visitante_options ?? [])); ?>;

    function montarFormVisitanteExame(eventoId, cpfDigits) {
      var cpfFormatado = cpfDigits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');

      if (!TURMAS_VISITANTE_EXAME.length) {
        return (
          '<h3>Inscrição indisponível</h3>' +
          '<div class="form-alert form-alert--erro">' +
          '<i class="fa-solid fa-circle-exclamation"></i> Nenhuma turma deste exame está liberada para inscrição de visitante no momento. Entre em contato com a unidade organizadora.' +
          '</div>'
        );
      }

      var opcoesTurma = TURMAS_VISITANTE_EXAME.map(function (t) {
        return '<option value="' + t.id + '">' + escapeHtml(t.nome) + '</option>';
      }).join('');

      return (
        '<h3>Complete seu cadastro</h3>' +
        '<p class="sub">Você será cadastrado como aluno visitante, apenas para este exame.</p>' +
        '<div class="form-group"><label>CPF do responsável</label><input type="text" value="' + escapeHtml(cpfFormatado) + '" disabled></div>' +
        '<form action="inscrever_exame_visitante.php" method="POST">' +
        '<input type="hidden" name="evento_id" value="' + eventoId + '">' +
        '<input type="hidden" name="cpf" value="' + escapeHtml(cpfDigits) + '">' +
        '<div class="form-group"><label for="visTelefone">WhatsApp do responsável</label>' +
        '<input type="tel" id="visTelefone" name="telefone" required placeholder="(45) 99999-9999"></div>' +
        '<div class="form-group"><label for="visEmail">E-mail do responsável</label>' +
        '<input type="email" id="visEmail" name="email" placeholder="seu@email.com"></div>' +
        '<div class="form-group"><label for="visNome">Nome completo do atleta</label>' +
        '<input type="text" id="visNome" name="nome_completo" required placeholder="Nome do atleta"></div>' +
        '<div class="form-group"><label for="visNascimento">Data de nascimento do atleta</label>' +
        '<input type="date" id="visNascimento" name="data_nascimento"></div>' +
        '<div class="form-group"><label for="visGenero">Gênero</label>' +
        '<select id="visGenero" name="genero"><option value="masculino">Masculino</option><option value="feminino">Feminino</option><option value="outro">Outro</option></select></div>' +
        '<div class="form-group"><label for="visTurma">Turma do atleta</label>' +
        '<select id="visTurma" name="turma_id" required><option value="">Selecione a turma</option>' + opcoesTurma + '</select></div>' +
        '<div class="form-group"><label for="visTamanhoFaixa">Tamanho da faixa (ex: M3, A2)</label>' +
        '<input type="text" id="visTamanhoFaixa" name="tamanho_faixa" placeholder="Ex: M3, A2"></div>' +
        '<button type="submit" class="btn btn--dark btn--lg">Confirmar inscrição de visitante' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7" /></svg>' +
        '</button>' +
        '</form>'
      );
    }

    function mostrarVisitanteExame(cpf) {
      var wizard = document.getElementById('wizardExame');
      var eventoId = wizard.getAttribute('data-evento-id');
      var digits = cpf.replace(/\D/g, '');

      document.getElementById('passoVisitanteExame').innerHTML = montarFormVisitanteExame(eventoId, digits) +
        '<button type="button" class="btn btn--dark-outline" onclick="voltarPasso(\'passoCpf\')" style="width:100%; justify-content:center; margin-top:8px;">Voltar</button>';
      voltarPasso('passoVisitanteExame');
    }

    function verificarCpfVisitante() {
      var wizard = document.getElementById('wizardVisitante');
      var tipo = wizard.getAttribute('data-tipo');
      var eventoId = wizard.getAttribute('data-evento-id');
      var cpfInput = document.getElementById('cpfVisitante');
      var msg = document.getElementById('cpfVisitanteMsg');
      msg.style.display = 'none';

      var digits = cpfInput.value.replace(/\D/g, '');
      if (!cpfValido(digits)) {
        msg.textContent = digits.length !== 11 ? 'Digite os 11 números do CPF.' : 'CPF inválido. Confira os números digitados.';
        msg.style.display = 'block';
        return;
      }

      // Torneio segue o mesmo fluxo do visitante: digita o CPF do responsável e preenche
      // os dados do atleta (sem listar os alunos já cadastrados com esse CPF).
      continuarComoVisitante(tipo, eventoId, digits, cpfInput.value, msg);
    }

    function continuarComoVisitante(tipo, eventoId, digits, cpfOriginal, msg) {
      var fd = new FormData();
      fd.append('cpf', cpfOriginal);
      fd.append('tipo', tipo);
      fd.append('evento_id', eventoId);

      fetch('api_visitante_cpf.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = data.message || 'Não foi possível continuar com esse CPF.';
            msg.style.display = 'block';
            return;
          }
          document.getElementById('passoVisitanteForm').innerHTML = montarFormVisitante(tipo, eventoId, digits, tipo === 'exame');
          document.getElementById('passoVisitanteCpf').style.display = 'none';
          document.getElementById('passoVisitanteForm').style.display = 'block';
        })
        .catch(function () {
          msg.textContent = 'Erro ao verificar o CPF. Tente novamente.';
          msg.style.display = 'block';
        });
    }

    // ── Torneio: CPF encontrado entre os alunos da unidade -> escolhe o atleta -> categoria ──

    var ALUNOS_TORNEIO_CACHE = [];

    function voltarCpfTorneio() {
      document.getElementById('passoVisitanteForm').style.display = 'none';
      document.getElementById('passoVisitanteCpf').style.display = 'block';
    }

    function renderAlunosTorneio(eventoId) {
      var html = '<h3>Selecione o atleta</h3>' +
        '<p class="sub">Alunos encontrados para este CPF nesta unidade.</p>' +
        '<div id="listaAlunosTorneio" class="lista-alunos"></div>' +
        '<button type="button" class="btn btn--dark-outline" onclick="voltarCpfTorneio()" style="width:100%; justify-content:center; margin-top:8px;">Voltar</button>';

      document.getElementById('passoVisitanteForm').innerHTML = html;

      var lista = document.getElementById('listaAlunosTorneio');
      ALUNOS_TORNEIO_CACHE.forEach(function (al) {
        var card = document.createElement('div');
        card.className = 'aluno-card' + (al.ja_inscrito ? ' is-disabled' : '');

        var avatar = al.foto
          ? '<img src="' + escapeHtml(al.foto) + '" alt="">'
          : escapeHtml(iniciais(al.nome));

        card.innerHTML =
          '<div class="aluno-card__avatar">' + avatar + '</div>' +
          '<div class="aluno-card__info">' +
          '<strong>' + escapeHtml(al.nome) + '</strong>' +
          '<span>Faixa ' + escapeHtml(al.faixa) + (al.turmas ? ' · ' + escapeHtml(al.turmas) : '') + (al.ja_inscrito ? ' · Já inscrito' : '') + '</span>' +
          '</div>';

        if (!al.ja_inscrito) {
          card.addEventListener('click', function () { selecionarAlunoTorneio(al.id, al.nome, eventoId); });
        }
        lista.appendChild(card);
      });
    }

    function selecionarAlunoTorneio(alunoId, nome, eventoId) {
      var alCache = ALUNOS_TORNEIO_CACHE.filter(function (a) { return a.id === alunoId; })[0];
      var categoriaCampo = htmlCategoriaCascata('tor', null, null, alCache ? alCache.faixa : '');

      var html = '<h3>Confirme a inscrição</h3>' +
        '<div class="form-group"><label>Atleta</label><input type="text" value="' + escapeHtml(nome) + '" disabled></div>' +
        categoriaCampo +
        '<div id="torneioConfirmMsg" class="form-alert form-alert--erro" style="display:none;"></div>' +
        '<button type="button" class="btn btn--dark btn--lg" onclick="confirmarInscricaoTorneio(' + alunoId + ', \'' + eventoId + '\')">Confirmar inscrição' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7" /></svg>' +
        '</button>' +
        '<button type="button" class="btn btn--dark-outline" onclick="renderAlunosTorneio(\'' + eventoId + '\')" style="width:100%; justify-content:center; margin-top:8px;">Voltar</button>';

      document.getElementById('passoVisitanteForm').innerHTML = html;
    }

    function confirmarInscricaoTorneio(alunoId, eventoId) {
      var msg = document.getElementById('torneioConfirmMsg');
      var catSelect = document.getElementById('torCategoria');
      if (catSelect && !catSelect.value) {
        msg.textContent = 'Selecione a categoria, a faixa e o peso.';
        msg.style.display = 'block';
        return;
      }
      var faixaSelect = document.getElementById('torCatFaixa');

      var fd = new FormData();
      fd.append('ajax', '1');
      fd.append('evento_id', eventoId);
      fd.append('aluno_id', alunoId);
      if (catSelect) fd.append('categoria_id', catSelect.value);
      if (faixaSelect && faixaSelect.value) fd.append('faixa', faixaSelect.value);

      fetch('inscrever_torneio_aluno.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = data.message || 'Não foi possível confirmar a inscrição.';
            msg.style.display = 'block';
            return;
          }
          // Marca esse aluno como já inscrito na lista em cache e volta pra ela,
          // permitindo inscrever outro aluno do mesmo CPF sem digitar tudo de novo.
          ALUNOS_TORNEIO_CACHE.forEach(function (al) {
            if (al.id === alunoId) al.ja_inscrito = true;
          });
          renderAlunosTorneio(eventoId);
          var lista = document.getElementById('listaAlunosTorneio');
          var aviso = document.createElement('div');
          aviso.className = 'form-alert form-alert--ok';
          aviso.style.marginBottom = '12px';
          aviso.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + escapeHtml(data.nome || 'Aluno') + ' inscrito com sucesso!';
          if (data.link_pagamento) {
            aviso.innerHTML += '<br><a href="' + escapeHtml(data.link_pagamento) + '" style="font-weight:800; text-decoration:underline;">' +
              'Pagar inscrição via PIX (R$ ' + Number(data.valor).toFixed(2).replace('.', ',') + ')</a>';
          }
          lista.parentNode.insertBefore(aviso, lista);
        })
        .catch(function () {
          msg.textContent = 'Erro ao confirmar. Tente novamente.';
          msg.style.display = 'block';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
      var cpfVisitanteInput = document.getElementById('cpfVisitante');
      if (cpfVisitanteInput) {
        cpfVisitanteInput.addEventListener('input', function () { mascaraCpf(cpfVisitanteInput); });
      }
    });
  </script>
</body>

</html>
