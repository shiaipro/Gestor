<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>SHIAI PRO - Mestre</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/Gestor/manifest.json">
    <link rel="shortcut icon" href="/favicon/favicon.ico">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboard_v2.css?v=<?php echo time(); ?>">

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
            var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl)
            });
        });
    </script>
</head>

<body>
    <?php
    if (!ehMestre()) {
        header('Location: ../login.php');
        exit;
    }
    ?>

    <div class="app-container">
        <?php
        try {
            // Buscar dados do usuário logado para o avatar
            $stmt_user = $pdo->prepare("SELECT foto FROM usuarios WHERE id = ?");
            $stmt_user->execute([$_SESSION['usuario_id']]);
            $usuario_sessao = $stmt_user->fetch();
        } catch (Exception $e) {}
        ?>

        <!-- Sidebar Esquerda (Full Height) -->
        <aside class="sidebar-left-v2">
            <div class="logo-square" style="overflow: hidden; width: 100px; height: 100px; background: var(--brand); display: flex; align-items: center; justify-content: center;">
                <img src="https://shiaipro.com.br/Gestor/assets/img/logotipos/6.png" style="width: 100%; height: 100%; object-fit: cover;">
            </div>

            <div style="margin-top: 20px; display: flex; flex-direction: column; align-items: center; width: 100%;">
                <a href="index.php" class="nav-link-left-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                </a>
                <a href="unidades.php" class="nav-link-left-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'unidades.php' || basename($_SERVER['PHP_SELF']) == 'nova_unidade.php' || basename($_SERVER['PHP_SELF']) == 'editar_unidade.php') ? 'active' : ''; ?>" title="Academias">
                    <i class="fa-solid fa-building"></i>
                </a>
                <a href="planos.php" class="nav-link-left-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'planos.php' || basename($_SERVER['PHP_SELF']) == 'novo_plano.php' || basename($_SERVER['PHP_SELF']) == 'editar_plano.php') ? 'active' : ''; ?>" title="Planos">
                    <i class="fa-solid fa-layer-group"></i>
                </a>
                <a href="config_asaas.php" class="nav-link-left-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'config_asaas.php') ? 'active' : ''; ?>" title="Pagamentos">
                    <i class="fa-solid fa-credit-card"></i>
                </a>

                <div style="width: 40px; height: 1px; background: rgba(255, 255, 255, 0.1); margin: 10px 0;"></div>

                <a href="adm.php" class="nav-link-left-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'adm.php') ? 'active' : ''; ?>" title="Administrativo">
                    <i class="fa-solid fa-folder-tree"></i>
                </a>
                <a href="comercial.php" class="nav-link-left-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'comercial.php') ? 'active' : ''; ?>" title="Comercial">
                    <i class="fa-solid fa-handshake"></i>
                </a>
                <a href="marketing.php" class="nav-link-left-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'marketing.php') ? 'active' : ''; ?>" title="Marketing">
                    <i class="fa-solid fa-bullhorn"></i>
                </a>
                <a href="eventos.php" class="nav-link-left-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'eventos.php') ? 'active' : ''; ?>" title="Eventos">
                    <i class="fa-solid fa-star"></i>
                </a>
                
                <a href="logs_acesso.php" class="nav-link-left-sq <?php echo (basename($_SERVER['PHP_SELF']) == 'logs_acesso.php') ? 'active' : ''; ?>" title="Logs de Acesso">
                    <i class="fa-solid fa-shield-halved"></i>
                </a>

                <div style="margin-top: 30px; padding-bottom: 20px;">
                    <a href="../logout.php" class="nav-link-left-sq" title="Sair" style="color: #EF4444;">
                        <i class="fa-solid fa-power-off"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Wrapper para Cabeçalho e Conteúdo -->
        <div class="main-content-wrapper">
            <!-- Top Header V2 (Square) -->
            <header class="top-header-v2">
                <div class="header-left">
                    <div class="header-breadcrumb-sq">
                        <span>GESTÃO</span>
                        <i class="fa-solid fa-chevron-right"></i>
                        <span class="active">MESTRE</span>
                    </div>
                </div>

                <div class="header-right">
                    <div class="search-bar-sq">
                        <i class="fa-solid fa-magnifying-glass" style="color: var(--brand-gold);"></i>
                        <input type="text" placeholder="Pesquisar...">
                    </div>

                    <div class="header-divider-sq"></div>

                    <div class="header-icon-btn">
                        <i class="fa-regular fa-bell"></i>
                        <span class="badge-dot"></span>
                    </div>

                    <div class="header-divider-sq"></div>
                    
                    <div class="dropdown">
                        <button class="user-profile dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="display: flex; align-items: center; gap: 15px; cursor: pointer; background: none; border: none; padding: 0; outline: none !important; color: inherit; font-family: inherit;">
                            <div style="text-align: right; line-height: 1.2;">
                                <div style="font-weight: 800; font-size: 14px;"><?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'Mestre'); ?></div>
                                <div style="font-size: 12px; color: var(--text-muted);">Painel Administrador</div>
                            </div>
                            <div class="avatar-sq" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($usuario_sessao['foto'])): ?>
                                    <img src="../uploads/perfil/<?php echo $usuario_sessao['foto']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    M
                                <?php endif; ?>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown" style="border-radius: 0 !important; border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 10px;">
                            <li><a class="dropdown-item" href="perfil.php" style="font-weight: 600; padding: 10px 15px;"><i class="fa-solid fa-user-circle me-2"></i> Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="configuracoes.php" style="font-weight: 600; padding: 10px 15px;"><i class="fa-solid fa-cog me-2"></i> Configurações Globais</a></li>
                            <li><a class="dropdown-item" href="logs_acesso.php" style="font-weight: 600; padding: 10px 15px;"><i class="fa-solid fa-shield-halved me-2"></i> Logs de Acesso</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php" style="font-weight: 600; padding: 10px 15px;"><i class="fa-solid fa-right-from-bracket me-2"></i> Sair do Sistema</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Layout Wrapper (Left Sidebar | Main | Sidebar Right) -->
            <div class="main-wrapper-v2">
                <!-- PWA Install Banner -->
                <div id="install-app-banner" style="display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #08153a; color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 10000; align-items: center; gap: 15px; flex-wrap: wrap; justify-content: center;">
                    <div style="display: flex; flex-direction: column; text-align: center;">
                        <span style="font-weight: bold; font-size: 14px;">Instalar SHIAIPRO</span>
                        <span style="font-size: 12px; opacity: 0.8;">Adicione o app à tela inicial para acesso rápido</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="btn-install-app" style="background: var(--brand, #ffc107); border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; color: #fff;">Instalar</button>
                        <button id="btn-close-install" style="background: transparent; border: 1px solid rgba(255,255,255,0.3); padding: 8px 15px; border-radius: 4px; color: white; cursor: pointer;">Agora não</button>
                    </div>
                </div>

                <script>
                    // Register Service Worker
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.register('/Gestor/sw.js')
                            .then(reg => console.log('Service Worker Registrado!', reg))
                            .catch(err => console.error('Service Worker falhou', err));
                    }

                    document.addEventListener('DOMContentLoaded', () => {
                        let deferredPrompt;
                        const installBanner = document.getElementById('install-app-banner');
                        const installBtn = document.getElementById('btn-install-app');
                        const closeBtn = document.getElementById('btn-close-install');

                        if (localStorage.getItem('hideInstallBanner') === 'true') {
                            return; 
                        }

                        window.addEventListener('beforeinstallprompt', (e) => {
                            e.preventDefault();
                            deferredPrompt = e;
                            installBanner.style.display = 'flex';
                        });

                        installBtn.addEventListener('click', async () => {
                            installBanner.style.display = 'none';
                            if (deferredPrompt) {
                                deferredPrompt.prompt();
                                const { outcome } = await deferredPrompt.userChoice;
                                if (outcome === 'accepted') {
                                    console.log('User accepted the install prompt');
                                }
                                deferredPrompt = null;
                            }
                        });

                        closeBtn.addEventListener('click', () => {
                            installBanner.style.display = 'none';
                            localStorage.setItem('hideInstallBanner', 'true');
                        });

                        window.addEventListener('appinstalled', () => {
                            installBanner.style.display = 'none';
                            deferredPrompt = null;
                            localStorage.setItem('hideInstallBanner', 'true');
                        });
                    });
                </script>
                <main class="content-area-v2">