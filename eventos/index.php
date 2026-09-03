<?php
require_once '../Gestor/config.php';

// Busca eventos públicos cadastrados pelas unidades (academias) ativas do SHIAI PRO.
$eventos = [];

// 1. Exames de Faixa (graduações)
$sql_grad = "SELECT eg.id, eg.titulo AS nome, eg.descricao, eg.data_evento AS data_evento, eg.horario, eg.local,
                    u.nome AS unidade_nome, u.cidade, u.estado
             FROM eventos_graduacao eg
             JOIN unidades u ON u.id = eg.unidade_id
             WHERE eg.data_evento >= CURDATE() AND eg.status = 'agendado' AND u.status = 'ativo'
             ORDER BY eg.data_evento ASC
             LIMIT 20";
foreach ($pdo->query($sql_grad)->fetchAll() as $r) {
    $eventos[] = [
        'tipo_slug' => 'exame',
        'id'     => $r['id'],
        'tipo'   => 'Exame de Faixa',
        'icone'  => 'fa-user-ninja',
        'nome'   => $r['nome'],
        'desc'   => $r['descricao'] ?: 'Exame de graduação de faixa aberto aos alunos da unidade.',
        'data'   => $r['data_evento'],
        'hora'   => $r['horario'] ?: null,
        'local'  => $r['local'] ?: trim(($r['cidade'] ?? '') . ($r['estado'] ? ' - ' . $r['estado'] : '')),
        'unidade'=> $r['unidade_nome'],
    ];
}

// 2. Torneios internos das unidades
$sql_tor = "SELECT c.id, c.nome, c.data_evento, c.localizacao,
                   u.nome AS unidade_nome, u.cidade, u.estado
            FROM competicoes c
            JOIN unidades u ON u.id = c.unidade_id
            WHERE c.data_evento >= CURDATE() AND c.status = 'aberto' AND u.status = 'ativo'
            ORDER BY c.data_evento ASC
            LIMIT 20";
foreach ($pdo->query($sql_tor)->fetchAll() as $r) {
    $eventos[] = [
        'tipo_slug' => 'torneio',
        'id'     => $r['id'],
        'tipo'   => 'Torneio',
        'icone'  => 'fa-trophy',
        'nome'   => $r['nome'],
        'desc'   => 'Torneio interno organizado pela unidade, aberto aos alunos e convidados.',
        'data'   => $r['data_evento'],
        'hora'   => null,
        'local'  => $r['localizacao'] ?: trim(($r['cidade'] ?? '') . ($r['estado'] ? ' - ' . $r['estado'] : '')),
        'unidade'=> $r['unidade_nome'],
    ];
}

// 3. Competições oficiais (calendário externo)
$sql_ofi = "SELECT co.id, co.nome, co.data_inicio, co.localizacao, co.organizacao,
                   u.nome AS unidade_nome, u.cidade, u.estado
            FROM competicoes_oficiais co
            JOIN unidades u ON u.id = co.unidade_id
            WHERE co.data_inicio >= CURDATE() AND co.status = 'aberto' AND u.status = 'ativo'
            ORDER BY co.data_inicio ASC
            LIMIT 20";
foreach ($pdo->query($sql_ofi)->fetchAll() as $r) {
    $eventos[] = [
        'tipo_slug' => 'oficial',
        'id'     => $r['id'],
        'tipo'   => 'Competição Oficial',
        'icone'  => 'fa-medal',
        'nome'   => $r['nome'],
        'desc'   => $r['organizacao'] ? ('Competição oficial organizada por ' . $r['organizacao'] . '.') : 'Competição do calendário oficial.',
        'data'   => $r['data_inicio'],
        'hora'   => null,
        'local'  => $r['localizacao'] ?: trim(($r['cidade'] ?? '') . ($r['estado'] ? ' - ' . $r['estado'] : '')),
        'unidade'=> $r['unidade_nome'],
    ];
}

// Ordena todos os eventos por data
usort($eventos, function ($a, $b) {
    return strtotime($a['data']) <=> strtotime($b['data']);
});

