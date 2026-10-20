<?php
// Creates an admin account directly in the database.
//
// Run it from the command line (it refuses to run in a browser):
//   D:\xampp\php\php.exe seed-admin.php
//   D:\xampp\php\php.exe seed-admin.php A-002 jdoe jdoe@school.edu MyStrongPass123
//
// Arguments (all optional): admin_number username email password
// Defaults:                 A-001         admin    admin@example.com  password
//
// Delete this file once you have your admin account.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line.\n");
}

require __DIR__ . '/db.php';   // provides $pdo

$adminNumber = $argv[1] ?? 'A-001';
$username    = strtolower($argv[2] ?? 'admin');
$email       = $argv[3] ?? 'admin@example.com';
$password    = $argv[4] ?? 'password';

if (strlen($adminNumber) > 20 || strlen($username) > 30) {
    exit("Admin number must be 20 characters or fewer and username 30 or fewer.\n");
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO admins (admin_number, password_hash) VALUES (:an, :hash) RETURNING id'
    );
    $stmt->execute([':an' => $adminNumber, ':hash' => password_hash($password, PASSWORD_DEFAULT)]);
    $id = (int) $stmt->fetchColumn();

    $pdo->prepare(
        'INSERT INTO admin_details (id, username, email) VALUES (:id, :un, :em)'
    )->execute([':id' => $id, ':un' => $username, ':em' => $email]);

    // profile row with all defaults
    $pdo->prepare('INSERT INTO admin_profiles (id) VALUES (:id)')->execute([':id' => $id]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23505') {
        exit("An admin with that admin number, username or email already exists.\n");
    }
    exit('Database error: ' . $e->getMessage() . "\n");
}

echo "Admin created.\n";
echo "  Admin ID: $adminNumber\n";
echo "  Username: $username\n";
echo "  Password: $password\n";
echo "Log in at admin-login.php, then delete seed-admin.php.\n";