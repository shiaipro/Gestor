<?php
/**
 * Página Pública de Inscrição
 * URL amigável: shiaipro.com.br/SLUG/inscricao
 * Este arquivo é chamado via .htaccess: /SLUG/inscricao → /inscricao.php?slug=SLUG&t=UUID
 *
 * Também pode ser acessado diretamente via:
 *   /inscricao.php?slug=SLUG&t=UUID
 *
 * Quando só o slug é informado (sem turma), exibe uma página de seleção
 * com as turmas ativas da unidade para o aluno escolher a dele.
 */

require_once 'Gestor/config.php';

// Impede que o navegador restaure esta página do cache (bfcache) ao clicar em "Voltar",
// o que reexibiria o formulário com os campos e a turma/academia já preenchidos.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * Envia um e-mail usando o SMTP configurado em Configurações do Gestor (unidades.smtp_*),
 * com fallback para a função mail() nativa quando a unidade não tem SMTP configurado.
 */
function enviarEmailSmtpOuNativo($smtp, $to, $assunto, $corpo) {
    $from_email = $smtp['smtp_user'] ?: 'contato@shiaipro.com.br';
    $from_nome  = $smtp['unidade_nome'] ?: 'SHIAI PRO';

    if (!empty($smtp['smtp_host'])) {
        enviarEmailSmtp($smtp, $to, $assunto, $corpo, $from_email, $from_nome);
        return;
    }

    if (function_exists('mail')) {
        $headers = "From: $from_nome <$from_email>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8";
        @mail($to, $assunto, $corpo, $headers);
    }
}

/**
 * Monta o HTML de e-mail com a identidade visual do SHIAI PRO (mesma paleta azul-marinho/dourado
 * usada em inscricao.php), a partir de um título e uma lista de parágrafos/linhas de conteúdo.
 */
function montarEmailHtml($titulo, $subtitulo, $linhas) {
    $html = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0; padding:0; background:#f1f5f9; font-family: Arial, Helvetica, sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:30px 15px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#ffffff; border-radius:4px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">'
        . '<tr><td style="height:5px; background:#ffc107; line-height:5px; font-size:0;">&nbsp;</td></tr>'
        . '<tr><td style="background:#08153a; padding:30px 35px;">'
        . '<div style="color:#ffffff; font-size:20px; font-weight:900; text-transform:uppercase; letter-spacing:0.03em;">' . htmlspecialchars($titulo) . '</div>'
        . '<div style="color:#94a3b8; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; margin-top:6px;">' . htmlspecialchars($subtitulo) . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:35px; color:#1e293b; font-size:14px; line-height:1.7;">';

    foreach ($linhas as $linha) {
        $html .= '<p style="margin:0 0 16px 0;">' . $linha . '</p>';
    }

    $html .= '</td></tr>'
        . '<tr><td style="padding:20px 35px; background:#fafafa; border-top:1px solid #e2e8f0; text-align:center;">'
        . '<div style="color:#94a3b8; font-size:10px; font-weight:900; letter-spacing:0.15em; text-transform:uppercase;">Powered by SHIAI PRO</div>'
        . '</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';

    return $html;
}

/**
 * Cliente SMTP simples via socket (sem dependência externa) — suporta SSL implícito (porta 465)
 * e STARTTLS (porta 587), com autenticação AUTH LOGIN.
 */
function enviarEmailSmtp($smtp, $to, $assunto, $corpo, $from_email, $from_nome) {
    $host   = $smtp['smtp_host'];
    $port   = (int)($smtp['smtp_port'] ?: 587);
    $user   = $smtp['smtp_user'] ?? '';
    $pass   = $smtp['smtp_pass'] ?? '';
    $secure = strtolower($smtp['smtp_secure'] ?? 'tls');

    $transporte = ($secure === 'ssl') ? 'ssl://' : '';
    $conn = @stream_socket_client("$transporte$host:$port", $errno, $errstr, 10);
    if (!$conn) {
        return false;
    }
    stream_set_timeout($conn, 10);

    $ler = function () use ($conn) {
        $resposta = '';
        while (($linha = fgets($conn, 515)) !== false) {
            $resposta .= $linha;
            if (isset($linha[3]) && $linha[3] === ' ') break;
        }
        return $resposta;
    };
    $enviar = function ($cmd) use ($conn) {
        fwrite($conn, $cmd . "\r\n");
    };

    $ler(); // saudação do servidor
    $enviar("EHLO shiaipro.com.br");
    $ler();

    if ($secure === 'tls') {
        $enviar("STARTTLS");
        $ler();
        stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $enviar("EHLO shiaipro.com.br");
        $ler();
    }

    if ($user) {
        $enviar("AUTH LOGIN");
        $ler();
        $enviar(base64_encode($user));
        $ler();
        $enviar(base64_encode($pass));
        $ler();
    }

    $enviar("MAIL FROM:<$from_email>");
    $ler();
    $enviar("RCPT TO:<$to>");
    $ler();
    $enviar("DATA");
    $ler();

    $headers = "From: =?UTF-8?B?" . base64_encode($from_nome) . "?= <$from_email>\r\n"
        . "To: <$to>\r\n"
        . "Subject: =?UTF-8?B?" . base64_encode($assunto) . "?=\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "MIME-Version: 1.0\r\n";
    $corpo_escapado = str_replace("\n.", "\n..", $corpo);
    $enviar($headers . "\r\n" . $corpo_escapado . "\r\n.");
    $ler();

    $enviar("QUIT");
    fclose($conn);
    return true;
}

