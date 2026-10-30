<?php
/**
 * Borrowing workflow, shared by admin-library.php and dashboard.php.
 *
 *   student requests (borrow.php)  -> status 'pending',  no due date, no copy taken
 *   admin approves                 -> status 'approved', due date set, one copy taken
 *   admin rejects                  -> status 'rejected'
 *   admin confirms the return      -> returned_at set (status stays 'approved'), copy given back
 *
 * Copies are taken at approval, not at request time, so several students can ask for the last
 * copy and the librarian decides. Approval re-checks stock inside a transaction.
 */
require_once __DIR__ . '/search_helpers.php'; // h(), formatPublishedDate(), userIsBanned()

if (!defined('LOAN_PERIOD_DAYS')) {
    define('LOAN_PERIOD_DAYS', 7);
}
if (!defined('EXTEND_MAX_DAYS')) {
    define('EXTEND_MAX_DAYS', 60); // longest single extension an admin can apply
}
if (!defined('OVERDUE_FEE_PER_DAY')) {
    define('OVERDUE_FEE_PER_DAY', 5); // pesos, same rate as profile.php
}

/** Whole days past the due date (0 when not overdue or no due date). */
function loanDaysLate(?string $dueDate): int
{
    if ($dueDate === null || $dueDate === '') {
        return 0;
    }
    $due   = new DateTime($dueDate);
    $today = new DateTime('today');
    return $today > $due ? (int) $due->diff($today)->days : 0;
}

function loanResult(bool $ok, string $message, ?array $receipt = null): array
{
    return ['ok' => $ok, 'message' => $message, 'receipt' => $receipt];
}

// ---------------------------------------------------------------------------
// Admin actions
// ---------------------------------------------------------------------------

/** Approve a pending request: take a copy, set the due date. Returns ['ok', 'message', 'receipt']. */
function approveLoanRequest(PDO $pdo, int $loanId): array
{
    $fail = function (string $message) use ($pdo): array {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return loanResult(false, $message);
    };

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT bb.id, bb.user_id, bb.book_id, bb.status, bb.borrowed_at, bb.returned_at,
                    CAST(u.student_number AS text) AS student_number,
                    COALESCE(d.username, CAST(u.student_number AS text)) AS username
               FROM borrowed_books bb
               JOIN users u ON u.id = bb.user_id
               LEFT JOIN user_details d ON d.id = u.id
              WHERE bb.id = :id
                FOR UPDATE OF bb"
        );
        $stmt->execute([':id' => $loanId]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$loan) {
            return $fail('That request could not be found.');
        }
        if ($loan['status'] !== 'pending' || $loan['returned_at'] !== null) {
            return $fail('That request has already been handled.');
        }

        // Lock the book row so two approvals can't take the last copy together
        $stmt = $pdo->prepare('SELECT title, copies_available FROM books WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => (int) $loan['book_id']]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$book) {
            return $fail('That book no longer exists.');
        }
        if (userIsBanned($pdo, (int) $loan['user_id'])) {
            return $fail($loan['username'] . '\'s account is suspended, so this request can\'t be approved. You can reject it instead.');
        }
        if ((int) $book['copies_available'] <= 0) {
            return $fail('No copies of "' . $book['title'] . '" are available, so this request can\'t be approved yet.');
        }

        $due = (new DateTime('today'))->modify('+' . LOAN_PERIOD_DAYS . ' days')->format('Y-m-d');

        $pdo->prepare('UPDATE books SET copies_available = copies_available - 1 WHERE id = :id')
            ->execute([':id' => (int) $loan['book_id']]);
        $pdo->prepare("UPDATE borrowed_books SET status = 'approved', due_date = :due WHERE id = :id")
            ->execute([':due' => $due, ':id' => $loanId]);

        $pdo->commit();

        return loanResult(true, 'Approved "' . $book['title'] . '" for ' . $loan['username'] . '. Due ' . formatPublishedDate($due) . '.', [
            'no'         => 'R-' . date('Ymd') . '-' . str_pad((string) $loanId, 5, '0', STR_PAD_LEFT),
            'title'      => (string) $book['title'],
            'borrower'   => (string) $loan['username'],
            'student_id' => (string) $loan['student_number'],
            'requested'  => formatPublishedDate(substr((string) $loan['borrowed_at'], 0, 10)),
            'approved'   => formatPublishedDate(date('Y-m-d')),
            'due'        => formatPublishedDate($due),
        ]);
    } catch (PDOException $e) {
        error_log('approveLoanRequest: ' . $e->getMessage());
        return $fail('Something went wrong approving that request. Please try again.');
    }
}

