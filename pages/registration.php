<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (
    isset($_GET["lang"]) &&
    is_string($_GET["lang"]) &&
    in_array($_GET["lang"], ["eng", "ja"], true)
) {
    $_SESSION["lang"] = $_GET["lang"];
}
$lang = in_array($_SESSION["lang"] ?? "eng", ["eng", "ja"], true)
    ? $_SESSION["lang"] ?? "eng"
    : "eng";
include __DIR__ . "/../lang/" . $lang . ".php";
include __DIR__ . "/../lang/" . $lang . ".php";
require_once __DIR__ . "/../db_connect.php";
require __DIR__ . "/agency-countries.php";
$conn->set_charset("utf8mb4");
if (empty($_SESSION["agency_csrf"])) {
    $_SESSION["agency_csrf"] = bin2hex(random_bytes(32));
}
$data = [];
$version = 0;
$savedUploads = [];
if (
    isset($_COOKIE["agency_draft"]) &&
    is_string($_COOKIE["agency_draft"]) &&
    preg_match('/^[a-f0-9]{64}$/D', $_COOKIE["agency_draft"])
) {
    $hash = hash("sha256", $_COOKIE["agency_draft"]);
    $stmt = $conn->prepare(
        "SELECT * FROM agency_registration_drafts WHERE access_hash=? AND expires_at>UTC_TIMESTAMP() AND submitted_agent_id IS NULL",
    );
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $draft = $stmt->get_result()->fetch_assoc();
    if ($draft) {
        $data = json_decode($draft["payload"], true) ?: [];
        $version = (int) $draft["version"];
        $id = (int) $draft["id"];
        $stmt = $conn->prepare(
            "SELECT id,category,document_type,original_name,issue_date,expiry_date,school_id FROM agency_registration_uploads WHERE draft_id=? AND agent_id IS NULL",
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $savedUploads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
$schools = $conn
    ->query(
        "SELECT id,name_eng,name_ja FROM agency_registration_schools WHERE active=1 ORDER BY id",
    )
    ->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="<?= $lang === "ja"
    ? "ja"
    : "en" ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(
    $text["agy_title"],
) ?></title>
    <link rel="stylesheet" href="../css/registration.css">
</head>

<body>
    <div class="registration-shell">
        <aside class="registration-sidebar"><a class="brand" href="../index.php"><img src="../img/logo.png"
                    alt="Admissions Portal"></a>
            <div class="sidebar-caption"><?= $text[
    "agy_title"
] ?></div>
            <nav id="stepNavigation"><?php foreach (
    ["head", "legal", "branches", "contacts", "links", "documents", "schools"]
    as $i => $key
): ?><button type="button" data-step="<?= $i ?>"><span><?= str_pad(
    (string) ($i + 1),
    2,
    "0",
    STR_PAD_LEFT,
) ?></span><?= $text[
    "agy_" . $key
] ?></button><?php endforeach; ?></nav><a class="sidebar-back" href="../index.php">← <?= $text[
    "agy_back"
] ?></a>
        </aside>
        <main class="registration-main">
            <header class="registration-top"><span>AGY-01 — AGY-07</span>
                <div class="language-switch"><a href="?lang=eng" class="<?= $lang ===
"eng"
    ? "active"
    : "" ?>"><img src="../img/flag-gb.svg" alt=""> English</a><a href="?lang=ja" class="<?= $lang ===
"ja"
    ? "active"
    : "" ?>"><img src="../img/flag-jp.svg" alt=""> 日本語</a></div>
            </header>
            <div class="heading">
                <h1><?= $text[
    "agy_title"
] ?></h1>
                <p><?= $text[
    "agy_intro"
] ?></p>
            </div>
            <div class="progress-label"><span id="stepLabel"></span><strong id="progressPercent"></strong></div>
            <div class="progress-track">
                <div id="progressBar"></div>
            </div>
            <p class="form-note"><?= $text[
    "agy_required"
] ?></p>
            <div id="formMessage" role="status" aria-live="polite"></div>
            <form id="agentRegister" method="post" action="agentReg" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(
    $_SESSION["agency_csrf"],
    ENT_QUOTES,
    "UTF-8",
) ?>">
                <section class="form-step" data-panel="0">
                    <div class="section-heading"><span>AGY-01</span>
                        <h2><?= $text[
    "agy_head"
] ?></h2>
                    </div>
                    <div class="field-grid">
                        <?php foreach (
    [
        "companyName",
        "registrationNumber",
        "country",
        "headOfficeAddress",
        "email",
        "contactNumber",
        "postalCode",
        "localCompanyName",
        "city",
        "yearEstablished",
    ]
    as $key
):
    $required = in_array(
        $key,
        [
            "companyName",
            "registrationNumber",
            "country",
            "headOfficeAddress",
            "email",
            "contactNumber",
            "city",
            "yearEstablished",
        ],
        true,
    ); ?>
                        <div class="input-group <?= $key === "headOfficeAddress"
    ? "full-width"
    : "" ?>"><label for="<?= $key ?>"><?=
$text["agy_" . $key] . ($required ? " *" : "")
?></label><?php if (
    $key === "headOfficeAddress"
): ?><textarea id="<?= $key ?>" name="<?= $key ?>" rows="3" maxlength="2000" required></textarea><?php elseif (
    $key === "country"
): ?><select id="country" name="country" required>
                                <option value=""><?= $text[
    "agy_select"
] ?></option><?php foreach (
    $agencyCountries
    as $country
): ?><option value="<?= htmlspecialchars(
    $country["value"],
    ENT_QUOTES,
    "UTF-8",
) ?>"><?= htmlspecialchars(
    $country["value"],
) ?></option><?php endforeach; ?>
                            </select><?php else: ?><input id="<?= $key ?>" name="<?= $key ?>" type="<?= $key ===
"email"
    ? "email"
    : ($key === "yearEstablished"
        ? "number"
        : ($key === "contactNumber"
            ? "tel"
            : "text")) ?>" maxlength="190" <?= $key === "yearEstablished"
    ? 'min="1800" max="' . date("Y") . '"'
    : "" ?> <?= $required ? "required" : "" ?>><?php endif; ?></div><?php
endforeach; ?>
                    </div>
                </section>
                <?php foreach (
    [1 => "legal", 2 => "branches", 3 => "contacts", 4 => "links"]
    as $i => $key
): ?><section class="form-step" data-panel="<?= $i ?>" hidden>
                    <div class="section-heading"><span>AGY-<?= str_pad(
    (string) ($i + 1),
    2,
    "0",
    STR_PAD_LEFT,
) ?></span>
                        <h2><?= $text[
    "agy_" . $key
] ?></h2>
                    </div>
                    <div id="rows-<?= $key ?>" class="repeat-rows"></div><button type="button"
                        class="button secondary add-row" data-add="<?= $key ?>">＋ <?= $text[
    "agy_add"
] ?></button>
                </section><?php endforeach; ?>
                <section class="form-step" data-panel="5" hidden>
                    <div class="section-heading"><span>AGY-06</span>
                        <h2><?= $text[
    "agy_documents"
] ?></h2>
                    </div>
                    <p class="form-note"><?= $text[
    "agy_filesHint"
] ?></p>
                    <div id="rows-documents" class="repeat-rows"></div><button type="button"
                        class="button secondary add-row" data-add="documents">＋ <?= $text[
    "agy_add"
] ?></button>
                </section>
                <section class="form-step" data-panel="6" hidden>
                    <div class="section-heading"><span>AGY-07</span>
                        <h2><?= $text[
    "agy_schools"
] ?></h2>
                    </div>
                    <p class="form-note"><?= $text[
    "agy_statusHint"
] ?></p>
                    <div id="rows-schools" class="repeat-rows"></div><button type="button"
                        class="button secondary add-row" data-add="schools">＋ <?= $text[
    "agy_add"
] ?></button>
                    <div class="account-section">
                        <h2><?= $text[
    "agy_account"
] ?></h2>
                        <p class="form-note"><?= $text[
    "agy_passwordHint"
] ?></p>
                        <div class="field-grid">
                            <div class="input-group"><label for="password"><?= $text[
    "agy_password"
] ?> *</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12"
                                    maxlength="72" required></div>
                            <div class="input-group"><label for="confirmPassword"><?= $text[
     "agy_confirmPassword"
 ] ?> *</label><input id="confirmPassword" name="confirmPassword" type="password" autocomplete="new-password"
                                    minlength="12" maxlength="72" required></div>
                        </div><label class="checkbox-label declaration"><input type="checkbox" name="declaration"
                                value="1" required><span><?= $text[
     "agy_declaration"
 ] ?></span></label>
                    </div>
                </section>
                <div class="form-actions"><button type="button" class="button secondary" id="saveDraft"><?= $text[
    "agy_save"
] ?></button>
                    <div><button type="button" class="button ghost" id="previousStep" hidden><?= $text[
    "agy_previous"
] ?></button><button type="button" class="button primary" id="nextStep"><?= $text[
    "agy_next"
] ?> →</button><button type="submit" class="button primary" id="submitRequest" hidden><?= $text[
     "agy_submit"
 ] ?> →</button></div>
                </div>
            </form>
            <p class="draft-note"><?= $text[
    "agy_draftHint"
] ?></p>
            <p class="draft-note"><?= $text["agy_optionalHint"] ?></p>
        </main>
    </div>
    <script>
    window.agencyRegistration = <?= json_encode(
    [
        "text" => $text,
        "data" => $data,
        "version" => $version,
        "uploads" => $savedUploads,
        "schools" => $schools,
        "countries" => $agencyCountries,
        "lang" => $lang,
    ],
    JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_UNESCAPED_UNICODE,
) ?>;
    </script>
    <script src="../js/registration.js"></script>
</body>

</html>