<?php
use Aws\S3\S3Client;

const PORTAL_S3_BUCKET = '';
const PORTAL_S3_REGION = '';

// Fill these in on your own installation. Share a copy with empty values.
const PORTAL_S3_ACCESS_KEY_ID = '';
const PORTAL_S3_SECRET_ACCESS_KEY = '';
const PORTAL_S3_SESSION_TOKEN = ''; // Only for temporary AWS credentials.

function portalS3(): S3Client
{
    static $client;
    if ($client !== null) {
        return $client;
    }
    if (PORTAL_S3_ACCESS_KEY_ID === '' || PORTAL_S3_SECRET_ACCESS_KEY === '') {
        throw new LogicException('Enter your AWS key ID and secret in s3-config.php');
    }
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new LogicException('Install aws/aws-sdk-php in the project root');
    }
    require_once $autoload;
    if (!class_exists(S3Client::class)) {
        throw new LogicException('The root vendor folder does not contain aws/aws-sdk-php');
    }
    $credentials = [
        'key' => PORTAL_S3_ACCESS_KEY_ID,
        'secret' => PORTAL_S3_SECRET_ACCESS_KEY,
    ];
    if (PORTAL_S3_SESSION_TOKEN !== '') {
        $credentials['token'] = PORTAL_S3_SESSION_TOKEN;
    }
    $options = [
        'version' => '2006-03-01',
        'region' => PORTAL_S3_REGION,
        'credentials' => $credentials,
        'http' => ['connect_timeout' => 5, 'timeout' => 30],
        'retries' => 2,
    ];
    return $client = new S3Client($options);
}

function agencyS3IsKey(string $key): bool
{
    return preg_match('~\Aagent-files/(?:company/[a-f0-9]{48}\.(?:pdf|jpg|jpeg|png)|agreement/[a-f0-9]{48}\.pdf)\z~', $key) === 1;
}

function agencyS3Key(string $category, string $storageFilename): string
{
    $key = 'agent-files/' . $category . '/' . $storageFilename;
    if (!agencyS3IsKey($key)) {
        throw new InvalidArgumentException('Invalid agent file key');
    }
    return $key;
}

function agencyOriginalName(string $name, string $extension): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    if (!$name || !preg_match('/\A.{1,190}/us', $name, $match)) {
        return 'agency-document.' . $extension;
    }
    return $match[0];
}

function agencyS3Put(string $key, string $source, string $mime): void
{
    if (!agencyS3IsKey($key) || !is_readable($source)) {
        throw new RuntimeException('Invalid upload source or object key');
    }
    $digest = md5_file($source, true);
    if ($digest === false) {
        throw new RuntimeException('Could not checksum the file');
    }
    portalS3()->putObject([
        'Bucket' => PORTAL_S3_BUCKET,
        'Key' => $key,
        'SourceFile' => $source,
        'ContentMD5' => base64_encode($digest),
        'ContentType' => $mime,
        'ContentDisposition' => 'attachment',
        'CacheControl' => 'private, no-store',
        'ServerSideEncryption' => 'AES256',
    ]);
}

// Keep SDK exception messages and signed URLs out of responses and logs.
function agencyStorageLog(string $operation, Throwable $error): void
{
    $detail = get_class($error);
    if ($error instanceof \Aws\Exception\AwsException) {
        $detail .= ' ' . ($error->getAwsErrorCode() ?: 'transport')
            . ' request=' . ($error->getAwsRequestId() ?: 'unavailable');
    } elseif ($error instanceof LogicException && $error->getFile() === __FILE__) {
        $detail .= ' ' . $error->getMessage();
    } elseif ($error instanceof mysqli_sql_exception) {
        $detail .= ' mysql=' . $error->getCode();
    }
    error_log('Agent storage ' . $operation . ' | ' . $detail);
}

function agencyS3Delete(string $key): void
{
    if (!agencyS3IsKey($key)) {
        throw new InvalidArgumentException('Invalid cleanup key');
    }
    portalS3()->deleteObject(['Bucket' => PORTAL_S3_BUCKET, 'Key' => $key]);
}

function agencyS3Rollback(array $keys): void
{
    foreach ($keys as $key) {
        try {
            agencyS3Delete($key);
        } catch (Throwable $error) {
            agencyStorageLog('cleanup required for ' . $key, $error);
        }
    }
}
