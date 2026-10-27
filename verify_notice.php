<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
page_header('Verify email');
?>

<div class="panel narrow">
    <h1>Verify your email</h1>
    <p>Your account must be verified before using protected features.</p>

    <form method="post" action="resend_verification.php">
        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
        <button class="btn">Resend verification email</button>
    </form>

    <a href="logout.php">Log out</a>
</div>

<?php page_footer();?>