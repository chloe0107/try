<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'librarian']);
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$actor = (int) current_user()['id'];

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare('SELECT * FROM borrowings WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $borrowing = $stmt->get_result()->fetch_assoc();
    if (!$borrowing) {
        throw new Exception('Record not found.');
    }

    $borrowingId = (int) $borrowing['book_id'];
    $detail = '';
    $dueDate = null;

    if ($action === 'approve' && $borrowing['status'] === 'pending') {
        $stmt = $conn->prepare(
            "UPDATE borrowings SET status='approved', approved_by=?, approved_at=NOW()
             WHERE id=? AND status='pending'"
        );
        $stmt->bind_param('ii', $actor, $id);
        $stmt->execute();
        $detail = 'Approved borrowing #' . $id;
    } elseif ($action === 'reject' && $borrowing['status'] === 'pending') {
        $stmt = $conn->prepare(
            "UPDATE borrowings SET status='rejected', approved_by=?, approved_at=NOW()
             WHERE id=? AND status='pending'"
        );
        $stmt->bind_param('ii', $actor, $id);
        $stmt->execute();
        $detail = 'Rejected borrowing #' . $id;
    } elseif ($action === 'release' && $borrowing['status'] === 'approved') {
        $stmt = $conn->prepare('SELECT available_copies FROM books WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $borrowingId);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        if (!$book || $book['available_copies'] < 1) {
            throw new Exception('No available copies remain.');
        }

        $stmt = $conn->prepare(
            "SELECT id FROM reservations
             WHERE book_id=? AND status IN('pending','approved','ready')
             ORDER BY id LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param('i', $borrowingId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows) {
            throw new Exception('An active reservation queue has priority. Process reservations first.');
        }

        $stmt = $conn->prepare(
            'UPDATE books SET available_copies=available_copies-1
             WHERE id=? AND available_copies>0'
        );
        $stmt->bind_param('i', $borrowingId);
        $stmt->execute();
        if (!$stmt->affected_rows) {
            throw new Exception('Inventory changed; retry.');
        }

        $dueDate = date('Y-m-d', strtotime('+14 days'));
        $stmt = $conn->prepare(
            "UPDATE borrowings
             SET status='borrowed', released_by=?, released_at=NOW(), due_date=?
             WHERE id=? AND status='approved'"
        );
        $stmt->bind_param('isi', $actor, $dueDate, $id);
        $stmt->execute();
        $detail = 'Released borrowing #' . $id;
    } elseif ($action === 'return' && $borrowing['status'] === 'borrowed') {
        $stmt = $conn->prepare(
            "UPDATE borrowings SET status='returned', returned_by=?, returned_at=NOW()
             WHERE id=? AND status='borrowed'"
        );
        $stmt->bind_param('ii', $actor, $id);
        $stmt->execute();
        if (!$stmt->affected_rows) {
            throw new Exception('Already processed.');
        }

        $stmt = $conn->prepare(
            'UPDATE books
             SET available_copies=LEAST(total_copies, available_copies+1)
             WHERE id=?'
        );
        $stmt->bind_param('i', $borrowingId);
        $stmt->execute();
        $detail = 'Returned borrowing #' . $id;
    } else {
        throw new Exception('Invalid action for this status.');
    }

    $conn->commit();
    log_activity($conn, $actor, 'borrowing_' . $action, $detail);
    flash('success', $detail);

} catch (Throwable $exception) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    flash('error', $exception->getMessage());
}

redirect('borrowings.php');