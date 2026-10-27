
<?php

require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (
        $name === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($pass) < 8 ||
        $pass !== $confirmPass
    ) {
        flash(
            'error',
            'Enter a valid name and email, and matching passwords of at least 8 characters.'
        );

        redirect('register.php');
    }

    try {
        $s = $conn->prepare(
            'SELECT id FROM users WHERE email = ? LIMIT 1'
        );

        $s->bind_param('s', $email);
        $s->execute();
        $existing = $s->get_result()->fetch_assoc();
        $s->close();

        if ($existing) {
            flash('error', 'Email already registered.');
            redirect('register.php');
        }

        /*
         * IMPORTANT:
         * The role must match the ENUM or allowed values
         * in your users table.
         */
        $role = 'member';

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400);

        $s = $conn->prepare(
            'INSERT INTO users
            (
                full_name,
                email,
                password_hash,
                role,
                verification_token,
                verification_expires
            )
            VALUES (?, ?, ?, ?, ?, ?)'
        );

        $s->bind_param(
            'ssssss',
            $name,
            $email,
            $hash,
            $role,
            $token,
            $expires
        );

        $s->execute();
        $uid = (int) $conn->insert_id;
        $s->close();

        log_activity(
            $conn,
            $uid,
            'account_created',
            'Self registration'
        );

        $sent = send_verification_email(
            $email,
            $name,
            $token
        );

        if ($sent) {
            flash(
                'success',
                'Account created. Check your email for the verification link.'
            );
        } else {
            /*
             * Keep the account unverified if email delivery fails.
             * Do not expose a verification token publicly.
             */
            error_log(
                'Verification email could not be sent for user ID ' . $uid
            );

            flash(
                'error',
                'Your account was created, but the verification email could not be sent. Check the server error log and SMTP settings, then use the resend verification feature.'
            );
        }

        redirect('login.php');

    } catch (\Throwable $ex) {
        error_log('Registration failed: ' . $ex->getMessage());

        flash(
            'error',
            'Registration failed. Check the database configuration and PHP error log.'
        );

        redirect('register.php');
    }
}

page_header('Register');
?>

<div class="auth-card">
    <h1>Create account</h1>

    <p class="muted">
        Create your library account. Your email must be verified
        before you can use protected features.
    </p>

    <form method="post" action="<?= e(app_url('register.php')) ?>">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <label for="full_name">Full name</label>
        <input
            id="full_name"
            type="text"
            name="full_name"
            maxlength="120"
            autocomplete="name"
            required
        >

        <label for="email">Email</label>
        <input
            id="email"
            type="email"
            name="email"
            autocomplete="email"
            required
        >

        <label for="password">Password (8+ characters)</label>
        <input
            id="password"
            type="password"
            name="password"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <label for="confirm_password">Confirm password</label>
        <input
            id="confirm_password"
            type="password"
            name="confirm_password"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <button type="submit" class="btn full">
            Register
        </button>
    </form>
</div>

<?php page_footer(); ?>
