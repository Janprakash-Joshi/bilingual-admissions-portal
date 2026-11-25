<?php
require_once __DIR__ . '/agent_page.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$_SESSION['agent_form_csrf'] ??= bin2hex(random_bytes(32));
$formValues = [
    'email' => $agentAccount['email'], 'contact_person' => $agentAccount['contact_person'],
    'contact' => $agentAccount['contact_number'], 'year' => $agentAccount['year_established'],
    'city' => $agentAccount['city'],
];
$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formValues as $key => $value) {
        $formValues[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $email = $formValues['email'];
    $contact = $formValues['contact_person'];
    $phone = $formValues['contact'];
    $city = $formValues['city'];
    $year = (int) $formValues['year'];
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['agent_form_csrf'], $_POST['csrf'])) {
        $formError = $desk['csrf'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 ||
        $contact === '' || $phone === '' || $city === '' ||
        max(strlen($contact), strlen($phone), strlen($city)) > 190 ||
        !ctype_digit($formValues['year']) || $year < 1800 || $year > (int) date('Y')) {
        $formError = $desk['invalidDetails'];
    } else {
        $conn->begin_transaction();
        try {
            $lock = $conn->prepare("SELECT email FROM agents WHERE id = ? AND registration_status = 'Approved' FOR UPDATE");
            $lock->bind_param('i', $agentId);
            $lock->execute();
            $current = $lock->get_result()->fetch_assoc();
            if (!$current) {
                throw new RuntimeException('Account unavailable');
            }
            if (strcasecmp($email, $current['email']) !== 0) {
                // Keep the same email uniqueness rule as agent registration.
                $duplicate = $conn->prepare('SELECT 1 FROM students WHERE email = ? LIMIT 1');
                $duplicate->bind_param('s', $email);
                $duplicate->execute();
                if ($duplicate->get_result()->num_rows) {
                    throw new DomainException('Email already used');
                }
            }
            $save = $conn->prepare('UPDATE agents SET email=?, contact_person=?, contact_number=?, year_established=?, city=? WHERE id=?');
            $save->bind_param('sssisi', $email, $contact, $phone, $year, $city, $agentId);
            $save->execute();

            // Registration also records the primary contact's name in its contact list.
            $contactsQuery = $conn->prepare('SELECT contact_persons FROM agency_registration_details WHERE agent_id = ? FOR UPDATE');
            $contactsQuery->bind_param('i', $agentId);
            $contactsQuery->execute();
            $detailRow = $contactsQuery->get_result()->fetch_assoc();
            if ($detailRow) {
                $contacts = json_decode($detailRow['contact_persons'], true, 512, JSON_THROW_ON_ERROR);
                foreach ($contacts as $n => $person) {
                    if (!empty($person['primary'])) {
                        $contacts[$n]['fullName'] = $contact;
                    }
                }
                $contactJson = json_encode($contacts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $saveContacts = $conn->prepare('UPDATE agency_registration_details SET contact_persons=? WHERE agent_id=?');
                $saveContacts->bind_param('si', $contactJson, $agentId);
                $saveContacts->execute();
            }
            $conn->commit();
            $_SESSION['auth']['email'] = $email;
            $_SESSION['agent_profile_saved'] = true;
            header('Location: profile#agency', true, 303);
            exit;
        } catch (Throwable $error) {
            $conn->rollback();
            $duplicateEmail = $error instanceof DomainException || (int) $error->getCode() === 1062;
            $formError = $duplicateEmail ? $text['agy_duplicate'] : $desk['saveFail'];
            if (!$duplicateEmail) {
                error_log('Agent profile update failed (' . $error->getCode() . ')');
            }
        }
    }
}

$profileSaved = !empty($_SESSION['agent_profile_saved']);
unset($_SESSION['agent_profile_saved']);
$detailsQuery = $conn->prepare('SELECT registration_number, head_office_address, postal_code, local_company_name,
    legal_representatives, contact_persons, branch_offices, online_links, preferred_language, submitted_at, reviewed_at
    FROM agency_registration_details WHERE agent_id = ?');
$detailsQuery->bind_param('i', $agentId);
$detailsQuery->execute();
$details = $detailsQuery->get_result()->fetch_assoc();
require __DIR__ . '/agent_school_list.php';
$schoolNames = [];
foreach ($agentSchools as $school) {
    $schoolNames[$school['school_id']] = $school['name_' . $lang] ?: $school['name_eng'];
}

$docsQuery = $conn->prepare('SELECT original_name, document_type, category, school_id, issue_date, expiry_date, created_at
    FROM agency_registration_uploads WHERE agent_id = ? ORDER BY created_at DESC, id DESC');
$docsQuery->bind_param('i', $agentId);
$docsQuery->execute();
$files = $docsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$countryNames = $lang === 'ja' ? require __DIR__ . '/../lang/ja_agent_countries.php' : [];
$officeFields = [
    $text['agy_companyName'] => $agentAccount['company_name'],
    $text['agy_country'] => $countryNames[$agentAccount['country']] ?? $agentAccount['country'],
    $text['agy_city'] => $agentAccount['city'],
    $text['agy_yearEstablished'] => $agentAccount['year_established'],
    $text['agy_email'] => $agentAccount['email'],
    $text['agy_contactNumber'] => $agentAccount['contact_number'],
    $desk['contactPerson'] => $agentAccount['contact_person'],
];
if ($details) {
    $officeFields += [
        $text['agy_localCompanyName'] => $details['local_company_name'],
        $text['agy_registrationNumber'] => $details['registration_number'],
        $text['agy_headOfficeAddress'] => $details['head_office_address'],
        $text['agy_postalCode'] => $details['postal_code'],
        $desk['registered'] => agentDate($details['submitted_at'], $lang),
        $desk['registrationLang'] => $details['preferred_language'] === 'ja' ? '日本語' : 'English',
        $desk['reviewed'] => agentDate($details['reviewed_at'], $lang),
    ];
}
$groups = [
    'legal_representatives' => ['legal', ['fullName','jobTitle','personEmail','phone','primary']],
    'contact_persons' => ['contacts', ['fullName','department','personEmail','phone','primary']],
    'branch_offices' => ['branches', ['branchName','branchCountry','address','manager','personEmail','active']],
    'online_links' => ['links', ['displayLabel','linkType','url']],
];
include __DIR__ . '/nav.php';
include __DIR__ . '/sidebar.php';
?>
<main class="ad-main"><div class="ad-inner">
    <div class="ad-heading"><div><h1><?= $desk['profile'] ?></h1><p class="ad-subtitle"><?= agentHtml($agentAccount['company_name']) ?></p></div>
        <span class="ad-badge tone-success"><?= $desk['accountApproved'] ?></span>
    </div>
    <nav class="ad-jumps" aria-label="<?= $desk['profileSections'] ?>">
        <a href="#agency"><?= $desk['profileBasics'] ?></a><a href="#registration"><?= $desk['registrations'] ?></a>
        <a href="#partnerships"><?= $text['agy_schools'] ?></a><a href="#documents"><?= $desk['docs'] ?></a>
    </nav>

    <section id="agency" class="ad-panel ad-profile-panel">
        <h2><?= $text['agy_head'] ?></h2>
        <dl class="ad-facts"><?php foreach ($officeFields as $label => $value): ?>
            <div><dt><?= agentHtml($label) ?></dt><dd><?= agentHtml($value !== '' && $value !== null ? $value : '—') ?></dd></div>
        <?php endforeach; ?></dl>
        <?php if ($profileSaved): ?><p class="ad-message ad-good" role="status"><?= $desk['infoSaved'] ?></p><?php endif; ?>
        <details class="ad-edit" <?= $formError ? 'open' : '' ?>>
            <summary><svg class="ad-icon" aria-hidden="true"><use href="./img/agent-icons.svg#edit"></use></svg><?= $desk['edit'] ?></summary>
            <?php if ($formError): ?><p class="ad-message" role="alert"><?= agentHtml($formError) ?></p><?php endif; ?>
            <form action="profile" method="post" class="ad-form">
                <input type="hidden" name="csrf" value="<?= agentHtml($_SESSION['agent_form_csrf']) ?>">
                <input type="hidden" name="form_type" value="agent">
                <label><?= $text['agy_email'] ?><input type="email" name="email" required autocomplete="email" value="<?= agentHtml($formValues['email']) ?>"></label>
                <label><?= $desk['contactPerson'] ?><input name="contact_person" required value="<?= agentHtml($formValues['contact_person']) ?>"></label>
                <label><?= $text['agy_contactNumber'] ?><input type="tel" name="contact" required autocomplete="tel" value="<?= agentHtml($formValues['contact']) ?>"></label>
                <label><?= $text['agy_yearEstablished'] ?><input type="number" name="year" min="1800" max="<?= date('Y') ?>" required value="<?= agentHtml($formValues['year']) ?>"></label>
                <label><?= $text['agy_city'] ?><input name="city" required value="<?= agentHtml($formValues['city']) ?>"></label>
                <div class="ad-form-actions"><button class="ad-action" type="submit"><?= $desk['save'] ?></button></div>
            </form>
        </details>
    </section>

    <section id="registration" class="ad-panel ad-profile-panel"><h2><?= $desk['registrations'] ?></h2>
        <?php if (!$details): ?><p class="ad-empty"><?= $desk['noDetails'] ?></p>
        <?php else: foreach ($groups as $column => [$groupKey,$fields]):
            $rows = json_decode($details[$column], true) ?: [];
        ?>
            <details class="ad-group">
                <summary><span><?= $text['agy_' . $groupKey] ?></span><span class="ad-group-count"><?= count($rows) ?></span></summary>
                <?php if (!$rows): ?><p class="ad-empty"><?= $desk['empty'] ?></p><?php endif; ?>
                <?php foreach ($rows as $record): ?><dl class="ad-facts ad-record">
                    <?php foreach ($fields as $key):
                        $value = $record[$key] ?? '';
                        if ($key === 'primary') $value = $value ? $desk['yes'] : $desk['no'];
                        if ($key === 'active') $value = $value ? $desk['active'] : $desk['inactive'];
                        if ($key === 'branchCountry') $value = $countryNames[$value] ?? $value;
                        if ($key === 'linkType') $value = $text['agy_' . $value] ?? $value;
                        $isWebLink = $key === 'url' && filter_var($value, FILTER_VALIDATE_URL)
                            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http','https'], true);
                    ?>
                        <div><dt><?= $text['agy_' . $key] ?></dt><dd>
                            <?php if ($isWebLink): ?><a href="<?= agentHtml($value) ?>" target="_blank" rel="noopener noreferrer"><?= agentHtml($value) ?></a>
                            <?php else: ?><?= agentHtml($value === '' ? '—' : $value) ?><?php endif; ?>
                        </dd></div>
                    <?php endforeach; ?>
                </dl><?php endforeach; ?>
            </details>
        <?php endforeach; endif; ?>
    </section>

    <section id="partnerships" class="ad-panel ad-profile-panel"><h2><?= $text['agy_schools'] ?></h2>
        <?php if (!$agentSchools): ?><p class="ad-empty"><?= $desk['noSchools'] ?></p><?php endif; ?>
        <?php foreach ($agentSchools as $school): ?>
            <article class="ad-school-row">
                <div><h3><?= agentHtml($schoolNames[$school['school_id']]) ?></h3>
                    <?php if ($school['request_note'] !== ''): ?><p><?= agentHtml($school['request_note']) ?></p><?php endif; ?>
                    <?php if ($school['reviewed_at']): ?><small><?= $desk['reviewed'] ?> · <?= agentDate($school['reviewed_at'], $lang) ?></small><?php endif; ?>
                </div>
                <span class="ad-badge <?= $school['status'] === 'Approved' ? 'tone-success' : ($school['status'] === 'Rejected' ? 'tone-rejected' : 'tone-pending') ?>">
                    <?= agentHtml($text[$agentStatusKeys[$school['status']]] ?? $school['status']) ?>
                </span>
            </article>
        <?php endforeach; ?>
    </section>

    <section id="documents" class="ad-panel ad-profile-panel"><h2><?= $desk['docs'] ?></h2>
        <?php if (!$files): ?><p class="ad-empty"><?= $desk['empty'] ?></p><?php endif; ?>
        <?php foreach ($files as $doc):
            $kind = $doc['category'] === 'agreement' ? $text['agy_signed_agreement'] : ($text['agy_' . $doc['document_type']] ?? $doc['document_type']);
        ?>
            <article class="ad-doc">
                <span class="ad-file-icon"><svg class="ad-icon" aria-hidden="true"><use href="./img/agent-icons.svg#paper"></use></svg></span>
                <div><h3><?= agentHtml($doc['original_name']) ?></h3>
                    <p><?= agentHtml($kind) ?><?php if (isset($schoolNames[$doc['school_id']])): ?> · <?= agentHtml($schoolNames[$doc['school_id']]) ?><?php endif; ?></p>
                    <small><?= $desk['uploaded'] ?> · <?= agentDate($doc['created_at'], $lang) ?>
                        <?php if ($doc['issue_date']): ?> · <?= $text['agy_issueDate'] ?> · <?= agentDate($doc['issue_date'], $lang) ?><?php endif; ?>
                        <?php if ($doc['expiry_date']): ?> · <?= $text['agy_expiryDate'] ?> · <?= agentDate($doc['expiry_date'], $lang) ?><?php endif; ?>
                    </small>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div></main>
<?php include __DIR__ . '/footer.php'; ?>
