<?php
require_once '../Gestor/config.php';

$tabelas_evento = [
    'exame'   => ['tabela' => 'eventos_graduacao', 'status_col' => "status = 'agendado'"],
    'torneio' => ['tabela' => 'competicoes', 'status_col' => "status = 'aberto'"],
    'oficial' => ['tabela' => 'competicoes_oficiais', 'status_col' => "status = 'aberto'"],
];

$tipo      = $_POST['tipo'] ?? '';
$evento_id = (int) ($_POST['evento_id'] ?? 0);

$redirect = "evento.php?tipo=" . urlencode($tipo) . "&id=" . $evento_id;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$evento_id || !isset($tabelas_evento[$tipo])) {
    header('Location: index.php');
    exit;
}

$cfg = $tabelas_evento[$tipo];

$nome            = trim(filter_input(INPUT_POST, 'nome', FILTER_DEFAULT) ?? '');
$cpf_digits      = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$data_nascimento = trim($_POST['data_nascimento'] ?? '') ?: null;
$telefone        = trim(filter_input(INPUT_POST, 'telefone', FILTER_DEFAULT) ?? '');
$email           = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null;
$faixa           = trim(filter_input(INPUT_POST, 'faixa', FILTER_DEFAULT) ?? '') ?: null;

if (!$nome || strlen($cpf_digits) !== 11 || !$telefone) {
    header('Location: ' . $redirect . '&erro=1');
    exit;
}

$stmt_evt = $pdo->prepare("SELECT ev.id, ev.unidade_id FROM {$cfg['tabela']} ev
                            JOIN unidades u ON u.id = ev.unidade_id
                            WHERE ev.id = ? AND ev.{$cfg['status_col']} AND u.status = 'ativo'");
$stmt_evt->execute([$evento_id]);
$evento = $stmt_evt->fetch();

if (!$evento) {
    header('Location: index.php');
    exit;
}

// Auto-migração (mesma tabela usada em api_visitante_cpf.php)
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
        UNIQUE KEY unique_visitante_evento (evento_tipo, evento_id, cpf)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

try {
    $stmt = $pdo->prepare("INSERT INTO eventos_visitantes (evento_tipo, evento_id, unidade_id, nome, cpf, data_nascimento, telefone, email, faixa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tipo, $evento_id, $evento['unidade_id'], $nome, $cpf_digits, $data_nascimento, $telefone, $email, $faixa]);
} catch (PDOException $e) {
    // Provável duplicidade (UNIQUE evento_tipo + evento_id + cpf)
    header('Location: ' . $redirect . '&ja_inscrito=1');
    exit;
}

header('Location: ' . $redirect . '&ok=1');
exit;
