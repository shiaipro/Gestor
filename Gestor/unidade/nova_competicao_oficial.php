<?php
require_once '../config.php';

$unidade_id = getUnidadeId();
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $data_inicio = $_POST['data_inicio'] ?? '';
    $data_fim = $_POST['data_fim'] ?: null;
    $localizacao = $_POST['localizacao'] ?? '';
    $organizacao = $_POST['organizacao'] ?? '';
    $responsaveis = $_POST['responsaveis'] ?? '';

    if ($nome && $data_inicio) {
        try {
            $stmt = $pdo->prepare("INSERT INTO competicoes_oficiais (unidade_id, nome, data_inicio, data_fim, localizacao, organizacao, responsaveis, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'aberto')");
            $stmt->execute([$unidade_id, $nome, $data_inicio, $data_fim, $localizacao, $organizacao, $responsaveis]);
            $mensagem = "Evento oficial cadastrado com sucesso!";
            header("refresh:2;url=competicoes_oficiais.php");
        } catch (PDOException $e) {
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    } else {
        $erro = "Nome e data de início são obrigatórios.";
    }
}

$custom_title = "Nova Competição Oficial";
$custom_subtitle = "Registre um evento do calendário oficial para gerenciar sua delegação.";
$back_link = "competicoes_oficiais.php";
$back_text = "Voltar para Lista";
include 'header.php';
?>


<div style="padding: 1.5rem;">
    <div style="display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 4rem; align-items: start;">
        <!-- Coluna Principal (Formulário) -->
        <div>
            <?php if ($mensagem): ?>
                <div class="alert"
                    style="background:#dcfce7; color:#166534; padding:1.25rem; margin-bottom:1.5rem; border: 1px solid #bbf7d0; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo $mensagem; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert"
                    style="background:#fee2e2; color:#991b1b; padding:1.25rem; margin-bottom:1.5rem; border: 1px solid #fecaca; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $erro; ?></span>
                </div>
            <?php endif; ?>

            <div style="background: transparent; border: none; box-shadow: none;">
                <form method="POST">
                    <h2 class="section-title-sq">Detalhes da Competição Oficial</h2>

                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Nome da Competição</label>
                        <input type="text" name="nome" class="form-control" required placeholder="Ex: Campeonato Brasileiro 2024"
                            style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                        <div class="form-group">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data de Início</label>
                            <input type="date" name="data_inicio" class="form-control" required
                                style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data de Término (Opcional)</label>
                            <input type="date" name="data_fim" class="form-control"
                                style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                        <div class="form-group">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Localização / Cidade</label>
                            <input type="text" name="localizacao" class="form-control" placeholder="Ex: Rio de Janeiro, RJ"
                                style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Organização / Federação</label>
                            <input type="text" name="organizacao" class="form-control" placeholder="Ex: CBJJ / IBJJF"
                                style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 3rem;">
                        <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Responsáveis pela Equipe</label>
                        <textarea name="responsaveis" class="form-control" rows="4"
                            placeholder="Liste os nomes dos responsáveis/professores que acompanharão a equipe..."
                            style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; min-height: 120px; border-radius: 0;"></textarea>
                    </div>

                    <div style="margin-top: 3rem; display: flex; gap: 1.5rem;  padding-top: 2.5rem;">
                        <button type="submit" class="btn-sq" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fa-solid fa-save"></i> CADASTRAR EVENTO OFICIAL
                        </button>
                        <a href="competicoes_oficiais.php" class="btn-sq-outline" style="flex: 1; height: 3.5rem; text-decoration: none; display: flex; align-items: center; justify-content: center;">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Coluna de Dicas (Sidebar) -->
        <div style="background: #f8fafc; padding: 2.5rem; position: sticky; top: 1.5rem; border: 1px solid #e2e8f0;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-lightbulb" style="color: #08153a;"></i> Eventos Oficiais
            </h3>

            <div style="display: grid; gap: 1.5rem;">
                <div style="padding-bottom:1.25rem; border-bottom:1px solid #e2e8f0;">
                    <h4 style="font-size:0.85rem; font-weight:750; color:#1e293b; margin-bottom:0.5rem;">Calendário Nacional</h4>
                    <p style="font-size:0.8rem; color:#64748b; line-height:1.6; margin:0;">
                        As competições oficiais ajudam a construir o ranking da sua unidade e o histórico de conquistas dos seus alunos.
                    </p>
                </div>
                <div style="padding-bottom:1.25rem; border-bottom:1px solid #e2e8f0;">
                    <h4 style="font-size:0.85rem; font-weight:750; color:#1e293b; margin-bottom:0.5rem;">Gestão de Delegações</h4>
                    <p style="font-size:0.8rem; color:#64748b; line-height:1.6; margin:0;">
                        Informe claramente quem são os professores responsáveis para que os pais e alunos saibam com quem contar durante a viagem.
                    </p>
                </div>
                <div style="padding-bottom:1.25rem; border-bottom:1px solid #e2e8f0;">
                    <h4 style="font-size:0.85rem; font-weight:750; color:#1e293b; margin-bottom:0.5rem;">Logística da Equipe</h4>
                    <p style="font-size:0.8rem; color:#64748b; line-height:1.6; margin:0;">
                        Data e local precisos garantem que todos organizem transporte e hospedagem com antecedência necessária.
                    </p>
                </div>
            </div>

            <div style="margin-top: 2.5rem; padding: 1.25rem; background: rgba(163, 230, 53, 0.05); border: 1px dashed rgba(163, 230, 53, 0.3);">
                <p style="font-size: 0.75rem; color: #4a5d23; line-height: 1.6; margin: 0; font-style: italic;">
                    <i class="fa-solid fa-circle-info" style="margin-right: 5px;"></i>
                    <strong>Dica:</strong> Após o cadastro, utilize a página principal de calendário oficial para listar e gerenciar quais atletas participarão da delegação.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>