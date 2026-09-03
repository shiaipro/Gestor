<?php
require_once 'config.php';

// Pegar ID da Campanha
$campaign_id = $_GET['cid'] ?? null;
if (!$campaign_id) {
    die("Campanha não encontrada.");
}

// Buscar dados da campanha
$stmt = $pdo->prepare("SELECT * FROM marketing_campanhas WHERE id = ?");
$stmt->execute([$campaign_id]);
$campanha = $stmt->fetch();

if (!$campanha) {
    die("Campanha inválida.");
}

$unidade_id = $campanha['unidade_id'];
$sucesso = false;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $whatsapp = $_POST['whatsapp'] ?? '';
    
    if (!empty($nome)) {
        $stmt = $pdo->prepare("INSERT INTO comercial_leads (unidade_id, nome, email, telefone, origem, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$unidade_id, $nome, $email, $whatsapp, $campanha['nome'], 'novo']);
        
        // Incrementar leads na campanha
        $pdo->prepare("UPDATE marketing_campanhas SET leads_gerados = leads_gerados + 1 WHERE id = ?")->execute([$campaign_id]);
        
        $sucesso = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contato - <?php echo htmlspecialchars($campanha['nome']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --input-bg: #f8fafc;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: transparent; 
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 10px;
        }
        .form-container {
            width: 100%;
            max-width: 400px;
            background: var(--bg);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
        }
        .header { margin-bottom: 1.5rem; text-align: center; }
        .header h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
        .header p { font-size: 0.875rem; color: var(--text-muted); }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--text-muted); }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border);
            background: var(--input-bg);
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        
        .btn-submit {
            width: 100%;
            padding: 0.875rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-submit:hover { background: var(--primary-hover); }
        
        .success-message {
            text-align: center;
            padding: 2rem;
        }
        .success-message i { font-size: 3rem; color: #22c55e; margin-bottom: 1rem; }
        .success-message h2 { font-size: 1.5rem; margin-bottom: 0.5rem; }
    </style>
</head>
<body>

<div class="form-container">
    <?php if ($sucesso): ?>
        <div class="success-message">
            <i class="fa-solid fa-circle-check"></i>
            <h2>Obrigado!</h2>
            <p>Recebemos suas informações e entraremos em contato em breve.</p>
        </div>
    <?php else: ?>
        <div class="header">
            <h1>Interesse em <?php echo htmlspecialchars($campanha['nome']); ?></h1>
            <p>Preencha os dados abaixo para receber um contato exclusivo.</p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Seu Nome</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: João Silva" required>
            </div>
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="email" class="form-control" placeholder="email@exemplo.com">
            </div>
            <div class="form-group">
                <label>WhatsApp (DDD + Número)</label>
                <input type="text" name="whatsapp" class="form-control" placeholder="(00) 00000-0000" id="whatsapp" required>
            </div>
            
            <button type="submit" class="btn-submit">
                Enviar Informações <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
// Máscara Simples para WhatsApp
document.getElementById('whatsapp').addEventListener('input', function (e) {
    let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
    e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
});
</script>

</body>
</html>
