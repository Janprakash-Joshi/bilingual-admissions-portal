<?php require_once __DIR__ . '/auth.php';
$adminJs = array_intersect_key(
    $adminWords,
    array_flip(['sending', 'generating', 'delete_confirm', 'failed', 'discard']),
);
?>
<script type="application/json" id="admin-js-labels"><?= json_encode(
    $adminJs,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE,
) ?></script>
<?php if (
    empty($managedChrome)
): ?><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><?php endif; ?>
<script src="./js/script.js"></script>
</body></html>
