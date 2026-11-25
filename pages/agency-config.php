<?php
// Private storage must remain OUTSIDE htdocs/public_html. Change if necessary.
$agencyPrivatePath =
    dirname(__DIR__, 3) . "/college-portal-private/agency-documents";
$agencyMaxFileBytes = 5 * 1024 * 1024;
$agencyMaxRows = 20;
