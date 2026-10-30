<?php
// Current account details, read from the database so every page shows the latest
// username (even right after it was changed on the profile page, or on another device).
// Also refreshes the session copies so any page that still reads them stays correct.

/** @return array{student_number:string, username:string}|null  null = student no longer exists */
function studentAccount(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.student_number, d.username
           FROM users u
           LEFT JOIN user_details d ON d.id = u.id
          WHERE u.id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $number   = (string) $row['student_number'];
    $username = trim((string) ($row['username'] ?? ''));
    $account  = ['student_number' => $number, 'username' => $username !== '' ? $username : $number];

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['student_number'] = $account['student_number'];
        $_SESSION['username']       = $account['username'];
    }
    return $account;
}

/** @return array{admin_number:string, username:string}|null  null = admin no longer exists */
function adminAccount(PDO $pdo, int $adminId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.admin_number, d.username
           FROM admins a
           LEFT JOIN admin_details d ON d.id = a.id
          WHERE a.id = :id'
    );
    $stmt->execute([':id' => $adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $number   = (string) $row['admin_number'];
    $username = trim((string) ($row['username'] ?? ''));
    $account  = ['admin_number' => $number, 'username' => $username !== '' ? $username : $number];

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['admin_number'] = $account['admin_number'];
        $_SESSION['admin_name']   = $account['username'];
    }
    return $account;
}
