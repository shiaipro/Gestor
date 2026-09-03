<?php
require_once '../config.php';
$custom_title = "Torneios / Competições";
$custom_subtitle = "Crie um novo evento exclusivo para os alunos da sua unidade.";
$back_link = "competicoes.php";
$back_text = "Voltar para Lista";
include 'header.php';

$unidade_id = getUnidadeId();
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $data_evento = $_POST['data_evento'] ?? '';
    $localizacao = $_POST['localizacao'] ?? '';

    if ($nome && $data_evento) {
        try {
            $stmt = $pdo->prepare("INSERT INTO competicoes (unidade_id, nome, data_evento, localizacao, status) VALUES (?, ?, ?, ?, 'aberto')");
            $stmt->execute([$unidade_id, $nome, $data_evento, $localizacao]);
            $mensagem = "Competição cadastrada com sucesso!";
            header("refresh:2;url=competicoes.php");
        } catch (PDOException $e) {
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    } else {
        $erro = "Nome e data são obrigatórios.";
    }
}
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

            <div class="card" style="padding: 0; border: none; box-shadow: none; background: transparent;">
                <form method="POST">
                    <div style="background: #ffffff; padding: 2.5rem; border: 1px solid #f1f5f9; margin-bottom: 2rem;">
                        <h2 class="section-title-sq">Detalhes do Evento</h2>

                        <div class="form-group" style="margin-bottom: 2rem;">
                            <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Nome do Evento</label>
                            <input type="text" name="nome" class="form-control" required placeholder="Ex: Open Kids Jiu-Jitsu"
                                style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                            <div class="form-group">
                                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Data do Evento</label>
                                <input type="date" name="data_evento" class="form-control" required
                                    style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Localização (Opcional)</label>
                                <input type="text" name="localizacao" class="form-control" placeholder="Ex: Ginásio Municipal"
                                    style="padding:1.1rem; border:1px solid #e2e8f0; background: #f8fafc; width:100%; font-size: 0.95rem; border-radius: 0;">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 3rem; display: flex; gap: 1.5rem;  padding-top: 2.5rem;">
                        <button type="submit" class="btn-sq" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fa-solid fa-save"></i> CADASTRAR TORNEIO
                        </button>
                        <a href="competicoes.php" class="btn-sq-outline" style="flex: 1; height: 3.5rem; text-decoration: none; display: flex; align-items: center; justify-content: center;">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Coluna de Dicas (Sidebar) -->
        <div style="background: #f8fafc; padding: 2.5rem; position: sticky; top: 1.5rem; border: 1px solid #e2e8f0;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-lightbulb" style="color: #08153a;"></i> Organização de Torneios
            </h3>

            <div style="display: grid; gap: 1.5rem;">
                <div style="padding-bottom:1.25rem; border-bottom:1px solid #e2e8f0;">
                    <h4 style="font-size:0.85rem; font-weight:750; color:#1e293b; margin-bottom:0.5rem;">Planejamento Inicial</h4>
                    <p style="font-size:0.8rem; color:#64748b; line-height:1.6; margin:0;">
                        Definir o nome e a data são os primeiros passos para que os alunos possam se programar e garantir a participação.
                    </p>
                </div>
                <div style="padding-bottom:1.25rem; border-bottom:1px solid #e2e8f0;">
                    <h4 style="font-size:0.85rem; font-weight:750; color:#1e293b; margin-bottom:0.5rem;">Divulgação Precoce</h4>
                    <p style="font-size:0.8rem; color:#64748b; line-height:1.6; margin:0;">
                        Quanto antes o torneio for cadastrado, mais tempo os alunos terão para se preparar e se inscrever, aumentando o engajamento.
                    </p>
                </div>
                <div style="padding-bottom:1.25rem; border-bottom:1px solid #e2e8f0;">
                    <h4 style="font-size:0.85rem; font-weight:750; color:#1e293b; margin-bottom:0.5rem;">Localização Clara</h4>
                    <p style="font-size:0.8rem; color:#64748b; line-height:1.6; margin:0;">
                        Informe o local exato para facilitar a logística dos responsáveis e evitar dúvidas de última hora no dia do evento.
                    </p>
                </div>
            </div>

            <div style="margin-top: 2.5rem; padding: 1.25rem; background: rgba(163, 230, 53, 0.05); border: 1px dashed rgba(163, 230, 53, 0.3);">
                <p style="font-size: 0.75rem; color: #4a5d23; line-height: 1.6; margin: 0; font-style: italic;">
                    <i class="fa-solid fa-circle-info" style="margin-right: 5px;"></i>
                    <strong>Dica:</strong> Após cadastrar, você poderá gerenciar as inscrições e categorias clicando em "Editar / Inscritos" na lista principal.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>