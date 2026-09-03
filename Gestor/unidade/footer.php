            </main>

            <?php if ((!isset($esconder_sidebar) || !$esconder_sidebar) && !restringirVisaoAsProprisTurmas()): ?>
            <!-- Sidebar à Direita -->
            <aside class="sidebar-right-v2">
                <div class="sidebar-section-sq">
                    <h3>Atividade Recente</h3>
                    <div style="font-size: var(--fs-sm); color: var(--text-muted); display: flex; flex-direction: column; gap: 12px;">
                        <?php
                        $aluno_recente = null;
                        $pag_recente = null;
                        if (isset($pdo) && isset($unidade_id)) {
                            // Buscar último aluno
                            $stmt_ult_aluno = $pdo->prepare("SELECT nome_completo FROM alunos WHERE unidade_id = ? ORDER BY id DESC LIMIT 1");
                            $stmt_ult_aluno->execute([$unidade_id]);
                            $aluno_recente = $stmt_ult_aluno->fetch();

                            // Buscar último pagamento (Prioriza lançamentos, fallback para mensalidade se não tiver tabela unificada ainda)
                            $stmt_ult_pag = $pdo->prepare("SELECT descricao FROM financeiro_lancamentos WHERE unidade_id = ? AND status = 'pago' AND tipo = 'receita' ORDER BY id DESC LIMIT 1");
                            $stmt_ult_pag->execute([$unidade_id]);
                            $pag_recente = $stmt_ult_pag->fetch();
                            
                            if (!$pag_recente) {
                                $stmt_ult_men = $pdo->prepare("SELECT a.nome_completo as descricao FROM mensalidades m JOIN alunos a ON m.aluno_id = a.id WHERE m.unidade_id = ? AND m.status = 'pago' ORDER BY m.id DESC LIMIT 1");
                                $stmt_ult_men->execute([$unidade_id]);
                                $pag_recente = $stmt_ult_men->fetch();
                                if ($pag_recente) {
                                    $pag_recente['descricao'] = "Mensalidade - " . $pag_recente['descricao'];
                                }
                            }
                        }
                        ?>
                        
                        <?php if ($aluno_recente): ?>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <div style="width: 8px; height: 8px; background: var(--primary-green); margin-top: 5px; flex-shrink: 0;"></div>
                            <div>Novo aluno matriculado: <strong><?php echo htmlspecialchars($aluno_recente['nome_completo']); ?></strong>.</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pag_recente): ?>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <div style="width: 8px; height: 8px; background: #3B82F6; margin-top: 5px; flex-shrink: 0;"></div>
                            <div>Pagamento confirmado: <strong><?php echo htmlspecialchars($pag_recente['descricao']); ?></strong>.</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!$aluno_recente && !$pag_recente): ?>
                        <div style="font-size: var(--fs-xs); opacity: 0.7;">Nenhuma atividade recente registrada.</div>
                        <?php endif; ?>
                    </div>
                </div>
 
                <div class="sidebar-section-sq">
                    <h3>Ações Rápidas</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php if (isset($custom_shortcuts) && is_array($custom_shortcuts)): ?>
                            <?php foreach ($custom_shortcuts as $s): ?>
                                <a href="<?php echo $s['link']; ?>" class="<?php echo $s['class'] ?? 'btn-sq'; ?>" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 2px;">
                                    <i class="<?php echo $s['icon']; ?>"></i> <?php echo $s['label']; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <a href="novo_aluno.php" class="btn-sq" style="text-decoration: none;">
                                <i class="fa-solid fa-plus-circle"></i> Matricular Aluno
                            </a>
                            <a href="financeiro.php" class="btn-sq-outline" style="text-decoration: none;">
                                <i class="fa-solid fa-money-bill-transfer"></i> Lançar Pagamento
                            </a>
                            <a href="turmas.php" class="btn-sq-outline" style="text-decoration: none;">
                                <i class="fa-solid fa-calendar-check"></i> Chamada de Turma
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
            <?php endif; ?>
        </div> <!-- /main-wrapper-v2 -->

        <footer class="footer-v2">
            &copy; <?php echo date('Y'); ?> SHIAI PRO - Gestão Inteligente para Academias.
        </footer>
    </div> <!-- /main-content-wrapper -->
</div> <!-- /app-container -->


</body>
</html>