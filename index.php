<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    isset($_GET['lang']) &&
    is_string($_GET['lang']) &&
    in_array($_GET['lang'], ['eng', 'ja'], true)
) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang = $_SESSION['lang'] ?? 'eng';

if (!in_array($lang, ['eng', 'ja'], true)) {
    $lang = 'eng';
}

include __DIR__ . '/lang/' . $lang . '.php';

$englishLink = $_GET;
$englishLink['lang'] = 'eng';

$japaneseLink = $_GET;
$japaneseLink['lang'] = 'ja';
?>

<!DOCTYPE html>
<html lang="<?= $lang === 'ja' ? 'ja' : 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $text['select_login_type'] ?></title>

    <link rel="stylesheet" href="css/index.css">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"
    >

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="./img/MIC.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./img/MIC.png">
</head>

<body>

    <div class="container" style="position: relative;">

        <!-- Left Section -->
        <div class="left">

            <!-- <a
                href=""
                style="position: absolute; font-size:2rem; text-decoration:none;top:0; left:0;"
            >🔙</a> -->

           <!-- Language selection -->
<div class="language-switch">

    <a
        href="?<?= htmlspecialchars(
            http_build_query($englishLink),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        class="<?= $lang === 'eng' ? 'active' : '' ?>"
        lang="en"
        <?= $lang === 'eng' ? 'aria-current="true"' : '' ?>
    >
        <svg
            class="language-flag"
            viewBox="0 0 60 40"
            aria-hidden="true"
        >
            <rect width="60" height="40" fill="#163b79"/>
            <path
                d="M0 0 60 40M60 0 0 40"
                stroke="#fff"
                stroke-width="9"
            />
            <path
                d="M0 0 60 40M60 0 0 40"
                stroke="#c93546"
                stroke-width="3"
            />
            <path
                d="M30 0V40M0 20H60"
                stroke="#fff"
                stroke-width="14"
            />
            <path
                d="M30 0V40M0 20H60"
                stroke="#c93546"
                stroke-width="8"
            />
        </svg>

        <span>English</span>
    </a>

    <a
        href="?<?= htmlspecialchars(
            http_build_query($japaneseLink),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        class="<?= $lang === 'ja' ? 'active' : '' ?>"
        lang="ja"
        <?= $lang === 'ja' ? 'aria-current="true"' : '' ?>
    >
        <svg
            class="language-flag"
            viewBox="0 0 60 40"
            aria-hidden="true"
        >
            <rect width="60" height="40" fill="#fff"/>
            <circle cx="30" cy="20" r="11" fill="#bc2540"/>
        </svg>

        <span>日本語</span>
    </a>

</div>

            <!-- Step 1: ROLE SELECTION -->
            <div id="roleSelect" class="role-select">

                <div class="SelectLoginTypeLogo">
                    <img src="img/logo.png" alt="JP">
                </div>

                <h2><?= $text['select_login_type'] ?></h2>

                <div class="role-buttons">

                    <div class="role-card" data-role="agent">
                        <img
                            src="https://cdn-icons-png.flaticon.com/512/1995/1995515.png"
                            alt="<?= $text['agent'] ?>"
                        >
                        <p><?= $text['agent'] ?></p>
                    </div>

                    <div class="role-card" data-role="admin">
                        <img
                            src="https://cdn-icons-png.flaticon.com/512/1828/1828466.png"
                            alt="<?= $text['admin'] ?>"
                        >
                        <p><?= $text['admin'] ?></p>
                    </div>

                </div>

                <div class="newReg">
                    <a href="pages/registration">
                        <?= $text['create_account'] ?>
                    </a>
                </div>

            </div>

            <!-- Step 2: LOGIN FORM (Hidden initially) -->
            <div id="loginForm" class="login-form hidden">

                <div>
                    <img
                        src="img/logo.png"
                        alt="JP"
                        width="50%"
                    >
                </div>

                <br>

                <h2 id="formTitle"><?= $text['login'] ?></h2>

                <form action="login" method="POST" id="loginFormElement">

                    <!-- Hidden role -->
                    <input type="hidden" name="role" id="roleInput">

                    <input
                        type="email"
                        name="email"
                        placeholder="<?= $text['email'] ?>"
                        id="email"
                        required
                    >

                    <div class="password-container">

                        <input
                            type="password"
                            name="password"
                            placeholder="<?= $text['password'] ?>"
                            id="password"
                            required
                        >

                        <span class="toggle-password">👁️</span>

                    </div>

                    <div class="extra">

                        <label>
                            <input
                                type="checkbox"
                                style="width:min-content;margin-right:5px;"
                            >
                            <?= $text['remember'] ?>
                        </label>

                        <a
                            href="#"
                            class="forgot"
                            onclick="fetchUserData()"
                        >
                            <?= $text['forgot_password'] ?>
                        </a>

                    </div>

                    <button type="submit" class="login-btn">
                        <?= $text['log_in'] ?>
                    </button>

                </form>

                <div class="newReg">
                    <a
                        href="pages/registration"
                        class="hidden"
                        id="create"
                    >
                        <?= $text['create_student_account'] ?>
                    </a>
                </div>

                <p class="back" id="backBtn">
                    ← <?= $text['back'] ?>
                </p>

            </div>

        </div>

        <!-- Right Image -->
        <div class="right">
            <img src="img/auth.png" alt="">
        </div>

    </div>


    <script>
    window.portalText = <?= json_encode(
        $text,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_UNESCAPED_UNICODE
    ) ?>;
</script>

    <script src="js/index.js"></script>

    

<script src="fetch.js"></script>

    <script>
        // ----------------- Show/Hide Password -----------------
        document.querySelectorAll('.toggle-password').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const input = toggle.previousElementSibling;

                if (input.type === 'password') {
                    input.type = 'text';
                    toggle.textContent = '🙈';
                } else {
                    input.type = 'password';
                    toggle.textContent = '👁️';
                }
            });
        });
    </script>

</body>
</html>