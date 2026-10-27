<?php
 
$host = 'localhost';
$port = '5432';        
$dbname = 'Library_Database';
$dbuser = 'postgres';  
$dbpass = 'passplease';          
 
try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $dbuser,
        $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION] // throw real errors instead of failing silently
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

