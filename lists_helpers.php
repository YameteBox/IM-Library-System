<?php
/**
 * Reading lists: a student's named lists (table `lists`) and the books on them (table `list_books`,
 * created on first use). Every function checks the list belongs to the student.
 */
require_once __DIR__ . '/search_helpers.php'; // h(), formatPublishedDate()

const LIST_NAME_MAX = 100;

/** Creates list_books the first time it is needed. Deleting a list or a book removes its entries. */
function ensureListTables(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS list_books (
            list_id  INTEGER NOT NULL REFERENCES lists(id) ON DELETE CASCADE,
            book_id  INTEGER NOT NULL REFERENCES books(id) ON DELETE CASCADE,
            added_at TIMESTAMP NOT NULL DEFAULT NOW(),
            PRIMARY KEY (list_id, book_id)
        )'
    );
}

function listResult(bool $ok, string $message, array $extra = []): array
{
    return ['ok' => $ok, 'message' => $message] + $extra;
}

/** Null when the name is fine, otherwise the error to show. */
function listNameError(PDO $pdo, int $userId, string $name, int $exceptListId = 0): ?string
{
    if ($name === '') {
        return 'List name cannot be empty.';
    }
    if (mb_strlen($name) > LIST_NAME_MAX) {
        return 'List name must be ' . LIST_NAME_MAX . ' characters or fewer.';
    }
    $stmt = $pdo->prepare('SELECT 1 FROM lists WHERE user_id = :uid AND LOWER(name) = LOWER(:n) AND id <> :id');
    $stmt->execute([':uid' => $userId, ':n' => $name, ':id' => $exceptListId]);
    return $stmt->fetchColumn() ? 'You already have a list with that name.' : null;
}

function createList(PDO $pdo, int $userId, string $name): array
{
    $name = trim($name);
    try {
        if ($error = listNameError($pdo, $userId, $name)) {
            return listResult(false, $error);
        }
        $pdo->prepare('INSERT INTO lists (user_id, name) VALUES (:uid, :n)')->execute([':uid' => $userId, ':n' => $name]);
        return listResult(true, 'List "' . $name . '" created.');
    } catch (PDOException $e) {
        error_log('createList: ' . $e->getMessage());
        return listResult(false, 'Something went wrong creating the list. Please try again.');
    }
}

function renameList(PDO $pdo, int $userId, int $listId, string $name): array
{
    $name = trim($name);
    try {
        if (!findUserList($pdo, $userId, $listId)) {
            return listResult(false, 'That list could not be found.');
        }
        if ($error = listNameError($pdo, $userId, $name, $listId)) {
            return listResult(false, $error);
        }
        $pdo->prepare('UPDATE lists SET name = :n WHERE id = :id AND user_id = :uid')
            ->execute([':n' => $name, ':id' => $listId, ':uid' => $userId]);
        return listResult(true, 'List renamed to "' . $name . '".');
    } catch (PDOException $e) {
        error_log('renameList: ' . $e->getMessage());
        return listResult(false, 'Something went wrong renaming the list. Please try again.');
    }
}

function deleteList(PDO $pdo, int $userId, int $listId): array
{
    try {
        ensureListTables($pdo);
        $list = findUserList($pdo, $userId, $listId);
        if (!$list) {
            return listResult(false, 'That list could not be found.');
        }
        $pdo->prepare('DELETE FROM lists WHERE id = :id AND user_id = :uid')->execute([':id' => $listId, ':uid' => $userId]);
        return listResult(true, 'List "' . $list['name'] . '" deleted.');
    } catch (PDOException $e) {
        error_log('deleteList: ' . $e->getMessage());
        return listResult(false, 'Something went wrong deleting the list. Please try again.');
    }
}

function findUserList(PDO $pdo, int $userId, int $listId): ?array
{
    $stmt = $pdo->prepare('SELECT id, name FROM lists WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $listId, ':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** The student's lists with how many books each holds. */
function userListsWithCounts(PDO $pdo, int $userId): array
{
    ensureListTables($pdo);
    $stmt = $pdo->prepare(
        'SELECT l.id, l.name, COUNT(lb.book_id) AS item_count
           FROM lists l LEFT JOIN list_books lb ON lb.list_id = l.id
          WHERE l.user_id = :uid
          GROUP BY l.id, l.name
          ORDER BY l.name'
    );
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function listBooks(PDO $pdo, int $listId): array
{
    ensureListTables($pdo);
    $stmt = $pdo->prepare(
        'SELECT bk.id, bk.title, bk.authors, bk.media_type, bk.isbn, bk.published_date, bk.shelf_number,
                bk.classification, bk.key_word, bk.copies_available, bk.cover_image, bk.accession_number
           FROM list_books lb JOIN books bk ON bk.id = lb.book_id
          WHERE lb.list_id = :id
          ORDER BY lb.added_at DESC, bk.title'
    );
    $stmt->execute([':id' => $listId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addBookToList(PDO $pdo, int $userId, int $listId, int $bookId): array
{
    try {
        ensureListTables($pdo);
        $list = findUserList($pdo, $userId, $listId);
        if (!$list) {
            return listResult(false, 'Select a list first.');
        }
        $stmt = $pdo->prepare('SELECT title FROM books WHERE id = :id');
        $stmt->execute([':id' => $bookId]);
        $title = $stmt->fetchColumn();
        if ($title === false) {
            return listResult(false, 'That book could not be found.');
        }
        $stmt = $pdo->prepare('INSERT INTO list_books (list_id, book_id) VALUES (:l, :b) ON CONFLICT DO NOTHING');
        $stmt->execute([':l' => $listId, ':b' => $bookId]);
        if ($stmt->rowCount() === 0) {
            return listResult(false, '"' . $title . '" is already in "' . $list['name'] . '".');
        }
        return listResult(true, '"' . $title . '" added to "' . $list['name'] . '".');
    } catch (PDOException $e) {
        error_log('addBookToList: ' . $e->getMessage());
        return listResult(false, 'Something went wrong. Please try again.');
    }
}

function removeBookFromList(PDO $pdo, int $userId, int $listId, int $bookId): array
{
    try {
        ensureListTables($pdo);
        $stmt = $pdo->prepare(
            'DELETE FROM list_books
              WHERE list_id = :l AND book_id = :b
                AND list_id IN (SELECT id FROM lists WHERE user_id = :uid)'
        );
        $stmt->execute([':l' => $listId, ':b' => $bookId, ':uid' => $userId]);
        return $stmt->rowCount() > 0
            ? listResult(true, 'Book removed from the list.')
            : listResult(false, 'That book is not in this list.');
    } catch (PDOException $e) {
        error_log('removeBookFromList: ' . $e->getMessage());
        return listResult(false, 'Something went wrong. Please try again.');
    }
}