/** Reject a pending request. No copy was taken, so stock is untouched. */
function rejectLoanRequest(PDO $pdo, int $loanId): array
{
    try {
        $stmt = $pdo->prepare(
            "UPDATE borrowed_books SET status = 'rejected'
              WHERE id = :id AND status = 'pending' AND returned_at IS NULL
          RETURNING (SELECT title FROM books WHERE books.id = borrowed_books.book_id) AS title"
        );
        $stmt->execute([':id' => $loanId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return loanResult(false, 'That request could not be found or was already handled.');
        }
        return loanResult(true, 'Rejected the request for "' . $row['title'] . '".');
    } catch (PDOException $e) {
        error_log('rejectLoanRequest: ' . $e->getMessage());
        return loanResult(false, 'Something went wrong rejecting that request. Please try again.');
    }
}

/** Confirm a loan was returned: stamp returned_at and give the copy back. */
function returnLoan(PDO $pdo, int $loanId): array
{
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT bb.book_id, bb.due_date, bk.title
               FROM borrowed_books bb
               JOIN books bk ON bk.id = bb.book_id
              WHERE bb.id = :id AND bb.status = 'approved' AND bb.returned_at IS NULL
                FOR UPDATE OF bb"
        );
        $stmt->execute([':id' => $loanId]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$loan) {
            $pdo->rollBack();
            return loanResult(false, 'That loan could not be found or was already returned.');
        }

        $pdo->prepare('UPDATE borrowed_books SET returned_at = NOW() WHERE id = :id')
            ->execute([':id' => $loanId]);
        $pdo->prepare('UPDATE books SET copies_available = COALESCE(copies_available, 0) + 1 WHERE id = :id')
            ->execute([':id' => (int) $loan['book_id']]);

        $pdo->commit();

        $message = 'Marked "' . $loan['title'] . '" as returned.';
        $late    = loanDaysLate((string) $loan['due_date']);
        if ($late > 0) {
            $message .= ' It was ' . $late . ' day' . ($late === 1 ? '' : 's') . ' overdue: collect ₱'
                . number_format($late * OVERDUE_FEE_PER_DAY, 2) . '.';
        }
        return loanResult(true, $message);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('returnLoan: ' . $e->getMessage());
        return loanResult(false, 'Something went wrong marking that loan as returned. Please try again.');
    }
}

/**
 * Push the due date of every active loan (approved, not yet returned) back by $days days.
 * For library closures, typhoons, etc. Overdue loans are moved too, so the late fee shrinks
 * accordingly. Returns ['ok', 'message', 'receipt'].
 */
function extendAllLoans(PDO $pdo, int $days): array
{
    if ($days < 1 || $days > EXTEND_MAX_DAYS) {
        return loanResult(false, 'Enter a number of days between 1 and ' . EXTEND_MAX_DAYS . '.');
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE borrowed_books
                SET due_date = due_date + (CAST(:n AS integer) * INTERVAL '1 day')
              WHERE status = 'approved' AND returned_at IS NULL AND due_date IS NOT NULL"
        );
        $stmt->bindValue(':n', $days, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();

        if ($count === 0) {
            return loanResult(false, 'Nothing is on loan right now, so no due dates were changed.');
        }
        return loanResult(true, 'Extended ' . $count . ' loan' . ($count === 1 ? '' : 's')
            . ' by ' . $days . ' day' . ($days === 1 ? '' : 's') . '.');
    } catch (PDOException $e) {
        error_log('extendAllLoans: ' . $e->getMessage());
        return loanResult(false, 'Something went wrong extending the due dates. Please try again.');
    }
}

/**
 * Push back the due date of active (approved, not yet returned) loans.
 *
 * @param string $scope    'loan' = one loan, 'user' = all of one borrower's loans, 'item' = all loans of one book
 * @param int    $targetId borrowed_books.id, users.id or books.id, depending on $scope
 * @param int    $days     1..EXTEND_MAX_DAYS
 * @return array  ['ok', 'message', 'receipt'] like extendAllLoans()
 */
