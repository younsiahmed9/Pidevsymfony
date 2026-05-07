<?php
require 'vendor/autoload.php';

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=fintrack',
    'root',
    ''
);

$stmt = $pdo->prepare('DESC users');
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Columns in 'users' table ===\n";
foreach($result as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . (($row['Null'] === 'NO') ? ' NOT NULL' : '') . "\n";
}

echo "\n=== Columns in 'clients' table ===\n";
$stmt = $pdo->prepare('DESC clients');
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($result as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . (($row['Null'] === 'NO') ? ' NOT NULL' : '') . "\n";
}
?>
