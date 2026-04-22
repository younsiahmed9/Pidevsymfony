<?php
// TEMPORARY migration script – DELETE this file after running!
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=fintrack;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// MariaDB uses CHANGE old_name new_name <full_type_definition> (not RENAME COLUMN)
$steps = [
    // ── COMPTE ──────────────────────────────────────────────────────
    'Rename date_ouverture → date_creation' =>
        "ALTER TABLE compte CHANGE date_ouverture date_creation DATE NOT NULL",
    'Rename statut → etat' =>
        "ALTER TABLE compte CHANGE statut etat VARCHAR(20) NOT NULL DEFAULT 'actif'",

    // ── CREDIT ──────────────────────────────────────────────────────
    'Rename montant_demande → montant' =>
        "ALTER TABLE credit CHANGE montant_demande montant NUMERIC(15,2) NOT NULL",
    'Rename date_demande → date_debut' =>
        "ALTER TABLE credit CHANGE date_demande date_debut DATE NOT NULL",
    'Rename statut → status' =>
        "ALTER TABLE credit CHANGE statut status VARCHAR(20) NOT NULL DEFAULT 'en_attente'",
];

echo "<pre style='font-family:monospace;font-size:14px;'>\n";
echo "=== FinTrack Schema Fix (MariaDB) ===\n\n";

foreach ($steps as $label => $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ $label\n";
    } catch (\PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, "Unknown column") || str_contains($msg, "doesn't exist")) {
            echo "⏭️  $label — already done, skipped\n";
        } else {
            echo "❌ $label — ERROR: $msg\n";
        }
    }
}

// ── Create a default user if the table is empty ──────────────────────
echo "\n--- Checking utilisateur table ---\n";
$count = $pdo->query("SELECT COUNT(*) FROM utilisateur")->fetchColumn();
if ($count == 0) {
    $hash = password_hash('admin123', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO utilisateur (email, nom, prenom, password, role, solde, created_at, updated_at)
                   VALUES (?, 'Admin', 'Super', ?, 'admin', 0.00, NOW(), NOW())")
        ->execute(['admin@fintrack.tn', $hash]);
    echo "✅ Default user created — email: admin\@fintrack.tn / password: admin123\n";
} else {
    echo "⏭️  utilisateur table already has $count user(s), skipped\n";
}

echo "\n=== Done! DELETE this file now (public/run_migration.php) ===\n";
echo "</pre>";