/**
 * Envia o e-mail de confirmação para o inscrito e a notificação para o administrador da unidade,
 * usando o SMTP cadastrado em Configurações (Gestor/unidade/configuracoes.php) quando disponível.
 */
function enviarEmailsInscricao($turma, $aluno_id, $nome_completo, $responsavel_nome, $responsavel_email, $responsavel_telefone) {
    global $pdo;

    $unidade_nome = $turma['unidade_nome'] ?? 'SHIAI PRO';

    $stmt_smtp = $pdo->prepare("SELECT email_contato, smtp_host, smtp_user, smtp_pass, smtp_port, smtp_secure FROM unidades WHERE id = ?");
    $stmt_smtp->execute([$turma['unidade_id']]);
    $config_unidade = $stmt_smtp->fetch();
    $config_unidade['unidade_nome'] = $unidade_nome;

    $nome_completo_esc    = htmlspecialchars($nome_completo);
    $responsavel_nome_esc = htmlspecialchars($responsavel_nome);
    $turma_nome_esc       = htmlspecialchars($turma['nome']);
    $unidade_nome_esc     = htmlspecialchars($unidade_nome);

    // E-mail para o inscrito/responsável
    if ($responsavel_email) {
        $assunto_inscrito = "Inscrição confirmada - $unidade_nome";
        $corpo_inscrito = montarEmailHtml($unidade_nome, 'Inscrição confirmada', [
            "Olá <strong>{$responsavel_nome_esc}</strong>,",
            "A inscrição de <strong>{$nome_completo_esc}</strong> na turma <strong>\"{$turma_nome_esc}\"</strong> ({$unidade_nome_esc}) foi recebida com sucesso.",
            "Em breve nossa equipe entrará em contato para os próximos passos.",
            "Equipe {$unidade_nome_esc}",
        ]);
        enviarEmailSmtpOuNativo($config_unidade, $responsavel_email, $assunto_inscrito, $corpo_inscrito);
    }

    // E-mail para o administrador da unidade
    $email_admin = $config_unidade['email_contato'] ?? null;
    if ($email_admin) {
        $assunto_admin = "Nova inscrição recebida - {$turma['nome']}";
        $corpo_admin = montarEmailHtml($unidade_nome, 'Nova inscrição recebida', [
            "Uma nova inscrição foi realizada.",
            "<strong>Aluno:</strong> {$nome_completo_esc}<br>"
                . "<strong>Turma:</strong> {$turma_nome_esc}<br>"
                . "<strong>Responsável:</strong> {$responsavel_nome_esc}<br>"
                . "<strong>Telefone:</strong> " . htmlspecialchars($responsavel_telefone) . "<br>"
                . "<strong>E-mail:</strong> " . htmlspecialchars($responsavel_email),
            "Acesse o painel para gerenciar o cadastro.",
        ]);
        enviarEmailSmtpOuNativo($config_unidade, $email_admin, $assunto_admin, $corpo_admin);
    }
}

$slug = $_GET['slug'] ?? '';
$uuid = $_GET['t'] ?? '';

if (!$slug && !$uuid) {
    http_response_code(404);
    die("Página não encontrada.");
}

