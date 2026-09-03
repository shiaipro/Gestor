<?php
// Arquivo de Configuração
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'wwwshai_admin');
define('DB_USER', 'wwwshai_admin');
define('DB_PASS', 'CYDswdE4BXYGRZtskKXp');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Falha na conexão com o banco de dados: " . $e->getMessage());
}

// Iniciar sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helpers de Autenticação
function estaLogado()
{
    return isset($_SESSION['usuario_id']);
}

function ehMestre()
{
    return estaLogado() && $_SESSION['nivel'] === 'mestre';
}

function ehAdmin()
{
    return estaLogado() && ($_SESSION['nivel'] === 'admin' || $_SESSION['nivel'] === 'mestre');
}

function getUnidadeId()
{
    return $_SESSION['unidade_id'] ?? null;
}

function moduloAtivo($modulo)
{
    global $pdo;
    $unidade_id = getUnidadeId();
    if (!$unidade_id) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT modulos FROM unidades WHERE id = ?");
        $stmt->execute([$unidade_id]);
        $row = $stmt->fetch();
        $modulos = json_decode($row['modulos'] ?? '[]', true);
        return is_array($modulos) && in_array($modulo, $modulos);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Retorna true quando o usuário logado deve ver apenas as turmas onde ele está
 * definido como instrutor (turmas.instrutor_equipe_id -> unidade_equipe.usuario_id).
 * O nível de acesso "instrutor" não existe mais (configuracoes.php normaliza todos
 * os colaboradores para nivel='colaborador'), então a restrição é decidida pelo
 * próprio vínculo instrutor <-> turma, por ID (não mais por nome em texto).
 * O Super Admin (ehAdmin) nunca é restrito.
 */
function restringirVisaoAsProprisTurmas()
{
    global $pdo;

    if (ehAdmin()) return false;

    $unidade_id = getUnidadeId();
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    if (!$unidade_id || !$usuario_id) return false;

    try {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM turmas t
            INNER JOIN unidade_equipe ue ON ue.id = t.instrutor_equipe_id
            WHERE t.unidade_id = ? AND ue.usuario_id = ?
            LIMIT 1
        ");
        $stmt->execute([$unidade_id, $usuario_id]);
        return (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function temPermissaoModulo($modulo)
{
    global $pdo;

    // Super Admin (mestre) e o Administrador da própria unidade (nivel 'admin') têm
    // acesso irrestrito a todos os módulos — só colaboradores (nivel 'colaborador')
    // dependem da grade de permissões por módulo configurada em unidade_permissoes.
    if (ehAdmin()) return true;

    $unidade_id = getUnidadeId();
    $nivel = $_SESSION['nivel'] ?? null;
    $usuario_id = $_SESSION['usuario_id'] ?? null;

    if (!$unidade_id || !$nivel || !$usuario_id) return false;

    try {
        $stmt = $pdo->prepare("SELECT permitido FROM unidade_permissoes WHERE unidade_id = ? AND usuario_id = ? AND modulo = ?");
        $stmt->execute([$unidade_id, $usuario_id, $modulo]);
        $row = $stmt->fetch();
        return $row && $row['permitido'] == 1;
    } catch (Exception $e) {
        // Se a tabela não existir, ou outro erro, por segurança retorna falso se não for admin
        return false;
    }
}

/**
 * Retorna o caminho da pasta de upload da unidade/academia, criando-a se necessário.
 * @param string $tipo 'logos', 'alunos', 'documentos'
 * @param int|null $academia_id Opcional
 */
function getUploadPath($tipo, $academia_id = null) {
    $unidade_id = getUnidadeId();
    if (!$unidade_id) return null;

    // Usar __DIR__ garante que pegamos a pasta do projeto Gestor corretamente
    $base = __DIR__ . '/uploads/u_' . $unidade_id;
    
    if ($academia_id) {
        $base .= '/academias/a_' . $academia_id;
    }

    $path = $base . '/' . $tipo;

    if (!is_dir($path)) {
        if (!mkdir($path, 0777, true)) {
            return null; // Falha ao criar diretório
        }
    }

    return $path;
}

/**
 * Retorna a URL pública para um arquivo
 */
function getUploadURL($tipo, $arquivo, $academia_id = null) {
    $unidade_id = getUnidadeId();
    if (!$unidade_id || !$arquivo) return null;

    // URL relativa para o navegador
    $url = '../uploads/u_' . $unidade_id;
    if ($academia_id) {
        $url .= '/academias/a_' . $academia_id;
    }
    
    return $url . '/' . $tipo . '/' . $arquivo;
}

/**
 * Retorna um AsaasClient configurado com a chave API da unidade, ou null se a unidade
 * não tiver integração Asaas configurada.
 */
function getAsaasClientParaUnidade($pdo, $unidade_id)
{
    require_once __DIR__ . '/assets/lib/AsaasClient.php';

    $stmt = $pdo->prepare("SELECT api_key, ambiente FROM configuracoes_pagamento WHERE unidade_id = ? AND gateway = 'asaas'");
    $stmt->execute([$unidade_id]);
    $config = $stmt->fetch();

    if (!$config || empty($config['api_key'])) return null;

    return new AsaasClient($config['api_key'], $config['ambiente'] === 'sandbox');
}

/**
 * Diretório privado (fora de uploads/, que é servido publicamente) onde ficam o certificado
 * e a chave privada mTLS da Cora de uma unidade. Protegido por .htaccess "Deny from all".
 */
function getCoraCertDir($unidade_id)
{
    $dir = __DIR__ . '/private/certs_cora/u_' . $unidade_id;
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $htaccess = __DIR__ . '/private/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Order deny,allow\nDeny from all\nRequire all denied\n");
    }
    return $dir;
}

/**
 * Garante que as colunas usadas pela integração Cora (certificado mTLS + flag de gateway ativo)
 * existem na tabela configuracoes_pagamento.
 */
function garantirColunasConfiguracoesPagamento($pdo)
{
    try {
        $pdo->query("SELECT cert_path FROM configuracoes_pagamento LIMIT 1");
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE configuracoes_pagamento
                ADD COLUMN cert_path VARCHAR(255) DEFAULT NULL,
                ADD COLUMN key_path VARCHAR(255) DEFAULT NULL");
        } catch (Exception $e2) {
            error_log('Falha ao migrar configuracoes_pagamento (cora): ' . $e2->getMessage());
        }
    }
    try {
        $pdo->query("SELECT ativo FROM configuracoes_pagamento LIMIT 1");
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE configuracoes_pagamento ADD COLUMN ativo TINYINT(1) DEFAULT 0");
        } catch (Exception $e2) {
            error_log('Falha ao migrar configuracoes_pagamento (ativo): ' . $e2->getMessage());
        }
    }
}

/**
 * Retorna um CoraClient configurado com o client_id + certificado/chave mTLS da unidade,
 * ou null se a unidade não tiver integração Cora configurada.
 */
function getCoraClientParaUnidade($pdo, $unidade_id)
{
    require_once __DIR__ . '/assets/lib/CoraClient.php';
    garantirColunasConfiguracoesPagamento($pdo);

    $stmt = $pdo->prepare("SELECT api_key, cert_path, key_path, ambiente FROM configuracoes_pagamento WHERE unidade_id = ? AND gateway = 'cora'");
    $stmt->execute([$unidade_id]);
    $config = $stmt->fetch();

    if (!$config || empty($config['api_key']) || empty($config['cert_path']) || empty($config['key_path'])) return null;
    if (!is_file($config['cert_path']) || !is_file($config['key_path'])) return null;

    return new CoraClient($config['api_key'], $config['cert_path'], $config['key_path'], $config['ambiente'] === 'sandbox');
}

/**
 * Diz qual gateway de pagamento (asaas|cora) está marcado como ativo para a unidade.
 * Se nenhum estiver explicitamente marcado, cai para o primeiro que estiver configurado
 * (cora tem prioridade por ser a integração mais recente).
 */
function getGatewayAtivoParaUnidade($pdo, $unidade_id)
{
    garantirColunasConfiguracoesPagamento($pdo);
    $stmt = $pdo->prepare("SELECT gateway, ativo FROM configuracoes_pagamento WHERE unidade_id = ? AND gateway IN ('asaas', 'cora')");
    $stmt->execute([$unidade_id]);
    $configs = $stmt->fetchAll();

    foreach ($configs as $c) {
        if (!empty($c['ativo'])) return $c['gateway'];
    }
    foreach (['cora', 'asaas'] as $preferido) {
        foreach ($configs as $c) {
            if ($c['gateway'] === $preferido) return $preferido;
        }
    }
    return null;
}

/**
 * Garante que as colunas de integração PIX/Asaas existem na tabela mensalidades.
 */
function garantirColunasPixMensalidades($pdo)
{
    try {
        $pdo->query("SELECT asaas_payment_id FROM mensalidades LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE mensalidades
            ADD COLUMN asaas_payment_id VARCHAR(60) DEFAULT NULL,
            ADD COLUMN asaas_pix_payload TEXT DEFAULT NULL,
            ADD COLUMN asaas_pix_qrcode LONGTEXT DEFAULT NULL,
            ADD COLUMN asaas_pix_expiracao DATETIME DEFAULT NULL");
    }
    try {
        $pdo->query("SELECT cora_invoice_id FROM mensalidades LIMIT 1");
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE mensalidades
                ADD COLUMN gateway_pagamento VARCHAR(20) DEFAULT NULL,
                ADD COLUMN cora_invoice_id VARCHAR(60) DEFAULT NULL,
                ADD COLUMN cora_pix_payload TEXT DEFAULT NULL,
                ADD COLUMN cora_boleto_url TEXT DEFAULT NULL");
        } catch (Exception $e2) {
            error_log('Falha ao migrar mensalidades (cora): ' . $e2->getMessage());
        }
    }
}

/**
 * Monta o customer/document exigidos pela Cora a partir de um CPF (pessoa física).
 */
function montarClienteCora($nome, $cpf, $email)
{
    $customer = [
        'name' => $nome,
        'document' => [
            'identity' => preg_replace('/\D/', '', $cpf),
            'type' => 'CPF',
        ],
    ];
    if ($email) $customer['email'] = $email;
    return $customer;
}

/**
 * Cria uma cobrança (boleto + PIX) na Cora e retorna o payload normalizado.
 * $valor em reais (será convertido para centavos).
 */
function criarCobrancaCora($cora, $codigo, $nome, $cpf, $email, $valor, $vencimento, $descricao)
{
    $invoice = $cora->createInvoice(
        $codigo,
        montarClienteCora($nome, $cpf, $email),
        [['name' => $descricao, 'amount' => (int) round($valor * 100)]],
        $vencimento
    );

    return [
        'gateway' => 'cora',
        'external_id' => $invoice['id'],
        'payload' => $invoice['pix']['emv'] ?? null,
        'qrcode' => null, // Cora não retorna imagem pronta; gerada no front a partir do payload.
        'boleto_url' => $invoice['payment_options']['bank_slip']['url'] ?? null,
    ];
}

/**
 * Gera (ou reaproveita, se ainda válida) uma cobrança no gateway ativo da unidade (Cora ou Asaas)
 * para uma mensalidade.
 * Retorna ['gateway','payload' => copia-e-cola PIX, 'qrcode' => base64 ou null, 'boleto_url' => url ou null].
 */
function gerarPixParaMensalidade($pdo, $mensalidade, $aluno)
{
    garantirColunasPixMensalidades($pdo);

    $gateway = getGatewayAtivoParaUnidade($pdo, $mensalidade['unidade_id']);
    if (!$gateway) {
        throw new Exception('Esta unidade ainda não configurou pagamentos online (Asaas/Cora).');
    }

    $cpf_cliente = $aluno['cpf'] ?: $aluno['responsavel_cpf'];
    $nome_cliente = $aluno['cpf'] ? $aluno['nome_completo'] : ($aluno['responsavel_nome'] ?: $aluno['nome_completo']);
    if (empty($cpf_cliente)) {
        throw new Exception('Aluno sem CPF cadastrado (nem do responsável) para gerar cobrança.');
    }

    $vencimento = $mensalidade['data_vencimento'];
    if (strtotime($vencimento) < strtotime(date('Y-m-d'))) {
        $vencimento = date('Y-m-d');
    }

    if ($gateway === 'cora') {
        // Reaproveita a cobrança já gerada enquanto o vencimento não tiver passado.
        if ($mensalidade['gateway_pagamento'] === 'cora' && !empty($mensalidade['cora_invoice_id'])
            && strtotime($mensalidade['data_vencimento']) >= strtotime(date('Y-m-d'))) {
            return [
                'gateway' => 'cora',
                'payload' => $mensalidade['cora_pix_payload'],
                'qrcode' => null,
                'boleto_url' => $mensalidade['cora_boleto_url'],
            ];
        }

        $cora = getCoraClientParaUnidade($pdo, $mensalidade['unidade_id']);
        if (!$cora) {
            throw new Exception('Esta unidade ainda não configurou pagamentos online (Cora).');
        }

        $cobranca = criarCobrancaCora(
            $cora,
            'mensalidade-' . $mensalidade['id'],
            $nome_cliente,
            $cpf_cliente,
            $aluno['email_pessoal'] ?? ($aluno['responsavel_email'] ?? null),
            $mensalidade['valor'],
            $vencimento,
            'Mensalidade - ' . $aluno['nome_completo']
        );

        $stmt = $pdo->prepare("UPDATE mensalidades SET gateway_pagamento = 'cora', cora_invoice_id = ?, cora_pix_payload = ?, cora_boleto_url = ? WHERE id = ?");
        $stmt->execute([$cobranca['external_id'], $cobranca['payload'], $cobranca['boleto_url'], $mensalidade['id']]);

        return $cobranca;
    }

    // Reaproveita PIX já gerado enquanto não tiver expirado
    if (!empty($mensalidade['asaas_pix_payload']) && !empty($mensalidade['asaas_pix_expiracao'])
        && strtotime($mensalidade['asaas_pix_expiracao']) > time()) {
        return [
            'gateway' => 'asaas',
            'payload' => $mensalidade['asaas_pix_payload'],
            'qrcode' => $mensalidade['asaas_pix_qrcode'],
            'boleto_url' => null,
        ];
    }

    $asaas = getAsaasClientParaUnidade($pdo, $mensalidade['unidade_id']);
    if (!$asaas) {
        throw new Exception('Esta unidade ainda não configurou pagamentos online (Asaas).');
    }

    $customer_id = $asaas->createCustomer($nome_cliente, preg_replace('/\D/', '', $cpf_cliente), $aluno['email_pessoal'] ?? ($aluno['responsavel_email'] ?? null));

    $payment = $asaas->createPayment(
        $customer_id,
        $mensalidade['valor'],
        $vencimento,
        'Mensalidade - ' . $aluno['nome_completo'],
        'PIX'
    );

    $qrcode = $asaas->getPixQrCode($payment['id']);

    $stmt = $pdo->prepare("UPDATE mensalidades SET gateway_pagamento = 'asaas', asaas_payment_id = ?, asaas_pix_payload = ?, asaas_pix_qrcode = ?, asaas_pix_expiracao = ? WHERE id = ?");
    $stmt->execute([
        $payment['id'],
        $qrcode['payload'],
        $qrcode['encodedImage'],
        $qrcode['expirationDate'] ?? date('Y-m-d H:i:s', strtotime('+1 day')),
        $mensalidade['id'],
    ]);

    return ['gateway' => 'asaas', 'payload' => $qrcode['payload'], 'qrcode' => $qrcode['encodedImage'], 'boleto_url' => null];
}

/**
 * Garante que as colunas de integração PIX/Asaas existem na tabela eventos_graduacao_inscricoes.
 */
function garantirColunasPixInscricoesExame($pdo)
{
    try {
        $pdo->query("SELECT asaas_payment_id FROM eventos_graduacao_inscricoes LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE eventos_graduacao_inscricoes
            ADD COLUMN asaas_payment_id VARCHAR(60) DEFAULT NULL,
            ADD COLUMN asaas_pix_payload TEXT DEFAULT NULL,
            ADD COLUMN asaas_pix_qrcode LONGTEXT DEFAULT NULL,
            ADD COLUMN asaas_pix_expiracao DATETIME DEFAULT NULL");
    }
    try {
        $pdo->query("SELECT cora_invoice_id FROM eventos_graduacao_inscricoes LIMIT 1");
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE eventos_graduacao_inscricoes
                ADD COLUMN gateway_pagamento VARCHAR(20) DEFAULT NULL,
                ADD COLUMN cora_invoice_id VARCHAR(60) DEFAULT NULL,
                ADD COLUMN cora_pix_payload TEXT DEFAULT NULL,
                ADD COLUMN cora_boleto_url TEXT DEFAULT NULL");
        } catch (Exception $e2) {
            error_log('Falha ao migrar eventos_graduacao_inscricoes (cora): ' . $e2->getMessage());
        }
    }
}

/**
 * Gera (ou reaproveita, se ainda válida) uma cobrança no gateway ativo da unidade (Cora ou Asaas)
 * para a taxa de um exame de faixa.
 * $inscricao precisa trazer: id, valor_pago, status_pagamento, unidade_id, evento_titulo.
 * Retorna ['gateway','payload' => copia-e-cola PIX, 'qrcode' => base64 ou null, 'boleto_url' => url ou null].
 */
function gerarPixParaInscricaoExame($pdo, $inscricao, $aluno)
{
    garantirColunasPixInscricoesExame($pdo);

    $gateway = getGatewayAtivoParaUnidade($pdo, $inscricao['unidade_id']);
    if (!$gateway) {
        throw new Exception('Esta unidade ainda não configurou pagamentos online (Asaas/Cora).');
    }

    $cpf_cliente = $aluno['cpf'] ?: $aluno['responsavel_cpf'];
    $nome_cliente = $aluno['cpf'] ? $aluno['nome_completo'] : ($aluno['responsavel_nome'] ?: $aluno['nome_completo']);
    if (empty($cpf_cliente)) {
        throw new Exception('Aluno sem CPF cadastrado (nem do responsável) para gerar cobrança.');
    }

    if ($gateway === 'cora') {
        if ($inscricao['gateway_pagamento'] === 'cora' && !empty($inscricao['cora_invoice_id'])) {
            return [
                'gateway' => 'cora',
                'payload' => $inscricao['cora_pix_payload'],
                'qrcode' => null,
                'boleto_url' => $inscricao['cora_boleto_url'],
            ];
        }

        $cora = getCoraClientParaUnidade($pdo, $inscricao['unidade_id']);
        if (!$cora) {
            throw new Exception('Esta unidade ainda não configurou pagamentos online (Cora).');
        }

        $cobranca = criarCobrancaCora(
            $cora,
            'exame-' . $inscricao['id'],
            $nome_cliente,
            $cpf_cliente,
            $aluno['email_pessoal'] ?? ($aluno['responsavel_email'] ?? null),
            $inscricao['valor_pago'],
            date('Y-m-d'),
            'Taxa de Exame - ' . $inscricao['evento_titulo']
        );

        $stmt = $pdo->prepare("UPDATE eventos_graduacao_inscricoes SET gateway_pagamento = 'cora', cora_invoice_id = ?, cora_pix_payload = ?, cora_boleto_url = ? WHERE id = ?");
        $stmt->execute([$cobranca['external_id'], $cobranca['payload'], $cobranca['boleto_url'], $inscricao['id']]);

        return $cobranca;
    }

    if (!empty($inscricao['asaas_pix_payload']) && !empty($inscricao['asaas_pix_expiracao'])
        && strtotime($inscricao['asaas_pix_expiracao']) > time()) {
        return [
            'gateway' => 'asaas',
            'payload' => $inscricao['asaas_pix_payload'],
            'qrcode' => $inscricao['asaas_pix_qrcode'],
            'boleto_url' => null,
        ];
    }

    $asaas = getAsaasClientParaUnidade($pdo, $inscricao['unidade_id']);
    if (!$asaas) {
        throw new Exception('Esta unidade ainda não configurou pagamentos online (Asaas).');
    }

    $customer_id = $asaas->createCustomer($nome_cliente, preg_replace('/\D/', '', $cpf_cliente), $aluno['email_pessoal'] ?? ($aluno['responsavel_email'] ?? null));

    $payment = $asaas->createPayment(
        $customer_id,
        $inscricao['valor_pago'],
        date('Y-m-d'),
        'Taxa de Exame - ' . $inscricao['evento_titulo'],
        'PIX'
    );

    $qrcode = $asaas->getPixQrCode($payment['id']);

    $stmt = $pdo->prepare("UPDATE eventos_graduacao_inscricoes SET gateway_pagamento = 'asaas', asaas_payment_id = ?, asaas_pix_payload = ?, asaas_pix_qrcode = ?, asaas_pix_expiracao = ? WHERE id = ?");
    $stmt->execute([
        $payment['id'],
        $qrcode['payload'],
        $qrcode['encodedImage'],
        $qrcode['expirationDate'] ?? date('Y-m-d H:i:s', strtotime('+1 day')),
        $inscricao['id'],
    ]);

    return ['gateway' => 'asaas', 'payload' => $qrcode['payload'], 'qrcode' => $qrcode['encodedImage'], 'boleto_url' => null];
}

/**
 * Calcula os requisitos (carência, frequência) de um aluno para um exame de faixa.
 * Carência: comparada automaticamente pela última data de graduação do aluno.
 * Frequência: calculada a partir das presenças registradas, comparada ao mínimo definido pelo admin no exame.
 */
function calcularRequisitosExame($pdo, $insc, $evento, $carencia_por_graduacao, $carencia_ativo = true) {
    $resultado = ['carencia' => null, 'carencia_faltam_meses' => 0, 'frequencia' => null, 'frequencia_pct' => null];

    // Carência (automática pela graduação pretendida, referenciada ao início das avaliações)
    $carencia_meses = $carencia_ativo ? ($carencia_por_graduacao[$insc['faixa_pretendida']] ?? 0) : 0;
    if ($carencia_meses > 0) {
        if (empty($insc['data_graduacao_atual'])) {
            $resultado['carencia'] = null; // sem data base para calcular
        } else {
            $data_base = new DateTime($insc['data_graduacao_atual']);
            $data_ref = new DateTime($evento['avaliacoes_inicio'] ?: ($evento['data_evento'] ?: date('Y-m-d')));
            $meses_passados = ($data_ref->diff($data_base)->y * 12) + $data_ref->diff($data_base)->m;
            $resultado['carencia'] = $meses_passados >= $carencia_meses;
            $resultado['carencia_faltam_meses'] = max(0, $carencia_meses - $meses_passados);
        }
    } else {
        $resultado['carencia'] = true; // graduação sem carência configurada
    }

    // Frequência (mínimo definido pelo admin no exame, medida na janela de avaliações)
    if (!empty($evento['requisito_frequencia_minima'])) {
        $data_ini = $evento['avaliacoes_inicio'] ?: $evento['inscricoes_inicio'];
        $data_fim = $evento['avaliacoes_fim'] ?: ($evento['data_evento'] ?: date('Y-m-d'));
        $stmt_freq = $pdo->prepare("SELECT
                SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presentes,
                COUNT(*) as total
            FROM presencas
            WHERE aluno_id = ?
            " . ($data_ini ? " AND data_aula >= ? " : "") . "
            AND data_aula <= ?");
        $params_freq = [$insc['aluno_id']];
        if ($data_ini) { $params_freq[] = $data_ini; }
        $params_freq[] = $data_fim;
        $stmt_freq->execute($params_freq);
        $freq = $stmt_freq->fetch();

        if (!$freq || (int)$freq['total'] === 0) {
            $resultado['frequencia'] = null;
        } else {
            $pct = ($freq['presentes'] / $freq['total']) * 100;
            $resultado['frequencia_pct'] = round($pct, 1);
            $resultado['frequencia'] = $pct >= (float)$evento['requisito_frequencia_minima'];
        }
    }

    return $resultado;
}

/**
 * Define se a checagem de carência está ativa para um aluno, respeitando a configuração
 * por turma (quando o exame não é "todas as turmas") ou a configuração geral do evento.
 */
function carenciaAtivaParaAluno($pdo, $aluno_id, $evento, $turmas_selecionadas_ids, $datas_turma_salvas) {
    $carencia_ativo = (int)($evento['requisito_carencia_ativo'] ?? 1) === 1;
    if (empty($evento['todas_turmas']) && !empty($turmas_selecionadas_ids)) {
        $placeholders_turma_aluno = implode(',', array_fill(0, count($turmas_selecionadas_ids), '?'));
        $stmt_turma_aluno = $pdo->prepare("SELECT turma_id FROM turma_alunos WHERE aluno_id = ? AND turma_id IN ($placeholders_turma_aluno) LIMIT 1");
        $stmt_turma_aluno->execute(array_merge([$aluno_id], $turmas_selecionadas_ids));
        $turma_aluno_row = $stmt_turma_aluno->fetch();
        if ($turma_aluno_row && isset($datas_turma_salvas[$turma_aluno_row['turma_id']]['requisito_carencia_ativo'])) {
            $carencia_ativo = (int)$datas_turma_salvas[$turma_aluno_row['turma_id']]['requisito_carencia_ativo'] === 1;
        }
    }
    return $carencia_ativo;
}

/**
 * Garante que a coluna que marca um aluno como "fixo" (matrícula regular) ou "visitante"
 * (cadastrado só para participar de um exame de faixa pontual) existe em `alunos`.
 */
function garantirColunaTipoCadastroAluno($pdo)
{
    try {
        $pdo->query("SELECT tipo_cadastro FROM alunos LIMIT 1");
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE alunos ADD COLUMN tipo_cadastro ENUM('fixo','visitante') NOT NULL DEFAULT 'fixo'");
        } catch (Exception $e2) {
            error_log('Falha ao migrar alunos (tipo_cadastro): ' . $e2->getMessage());
        }
    }
}

/**
 * Registra comissão para o professor quando uma mensalidade é paga
 */
function registrarComissoesMensalidade($mensalidade_id, $unidade_id) {
    global $pdo;
    try {
        // 1. Obter informações da mensalidade e do aluno
        $stmt = $pdo->prepare("SELECT m.*, a.nome_completo FROM mensalidades m JOIN alunos a ON m.aluno_id = a.id WHERE m.id = ? AND m.unidade_id = ?");
        $stmt->execute([$mensalidade_id, $unidade_id]);
        $mensalidade = $stmt->fetch();
        if (!$mensalidade) return;

        // 2. Verificar se a mensalidade está paga
        if ($mensalidade['status'] !== 'pago') return;

        // 3. Buscar turmas vinculadas ao aluno
        $stmt_turmas = $pdo->prepare("
            SELECT t.* 
            FROM turma_alunos ta 
            JOIN turmas t ON ta.turma_id = t.id 
            WHERE ta.aluno_id = ? AND t.unidade_id = ?
        ");
        $stmt_turmas->execute([$mensalidade['aluno_id'], $unidade_id]);
        $turmas = $stmt_turmas->fetchAll();

        if (empty($turmas)) return;

        // 4. Garantir que a categoria "Comissionamento" existe no Financeiro (tipo despesa)
        $stmt_cat = $pdo->prepare("SELECT id FROM financeiro_categorias WHERE nome = 'Comissionamento' AND tipo = 'despesa' AND unidade_id = ?");
        $stmt_cat->execute([$unidade_id]);
        $cat = $stmt_cat->fetch();
        if (!$cat) {
            $stmt_new_cat = $pdo->prepare("INSERT INTO financeiro_categorias (unidade_id, nome, tipo) VALUES (?, 'Comissionamento', 'despesa')");
            $stmt_new_cat->execute([$unidade_id]);
            $categoria_id = $pdo->lastInsertId();
        } else {
            $categoria_id = $cat['id'];
        }

        // 5. Para cada turma, processar a comissão
        foreach ($turmas as $turma) {
            // Se houver comissão do professor
            if ($turma['comissao_prof_valor'] > 0 && !empty($turma['instrutor'])) {
                $valor_comissao = 0.0;
                if ($turma['comissao_prof_tipo'] === 'percentual') {
                    $valor_comissao = round($mensalidade['valor'] * ($turma['comissao_prof_valor'] / 100), 2);
                } else { // fixo
                    $valor_comissao = (float)$turma['comissao_prof_valor'];
                }

                if ($valor_comissao > 0) {
                    // Verificar se já existe lançamento de comissão para esta mensalidade nesta turma para evitar duplicidade
                    $descr_busca = "Comissão Prof. " . $turma['instrutor'] . " - Ref. Mensalidade ID " . $mensalidade['id'] . " - Turma: " . $turma['nome'];
                    $stmt_check = $pdo->prepare("SELECT id FROM financeiro_lancamentos WHERE unidade_id = ? AND tipo = 'despesa' AND descricao = ?");
                    $stmt_check->execute([$unidade_id, $descr_busca]);
                    if (!$stmt_check->fetch()) {
                        // Inserir despesa no contas a pagar
                        $stmt_ins = $pdo->prepare("
                            INSERT INTO financeiro_lancamentos 
                            (unidade_id, academia_id, categoria_id, tipo, descricao, valor, data_vencimento, status, recorrencia, total_parcelas, parcela_atual) 
                            VALUES (?, ?, ?, 'despesa', ?, ?, 'pendente', 'nenhuma', 1, 1)
                        ");
                        $stmt_ins->execute([
                            $unidade_id,
                            $mensalidade['academia_id'],
                            $categoria_id,
                            $descr_busca,
                            $valor_comissao,
                            $mensalidade['data_pagamento'] ?: date('Y-m-d')
                        ]);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Silencioso
    }
}
?>