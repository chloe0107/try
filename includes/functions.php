
<?php

function local_development_base_url(): ?string
{
    if (
        APP_ENV !== 'development' ||
        getenv('BASE_URL') !== false ||
        !isset($_SERVER['HTTP_HOST']) ||
        preg_match(
            '/\A(localhost|(?:\d{1,3}\.){3}\d{1,3})(?::(\d{1,5}))?\z/i',
            $_SERVER['HTTP_HOST'],
            $matches
        ) !== 1
    ) {
        return null;
    }

    $host = $matches[1];
    $port = $matches[2] ?? '';
    $isLocalHost = strtolower($host) === 'localhost';
    $ip = $isLocalHost ? false : filter_var(
        $host,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_IPV4
    );
    $isPrivateIp = $ip !== false && (
        str_starts_with($ip, '10.') ||
        str_starts_with($ip, '192.168.') ||
        preg_match('/\A172\.(1[6-9]|2[0-9]|3[01])\./', $ip) ||
        str_starts_with($ip, '127.')
    );
    $validPort = $port === '' || ((int) $port >= 1 && (int) $port <= 65535);

    if ((!$isLocalHost && !$isPrivateIp) || !$validPort) {
        return null;
    }

    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        ? 'https'
        : 'http';

    return $scheme . '://' . $host .
        ($port !== '' ? ':' . $port : '') .
        (parse_url(BASE_URL, PHP_URL_PATH) ?: '');
}

function is_local_development_request(): bool
{
    return local_development_base_url() !== null;
}

function app_url(string $path = ''): string
{
    $base = rtrim(local_development_base_url() ?? BASE_URL, '/');

    $parts = parse_url($base);

    if (
        !is_array($parts) ||
        !in_array(
            strtolower($parts['scheme'] ?? ''),
            ['http', 'https'],
            true
        ) ||
        empty($parts['host'])
    ) {
        throw new RuntimeException(
            'Invalid BASE_URL. Check config/config.php.'
        );
    }

    return $base . (
        $path !== '' ? '/' . ltrim($path, '/') : ''
    );
}

function redirect(string $p): never
{
    header('Location: ' . app_url($p));
    exit;
}

function send_verification_email(
    string $email,
    string $name,
    string $token
): bool {
    if (
        !defined('SMTP_USERNAME') ||
        !defined('SMTP_PASSWORD') ||
        !defined('MAIL_FROM') ||
        SMTP_USERNAME === '' ||
        SMTP_PASSWORD === '' ||
        MAIL_FROM === ''
    ) {
        error_log('Verification email failed: SMTP settings are missing.');
        return false;
    }

    try {
        require_once __DIR__ . '/../vendor/autoload.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure =
            \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($email, $name);

        $url = app_url(
            'verify.php?token=' . rawurlencode($token)
        );

        $safeName = htmlspecialchars(
            $name,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $safeUrl = htmlspecialchars(
            $url,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $mail->isHTML(true);
        $mail->Subject = APP_NAME . ' - Verify your email';

        $mail->Body = "
            <h2>Hello {$safeName}!</h2>
            <p>Please click the link below to verify your account.</p>
            <p><a href=\"{$safeUrl}\">Verify My Email</a></p>
            <p>This link expires in 24 hours.</p>
            <p>If you did not register, ignore this email.</p>
        ";

        $mail->AltBody =
            "Hello {$name},\n\n" .
            "Verify your account using this link (expires in 24 hours):\n" .
            $url . "\n\n" .
            "If you did not register, ignore this email.";

        $mail->send();

        return true;
    } catch (\Throwable $ex) {
        error_log(
            'Verification email failed: ' . $ex->getMessage()
        );

        return false;
    }
}

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    return is_array($user) ? $user : null;
}

function require_login(): void
{
    global $conn;

    $user = current_user();
    if (!$user || empty($user['id'])) {
        redirect('login.php');
    }

    $stmt = $conn->prepare(
        'SELECT id, full_name, email, role, email_verified, is_active
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $freshUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$freshUser || !(int) $freshUser['is_active']) {
        unset($_SESSION['user']);
        flash('error', 'Your account is inactive or no longer available.');
        redirect('login.php');
    }

    $_SESSION['user'] = [
        'id' => (int) $freshUser['id'],
        'full_name' => $freshUser['full_name'],
        'email' => $freshUser['email'],
        'role' => $freshUser['role'],
        'email_verified' => (int) $freshUser['email_verified'],
    ];
}

function require_verified(): void
{
    require_login();

    if (!(int) current_user()['email_verified']) {
        redirect('verify_notice.php');
    }
}

function require_role(array $roles): void
{
    require_verified();

    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        page_header('Access denied');
        echo '<div class="panel"><h1>Access denied</h1>';
        echo '<p>You do not have permission to view this page.</p></div>';
        page_footer();
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($known) ||
        $known === '' || !hash_equals($known, $submitted)) {
        http_response_code(419);
        exit('The form has expired. Please go back and try again.');
    }
}

