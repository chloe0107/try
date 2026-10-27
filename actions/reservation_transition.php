<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['admin','librarian']);
verify_csrf();
$id=(int)($_POST['id']??0);
$a=(string)($_POST['action']??'');
$actor=(int)current_user()['id'];
try {
    $conn->begin_transaction();
    // Read book ID first without a lock, then consistently lock book before reservation.
    $s=$conn->prepare('SELECT book_id FROM reservations WHERE id=?');
    $s->bind_param('i',$id); $s->execute(); $initial=$s->get_result()->fetch_assoc();
    if(!$initial) throw new Exception('Reservation not found.');
    $bid=(int)$initial['book_id'];
    $s=$conn->prepare('SELECT id,available_copies,is_archived FROM books WHERE id=? FOR UPDATE');
    $s->bind_param('i',$bid); $s->execute(); $book=$s->get_result()->fetch_assoc();
    if(!$book || (int)$book['is_archived']) throw new Exception('Book is missing or archived.');
    $s=$conn->prepare('SELECT * FROM reservations WHERE id=? FOR UPDATE');
    $s->bind_param('i',$id); $s->execute(); $r=$s->get_result()->fetch_assoc();
    if(!$r || (int)$r['book_id']!==$bid) throw new Exception('Reservation changed; please retry.');

    if($a==='approve' && $r['status']==='pending') {
        $s=$conn->prepare("UPDATE reservations SET status='approved',processed_by=?,updated_at=NOW() WHERE id=? AND status='pending'");
        $s->bind_param('ii',$actor,$id); $s->execute();
        if(!$s->affected_rows) throw new Exception('Reservation state changed; retry.');
        $detail='Approved reservation #'.$id;
    } elseif($a==='reject' && $r['status']==='pending') {
        $s=$conn->prepare("UPDATE reservations SET status='rejected',processed_by=?,updated_at=NOW() WHERE id=? AND status='pending'");
        $s->bind_param('ii',$actor,$id); $s->execute();
        if(!$s->affected_rows) throw new Exception('Reservation state changed; retry.');
        $detail='Rejected reservation #'.$id;
    } elseif($a==='ready' && $r['status']==='approved') {
        if((int)$book['available_copies']<1) throw new Exception('No copy is available yet. Keep this reservation approved until a copy is returned.');
        $s=$conn->prepare("SELECT id FROM reservations WHERE book_id=? AND id<? AND status IN('pending','approved','ready') ORDER BY id LIMIT 1 FOR UPDATE");
        $s->bind_param('ii',$bid,$id); $s->execute();
        if($s->get_result()->num_rows) throw new Exception('An earlier active reservation has priority. Process the queue in order.');
        // Marking Ready reserves one physical copy immediately, preventing double allocation.
        $s=$conn->prepare('UPDATE books SET available_copies=available_copies-1 WHERE id=? AND available_copies>0');
        $s->bind_param('i',$bid); $s->execute();
        if(!$s->affected_rows) throw new Exception('Inventory changed; retry.');
        $s=$conn->prepare("UPDATE reservations SET status='ready',processed_by=?,updated_at=NOW() WHERE id=? AND status='approved'");
        $s->bind_param('ii',$actor,$id); $s->execute();
        if(!$s->affected_rows) throw new Exception('Reservation state changed; retry.');
        $detail='Marked reservation ready for pickup #'.$id.'; one copy allocated';
    } elseif($a==='complete' && $r['status']==='ready') {
        $s=$conn->prepare("SELECT id FROM borrowings WHERE user_id=? AND book_id=? AND status IN('pending','approved','borrowed')");
        $s->bind_param('ii',$r['user_id'],$bid); $s->execute();
        if($s->get_result()->num_rows) throw new Exception('This member already has an active loan/request for this book.');
        // The copy was deducted when status became Ready, so do not deduct it again here.
        $due=date('Y-m-d',strtotime('+14 days'));
        $s=$conn->prepare("INSERT INTO borrowings(user_id,book_id,status,approved_by,approved_at,released_by,released_at,due_date) VALUES(?,?,'borrowed',?,NOW(),?,NOW(),?)");
        $s->bind_param('iiiis',$r['user_id'],$bid,$actor,$actor,$due); $s->execute();
        $loanId=$conn->insert_id;
        $s=$conn->prepare("UPDATE reservations SET status='fulfilled',processed_by=?,updated_at=NOW(),borrowing_id=? WHERE id=? AND status='ready'");
        $s->bind_param('iii',$actor,$loanId,$id); $s->execute();
        if(!$s->affected_rows) throw new Exception('Reservation state changed; retry.');
        $detail='Completed reservation #'.$id.'; created borrowing #'.$loanId;
    } elseif($a==='expire' && $r['status']==='ready') {
        // Release the copy held for pickup when the reservation expires/no-show is recorded.
        $s=$conn->prepare("UPDATE reservations SET status='expired',processed_by=?,updated_at=NOW() WHERE id=? AND status='ready'");
        $s->bind_param('ii',$actor,$id); $s->execute();
        if(!$s->affected_rows) throw new Exception('Reservation state changed; retry.');
        $s=$conn->prepare('UPDATE books SET available_copies=LEAST(total_copies,available_copies+1) WHERE id=?');
        $s->bind_param('i',$bid); $s->execute();
        $detail='Expired reservation #'.$id.'; allocated copy returned to available stock';
    } else {
        throw new Exception('Invalid action for this reservation status.');
    }
    $conn->commit();
    log_activity($conn,$actor,'reservation_'.$a,$detail);
    flash('success',$detail);
} catch(Throwable $e) {
    try { $conn->rollback(); } catch(Throwable $ignored) {}
    flash('error',$e->getMessage());
}
redirect('reservations.php');