// Link geral da unidade: aluno escolhe a turma
if ($slug && !$uuid) {
    $stmt_unidade = $pdo->prepare("SELECT id, nome, slug FROM unidades WHERE slug = ? AND status = 'ativo'");
    $stmt_unidade->execute([$slug]);
    $unidade_sel = $stmt_unidade->fetch();

    if (!$unidade_sel) {
        http_response_code(404);
        die("Página não encontrada.");
    }

    $stmt_turmas = $pdo->prepare("
        SELECT t.uuid_inscricao, t.nome, t.instrutor, t.horario, t.dias_semana, t.preco_mensal,
               ac.nome as academia_nome
        FROM turmas t
        LEFT JOIN academias ac ON t.academia_id = ac.id
        WHERE t.unidade_id = ? AND t.status = 'ativo' AND t.uuid_inscricao IS NOT NULL AND t.uuid_inscricao <> ''
        ORDER BY ac.nome ASC, t.nome ASC
    ");
    $stmt_turmas->execute([$unidade_sel['id']]);
    $turmas_disponiveis = $stmt_turmas->fetchAll();

    $academias_disponiveis = [];
    foreach ($turmas_disponiveis as $td) {
        $nome_ac = $td['academia_nome'] ?: 'Principal';
        if (!in_array($nome_ac, $academias_disponiveis)) {
            $academias_disponiveis[] = $nome_ac;
        }
    }

    $dias_map_sel = ['seg' => 'SEG', 'ter' => 'TER', 'qua' => 'QUA', 'qui' => 'QUI', 'sex' => 'SEX', 'sab' => 'SAB', 'dom' => 'DOM'];
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Inscrição — <?php echo htmlspecialchars($unidade_sel['nome']); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --brand: #08153a;
                --brand-gold: #ffc107;
                --primary-green: #22c55e;
                --text-dark: #1e293b;
                --text-muted: #64748b;
                --border-color: #e2e8f0;
                --bg: #f1f5f9;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Inter', sans-serif;
                background: var(--bg);
                color: var(--text-dark);
                min-height: 100vh;
                padding: 30px 20px;
            }
            .page-wrapper { width: 100%; max-width: 720px; margin: 0 auto; }
            .logo-bar { text-align: center; margin-bottom: 30px; }
            .logo-bar img { height: 42px; width: auto; }
            .card-header {
                background: var(--brand); color: #fff; padding: 35px 40px;
                text-align: center; border-top: 5px solid var(--brand-gold);
            }
            .card-header .academy-name {
                font-size: 22px; font-weight: 900; text-transform: uppercase;
                letter-spacing: 0.05em; margin-bottom: 6px;
            }
            .card-header .subtitle {
                font-size: 12px; font-weight: 600; color: #94a3b8;
                text-transform: uppercase; letter-spacing: 0.1em;
            }
            .filtro-area {
                background: #fff; padding: 30px 40px; border-bottom: 1px solid var(--border-color);
            }
            .filtro-area label {
                display: block; font-size: 11px; font-weight: 900; text-transform: uppercase;
                letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 10px;
            }
            .filtro-area select {
                width: 100%; height: 52px; border: 1.5px solid var(--border-color);
                background: #fafafa; padding: 0 15px; font-size: 14px; font-weight: 800;
                text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dark);
                font-family: 'Inter', sans-serif;
            }
            .turmas-list { background: #fff; padding: 10px 40px 35px; display: none; }
            .turmas-list.show { display: block; }
            .escolha-msg {
                text-align: center; padding: 40px 30px; color: var(--text-muted); font-weight: 700;
            }
            .turma-card {
                display: block; text-decoration: none; color: inherit;
                border: 1.5px solid var(--border-color); padding: 20px 25px;
                margin-top: 20px; transition: border-color 0.15s, transform 0.1s;
            }
            .turma-card:hover { border-color: var(--brand); transform: translateY(-1px); }
            .turma-card h3 {
                font-size: 16px; font-weight: 900; color: var(--brand);
                text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 10px;
            }
            .info-pills { display: flex; flex-wrap: wrap; gap: 8px; }
            .info-pill {
                display: flex; align-items: center; gap: 6px; background: #fafbff;
                border: 1px solid var(--border-color); padding: 6px 12px; font-size: 11px;
                font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.05em;
            }
            .info-pill i { color: var(--brand); font-size: 10px; }
            .vazio {
                text-align: center; padding: 60px 30px; color: var(--text-muted); font-weight: 700;
            }
            .powered-by {
                text-align: center; margin-top: 25px; font-size: 10px; font-weight: 900;
                letter-spacing: 0.15em; text-transform: uppercase; color: var(--text-muted); opacity: 0.5;
            }
            @media (max-width: 480px) {
                .card-header, .filtro-area, .turmas-list { padding-left: 25px; padding-right: 25px; }
            }
        </style>
    </head>
    <body>
        <script>
            // Reforço para navegadores (ex: Safari) que restauram do bfcache mesmo com Cache-Control: no-store
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) window.location.reload();
            });
        </script>
    <div class="page-wrapper">
        <div class="logo-bar">
            <img src="/Gestor/assets/img/logo.png" alt="SHIAI PRO">
        </div>

        <div class="card-header">
            <div class="academy-name"><?php echo htmlspecialchars($unidade_sel['nome']); ?></div>
            <div class="subtitle">Escolha sua turma para se inscrever</div>
        </div>

        <div class="filtro-area">
            <select id="filtroAcademia" onchange="filtrarTurmas()">
                <option value="">— Selecione a academia/escola —</option>
                <?php foreach ($academias_disponiveis as $nome_ac): ?>
                    <option value="<?php echo htmlspecialchars($nome_ac); ?>"><?php echo htmlspecialchars($nome_ac); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (empty($turmas_disponiveis)): ?>
        <div class="turmas-list show">
            <div class="vazio">No momento não há turmas disponíveis para inscrição.</div>
        </div>
        <?php else: ?>
        <div class="escolha-msg" id="escolhaMsg">Escolha uma unidade acima para ver as turmas disponíveis.</div>
        <div class="turmas-list" id="turmasList">
                <?php foreach ($turmas_disponiveis as $td):
                    $nome_ac = $td['academia_nome'] ?: 'Principal';
                    $dias_sel = explode(',', $td['dias_semana']);
                ?>
                <a class="turma-card" data-academia="<?php echo htmlspecialchars($nome_ac); ?>"
                   href="/<?php echo htmlspecialchars($slug); ?>/inscricao/<?php echo htmlspecialchars($td['uuid_inscricao']); ?>">
                    <h3><?php echo htmlspecialchars($td['nome']); ?></h3>
                    <div class="info-pills">
                        <div class="info-pill"><i class="fa-solid fa-location-dot"></i><?php echo htmlspecialchars($nome_ac); ?></div>
                        <?php if ($td['instrutor']): ?>
                        <div class="info-pill"><i class="fa-solid fa-user-tie"></i><?php echo htmlspecialchars($td['instrutor']); ?></div>
                        <?php endif; ?>
                        <div class="info-pill"><i class="fa-regular fa-clock"></i><?php echo date('H:i', strtotime($td['horario'])); ?></div>
                        <div class="info-pill">
                            <?php foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $dia_cod): if (in_array($dia_cod, $dias_sel)): ?>
                                <?php echo $dias_map_sel[$dia_cod]; ?>&nbsp;
                            <?php endif; endforeach; ?>
                        </div>
                        <?php if ($td['preco_mensal'] > 0): ?>
                        <div class="info-pill"><i class="fa-solid fa-tag"></i>R$ <?php echo number_format($td['preco_mensal'], 2, ',', '.'); ?>/mês</div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="powered-by">Powered by SHIAI PRO</div>
    </div>

    <script>
    function filtrarTurmas() {
        var filtro = document.getElementById('filtroAcademia').value;
        var lista = document.getElementById('turmasList');
        var msg = document.getElementById('escolhaMsg');
        if (!filtro) {
            if (lista) lista.classList.remove('show');
            if (msg) msg.style.display = 'block';
            return;
        }
        if (msg) msg.style.display = 'none';
        if (lista) lista.classList.add('show');
        document.querySelectorAll('.turma-card').forEach(function(card) {
            card.style.display = (card.dataset.academia === filtro) ? 'block' : 'none';
        });
    }
    </script>
    </body>
    </html>
    <?php
    exit;
}

