<?php
require_once __DIR__ . '/portal_mail.php';

function sendAgentApprovalNotice(mysqli $conn, int $agentId): string
{
    $stmt = $conn->prepare("
        SELECT a.email, a.company_name, d.preferred_language
        FROM agents AS a
        JOIN agency_registration_details AS d ON d.agent_id = a.id
        WHERE a.id = ? AND a.registration_status = 'Approved'
    ");
    $stmt->bind_param('i', $agentId);
    $stmt->execute();
    $agent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$agent) {
        return 'mail_unchanged';
    }

    $isJapanese = $agent['preferred_language'] === 'ja';
    $language = $isJapanese ? 'ja' : 'eng';
    $htmlLang = $isJapanese ? 'ja' : 'en';
    $words = require __DIR__ . '/../lang/' . $language . '_admin.php';

    // Change this one line when the portal goes live.
    $portalUrl = 'http://localhost/portal/';
    $logoUrl = 'https://janprakash-joshi.github.io/portal/logo.png';

    $email = trim($agent['email']);
    $company = trim($agent['company_name']);

    if ($isJapanese) {
        $greeting = $company !== '' ? $company . ' ご担当者様' : 'ご担当者様';
        $heading = '代理店登録が承認されました';
        $intro = '入学申請ポータルをご利用いただけるようになりました。';
        $button = '入学申請ポータルを開く';
        $linkHelp = 'ボタンが開かない場合は、以下のリンクをご利用ください。';
        $footer = 'このメールは入学申請ポータルから自動送信されています。返信はご遠慮ください。';
        $logoAlt = '入学申請ポータル';
    } else {
        $greeting = $company !== '' ? 'Hello ' . $company . ',' : 'Hello,';
        $heading = 'Your agent account is approved';
        $intro = 'You can now sign in to the admissions portal.';
        $button = 'Open admissions portal';
        $linkHelp = 'If the button does not open, use this link.';
        $footer = 'This is an automated email from the Admissions Portal. Please do not reply.';
        $logoAlt = 'Admissions Portal';
    }

    // Escape text before inserting it into the HTML email.
    $greetingHtml = adminH($greeting);
    $emailHtml = adminH($email);
    $loginLabel = adminH($words['login_email']);
    $passwordHelp = adminH($words['letter_password']);
    $resetHelp = adminH($words['letter_reset']);
    $portalLink = adminH($portalUrl);

    $content = <<<HTML
<!DOCTYPE html>
<html lang="{$htmlLang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$heading}</title>
</head>
<body style="margin:0;padding:0;background-color:#edf2f7;color:#20344c;font-family:Arial,'Yu Gothic',Meiryo,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="background-color:#edf2f7;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px;background-color:#ffffff;border:1px solid #dbe4ed;border-top:5px solid #163958;border-radius:12px;">

                    <tr>
                        <td style="padding:26px 26px 20px;border-bottom:1px solid #e4eaf0;">
                            <img src="{$logoUrl}" alt="{$logoAlt}" width="180"
                                 style="display:block;width:180px;max-width:100%;height:auto;border:0;color:#163958;font-size:18px;">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px;font-size:16px;line-height:1.65;">
                            <p style="margin:0 0 12px;">{$greetingHtml}</p>

                            <h1 style="margin:0 0 8px;color:#163958;font-size:25px;line-height:1.35;">
                                {$heading}
                            </h1>
                            <p style="margin:0 0 20px;">{$intro}</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                   style="background-color:#eff5fa;border-left:4px solid #32749a;">
                                <tr>
                                    <td style="padding:13px 16px;">
                                        <p style="margin:0 0 3px;color:#465c70;font-size:14px;">
                                            {$loginLabel}
                                        </p>
                                        <p style="margin:0;color:#163958;font-size:18px;font-weight:bold;word-break:break-word;">
                                            {$emailHtml}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:20px 0 8px;">{$passwordHelp}</p>
                            <p style="margin:0 0 20px;">{$resetHelp}</p>

                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td bgcolor="#163958" style="border-radius:7px;mso-padding-alt:13px 22px;">
                                        <a href="{$portalLink}"
                                           style="display:inline-block;padding:13px 22px;color:#ffffff;font-size:16px;font-weight:bold;text-decoration:none;">
                                            {$button}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:18px 0 0;color:#506276;font-size:14px;">
                                {$linkHelp}<br>
                                <a href="{$portalLink}" style="color:#245c83;text-decoration:underline;">
                                    {$portalLink}
                                </a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 26px;border-top:1px solid #e4eaf0;color:#546477;font-size:13px;line-height:1.6;">
                            {$footer}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    // Preserve the specific MAIL error code from the updated dashboard mailer.
    $reason = 'mail_failed';
    $sent = adminSendPortalMail($email, $words['letter_subject'], $content, $reason);

    return $sent ? 'mail_sent' : $reason;
}