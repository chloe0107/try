
<?php

define('APP_NAME', 'Library Borrowing and Reservation System');
define('APP_ENV', getenv('APP_ENV') ?: 'development');

/*
|--------------------------------------------------------------------------
| Application URL
|--------------------------------------------------------------------------
*/
$projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$basePath = '';

if (
    $documentRoot !== '' &&
    stripos($projectRoot, $documentRoot) === 0
) {
    $basePath = '/' . trim(
        str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            substr($projectRoot, strlen($documentRoot))
        ),
        '/'
    );
    $basePath = $basePath === '/' ? '' : $basePath;
}

define(
    'BASE_URL',
    rtrim(
        getenv('BASE_URL') ?: 'http://localhost' . $basePath,
        '/'
    )
);

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'library_management_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

/*
|--------------------------------------------------------------------------
| SMTP
|--------------------------------------------------------------------------
*/
$localMailConfigPath = __DIR__ . '/mail.local.php';

$localMailConfig = is_file($localMailConfigPath)
    ? require $localMailConfigPath
    : [];

if (!is_array($localMailConfig)) {
    throw new RuntimeException(
        'mail.local.php must return an array.'
    );
}

define(
    'SMTP_HOST',
    getenv('SMTP_HOST')
        ?: ($localMailConfig['SMTP_HOST'] ?? 'smtp.gmail.com')
);

define(
    'SMTP_PORT',
    (int) (
        getenv('SMTP_PORT')
            ?: ($localMailConfig['SMTP_PORT'] ?? 587)
    )
);

define(
    'SMTP_USERNAME',
    getenv('SMTP_USERNAME')
        ?: ($localMailConfig['SMTP_USERNAME'] ?? '')
);

define(
    'SMTP_PASSWORD',
    getenv('SMTP_PASSWORD')
        ?: ($localMailConfig['SMTP_PASSWORD'] ?? '')
);

define(
    'MAIL_FROM',
    getenv('MAIL_FROM')
        ?: ($localMailConfig['MAIL_FROM'] ?? SMTP_USERNAME)
);

define('MAIL_FROM_NAME', APP_NAME);
