            </main>

            <?php if (!isset($esconder_sidebar) || !$esconder_sidebar): ?>
            <!-- Sidebar à Direita -->
            <aside class="sidebar-right-v2">
                <div class="sidebar-section-sq">
                    <h3>Status Global</h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="background: var(--light-green); padding: 15px; border: 1px solid var(--primary-green);">
                            <div style="font-size: 11px; color: var(--primary-green); font-weight: 800; margin-bottom: 5px;">SISTEMA ONLINE</div>
                            <div style="font-size: 13px; color: #15803D;">Todos os serviços operacionais.</div>
                        </div>
                    </div>
                </div>

                <div class="sidebar-section-sq">
                    <h3>Ações Rápidas</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="nova_unidade.php" class="btn-sq" style="text-decoration: none;">
                            <i class="fa-solid fa-plus-circle"></i> Nova Academia
                        </a>
                        <a href="planos.php" class="btn-sq-outline" style="text-decoration: none;">
                            <i class="fa-solid fa-layer-group"></i> Gerenciar Planos
                        </a>
                        <a href="faturas.php" class="btn-sq-outline" style="text-decoration: none;">
                            <i class="fa-solid fa-file-invoice-dollar"></i> Ver Faturas
                        </a>
                    </div>
                </div>


            </aside>
            <?php endif; ?>
        </div> <!-- /main-wrapper-v2 -->

        <footer class="footer-v2">
            &copy; <?php echo date('Y'); ?> SHIAI PRO - Administração Central.
        </footer>
    </div> <!-- /main-content-wrapper -->
</div> <!-- /app-container -->

</body>
</html>