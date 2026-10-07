<?php
require_once '../Gestor/config.php';
require_once __DIR__ . '/inc_cpf.php';
header('Content-Type: application/json; charset=utf-8');

function resposta($ok, $dados = [], $mensagem = '') {
    echo json_encode(array_merge(['success' => $ok, 'message' => $mensagem], $dados));
    exit;
}

$tabelas_evento = [
    'exame'   => ['tabela' => 'eventos_graduacao', 'status_col' => "status = 'agendado'"],
    'torneio' => ['tabela' => 'competicoes', 'status_col' => "status = 'aberto'"],
    'oficial' => ['tabela' => 'competicoes_oficiais', 'status_col' => "status = 'aberto'"],
];

$tipo = $_POST['tipo'] ?? '';
$evento_id = (int) ($_POST['evento_id'] ?? 0);
$cpf_digits = preg_replace('/\D/', '', $_POST['cpf'] ?? '');

if (!$evento_id || !isset($tabelas_evento[$tipo]) || !cpfValido($cpf_digits)) {
    resposta(false, [], 'CPF inválido. Confira os números digitados.');
}

$cfg = $tabelas_evento[$tipo];

$stmt_evt = $pdo->prepare("SELECT ev.id, ev.unidade_id FROM {$cfg['tabela']} ev
                            JOIN unidades u ON u.id = ev.unidade_id
                            WHERE ev.id = ? AND ev.{$cfg['status_col']} AND u.status = 'ativo'");
$stmt_evt->execute([$evento_id]);
$evento = $stmt_evt->fetch();

if (!$evento) {
    resposta(false, [], 'Evento não encontrado.');
}

// Auto-migração: tabela de visitantes cadastrados só para um evento específico
try {
    $pdo->query("SELECT 1 FROM eventos_visitantes LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS eventos_visitantes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evento_tipo ENUM('exame','torneio','oficial') NOT NULL,
        evento_id INT NOT NULL,
        unidade_id INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        cpf VARCHAR(14) NOT NULL,
        data_nascimento DATE DEFAULT NULL,
        telefone VARCHAR(30) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        faixa VARCHAR(50) DEFAULT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
        UNIQUE KEY unique_visitante_evento (evento_tipo, evento_id, cpf, nome(150))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// O CPF é do responsável e pode inscrever mais de um atleta (ex: irmãos), por isso não
// bloqueia aqui só pelo CPF — a duplicidade (CPF + nome do atleta) é checada ao gravar.
resposta(true, ['cpf' => $cpf_digits]);
