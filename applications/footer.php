<?php if (!isset($agentAccount)) { http_response_code(404); exit; } ?>
<script>
window.agentShellLabels = <?= json_encode([
    'filePreview' => $desk['filePreview'], 'pdfPreview' => $desk['pdfPreview'],
    'badPreview' => $desk['badPreview'], 'languageFailed' => $desk['languageFailed']
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
</script>
<script src="./js/agent-shell.js"></script>
<?php if (!$agentManagedScreen): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php endif; ?>
</body>
</html>
