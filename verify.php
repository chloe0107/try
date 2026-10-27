
<?php

require_once __DIR__ . '/includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));

if (
    $token === '' ||
    !preg_match('/\A[a-f0-9]{64}\z/i', $token)
) {
    flash(
        'error',
        'Invalid verification link. Please request a new verification email.'
    );

    redirect('login.php');
}

try {
    $conn->begin_transaction();

    $s = $conn->prepare(
        'SELECT id
         FROM users
         WHERE verification_token = ?
           AND email_verified = 0
           AND verification_expires > NOW()
         LIMIT 1
         FOR UPDATE'
    );

    $s->bind_param('s', $token);
    $s->execute();

    $user = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$user) {
        $conn->rollback();

        flash(
            'error',
            'This verification link is invalid, expired, or already used.'
        );

        redirect('login.php');
    }

    $id = (int) $user['id'];

    $s = $conn->prepare(
        'UPDATE users
         SET email_verified = 1,
             verification_token = NULL,
             verification_expires = NULL
         WHERE id = ?
           AND email_verified = 0'
    );

    $s->bind_param('i', $id);
    $s->execute();

    $updated = $s->affected_rows;
    $s->close();

    if ($updated !== 1) {
        $conn->rollback();

        flash(
            'error',
            'The account could not be verified. Please request a new link.'
        );

        redirect('login.php');
    }

    log_activity(
        $conn,
        $id,
        'email_verified',
        'Verification successful'
    );

    $conn->commit();

    flash(
        'success',
        'Email verified successfully! You can now log in.'
    );

} catch (\Throwable $ex) {
    try {
        $conn->rollback();
    } catch (\Throwable $ignored) {
        // Transaction may already have ended.
    }

    error_log('Email verification failed: ' . $ex->getMessage());

    flash(
        'error',
        'Verification failed because of a server or database error.'
    );
}

redirect('login.php');
?>