// Buscar a turma com informações da unidade (pelo slug da unidade + uuid da turma)
if ($slug && $uuid) {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               ac.nome as academia_nome, 
               un.nome as unidade_nome,
               un.slug as unidade_slug,
               un.id as unidade_id_real
        FROM turmas t
        LEFT JOIN academias ac ON t.academia_id = ac.id
        INNER JOIN unidades un ON t.unidade_id = un.id
        WHERE t.uuid_inscricao = ? AND un.slug = ? AND t.status = 'ativo' AND un.status = 'ativo'
    ");
    $stmt->execute([$uuid, $slug]);
} elseif ($uuid) {
    // Fallback: apenas pelo UUID (compatibilidade com links antigos)
    $stmt = $pdo->prepare("
        SELECT t.*, 
               ac.nome as academia_nome, 
               un.nome as unidade_nome,
               un.slug as unidade_slug,
               un.id as unidade_id_real
        FROM turmas t
        LEFT JOIN academias ac ON t.academia_id = ac.id
        INNER JOIN unidades un ON t.unidade_id = un.id
        WHERE t.uuid_inscricao = ? AND t.status = 'ativo'
    ");
    $stmt->execute([$uuid]);
} else {
    http_response_code(404);
    die("Turma não identificada.");
}

$turma = $stmt->fetch();

if (!$turma) {
    http_response_code(404);
    die("Turma não encontrada ou inativa.");
}

$erro = '';
$sucesso = '';

// URL absoluta (caminho amigável /SLUG/inscricao/UUID) usada no redirect (PRG) após salvar
if ($slug && $uuid) {
    $url_inscricao = '/' . $slug . '/inscricao/' . $uuid;
} elseif ($slug) {
    $url_inscricao = '/' . $slug . '/inscricao';
} else {
    $url_inscricao = '/inscricao.php?' . http_build_query(['t' => $uuid]);
}

// URL usada no botão "Fazer Nova Inscrição": sempre a tela de seleção de turma/academia
// zerada (sem UUID fixo), para o próximo cadastro poder escolher a turma do zero.
$url_nova_inscricao = $slug ? ('/' . $slug . '/inscricao') : $url_inscricao;

// URL opcional de retorno (ex: voltar para a ficha de um evento após o cadastro).
// Só aceita caminhos internos relativos (começando com "/") para evitar open redirect.
$voltar_url = $_POST['voltar'] ?? $_GET['voltar'] ?? '';
if ($voltar_url !== '' && substr($voltar_url, 0, 1) !== '/') {
    $voltar_url = '';
}
if ($voltar_url !== '') {
    $url_inscricao .= (strpos($url_inscricao, '?') !== false ? '&' : '?') . 'voltar=' . urlencode($voltar_url);
}

if (isset($_GET['inscrito']) && $_GET['inscrito'] === '1') {
    $sucesso = "Inscrição realizada com sucesso! Seja bem-vindo(a) — em breve nossa equipe entrará em contato.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_completo    = trim($_POST['nome_completo'] ?? '');
    $cpf              = '';
    $rg               = '';
    $data_nascimento  = $_POST['data_nascimento'] ?: null;
    $genero           = $_POST['genero'] ?: null;

    $responsavel_nome       = trim($_POST['responsavel_nome'] ?? '');
    $responsavel_parentesco = trim($_POST['responsavel_parentesco'] ?? '');
    $responsavel_cpf        = trim($_POST['responsavel_cpf'] ?? '');
    $responsavel_telefone   = trim($_POST['responsavel_telefone'] ?? '');
    $responsavel_email      = trim($_POST['responsavel_email'] ?? '');

    // Dados do aluno (email/telefone) são preenchidos com os do responsável
    $email_pessoal = $responsavel_email;
    $telefone      = $responsavel_telefone;

    $cep                  = '';
    $endereco             = '';
    $endereco_numero      = '';
    $endereco_complemento = '';
    $bairro               = '';
    $cidade               = '';
    $estado               = '';

    if ($nome_completo && $data_nascimento && $genero && $responsavel_nome && $responsavel_parentesco
        && $responsavel_cpf && $responsavel_telefone && $responsavel_email) {
        try {
            $pdo->beginTransaction();

            // Verificar se o aluno já existe: precisa bater o NOME do aluno junto com CPF, e-mail ou telefone
            // (o e-mail/telefone salvos são os do responsável, então checar só isso causaria falso positivo
            // entre irmãos diferentes que usam o mesmo responsável/contato)
            $stmt_check = $pdo->prepare("
                SELECT id FROM alunos
                WHERE unidade_id = ?
                  AND LOWER(nome_completo) = LOWER(?)
                  AND (
                      (email_pessoal = ? AND email_pessoal != '')
                      OR (cpf = ? AND cpf != '')
                      OR (telefone = ? AND telefone != '')
                  )
                LIMIT 1
            ");
            $stmt_check->execute([$turma['unidade_id'], $nome_completo, $email_pessoal, $cpf, $telefone]);
            $aluno_existente = $stmt_check->fetch();

            if ($aluno_existente) {
                $aluno_id = $aluno_existente['id'];
            } else {
                // Criar novo aluno
                $sql_aluno = "INSERT INTO alunos (
                    unidade_id, academia_id, nome_completo, data_nascimento, genero, telefone, email_pessoal, cpf, rg,
                    responsavel_nome, responsavel_cpf, responsavel_parentesco, responsavel_telefone, responsavel_email,
                    cep, endereco, endereco_numero, endereco_complemento, bairro, cidade, estado, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'inativo')";
                $stmt_insert = $pdo->prepare($sql_aluno);
                $stmt_insert->execute([
                    $turma['unidade_id'],
                    $turma['academia_id'],
                    $nome_completo,
                    $data_nascimento,
                    $genero,
                    $telefone,
                    $email_pessoal,
                    $cpf,
                    $rg,
                    $responsavel_nome,
                    $responsavel_cpf,
                    $responsavel_parentesco,
                    $responsavel_telefone,
                    $responsavel_email,
                    $cep,
                    $endereco,
                    $endereco_numero,
                    $endereco_complemento,
                    $bairro,
                    $cidade,
                    $estado
                ]);
                $aluno_id = $pdo->lastInsertId();
            }

            // Verificar se o aluno já está na turma
            $stmt_turma_check = $pdo->prepare("SELECT 1 FROM turma_alunos WHERE aluno_id = ? AND turma_id = ?");
            $stmt_turma_check->execute([$aluno_id, $turma['id']]);

            if (!$stmt_turma_check->fetchColumn()) {
                // Vincular aluno à turma
                $pdo->prepare("INSERT INTO turma_alunos (aluno_id, turma_id) VALUES (?, ?)")->execute([$aluno_id, $turma['id']]);

                // Gerar a primeira mensalidade
                if ($turma['preco_mensal'] > 0) {
                    $dia_vencimento = 10;
                    $data_vencimento = date('Y-m-') . $dia_vencimento;
                    if (strtotime($data_vencimento) < time()) {
                        $data_vencimento = date('Y-m-d', strtotime('+1 month', strtotime($data_vencimento)));
                    }

                    $pdo->prepare("INSERT INTO mensalidades (unidade_id, academia_id, aluno_id, valor, data_vencimento, status) VALUES (?, ?, ?, ?, ?, 'pendente')")
                        ->execute([$turma['unidade_id'], $turma['academia_id'], $aluno_id, $turma['preco_mensal'], $data_vencimento]);
                }
            }

            $pdo->commit();

            // Enviar e-mails de confirmação (inscrito e administrador) já com a inscrição salva.
            // Feito depois do commit e protegido por try/catch para que uma falha de e-mail
            // (ex: SMTP indisponível/lento) nunca derrube ou atrase a gravação da inscrição.
            try {
                enviarEmailsInscricao($turma, $aluno_id, $nome_completo, $responsavel_nome, $responsavel_email, $responsavel_telefone);
            } catch (Throwable $e) {
                // Inscrição já foi salva; ignora falha de envio de e-mail.
            }

            // Redireciona (Post/Redirect/Get) para impedir reenvio do formulário ao voltar/atualizar a página
            $separador = (strpos($url_inscricao, '?') !== false) ? '&' : '?';
            header('Location: ' . $url_inscricao . $separador . 'inscrito=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao processar inscrição. Por favor, tente novamente.";
        }
    } else {
        $erro = "Por favor, preencha todos os campos obrigatórios.";
    }
}

$dias_map = ['seg' => 'SEG', 'ter' => 'TER', 'qua' => 'QUA', 'qui' => 'QUI', 'sex' => 'SEX', 'sab' => 'SAB', 'dom' => 'DOM'];
$dias_selecionados = explode(',', $turma['dias_semana']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição em <?php echo htmlspecialchars($turma['nome']); ?> — <?php echo htmlspecialchars($turma['unidade_nome']); ?></title>
    <meta name="description" content="Formulário de pré-matrícula e inscrição online para a turma <?php echo htmlspecialchars($turma['nome']); ?> em <?php echo htmlspecialchars($turma['unidade_nome']); ?>.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #08153a;
            --brand-gold: #ffc107;
            --primary-green: #22c55e;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --bg: #f1f5f9;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }
        .page-wrapper {
            width: 100%;
            max-width: 780px;
        }
        .logo-bar {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-bar img {
            height: 46px;
            width: auto;
        }
        .card {
            background: #fff;
            border-top: 5px solid var(--brand-gold);
            box-shadow: 0 20px 60px -10px rgba(8, 21, 58, 0.12);
        }
        .card-header {
            background: var(--brand);
            color: #fff;
            padding: 40px 50px;
            text-align: center;
        }
        .card-header .academy-name {
            font-size: 28px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .card-header .subtitle {
            font-size: 14px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .turma-info {
            background: #fafbff;
            padding: 30px 50px;
            border-bottom: 1px solid var(--border-color);
        }
        .turma-info h2 {
            font-size: 22px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--brand);
            margin-bottom: 15px;
        }
        .info-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .info-pill {
            display: flex;
            align-items: center;
            gap: 7px;
            background: #fff;
            border: 1px solid var(--border-color);
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 800;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-pill i {
            color: var(--brand);
            font-size: 13px;
        }
        .dias-badges {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .dia-badge {
            background: var(--brand);
            color: #fff;
            font-size: 11px;
            font-weight: 900;
            padding: 4px 10px;
            letter-spacing: 0.08em;
        }
        .form-area {
            padding: 40px 50px;
        }
        .form-area h3 {
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            margin-bottom: 25px;
        }
        .alert {
            padding: 18px 22px;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid var(--primary-green);
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .form-group {
            margin-bottom: 22px;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
            letter-spacing: 0.08em;
        }
        .form-label .required {
            color: #ef4444;
            margin-left: 3px;
        }
        .form-control {
            width: 100%;
            height: 58px;
            border: 1.5px solid var(--border-color);
            background: #fafafa;
            padding: 0 18px;
            font-size: 16px;
            font-weight: 700;
            color: var(--text-dark);
            outline: none;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.15s, background 0.15s;
        }
        .form-control:focus {
            border-color: var(--brand);
            background: #fff;
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg xmlns%3D'http%3A//www.w3.org/2000/svg' width%3D'12' height%3D'12' viewBox%3D'0 0 24 24' fill%3D'none' stroke%3D'%2364748b' stroke-width%3D'2.5'%3E%3Cpolyline points%3D'6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn-submit {
            width: 100%;
            height: 64px;
            background: var(--brand);
            color: #fff;
            border: none;
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            cursor: pointer;
            letter-spacing: 0.08em;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-family: 'Inter', sans-serif;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover {
            background: #03081b;
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .btn-nova-inscricao {
            width: 100%;
            height: 64px;
            background: var(--brand-gold);
            color: #08153a;
            border: none;
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            cursor: pointer;
            letter-spacing: 0.08em;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-family: 'Inter', sans-serif;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-nova-inscricao:hover {
            background: #e0a800;
        }
        .btn-nova-inscricao:active {
            transform: scale(0.99);
        }
        .footer-note {
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 20px;
            padding: 0 10px;
        }
        .footer-note a {
            color: var(--brand);
            text-decoration: none;
            font-weight: 800;
        }
        .powered-by {
            text-align: center;
            margin-top: 25px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--text-muted);
            opacity: 0.5;
        }
        @media (max-width: 480px) {
            .card-header { padding: 25px; }
            .form-area { padding: 25px; }
            .turma-info { padding: 20px 25px; }
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<script>
    // Reforço para navegadores (ex: Safari) que restauram do bfcache mesmo com Cache-Control: no-store
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) window.location.reload();
    });

    // Ao enviar a ficha, troca o endereço desta página (no histórico do navegador) para a
    // tela geral de seleção de academia/turma. Assim, se o usuário apertar "Voltar" depois
    // de concluir a inscrição, ele cai na seleção zerada em vez desta turma específica.
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-inscricao');
        if (form) {
            form.addEventListener('submit', function () {
                try {
                    window.history.replaceState(null, '', <?php echo json_encode($url_nova_inscricao); ?>);
                } catch (e) {}
            });
        }
    });
</script>

<div class="page-wrapper">
    <div class="logo-bar">
        <img src="/Gestor/assets/img/logo.png" alt="SHIAI PRO">
    </div>

    <div class="card">
        <div class="card-header">
            <div class="academy-name"><?php echo htmlspecialchars($turma['unidade_nome']); ?></div>
            <div class="subtitle">Formulário de Pré-Matrícula &amp; Inscrição Online</div>
        </div>

        <div class="turma-info">
            <h2><?php echo htmlspecialchars($turma['nome']); ?></h2>
            <div class="info-pills">
                <?php if ($turma['instrutor']): ?>
                <div class="info-pill">
                    <i class="fa-solid fa-user-tie"></i>
                    <?php echo htmlspecialchars($turma['instrutor']); ?>
                </div>
                <?php endif; ?>
                <?php if ($turma['academia_nome']): ?>
                <div class="info-pill">
                    <i class="fa-solid fa-location-dot"></i>
                    <?php echo htmlspecialchars($turma['academia_nome']); ?>
                </div>
                <?php endif; ?>
                <div class="info-pill">
                    <i class="fa-regular fa-clock"></i>
                    <?php echo date('H:i', strtotime($turma['horario'])); ?>
                </div>
                <?php if ($turma['preco_mensal'] > 0): ?>
                <div class="info-pill">
                    <i class="fa-solid fa-tag"></i>
                    R$ <?php echo number_format($turma['preco_mensal'], 2, ',', '.'); ?>/mês
                </div>
                <?php endif; ?>
            </div>
            <div class="dias-badges">
                <?php
                foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $dia_cod) {
                    if (in_array($dia_cod, $dias_selecionados)) {
                        echo '<span class="dia-badge">' . $dias_map[$dia_cod] . '</span>';
                    }
                }
                ?>
            </div>
        </div>

        <div class="form-area">
            <?php if ($sucesso): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check fa-lg"></i>
                    <div><?php echo $sucesso; ?></div>
                </div>
                <?php if ($voltar_url): ?>
                    <a href="<?php echo htmlspecialchars($voltar_url); ?>" class="btn-nova-inscricao" style="text-decoration: none;">
                        VOLTAR PARA O EVENTO
                    </a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($url_nova_inscricao); ?>" class="btn-nova-inscricao" style="text-decoration: none; <?php echo $voltar_url ? 'background:transparent; color:var(--azul-marinho, #0a1e4d); border:2px solid currentColor; margin-top:10px;' : ''; ?>">
                    FAZER NOVA INSCRIÇÃO
                </a>
            <?php else: ?>

                <?php if ($erro): ?>
                    <div class="alert alert-danger">
                        <i class="fa-solid fa-circle-exclamation fa-lg"></i>
                        <div><?php echo $erro; ?></div>
                    </div>
                <?php endif; ?>

                <h3>Preencha seus dados para se inscrever</h3>

                <form method="POST" id="form-inscricao" action="<?php echo htmlspecialchars($url_inscricao); ?>">
                    <div class="form-group">
                        <label class="form-label">Nome Completo do Aluno <span class="required">*</span></label>
                        <input type="text" name="nome_completo" required class="form-control" placeholder="Digite o nome completo">
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Data de Nascimento <span class="required">*</span></label>
                            <input type="date" name="data_nascimento" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gênero <span class="required">*</span></label>
                            <select name="genero" required class="form-control">
                                <option value="">— Selecione —</option>
                                <option value="masculino">Masculino</option>
                                <option value="feminino">Feminino</option>
                            </select>
                        </div>
                    </div>

                    <h3 style="margin-top: 15px;">Responsável</h3>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Nome do Responsável <span class="required">*</span></label>
                            <input type="text" name="responsavel_nome" required class="form-control" placeholder="Nome completo do responsável">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Parentesco <span class="required">*</span></label>
                            <input type="text" name="responsavel_parentesco" required class="form-control" placeholder="Ex: Pai, Mãe, Avó...">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">CPF do Responsável <span class="required">*</span></label>
                            <input type="text" name="responsavel_cpf" required class="form-control" placeholder="000.000.000-00" id="respCpfInput">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Telefone do Responsável <span class="required">*</span></label>
                            <input type="tel" name="responsavel_telefone" required class="form-control" placeholder="(00) 00000-0000" id="respTelefoneInput">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">E-mail do Responsável <span class="required">*</span></label>
                        <input type="email" name="responsavel_email" required class="form-control" placeholder="exemplo@email.com">
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-check-circle"></i>
                        CONFIRMAR MINHA INSCRIÇÃO
                    </button>
                </form>
            <?php endif; ?>

            <div class="footer-note">
                Ao se inscrever, você concorda com os termos de uso da academia.<br>
                Dúvidas? Entre em contato com a academia diretamente.
            </div>
        </div>
    </div>

    <div class="powered-by">Powered by SHIAI PRO</div>
</div>

<script>
// Máscara simples de telefone
document.getElementById('telefoneInput')?.addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 11) v = v.slice(0, 11);
    if (v.length > 6) v = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
    else if (v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
    else if (v.length > 0) v = '(' + v;
    e.target.value = v;
});

// Máscara CPF
function mascararCPF(e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 11) v = v.slice(0, 11);
    if (v.length > 9) v = v.slice(0,3) + '.' + v.slice(3,6) + '.' + v.slice(6,9) + '-' + v.slice(9);
    else if (v.length > 6) v = v.slice(0,3) + '.' + v.slice(3,6) + '.' + v.slice(6);
    else if (v.length > 3) v = v.slice(0,3) + '.' + v.slice(3);
    e.target.value = v;
}
document.getElementById('cpfInput')?.addEventListener('input', mascararCPF);
document.getElementById('respCpfInput')?.addEventListener('input', mascararCPF);

// Máscara telefone do responsável
document.getElementById('respTelefoneInput')?.addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 11) v = v.slice(0, 11);
    if (v.length > 6) v = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
    else if (v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
    else if (v.length > 0) v = '(' + v;
    e.target.value = v;
});
</script>
</body>
</html>