function extendActiveLoans(PDO $pdo, string $scope, int $targetId, int $days): array
{
    // Whitelisted column names: $scope is never put into the SQL directly
    $columns = ['loan' => 'bb.id', 'user' => 'bb.user_id', 'item' => 'bb.book_id'];
    if (!isset($columns[$scope]) || $targetId <= 0) {
        return loanResult(false, 'Choose what to extend.');
    }
    if ($days < 1 || $days > EXTEND_MAX_DAYS) {
        return loanResult(false, 'Enter a number of days between 1 and ' . EXTEND_MAX_DAYS . '.');
    }
    $col = $columns[$scope];

    try {
        $stmt = $pdo->prepare(
            "UPDATE borrowed_books AS bb
                SET due_date = bb.due_date + (CAST(:days AS integer) * INTERVAL '1 day')
              WHERE $col = :id
                AND bb.status = 'approved'
                AND bb.returned_at IS NULL
                AND bb.due_date IS NOT NULL"
        );
        $stmt->execute([':days' => $days, ':id' => $targetId]);
        $count = $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('extendActiveLoans: ' . $e->getMessage());
        return loanResult(false, 'Something went wrong extending the loan(s). Please try again.');
    }

    if ($count === 0) {
        return loanResult(false, 'No active loans matched, so nothing was extended.');
    }

    $what = $scope === 'loan' ? 'the loan' : ($scope === 'user' ? 'that borrower’s loan(s)' : 'that item’s loan(s)');
    return loanResult(true, 'Extended ' . $what . ' by ' . $days . ' day' . ($days === 1 ? '' : 's')
        . ' (' . $count . ' loan' . ($count === 1 ? '' : 's') . ' updated).');
}

/**
 * Borrowers and items that currently have an active loan, for the "extend for…" dropdowns.
 *
 * @return array{users: list<array>, books: list<array>}
 */
function extendTargetOptions(PDO $pdo): array
{
    $users = $pdo->query(
        "SELECT u.id, CAST(u.student_number AS text) AS student_number, d.username, COUNT(*) AS loans
           FROM borrowed_books bb
           JOIN users u ON u.id = bb.user_id
           LEFT JOIN user_details d ON d.id = u.id
          WHERE bb.status = 'approved' AND bb.returned_at IS NULL
          GROUP BY u.id, u.student_number, d.username
          ORDER BY d.username, u.student_number"
    )->fetchAll(PDO::FETCH_ASSOC);

    $books = $pdo->query(
        "SELECT bk.id, bk.title, COUNT(*) AS loans
           FROM borrowed_books bb
           JOIN books bk ON bk.id = bb.book_id
          WHERE bb.status = 'approved' AND bb.returned_at IS NULL
          GROUP BY bk.id, bk.title
          ORDER BY bk.title"
    )->fetchAll(PDO::FETCH_ASSOC);

    return ['users' => $users, 'books' => $books];
}

// ---------------------------------------------------------------------------
// Admin lists
// ---------------------------------------------------------------------------

