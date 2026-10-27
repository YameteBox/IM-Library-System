<?php
 
$host = 'localhost';
$port = '5432';        
$dbname = 'Library_Database';
$dbuser = 'postgres';  
$dbpass = 'makulit11';          
 
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

function sanitizeHexColor(?string $value, string $fallback = '#1E5EBA'): string
{
    if ($value !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
        return $value;
    }
 
    return $fallback;
}

function contrastingTextColor(string $hexColor): string
{
    $hex = ltrim($hexColor, '#');
 
    if (strlen($hex) !== 6) {
        return '#ffffff';
    }
 
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
 
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
 
    return $brightness > 150 ? '#000000' : '#ffffff';
}

function avatarTextColor(string $color1, bool $useGradient = false, ?string $color2 = null): string
{
    if (!$useGradient || $color2 === null) {
        return contrastingTextColor($color1);
    }
 
    $c1 = ltrim($color1, '#');
    $c2 = ltrim($color2, '#');
 
    if (strlen($c1) !== 6 || strlen($c2) !== 6) {
        return contrastingTextColor($color1);
    }
 
    $blended = '#';
    for ($i = 0; $i < 6; $i += 2) {
        $v1 = hexdec(substr($c1, $i, 2));
        $v2 = hexdec(substr($c2, $i, 2));
        $blended .= str_pad(dechex((int) round(($v1 + $v2) / 2)), 2, '0', STR_PAD_LEFT);
    }
 
    return contrastingTextColor($blended);
}