function flash(string $type, string $message): void
{
    $allowedTypes = ['success', 'error', 'warning'];
    $type = in_array($type, $allowedTypes, true) ? $type : 'success';
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function log_activity(
    mysqli $conn,
    ?int $userId,
    string $action,
    ?string $details = null
): void {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $conn->prepare(
        'INSERT INTO activity_logs (user_id, action, details, ip_address)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('isss', $userId, $action, $details, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

function page_header(string $title): void
{
    $user = current_user();
    $isVerified = $user && (int) ($user['email_verified'] ?? 0) === 1;
    $isStaff = $isVerified && in_array(
        $user['role'] ?? '',
        ['admin', 'librarian'],
        true
    );
    $isAdmin = $isVerified && ($user['role'] ?? '') === 'admin';

    echo '<!doctype html><html lang="en"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' | ' . e(APP_NAME) . '</title>';
    echo '<link rel="stylesheet" href="' . e(app_url('assets/style.css')) . '">';
    echo '<script defer src="' . e(app_url('assets/app.js')) . '"></script>';
    echo '</head><body>';
    echo '<header class="topbar"><a class="brand" href="' . e(app_url('index.php')) . '">' . e(APP_NAME) . '</a><nav>';

    if ($isVerified) {
        echo '<a href="' . e(app_url('dashboard.php')) . '">Dashboard</a>';
        echo '<a href="' . e(app_url('books.php')) . '">Books</a>';
        echo '<a href="' . e(app_url('borrowings.php')) . '">Borrowings</a>';
        echo '<a href="' . e(app_url('reservations.php')) . '">Reservations</a>';
        if ($isStaff) {
            echo '<a href="' . e(app_url('categories.php')) . '">Categories</a>';
            echo '<a href="' . e(app_url('reports.php')) . '">Reports</a>';
        }
        if ($isAdmin) {
            echo '<a href="' . e(app_url('users.php')) . '">Users</a>';
            echo '<a href="' . e(app_url('activity_logs.php')) . '">Activity logs</a>';
        }
        echo '<a href="' . e(app_url('logout.php')) . '">Log out</a>';
    } else {
        echo '<a href="' . e(app_url('login.php')) . '">Sign in</a>';
        echo '<a href="' . e(app_url('register.php')) . '">Register</a>';
    }

    echo '</nav></header><main class="container">';

    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $type = in_array($message['type'] ?? '', ['success', 'error', 'warning'], true)
            ? $message['type']
            : 'success';
        echo '<div class="flash ' . e($type) . '" role="status">' .
            e($message['message'] ?? '') . '</div>';
    }
}

function page_footer(): void
{
    echo '</main><footer class="site-footer"><div class="container">' .
        e(APP_NAME) . '</div></footer></body></html>';
}