$meses_pt = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-7TR2SN75QF"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());

    gtag('config', 'G-7TR2SN75QF');
  </script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Eventos | SHIAI PRO</title>
  <meta name="description"
    content="Confira os próximos eventos, exames de faixa, campeonatos e workshops do ecossistema SHIAI PRO.">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://www.shiaipro.com.br/eventos/">

  <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
  <link rel="manifest" href="/favicon/site.webmanifest">
  <link rel="shortcut icon" href="/favicon/favicon.ico">

  <link rel="stylesheet" href="../Tema/css/reset.css" />
  <link rel="stylesheet" href="../Tema/css/style.css" />
  <link rel="stylesheet" href="../Tema/css/animations.css" />

  <link
    href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <style>
    /* ── Página de Eventos ── */
    .eventos-hero {
      padding: 160px 0 80px;
      background: var(--brand-bg);
      text-align: center;
    }

    .eventos-hero .section__tag {
      margin: 0 auto 16px;
    }

    .eventos-hero h1 {
      font-family: 'Sora', sans-serif;
      font-size: 44px;
      font-weight: 800;
      color: var(--brand-darker);
      line-height: 1.15;
      margin-bottom: 16px;
    }

    .eventos-hero p {
      max-width: 560px;
      margin: 0 auto;
      color: var(--gray-500);
      font-size: 17px;
      line-height: 1.6;
    }

    .eventos-filtros {
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 32px;
    }

    .filtro-chip {
      padding: 8px 18px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: #fff;
      font-size: 14px;
      font-weight: 600;
      color: var(--gray-500);
      cursor: pointer;
      transition: all .2s ease;
    }

    .filtro-chip:hover,
    .filtro-chip.is-active {
      background: var(--brand);
      border-color: var(--brand);
      color: #fff;
    }

    .eventos-grid {
      padding: 80px 0;
    }

    .eventos-grid__list {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
      margin-top: 40px;
    }

    .evento-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--r-md);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: transform .25s ease, box-shadow .25s ease;
    }

    .evento-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 16px 40px rgba(19, 48, 128, .12);
    }

    .evento-card__banner {
      height: 160px;
      background: linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%);
      position: relative;
      display: flex;
      align-items: flex-end;
      padding: 16px;
    }

    .evento-card__banner i {
      font-size: 56px;
      color: rgba(255, 255, 255, .25);
      position: absolute;
      top: 16px;
      right: 16px;
    }

    .evento-card__data {
      background: #fff;
      color: var(--brand-darker);
      border-radius: 10px;
      padding: 8px 12px;
      text-align: center;
      line-height: 1.1;
      box-shadow: 0 6px 16px rgba(0, 0, 0, .15);
    }

    .evento-card__data strong {
      display: block;
      font-size: 20px;
      font-weight: 800;
    }

    .evento-card__data span {
      display: block;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--gray-500);
    }

    .evento-card__body {
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      flex: 1;
    }

    .evento-card__tag {
      align-self: flex-start;
      background: var(--brand-muted);
      color: var(--brand-darker);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      padding: 4px 10px;
      border-radius: 999px;
    }

    .evento-card__body h3 {
      font-family: 'Sora', sans-serif;
      font-size: 19px;
      font-weight: 700;
      color: var(--text, #111);
    }

    .evento-card__body p {
      font-size: 14px;
      color: var(--gray-500);
      line-height: 1.55;
      flex: 1;
    }

    .evento-card__meta {
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-size: 13px;
      color: var(--gray-500);
    }

    .evento-card__meta i {
      width: 16px;
      color: var(--brand);
    }

    .evento-card__footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 4px;
    }

    .evento-card__vagas {
      font-size: 12px;
      font-weight: 600;
      color: var(--gray-400);
    }

    @media (max-width: 992px) {
      .eventos-grid__list {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 640px) {
      .eventos-hero h1 {
        font-size: 30px;
      }

      .eventos-grid__list {
        grid-template-columns: 1fr;
      }
    }

    .eventos-cta {
      background: var(--brand);
      padding: 70px 0;
      text-align: center;
      color: #fff;
    }

    .eventos-cta h2 {
      font-family: 'Sora', sans-serif;
      font-size: 30px;
      font-weight: 800;
      margin-bottom: 12px;
    }

    .eventos-cta p {
      color: rgba(255, 255, 255, .8);
      max-width: 480px;
      margin: 0 auto 28px;
    }
  </style>
</head>

<body>

  <!-- ╔══════════════════════════════════════╗ -->
  <!-- ║              HEADER                  ║ -->
  <!-- ╚══════════════════════════════════════╝ -->
  <header class="header" id="header">
    <div class="container header__inner">
      <a href="../Tema/index.html" class="logo">
        <img src="../Gestor/assets/img/logotipos/10.png" alt="SHIAIPRO Logo" style="height: 40px; width: auto;">
      </a>
      <nav class="nav" id="nav">
        <a href="../Tema/index.html#features" class="nav__link">Funcionalidades</a>
        <a href="../Tema/index.html#plans" class="nav__link">Planos</a>
        <a href="index.php" class="nav__link">Eventos</a>
        <a href="../Tema/index.html#faq" class="nav__link">FAQ</a>
      </nav>
      <div class="header__actions">
        <a href="/Gestor" class="btn btn--dark-outline" target="_blank">Entrar</a>
        <a href="https://wa.me/5545988128964" class="btn btn--dark" target="_blank">Teste grátis</a>
      </div>
      <button class="hamburger" id="hamburger" aria-label="Abrir menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <!-- ╔══════════════════════════════════════╗ -->
  <!-- ║              HERO                    ║ -->
  <!-- ╚══════════════════════════════════════╝ -->
  <section class="eventos-hero">
    <div class="container">
      <div class="section__tag">Agenda SHIAI PRO</div>
      <h1>Eventos, exames de faixa e campeonatos</h1>
      <p>Acompanhe os próximos eventos das academias que usam o SHIAI PRO: exames de graduação, campeonatos,
        seminários e workshops abertos à comunidade.</p>
      <div class="eventos-filtros">
        <span class="filtro-chip is-active">Todos</span>
        <span class="filtro-chip">Exame de Faixa</span>
        <span class="filtro-chip">Campeonato</span>
        <span class="filtro-chip">Workshop</span>
      </div>
    </div>
  </section>

  <!-- ╔══════════════════════════════════════╗ -->
  <!-- ║         GRID DE EVENTOS              ║ -->
  <!-- ╚══════════════════════════════════════╝ -->
  <section class="eventos-grid section--white" id="lista">
    <div class="container">
      <?php if (empty($eventos)): ?>
        <div style="text-align:center; padding:60px 20px; color:var(--gray-500);">
          <i class="fa-regular fa-calendar" style="font-size:40px; margin-bottom:16px; display:block; color:var(--gray-400);"></i>
          <p>Nenhum evento agendado no momento. Volte em breve para conferir as próximas novidades das academias
            SHIAI PRO.</p>
        </div>
      <?php else: ?>
        <div class="eventos-grid__list">
          <?php foreach ($eventos as $i => $ev):
            $dia = date('d', strtotime($ev['data']));
            $mes = $meses_pt[date('m', strtotime($ev['data']))];
          ?>
            <article class="evento-card" data-aos="fade-up" data-aos-delay="<?php echo ($i % 3) * 100; ?>">
              <div class="evento-card__banner">
                <i class="fa-solid <?php echo htmlspecialchars($ev['icone']); ?>"></i>
                <div class="evento-card__data">
                  <strong><?php echo htmlspecialchars($dia); ?></strong>
                  <span><?php echo htmlspecialchars($mes); ?></span>
                </div>
              </div>
              <div class="evento-card__body">
                <span class="evento-card__tag"><?php echo htmlspecialchars($ev['tipo']); ?></span>
                <h3><?php echo htmlspecialchars($ev['nome']); ?></h3>
                <p><?php echo htmlspecialchars($ev['desc']); ?></p>
                <div class="evento-card__meta">
                  <?php if (!empty($ev['local'])): ?>
                    <span><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($ev['local']); ?></span>
                  <?php endif; ?>
                  <?php if (!empty($ev['hora'])): ?>
                    <span><i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars(substr($ev['hora'], 0, 5)); ?></span>
                  <?php endif; ?>
                </div>
                <div class="evento-card__footer">
                  <span class="evento-card__vagas"><?php echo htmlspecialchars($ev['unidade']); ?></span>
                  <a href="evento.php?tipo=<?php echo urlencode($ev['tipo_slug']); ?>&id=<?php echo (int)$ev['id']; ?>"
                    class="btn btn--dark-outline">Saiba mais</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ╔══════════════════════════════════════╗ -->
  <!-- ║              CTA                     ║ -->
  <!-- ╚══════════════════════════════════════╝ -->
  <section class="eventos-cta">
    <div class="container">
      <h2>Sua academia também vai organizar um evento?</h2>
      <p>Com o SHIAI PRO você gerencia inscrições, financeiro e checklist do exame de faixa direto pelo sistema.</p>
      <a href="https://wa.me/5545988128964" class="btn btn--dark btn--lg" target="_blank" style="background:#fff;color:var(--brand-darker);border-color:#fff;">Falar com um especialista</a>
    </div>
  </section>

  <!-- ╔══════════════════════════════════════╗ -->
  <!-- ║              FOOTER                  ║ -->
  <!-- ╚══════════════════════════════════════╝ -->
  <footer class="footer" id="footer">
    <div class="container footer__inner">
      <div class="footer__top">
        <div class="footer__brand">
          <a href="../Tema/index.html" class="logo logo--light">
            <img src="https://shiaipro.com.br/Gestor/assets/img/logotipos/10.png" alt="SHIAIPRO Logo"
              style="height: 40px; width: auto;">
          </a>
          <p>O sistema de gestão completo para academias de judô e jiu-jitsu do Brasil.</p>
          <div class="footer__social">
            <a href="#" aria-label="Instagram" class="social__link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                <rect x="2" y="2" width="20" height="20" rx="5" />
                <circle cx="12" cy="12" r="5" />
                <circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" stroke="none" />
              </svg>
            </a>
            <a href="#" aria-label="YouTube" class="social__link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                <path
                  d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z" />
                <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="currentColor" stroke="none" />
              </svg>
            </a>
            <a href="#" aria-label="LinkedIn" class="social__link">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z" />
                <rect x="2" y="9" width="4" height="12" />
                <circle cx="4" cy="4" r="2" />
              </svg>
            </a>
          </div>
        </div>
        <div class="footer__cols">
          <div class="footer__col">
            <h4>Produto</h4>
            <a href="../Tema/produto.html#funcionalidades">Funcionalidades</a>
            <a href="../Tema/produto.html#precos">Preços</a>
          </div>
          <div class="footer__col">
            <h4>Recursos</h4>
            <a href="index.php">Eventos</a>
            <a href="#">Central de Ajuda</a>
          </div>
          <div class="footer__col">
            <h4>Empresa</h4>
            <a href="../Tema/sobre-nos.html#sobre">Sobre nós</a>
            <a href="../Tema/sobre-nos.html#contato">Contato</a>
          </div>
          <div class="footer__col">
            <h4>Legal</h4>
            <a href="../Tema/legal.html#termos">Termos de Uso</a>
            <a href="../Tema/legal.html#privacidade">Privacidade</a>
          </div>
        </div>
      </div>
      <div class="footer__bottom">
        <p class="footer__copy">© 2025 SHIAI PRO · Feito com <i class="fa-solid fa-user-ninja"></i> no Brasil</p>
        <p class="footer__cnpj">R RAMIREZ | 45.949.568/0001-91</p>
      </div>
    </div>
  </footer>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.8/ScrollMagic.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.8/plugins/animation.gsap.min.js"></script>
  <script src="../Tema/js/components.js"></script>
  <script src="../Tema/js/main.js"></script>

  <script>
    document.querySelectorAll('.filtro-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        document.querySelectorAll('.filtro-chip').forEach(function (c) { c.classList.remove('is-active'); });
        chip.classList.add('is-active');
      });
    });
  </script>
</body>

</html>
