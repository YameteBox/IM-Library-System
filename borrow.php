<?php
// borrow.php: records a "request to borrow" for the logged-in student.
// POST book_id=123  ->  JSON { ok: bool, message: string }
session_start();
require 'db.php';
require_once 'notifications_helpers.php'; // userIsBanned()

header('Content-Type: application/json; charset=utf-8');

function reply(int $status, bool $ok, string $message): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(405, false, 'Invalid request.');
}
if (empty($_SESSION['user_id'])) {
    reply(401, false, 'Please log in first.');
}

$userId = (int) $_SESSION['user_id'];
$bookId = (int) ($_POST['book_id'] ?? 0);
if ($bookId <= 0) {
    reply(400, false, 'No book selected.');
}

if (userIsBanned($pdo, $userId)) {
    reply(403, false, 'Your account is suspended, so you cannot borrow right now.');
}

try {
    $pdo->beginTransaction();

    // Lock the book row so two students can't grab the last copy at once
    $stmt = $pdo->prepare('SELECT title, copies_available FROM books WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        $pdo->rollBack();
        reply(404, false, 'That book could not be found.');
    }
    if ((int) $book['copies_available'] <= 0) {
        $pdo->rollBack();
        reply(409, false, 'No copies of "' . $book['title'] . '" are available right now.');
    }

    // Already pending, or already borrowed and not yet returned?
    $stmt = $pdo->prepare(
        "SELECT status FROM borrowed_books
          WHERE user_id = :uid AND book_id = :bid
            AND returned_at IS NULL AND status IN ('pending', 'approved')
          LIMIT 1"
    );
    $stmt->execute([':uid' => $userId, ':bid' => $bookId]);
    $existing = $stmt->fetchColumn();

    if ($existing === 'pending') {
        $pdo->rollBack();
        reply(409, false, 'You already have a pending request for this book.');
    }
    if ($existing === 'approved') {
        $pdo->rollBack();
        reply(409, false, 'You already have this book borrowed.');
    }

    // due_date is left empty: the librarian sets it when approving the request.
    // (If your column is NOT NULL, change NULL below to  CURRENT_DATE + 7 )
    $pdo->prepare(
        "INSERT INTO borrowed_books (user_id, book_id, status, borrowed_at, due_date)
         VALUES (:uid, :bid, 'pending', NOW(), NULL)"
    )->execute([':uid' => $userId, ':bid' => $bookId]);

    $pdo->commit();
    reply(200, true, 'Requested "' . $book['title'] . '". Waiting for librarian approval.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    reply(500, false, 'Something went wrong. Please try again.');
}