/** Pending requests, oldest first. */
function pendingLoanRequests(PDO $pdo): array
{
    return $pdo->query(
        "SELECT bb.id AS loan_id, bb.borrowed_at, bk.title, bk.copies_available,
                COALESCE(d.username, CAST(u.student_number AS text)) AS borrower_username,
                CAST(u.student_number AS text) AS borrower_student_id
           FROM borrowed_books bb
           JOIN books bk ON bk.id = bb.book_id
           JOIN users u  ON u.id = bb.user_id
           LEFT JOIN user_details d ON d.id = u.id
          WHERE bb.status = 'pending' AND bb.returned_at IS NULL
          ORDER BY bb.borrowed_at, bb.id"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** Approved, not-yet-returned loans, soonest due first. */
function activeLoansForAdmin(PDO $pdo): array
{
    return $pdo->query(
        "SELECT bb.id AS loan_id, bb.due_date, bk.title,
                COALESCE(d.username, CAST(u.student_number AS text)) AS borrower_username,
                CAST(u.student_number AS text) AS borrower_student_id
           FROM borrowed_books bb
           JOIN books bk ON bk.id = bb.book_id
           JOIN users u  ON u.id = bb.user_id
           LEFT JOIN user_details d ON d.id = u.id
          WHERE bb.status = 'approved' AND bb.returned_at IS NULL
          ORDER BY bb.due_date, bb.id"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** Every loan (any status) for the given books, newest first, grouped by book id. */
function loanHistoryByBook(PDO $pdo, array $bookIds): array
{
    $bookIds = array_values(array_unique(array_map('intval', $bookIds)));
    if (empty($bookIds)) {
        return [];
    }

    $in   = implode(',', array_fill(0, count($bookIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT bb.id AS loan_id, bb.book_id, bb.status, bb.borrowed_at, bb.due_date, bb.returned_at,
                COALESCE(d.username, CAST(u.student_number AS text)) AS borrower_username,
                CAST(u.student_number AS text) AS borrower_student_id
           FROM borrowed_books bb
           JOIN users u ON u.id = bb.user_id
           LEFT JOIN user_details d ON d.id = u.id
          WHERE bb.book_id IN ($in)
          ORDER BY bb.borrowed_at DESC, bb.id DESC"
    );
    $stmt->execute($bookIds);

    $byBook = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byBook[(int) $row['book_id']][] = $row;
    }
    return $byBook;
}

// ---------------------------------------------------------------------------
// Student dashboard
// ---------------------------------------------------------------------------

/** This student's approved, not-yet-returned loans, soonest due first. */
function studentActiveLoans(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT bk.title, bk.authors, bb.due_date
           FROM borrowed_books bb
           JOIN books bk ON bk.id = bb.book_id
          WHERE bb.user_id = :uid AND bb.status = 'approved' AND bb.returned_at IS NULL
          ORDER BY bb.due_date, bb.id"
    );
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** This student's requests still waiting for the librarian, newest first. */
function studentPendingRequests(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT bk.title, bk.authors, bb.borrowed_at
           FROM borrowed_books bb
           JOIN books bk ON bk.id = bb.book_id
          WHERE bb.user_id = :uid AND bb.status = 'pending' AND bb.returned_at IS NULL
          ORDER BY bb.borrowed_at DESC, bb.id DESC"
    );
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** The most-borrowed items overall (approved loans). */
function popularBooks(PDO $pdo, int $limit = 5): array
{
    $stmt = $pdo->prepare(
        "SELECT bk.id, bk.title, bk.authors, bk.media_type, bk.classification, bk.copies_available,
                COUNT(*) AS times_borrowed
           FROM borrowed_books bb
           JOIN books bk ON bk.id = bb.book_id
          WHERE bb.status = 'approved'
          GROUP BY bk.id, bk.title, bk.authors, bk.media_type, bk.classification, bk.copies_available
          ORDER BY times_borrowed DESC, bk.title
          LIMIT :n"
    );
    $stmt->bindValue(':n', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Classifications this student borrows most, most-borrowed first. */
function studentFavouriteClassifications(PDO $pdo, int $userId, int $limit = 3): array
{
    $stmt = $pdo->prepare(
        "SELECT bk.classification
           FROM borrowed_books bb
           JOIN books bk ON bk.id = bb.book_id
          WHERE bb.user_id = :uid AND bb.status = 'approved'
            AND bk.classification IS NOT NULL AND bk.classification <> ''
          GROUP BY bk.classification
          ORDER BY COUNT(*) DESC, bk.classification
          LIMIT :n"
    );
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':n', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Up to 100 in-stock books this student has never requested, most popular first. */
function recommendationCandidates(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT bk.id, bk.title, bk.authors, bk.media_type, bk.classification, bk.copies_available,
                COALESCE(p.times_borrowed, 0) AS times_borrowed
           FROM books bk
           LEFT JOIN (SELECT book_id, COUNT(*) AS times_borrowed
                        FROM borrowed_books WHERE status = 'approved' GROUP BY book_id) p
                  ON p.book_id = bk.id
          WHERE COALESCE(bk.copies_available, 0) > 0
            AND NOT EXISTS (SELECT 1 FROM borrowed_books x
                             WHERE x.user_id = :uid AND x.book_id = bk.id
                               AND x.status IN ('pending', 'approved'))
          ORDER BY times_borrowed DESC, bk.title
          LIMIT 100"
    );
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------------------
// Catalog
// ---------------------------------------------------------------------------

/**
 * Delete a catalog item. Refused while it is on loan or has pending requests (return or reject
 * those first), and when it has loan history, which the database keeps. List entries and
 * "notify me" watches go with it. Returns ['ok', 'message', 'cover'] so the caller can remove the file.
 */
function deleteCatalogItem(PDO $pdo, int $bookId): array
{
    $result = function (bool $ok, string $message, ?string $cover = null): array {
        return ['ok' => $ok, 'message' => $message, 'cover' => $cover];
    };

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT title, cover_image FROM books WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $bookId]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$book) {
            $pdo->rollBack();
            return $result(false, 'That book could not be found.');
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM borrowed_books
              WHERE book_id = :id AND returned_at IS NULL AND status IN ('pending', 'approved')"
        );
        $stmt->execute([':id' => $bookId]);
        if ((int) $stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            return $result(false, '"' . $book['title'] . '" is on loan or has pending requests. Return or reject those first, then delete it.');
        }

        $pdo->prepare('DELETE FROM books WHERE id = :id')->execute([':id' => $bookId]);
        $pdo->commit();

        return $result(true, 'Deleted "' . $book['title'] . '" from the catalog.', $book['cover_image'] ?? null);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23503') { // foreign key: loan history still points at this item
            return $result(false, 'That item has loan history, so it can\'t be deleted.');
        }
        error_log('deleteCatalogItem: ' . $e->getMessage());
        return $result(false, 'Something went wrong deleting that item. Please try again.');
    }
}
