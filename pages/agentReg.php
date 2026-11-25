<?php
// Agent-only registration endpoint. No registration-token requirement.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
$lang = in_array($_SESSION["lang"] ?? "eng", ["eng", "ja"], true)
    ? $_SESSION["lang"] ?? "eng"
    : "eng";
include __DIR__ . "/../lang/" . $lang . ".php";
function registrationReply(
    string $status,
    string $message,
    array $extra = [],
): never {
    echo json_encode(
        array_merge(["status" => $status, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE,
    );
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    registrationReply("error", $text["agy_error"]);
}
if (
    empty($_SESSION["agency_csrf"]) ||
    !is_string($_POST["csrf"] ?? null) ||
    !hash_equals($_SESSION["agency_csrf"], $_POST["csrf"])
) {
    http_response_code(403);
    registrationReply("error", $text["agy_expired"]);
}
require_once __DIR__ . "/../db_connect.php";
require __DIR__ . "/agency-config.php";
require_once __DIR__ . "/../s3-config.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");
$mode = $_POST["action"] ?? "";
if (!in_array($mode, ["draft", "submit"], true)) {
    registrationReply("error", $text["agy_error"]);
}
$raw = is_string($_POST["payload"] ?? null) ? $_POST["payload"] : "";
if (strlen($raw) > 200000) {
    registrationReply("error", $text["agy_invalid"]);
}
$input = json_decode($raw, true);
if (!is_array($input)) {
    registrationReply("error", $text["agy_invalid"]);
}
$data = [];
foreach (
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
) {
    $data[$key] = is_string($input[$key] ?? null) ? trim($input[$key]) : "";
    if (strlen($data[$key]) > ($key === "headOfficeAddress" ? 2000 : 190)) {
        registrationReply("error", $text["agy_invalid"]);
    }
}
$data["email"] = strtolower($data["email"]);
if (
    strlen($data["registrationNumber"]) > 150 ||
    strlen($data["postalCode"]) > 40
) {
    registrationReply("error", $text["agy_invalid"]);
}
$specs = [
    "legal" => ["fullName", "jobTitle", "personEmail", "phone", "primary"],
    "branches" => [
        "branchName",
        "branchCountry",
        "address",
        "manager",
        "personEmail",
        "active",
    ],
    "contacts" => ["fullName", "department", "personEmail", "phone", "primary"],
    "links" => ["linkType", "url", "displayLabel"],
    "documents" => ["documentType", "issueDate", "expiryDate", "existing_id"],
    "schools" => ["school_id", "requestNote", "existing_id"],
];
foreach ($specs as $group => $fields) {
    $rows = $input[$group] ?? [];
    if (!is_array($rows) || count($rows) > $agencyMaxRows) {
        registrationReply("error", $text["agy_invalid"]);
    }
    $data[$group] = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            registrationReply("error", $text["agy_invalid"]);
        }
        $clean = [];
        foreach ($fields as $key) {
            $value = $row[$key] ?? "";
            if (in_array($key, ["primary", "active"], true)) {
                $clean[$key] = !empty($value) ? 1 : 0;
            } elseif (in_array($key, ["existing_id", "school_id"], true)) {
                $clean[$key] = max(0, (int) $value);
            } else {
                $clean[$key] = is_string($value) ? trim($value) : "";
                if (
                    strlen($clean[$key]) >
                    ($key === "address" || $key === "requestNote" ? 2000 : 300)
                ) {
                    registrationReply("error", $text["agy_invalid"]);
                }
            }
        }
        $data[$group][] = $clean;
    }
}
if ($mode === "submit") {
    foreach (
        [
            "companyName",
            "registrationNumber",
            "country",
            "headOfficeAddress",
            "email",
            "contactNumber",
            "city",
            "yearEstablished",
        ]
        as $key
    ) {
        if ($data[$key] === "") {
            registrationReply("error", $text["agy_invalid"]);
        }
    }
    if (
        !filter_var($data["email"], FILTER_VALIDATE_EMAIL) ||
        !ctype_digit($data["yearEstablished"]) ||
        (int) $data["yearEstablished"] < 1800 ||
        (int) $data["yearEstablished"] > (int) date("Y")
    ) {
        registrationReply("error", $text["agy_invalid"]);
    }
    foreach (["legal", "contacts"] as $group) {
        if (
            !$data[$group] ||
            array_sum(array_column($data[$group], "primary")) !== 1
        ) {
            registrationReply("error", $text["agy_invalid"]);
        }
        foreach ($data[$group] as $r) {
            if (
                $r["fullName"] === "" ||
                $r["phone"] === "" ||
                !filter_var($r["personEmail"], FILTER_VALIDATE_EMAIL)
            ) {
                registrationReply("error", $text["agy_invalid"]);
            }
        }
    }
    foreach ($data["branches"] as $r) {
        if (
            $r["branchName"] === "" ||
            $r["branchCountry"] === "" ||
            $r["address"] === "" ||
            ($r["personEmail"] !== "" &&
                !filter_var($r["personEmail"], FILTER_VALIDATE_EMAIL))
        ) {
            registrationReply("error", $text["agy_invalid"]);
        }
    }
    foreach ($data["links"] as $r) {
        if (
            !in_array(
                $r["linkType"],
                ["website", "facebook", "instagram", "linkedin", "other"],
                true,
            ) ||
            !filter_var($r["url"], FILTER_VALIDATE_URL) ||
            !in_array(
                strtolower(parse_url($r["url"], PHP_URL_SCHEME) ?? ""),
                ["http", "https"],
                true,
            )
        ) {
            registrationReply("error", $text["agy_invalid"]);
        }
    }
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";
    $confirm = is_string($_POST["confirmPassword"] ?? null)
        ? $_POST["confirmPassword"]
        : "";
    if (
        str_contains($password, "\0") ||
        strlen($password) > 72 ||
        preg_match_all("/./us", $password) < 12 ||
        $password !== $confirm
    ) {
        registrationReply("error", $text["agy_passwordError"]);
    }
    if (($_POST["declaration"] ?? "") !== "1" || !$data["schools"]) {
        registrationReply("error", $text["agy_invalid"]);
    }
}
$schoolIds = [];
foreach ($data["schools"] as $r) {
    $sid = (int) $r["school_id"];
    if (!$sid) {
        if ($mode === "submit") {
            registrationReply("error", $text["agy_noSchools"]);
        }
        continue;
    }
    if (in_array($sid, $schoolIds, true)) {
        registrationReply("error", $text["agy_invalid"]);
    }
    $stmt = $conn->prepare(
        "SELECT id FROM agency_registration_schools WHERE id=? AND active=1",
    );
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        registrationReply("error", $text["agy_noSchools"]);
    }
    $schoolIds[] = $sid;
}
// Persistent private draft key. This is NOT a registration/invitation token.
$key = $_COOKIE["agency_draft"] ?? "";
$newKey = false;
if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) {
    $key = bin2hex(random_bytes(32));
    $newKey = true;
}
$hash = hash("sha256", $key);
$newKeys = [];
$commitStarted = false;
try {
    $conn->begin_transaction();
    $stmt = $conn->prepare(
        "SELECT * FROM agency_registration_drafts WHERE access_hash=? FOR UPDATE",
    );
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $draft = $stmt->get_result()->fetch_assoc();
    if (
        $draft &&
        ($draft["submitted_agent_id"] !== null ||
            $draft["expires_at"] <= gmdate("Y-m-d H:i:s"))
    ) {
        $conn->rollback();
        registrationReply("error", $text["agy_expired"]);
    }
    if ($draft && (int) ($_POST["version"] ?? 0) !== (int) $draft["version"]) {
        $conn->rollback();
        registrationReply("error", $text["agy_conflict"]);
    }
    if (!$draft) {
        // Limit anonymous draft creation per PHP session; use perimeter rate limiting before launch.
        $_SESSION["agency_created_times"] = array_values(
            array_filter(
                $_SESSION["agency_created_times"] ?? [],
                fn($v) => $v > time() - 3600,
            ),
        );
        if (count($_SESSION["agency_created_times"]) >= 5) {
            $conn->rollback();
            registrationReply("error", $text["agy_tooMany"]);
        }
        $_SESSION["agency_created_times"][] = time();
        $stmt = $conn->prepare(
            "INSERT INTO agency_registration_drafts(access_hash,payload,version,expires_at,updated_at) VALUES (?,'{}',0,UTC_TIMESTAMP()+INTERVAL 7 DAY,UTC_TIMESTAMP())",
        );
        $stmt->bind_param("s", $hash);
        $stmt->execute();
        $draftId = $conn->insert_id;
        $currentVersion = 0;
    } else {
        $draftId = (int) $draft["id"];
        $currentVersion = (int) $draft["version"];
    }
    $version = $currentVersion + 1;
    $uploads = [];
    $keptIds = [];
    $stmt = $conn->prepare(
        "SELECT COUNT(*) count_files,COALESCE(SUM(file_size),0) total_bytes FROM agency_registration_uploads WHERE draft_id=?",
    );
    $stmt->bind_param("i", $draftId);
    $stmt->execute();
    $usage = $stmt->get_result()->fetch_assoc();
    $fileCount = (int) $usage["count_files"];
    $fileBytes = (int) $usage["total_bytes"];
    foreach (
        ["documents" => "company", "schools" => "agreement"]
        as $group => $category
    ) {
        foreach ($data[$group] as $i => &$row) {
            $field = ($group === "documents" ? "document_" : "agreement_") . $i;
            $file = $_FILES[$field] ?? null;
            $existing = (int) $row["existing_id"];
            $saved = null;
            $issue =
                $group === "documents" ? ($row["issueDate"] ?: null) : null;
            $expiry =
                $group === "documents" ? ($row["expiryDate"] ?: null) : null;
            foreach ([$issue, $expiry] as $date) {
                if ($date !== null) {
                    $dt = DateTimeImmutable::createFromFormat("!Y-m-d", $date);
                    if (!$dt || $dt->format("Y-m-d") !== $date) {
                        throw new DomainException("file");
                    }
                }
            }
            if ($issue && $expiry && $expiry < $issue) {
                throw new DomainException("file");
            }
            $school = $group === "schools" ? ($row["school_id"] ?: null) : null;
            $type =
                $group === "documents"
                    ? $row["documentType"]
                    : "signed_agreement";
            if (
                $mode === "draft" &&
                $group === "documents" &&
                $type === "" &&
                !$existing &&
                (!$file ||
                    ($file["error"] ?? UPLOAD_ERR_NO_FILE) ===
                        UPLOAD_ERR_NO_FILE)
            ) {
                continue;
            }
            if (
                $group === "documents" &&
                !in_array(
                    $type,
                    [
                        "company_registration",
                        "business_license",
                        "company_profile",
                        "other",
                    ],
                    true,
                )
            ) {
                throw new DomainException("file");
            }
            if (
                $file &&
                ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ) {
                if (
                    !is_int($file["error"]) ||
                    $file["error"] !== UPLOAD_ERR_OK ||
                    !is_uploaded_file($file["tmp_name"]) ||
                    $file["size"] > $agencyMaxFileBytes ||
                    $file["size"] <= 0
                ) {
                    throw new DomainException("file");
                }
                if (
                    ++$fileCount > 80 ||
                    ($fileBytes += (int) $file["size"]) > 100 * 1024 * 1024
                ) {
                    throw new DomainException("file");
                }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file(
                    $file["tmp_name"],
                );
                $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
                $allowed = [
                    "application/pdf" => ["pdf"],
                    "image/jpeg" => ["jpg", "jpeg"],
                    "image/png" => ["png"],
                ];
                if (
                    !isset($allowed[$mime]) ||
                    !in_array($ext, $allowed[$mime], true) ||
                    ($category === "agreement" && $mime !== "application/pdf")
                ) {
                    throw new DomainException("file");
                }
                $stored = agencyS3Key($category, bin2hex(random_bytes(24)) . "." . $ext);
                // Track before PUT so a lost AWS response can also be cleaned up.
                $newKeys[] = $stored;
                agencyS3Put($stored, $file["tmp_name"], $mime);
                $original = agencyOriginalName($file["name"], $ext);
                $bytes = (int) $file["size"];
                $stmt = $conn->prepare(
                    "INSERT INTO agency_registration_uploads(draft_id,category,document_type,stored_name,original_name,mime_type,file_size,issue_date,expiry_date,school_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())",
                );
                $stmt->bind_param(
                    "isssssissi",
                    $draftId,
                    $category,
                    $type,
                    $stored,
                    $original,
                    $mime,
                    $bytes,
                    $issue,
                    $expiry,
                    $school,
                );
                $stmt->execute();
                $existing = $conn->insert_id;
            }
            if ($existing) {
                $stmt = $conn->prepare(
                    "SELECT id,category,original_name FROM agency_registration_uploads WHERE id=? AND draft_id=? AND agent_id IS NULL",
                );
                $stmt->bind_param("ii", $existing, $draftId);
                $stmt->execute();
                $saved = $stmt->get_result()->fetch_assoc();
                if (
                    !$saved ||
                    $saved["category"] !== $category ||
                    in_array($existing, $keptIds, true)
                ) {
                    throw new DomainException("file");
                }
                $stmt = $conn->prepare(
                    "UPDATE agency_registration_uploads SET document_type=?,issue_date=?,expiry_date=?,school_id=? WHERE id=?",
                );
                $stmt->bind_param(
                    "sssii",
                    $type,
                    $issue,
                    $expiry,
                    $school,
                    $existing,
                );
                $stmt->execute();
                $keptIds[] = $existing;
            } elseif ($mode === "submit" && $group === "documents") {
                throw new DomainException("file");
            }
            $row["existing_id"] = $existing;
            if ($saved) {
                $uploads[] = [
                    "group" => $group,
                    "index" => $i,
                    "id" => $existing,
                    "name" => $saved["original_name"],
                ];
            }
        }
        unset($row);
    }
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = $conn->prepare(
        "UPDATE agency_registration_drafts SET payload=?,version=?,updated_at=UTC_TIMESTAMP() WHERE id=?",
    );
    $stmt->bind_param("sii", $payload, $version, $draftId);
    $stmt->execute();
    if ($mode === "submit") {
        // Preserve your existing duplicate check across students and agents.
        $email = $data["email"];
        $stmt = $conn->prepare(
            "SELECT email FROM students WHERE email=? UNION SELECT email FROM agents WHERE email=?",
        );
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new DomainException("duplicate");
        }
        $contact = "";
        foreach ($data["contacts"] as $r) {
            if ($r["primary"]) {
                $contact = $r["fullName"];
            }
        }
        $company = $data["companyName"];
        $country = $data["country"];
        $city = $data["city"];
        $year = $data["yearEstablished"];
        $phone = $data["contactNumber"];
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO agents(company_name,country,city,year_established,email,contact_person,contact_number,password,registration_status) VALUES (?,?,?,?,?,?,?,?,'Pending')",
        );
        $stmt->bind_param(
            "ssssssss",
            $company,
            $country,
            $city,
            $year,
            $email,
            $contact,
            $phone,
            $passwordHash,
        );
        $stmt->execute();
        $agentId = $conn->insert_id;
        $legal = json_encode($data["legal"], JSON_UNESCAPED_UNICODE);
        $branches = json_encode($data["branches"], JSON_UNESCAPED_UNICODE);
        $contacts = json_encode($data["contacts"], JSON_UNESCAPED_UNICODE);
        $links = json_encode($data["links"], JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare(
            "INSERT INTO agency_registration_details(agent_id,registration_number,head_office_address,postal_code,local_company_name,legal_representatives,branch_offices,contact_persons,online_links,preferred_language,submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())",
        );
        $stmt->bind_param(
            "isssssssss",
            $agentId,
            $data["registrationNumber"],
            $data["headOfficeAddress"],
            $data["postalCode"],
            $data["localCompanyName"],
            $legal,
            $branches,
            $contacts,
            $links,
            $lang,
        );
        $stmt->execute();
        foreach ($data["schools"] as $row) {
            $sid = (int) $row["school_id"];
            $note = $row["requestNote"];
            $stmt = $conn->prepare(
                "INSERT INTO agency_school_partnerships(agent_id,school_id,request_note,status) VALUES (?,?,?,'Pending')",
            );
            $stmt->bind_param("iis", $agentId, $sid, $note);
            $stmt->execute();
        }
        foreach ($keptIds as $fileId) {
            $stmt = $conn->prepare(
                "UPDATE agency_registration_uploads SET agent_id=? WHERE id=? AND draft_id=?",
            );
            $stmt->bind_param("iii", $agentId, $fileId, $draftId);
            $stmt->execute();
        }
        $stmt = $conn->prepare(
            "UPDATE agency_registration_drafts SET submitted_agent_id=? WHERE id=?",
        );
        $stmt->bind_param("ii", $agentId, $draftId);
        $stmt->execute();
    }
    $commitStarted = true;
    $conn->commit();
    setcookie("agency_draft", $mode === "submit" ? "" : $key, [
        "expires" => $mode === "submit" ? time() - 3600 : time() + 7 * 86400,
        "path" => "/",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    registrationReply(
        "success",
        $text[$mode === "submit" ? "agy_success" : "agy_saved"],
        [
            "version" => $version,
            "uploads" => $uploads,
            "submitted" => $mode === "submit",
        ],
    );
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        agencyStorageLog("draft rollback", $rollbackError);
    }
    if (!$commitStarted) {
        agencyS3Rollback($newKeys);
    } elseif ($newKeys) {
        // A disconnected commit might have succeeded. Keep objects until DB state is verified.
        error_log("Agent storage: verify database references before cleanup: " . implode(", ", $newKeys));
    }
    if ($e instanceof DomainException) {
        registrationReply(
            "error",
            $text[
                $e->getMessage() === "duplicate"
                    ? "agy_duplicate"
                    : "agy_fileError"
            ],
        );
    }
    if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
        registrationReply("error", $text["agy_duplicate"]);
    }
    agencyStorageLog("registration save", $e);
    http_response_code(500);
    registrationReply("error", $text["agy_server"]);
}
