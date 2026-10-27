<?php
// JSON endpoint for the notifications bell in library.php:
//   action=read    keys=overdue:12,stock:7   mark notifications as read
//   action=watch   book_id=7                 tell me when this book has copies again
//   action=unwatch book_id=7                 stop telling me
session_start();
header('Content-Type: application/json; charset=utf-8');

function notifReply(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if (empty($_SESSION['user_id'])) {
    notifReply(401, ['ok' => false, 'error' => 'Please log in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notifReply(405, ['ok' => false, 'error' => 'Invalid request.']);
}

require 'db.php';
require_once 'notifications_helpers.php';

$sessionToken = (string) ($_SESSION['notif_csrf'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, (string) ($_POST['csrf'] ?? ''))) {
    notifReply(403, ['ok' => false, 'error' => 'Your session expired. Reload the page and try again.']);
}

$userId = (int) $_SESSION['user_id'];
$action = (string) ($_POST['action'] ?? '');

try {
    ensureNotificationTables($pdo);

    if ($action === 'read') {
        $keys = array_unique(array_filter(explode(',', (string) ($_POST['keys'] ?? '')), 'isValidNotificationKey'));
        if (empty($keys) || count($keys) > 100) {
            notifReply(400, ['ok' => false, 'error' => 'Nothing to mark as read.']);
        }

        $markRead = $pdo->prepare(
            'INSERT INTO notification_reads (user_id, notif_key) VALUES (:uid, :k) ON CONFLICT DO NOTHING'
        );
        $unwatch = $pdo->prepare('DELETE FROM book_watches WHERE user_id = :uid AND book_id = :bid');

        $pdo->beginTransaction();
        foreach ($keys as $key) {
            [$type, $id] = explode(':', $key, 2);
            if ($type === 'stock') {
                // Reading a back-in-stock notice ends the watch, so a later restock after
                // another sell-out can notify again.
                $unwatch->execute([':uid' => $userId, ':bid' => (int) $id]);
            } else {
                $markRead->execute([':uid' => $userId, ':k' => $key]);
            }
        }
        $pdo->commit();

        notifReply(200, ['ok' => true]);
    }

    if ($action === 'watch' || $action === 'unwatch') {
        $bookId = filter_var($_POST['book_id'] ?? '', FILTER_VALIDATE_INT);
        if ($bookId === false || $bookId < 1) {
            notifReply(400, ['ok' => false, 'error' => 'That book could not be found.']);
        }

        if ($action === 'unwatch') {
            $pdo->prepare('DELETE FROM book_watches WHERE user_id = :uid AND book_id = :bid')
                ->execute([':uid' => $userId, ':bid' => $bookId]);
            notifReply(200, ['ok' => true]);
        }

        $stmt = $pdo->prepare('SELECT COALESCE(copies_available, 0) FROM books WHERE id = :id');
        $stmt->execute([':id' => $bookId]);
        $copies = $stmt->fetchColumn();

        if ($copies === false) {
            notifReply(404, ['ok' => false, 'error' => 'That book could not be found.']);
        }
        if ((int) $copies > 0) {
            notifReply(409, ['ok' => false, 'error' => 'This book is available now. You can request to borrow it.']);
        }
        if (userIsBanned($pdo, $userId)) {
            notifReply(403, ['ok' => false, 'error' => 'Your account is banned from borrowing.']);
        }

        $pdo->prepare('INSERT INTO book_watches (user_id, book_id) VALUES (:uid, :bid) ON CONFLICT DO NOTHING')
            ->execute([':uid' => $userId, ':bid' => $bookId]);
        notifReply(200, ['ok' => true]);
    }

    notifReply(400, ['ok' => false, 'error' => 'Invalid request.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('notifications.php: ' . $e->getMessage());
    notifReply(500, ['ok' => false, 'error' => 'Something went wrong. Please try again.']);
}
