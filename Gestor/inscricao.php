<?php
require_once 'config.php';

$uuid = $_GET['t'] ?? '';

if (!$uuid) {
    die("Turma não identificada.");
}

// Buscar a turma e o slug da unidade para redirecionar para a URL amigável
$stmt = $pdo->prepare("
    SELECT t.*, ac.nome as academia_nome, un.nome as unidade_nome, un.slug as unidade_slug
    FROM turmas t
    LEFT JOIN academias ac ON t.academia_id = ac.id
    LEFT JOIN unidades un ON t.unidade_id = un.id
    WHERE t.uuid_inscricao = ? AND t.status = 'ativo'
");
$stmt->execute([$uuid]);
$turma = $stmt->fetch();

if (!$turma) {
    die("Turma não encontrada ou inativa.");
}

// Redirecionar para a URL amigável com slug
if (!empty($turma['unidade_slug'])) {
    header("Location: https://shiaipro.com.br/" . $turma['unidade_slug'] . "/inscricao/" . $uuid, true, 301);
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $email_pessoal = trim($_POST['email_pessoal'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?: null;
    $genero = $_POST['genero'] ?: null;

    if ($nome_completo && $email_pessoal && $telefone) {
        try {
            $pdo->beginTransaction();

            // Verificar se o aluno já existe: por CPF, e-mail ou (nome + telefone)
            $stmt_check = $pdo->prepare("
                SELECT id FROM alunos
                WHERE unidade_id = ?
                  AND (
                      email_pessoal = ?
                      OR (cpf = ? AND cpf != '')
                      OR (LOWER(nome_completo) = LOWER(?) AND telefone = ? AND telefone != '')
                  )
                LIMIT 1
            ");
            $stmt_check->execute([$turma['unidade_id'], $email_pessoal, $cpf, $nome_completo, $telefone]);
            $aluno_existente = $stmt_check->fetch();

            if ($aluno_existente) {
                $aluno_id = $aluno_existente['id'];
            } else {
                // Criar novo aluno
                $sql_aluno = "INSERT INTO alunos (
                    unidade_id, academia_id, nome_completo, data_nascimento, genero, telefone, email_pessoal, cpf, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ativo')";
                $stmt_insert = $pdo->prepare($sql_aluno);
                $stmt_insert->execute([
                    $turma['unidade_id'],
                    $turma['academia_id'],
                    $nome_completo,
                    $data_nascimento,
                    $genero,
                    $telefone,
                    $email_pessoal,
                    $cpf
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
                    $dia_vencimento = 10; // Padrão
                    $data_vencimento = date('Y-m-') . $dia_vencimento;
                    if (strtotime($data_vencimento) < time()) {
                        $data_vencimento = date('Y-m-d', strtotime('+1 month', strtotime($data_vencimento)));
                    }

                    $pdo->prepare("INSERT INTO mensalidades (unidade_id, academia_id, aluno_id, valor, data_vencimento, status) VALUES (?, ?, ?, ?, ?, 'pendente')")
                        ->execute([$turma['unidade_id'], $turma['academia_id'], $aluno_id, $turma['preco_mensal'], $data_vencimento]);
                }
            }

            $pdo->commit();
            $sucesso = "Inscrição realizada com sucesso! Seja bem-vindo(a).";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao processar inscrição: " . $e->getMessage();
        }
    } else {
        $erro = "Por favor, preencha os campos obrigatórios (Nome, E-mail e Telefone).";
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
    <title>Inscrição - SHIAI PRO</title>
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
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 580px;
            background: #fff;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
        }
        .header {
            background-color: var(--brand);
            color: #fff;
            padding: 30px;
            text-align: center;
            border-bottom: 5px solid var(--brand-gold);
        }
        .header h1 {
            font-size: 20px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 13px;
            color: #94a3b8;
            font-weight: 500;
        }
        .turma-info {
            background: #fafafa;
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
        }
        .turma-info h2 {
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 10px;
            color: var(--brand);
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            font-size: 13px;
            font-weight: 700;
        }
        .info-item {
            color: var(--text-muted);
        }
        .info-item span {
            color: var(--text-dark);
            display: block;
            font-weight: 800;
            margin-top: 2px;
        }
        .form-area {
            padding: 30px;
        }
        .alert {
            padding: 15px 20px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 25px;
            letter-spacing: 0.05em;
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
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
            letter-spacing: 0.05em;
        }
        .form-control {
            width: 100%;
            height: 48px;
            border: 1px solid var(--border-color);
            background: #fafafa;
            padding: 0 15px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
            outline: none;
        }
        .form-control:focus {
            border-color: var(--brand);
            background: #fff;
        }
        .btn-submit {
            width: 100%;
            height: 55px;
            background: var(--brand);
            color: #fff;
            border: none;
            font-size: 14px;
            font-weight: 900;
            text-transform: uppercase;
            cursor: pointer;
            letter-spacing: 0.05em;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-submit:hover {
            background: #03081b;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><?php echo htmlspecialchars(strtoupper($turma['unidade_nome'])); ?></h1>
        <p>Formulário de Pré-Matrícula e Inscrição Online</p>
    </div>
    
    <div class="turma-info">
        <h2><?php echo htmlspecialchars($turma['nome']); ?></h2>
        <div class="info-grid">
            <div class="info-item">
                Local: <span><?php echo htmlspecialchars($turma['academia_nome'] ?: 'Principal'); ?></span>
            </div>
            <div class="info-item">
                Professor: <span><?php echo htmlspecialchars($turma['instrutor'] ?: 'A Definir'); ?></span>
            </div>
            <div class="info-item" style="grid-column: span 2;">
                Horário e Dias: 
                <span>
                    <?php echo date('H:i', strtotime($turma['horario'])); ?> - 
                    <?php 
                    $d_list = [];
                    foreach ($dias_selecionados as $d) {
                        if (isset($dias_map[$d])) $d_list[] = $dias_map[$d];
                    }
                    echo implode(', ', $d_list);
                    ?>
                </span>
            </div>
        </div>
    </div>

    <div class="form-area">
        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?php echo $sucesso; ?>
            </div>
        <?php else: ?>
            
            <?php if ($erro): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $erro; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome_completo" required class="form-control" placeholder="DIGITE SEU NOME COMPLETO">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>E-mail *</label>
                        <input type="email" name="email_pessoal" required class="form-control" placeholder="exemplo@email.com">
                    </div>
                    <div class="form-group">
                        <label>Celular / WhatsApp *</label>
                        <input type="tel" name="telefone" required class="form-control" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>CPF</label>
                        <input type="text" name="cpf" class="form-control" placeholder="000.000.000-00">
                    </div>
                    <div class="form-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="data_nascimento" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Gênero</label>
                    <select name="genero" class="form-control">
                        <option value="">— SELECIONE —</option>
                        <option value="M">Masculino</option>
                        <option value="F">Feminino</option>
                        <option value="O">Outro</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">
                    CONFIRMAR MINHA INSCRIÇÃO <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
