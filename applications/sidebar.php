<?php
if (!isset($agentAccount)) {
    http_response_code(404);
    exit;
}
$agentMenu = [
    'workspace' => [
        'index' => ['./', 'dashboard'], 'my_applications' => ['my_applications', 'applications'],
        'appointment-request' => ['appointment-request', 'appointments'], 'event' => ['event', 'events'],
        'media-gallery' => ['media-gallery', 'gallery'], 'help-desk' => ['help-desk', 'help'],
    ],
    'account' => [
        'profile' => ['profile', 'profile'], 'reset_password' => ['reset_password', 'password'],
        'logout' => ['logout', 'logout'],
    ],
];
?>
<aside id="sidebar" class="ad-rail" aria-label="<?= $desk['navigation'] ?>" data-ad-aria="navigation">
    <a class="ad-brand" href="./"><img src="../img/logo.png" alt="<?= $desk['portalLogo'] ?>" data-ad-alt="portalLogo"></a>
    <button type="button" class="ad-drawer-close" aria-label="<?= $desk['menuClose'] ?>" data-ad-aria="menuClose">×</button>
    <nav>
        <?php foreach ($agentMenu as $section => $links): ?>
            <p data-ad-label="<?= $section ?>" class="ad-menu-heading"><?= $desk[$section] ?></p>
            <ul>
                <?php foreach ($links as $screen => [$href,$key]): ?>
                    <li><a href="<?= $href ?>" <?= $agentScreen === $screen ? 'aria-current="page"' : '' ?>>
                        <svg class="ad-icon" aria-hidden="true"><use href="./img/agent-icons.svg#<?= $key ?>"></use></svg>
                        <span data-ad-label="<?= $key ?>"><?= $desk[$key] ?></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>
</aside>
<button class="ad-scrim" type="button" hidden aria-label="<?= $desk['menuClose'] ?>" data-ad-aria="menuClose"></button>
