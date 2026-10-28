<?php
// JSON endpoint for "Add to list" on library.php:  POST list_id=3 book_id=7
session_start();
require 'db.php';
require_once 'lists_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function listsReply(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    listsReply(405, ['ok' => false, 'message' => 'Invalid request.']);
}
if (empty($_SESSION['user_id'])) {
    listsReply(401, ['ok' => false, 'message' => 'Please log in first.']);
}

$result = addBookToList($pdo, (int) $_SESSION['user_id'], (int) ($_POST['list_id'] ?? 0), (int) ($_POST['book_id'] ?? 0));
listsReply($result['ok'] ? 200 : 400, ['ok' => $result['ok'], 'message' => $result['message']]);
