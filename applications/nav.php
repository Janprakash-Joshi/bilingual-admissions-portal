<?php
require_once __DIR__ . '/agent_page.php';
$agentLanguageLinks = [];
foreach (['eng', 'ja'] as $choice) {
    $agentLanguageLinks[$choice] = '?' . http_build_query(array_merge($_GET, ['lang' => $choice]));
}
?>
<!doctype html>
<html lang="<?= $lang === 'ja' ? 'ja' : 'en' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= agentHtml($desk[$agentTitleKey] . ' · ' . $desk['portal']) ?></title>
    <link rel="icon" href="../img/logo.png">
    <?php if (!$agentManagedScreen): ?>
        <link rel="stylesheet" href="./css/style.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="./css/agent-legacy-forms.css">
    <?php endif; ?>
    <link rel="stylesheet" href="./css/agent-dashboard.css">
</head>
<body class="<?= $agentManagedScreen ? 'ad-page' : 'ad-legacy' ?>">
<header class="ad-top" data-ad-language-mode="<?= $agentManagedScreen ? 'page' : 'shell' ?>" data-ad-title="<?= agentHtml($agentTitleKey) ?>">
    <div class="ad-top-start">
        <button class="ad-drawer-btn" type="button" aria-controls="sidebar" aria-expanded="false"
                aria-label="<?= agentHtml($desk['menu']) ?>" data-ad-aria="menu">
            <svg class="ad-icon" aria-hidden="true"><use href="./img/agent-icons.svg#menu"></use></svg>
        </button>
        <a class="ad-home" href="./" data-ad-label="portal"><?= $desk['portal'] ?></a>
    </div>
    <div class="ad-top-end">
        <a class="ad-action" href="applications">
            <svg class="ad-icon" aria-hidden="true"><use href="./img/agent-icons.svg#plus"></use></svg>
            <span data-ad-label="apply"><?= $desk['apply'] ?></span>
        </a>
        <nav class="ad-languages" aria-label="<?= $desk['language'] ?>" data-ad-aria="language">
            <?php foreach (['eng' => ['en','gb','English'], 'ja' => ['ja','jp','日本語']] as $choice => [$tag,$flag,$label]): ?>
                <a href="<?= agentHtml($agentLanguageLinks[$choice]) ?>" lang="<?= $tag ?>" hreflang="<?= $tag ?>"
                   data-ad-lang="<?= $choice ?>" <?= $choice === $lang ? 'aria-current="true"' : '' ?>>
                    <img src="../img/flag-<?= $flag ?>.svg" alt="" width="16" height="11">
                    <span><?= $label ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <details class="ad-account">
            <summary aria-label="<?= agentHtml($desk['accountMenu']) ?>" data-ad-aria="accountMenu">
                <span class="ad-avatar"><svg class="ad-icon" aria-hidden="true"><use href="./img/agent-icons.svg#person"></use></svg></span>
                <span class="ad-account-name"><?= agentHtml($agentAccount['company_name']) ?></span>
                <svg class="ad-chevron" aria-hidden="true"><use href="./img/agent-icons.svg#down"></use></svg>
            </summary>
            <div class="ad-account-menu">
                <span class="ad-account-email"><?= agentHtml($agentAccount['email']) ?></span>
                <a href="profile" data-ad-label="profile"><?= $desk['profile'] ?></a>
                <a href="logout" data-ad-label="logout"><?= $desk['logout'] ?></a>
            </div>
        </details>
    </div>
</header>
