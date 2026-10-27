LIBRARY MANAGEMENT SYSTEM - XAMPP SETUP
=======================================
Stack: PHP 8.x, MySQL/MariaDB, MySQLi prepared statements, HTML, CSS, JavaScript.

INSTALL
1. Extract the project folder into C:\xampp\htdocs\ (the folder can have any name).
2. Start Apache and MySQL in XAMPP.
3. Open http://localhost/phpmyadmin and import database.sql.
4. The database defaults are library_management_system, localhost, and XAMPP's root user with a blank password. Set DB_HOST, DB_NAME, DB_USER, and DB_PASS environment variables to override them.
5. Visit http://localhost/<project-folder>/register.php and create an account. The app detects its URL from the folder where it is installed.
6. Email verification is required before protected pages can be used. For local XAMPP testing without SMTP, the registration/resend page shows a one-time local verification link; it is only shown for localhost requests while APP_ENV is development.

PHONE TESTING ON THE SAME WI-FI
In development, the app uses the host name used to open it: localhost on the computer and the computer's private Wi-Fi IP address on a phone connected to the same Wi-Fi. Use http://<computer-ip>/<project-folder> on the phone. Allow Apache through Windows Firewall if the phone cannot connect. A BASE_URL environment variable overrides this behavior. The local IP is not available outside the Wi-Fi network; use a public HTTPS domain for remote access.

The home page library photograph is bundled locally at assets/library-interior.jpg and credited to Unsplash, so the image itself does not need an internet connection to display.

CREATE FIRST ADMIN
The first registration is a member. After registering, promote your account in phpMyAdmin:
UPDATE users SET role='admin', email_verified=1 WHERE email='your-email@example.com';
Log out and log back in. Use Users page to assign librarian/member roles to other accounts.
For a quick classroom demonstration, this SQL manually marks your account verified. To demonstrate actual email verification, configure email delivery and register another test account.

EMAIL DELIVERY
The application sends verification messages through PHPMailer over SMTP. For Gmail, enable 2-Step Verification and create a Google App Password; do not use your normal Gmail password. Copy config/mail.local.example.php to config/mail.local.php, then set SMTP_USERNAME, SMTP_PASSWORD, and MAIL_FROM in config/mail.local.php. The local file is excluded from Git. Alternatively, set SMTP_USERNAME and SMTP_PASSWORD as environment variables; SMTP_HOST defaults to smtp.gmail.com and SMTP_PORT to 587. Environment variables override the local file. Restart Apache after changing environment variables. Never share or commit credentials or include them in a submission ZIP. Registration sends a one-time verification link (not a numeric code) that expires after 24 hours. If credentials are missing or sending fails, the local verification link is only shown on localhost during development; set APP_ENV=production when deploying.

DEMO
1. Register and verify a member.
2. Admin creates/assigns librarian account in Users.
3. Staff adds/edits books and categories.
4. Member submits borrowing request.
5. Member submits a reservation (even if no copies are available).
6. Librarian approves -> marks ready for pickup when a copy is available. Marking Ready reserves/deducts one copy immediately so it cannot be allocated twice. Completing pickup creates the borrowing record without deducting stock again.
7. If a ready reservation is not picked up, staff can use Expire / no-show to release the reserved copy back to available stock.
8. Demonstrate queue order, cancellation, dashboard, search/filter, print report, role permissions, invalid actions, and activity logs.
9. Return the issued book from Borrowing; inventory increases and can make the next reservation eligible for pickup. Direct borrowing releases are blocked while an active reservation queue exists.

ROLES
admin: manage users/roles, catalog, borrowing, reports, logs
librarian: manage books/categories, approve/reject/release/return, reports
member: browse books, request borrowing, reserve books, cancel eligible reservations, view only own records

DATA STRUCTURE / SECURITY
Normalized relational tables with primary/foreign keys, indexes and copy-count constraint. Borrowing uses explicit transaction states. InnoDB transactions and row locks protect inventory updates. Reservations use a FIFO queue per book: earlier active reservations take priority; the librarian marks a reservation ready only when a copy is available. Completing pickup creates the borrowing record and reduces available copies in the same transaction. Passwords use password_hash/password_verify; queries use MySQLi prepared statements; forms use CSRF tokens; output is escaped; permissions enforced server-side.

The application refreshes account status and role from the database on protected requests, so deactivated users lose access without relying on a fresh login.
Before public deployment: use HTTPS, dedicated DB user, strong secrets, configured SMTP, backups, rate limiting, and production error logging. This is a classroom starter project; test locally before submission.
