<?php
require 'db.php';

echo "<h2>🔧 Migration Compatible MariaDB</h2>";

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($query);
    return $result && $result->num_rows > 0;
}

// Step 1: Add columns one by one to avoid syntax issues
$columns_to_add = [
    'reclamation_type' => "ENUM('notes', 'correction', 'autre') DEFAULT 'notes' AFTER status",
    'category' => "VARCHAR(100) AFTER reclamation_type",
    'priority' => "ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal' AFTER category",
    'admin_comment' => "TEXT AFTER info",
    'session_type' => "VARCHAR(50) AFTER admin_comment",
    'result_type' => "VARCHAR(50) AFTER session_type",
    'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
];

echo "<h3>➕ Ajout des colonnes manquantes :</h3>";

foreach ($columns_to_add as $column => $definition) {
    if (!columnExists($conn, 'reclamations', $column)) {
        $sql = "ALTER TABLE reclamations ADD COLUMN $column $definition";

        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Colonne '$column' ajoutée</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur '$column': " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Colonne '$column' existe déjà</p>";
    }
}

// Step 2: Update existing records
echo "<h3>🔄 Mise à jour des enregistrements :</h3>";

$updates = [
    "UPDATE reclamations SET reclamation_type = 'notes' WHERE reclamation_type IS NULL",
    "UPDATE reclamations SET category = 'zero' WHERE note = 'zero' AND category IS NULL",
    "UPDATE reclamations SET category = 'absent' WHERE note = 'absent' AND category IS NULL",
    "UPDATE reclamations SET category = 'note_manquante' WHERE note = 'other' AND category IS NULL",
    "UPDATE reclamations SET priority = 'normal' WHERE priority IS NULL"
];

foreach ($updates as $sql) {
    if ($conn->query($sql)) {
        $affected = $conn->affected_rows;
        echo "<p style='color: green;'>✅ $affected enregistrements mis à jour</p>";
    } else {
        echo "<p style='color: red;'>❌ Erreur: " . $conn->error . "</p>";
    }
}

// Step 3: Add indexes
echo "<h3>📊 Ajout des index :</h3>";

$indexes = [
    "ALTER TABLE reclamations ADD INDEX idx_reclamation_type (reclamation_type)",
    "ALTER TABLE reclamations ADD INDEX idx_status_type (status)",
    "ALTER TABLE reclamations ADD INDEX idx_category (category)",
    "ALTER TABLE reclamations ADD INDEX idx_created_date (created_at)"
];

foreach ($indexes as $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✅ Index ajouté</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Index: " . $conn->error . "</p>";
    }
}

// Step 4: Create categories table
echo "<h3>📂 Table des catégories :</h3>";

$categories_sql = "
CREATE TABLE IF NOT EXISTS reclamation_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('notes', 'correction', 'autre') NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_type_category (type, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

if ($conn->query($categories_sql)) {
    echo "<p style='color: green;'>✅ Table reclamation_categories créée</p>";

    // Insert categories
    $categories = [
        ['notes', 'zero', 'Note zéro non justifiée'],
        ['notes', 'absent', 'Marqué absent alors que présent'],
        ['notes', 'note_manquante', 'Note manquante'],
        ['notes', 'erreur_calcul', 'Erreur de calcul'],
        ['correction', 'nom_prenom', 'Erreur nom/prénom'],
        ['correction', 'date_naissance', 'Erreur date naissance'],
        ['correction', 'code_apogee', 'Problème code Apogée'],
        ['autre', 'probleme_technique', 'Problème technique'],
        ['autre', 'demande_info', 'Demande information'],
        ['autre', 'attestation', 'Demande attestation']
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO reclamation_categories (type, category, description) VALUES (?, ?, ?)");
    $inserted = 0;

    foreach ($categories as $cat) {
        $stmt->bind_param('sss', $cat[0], $cat[1], $cat[2]);
        if ($stmt->execute()) $inserted++;
    }

    echo "<p style='color: green;'>✅ $inserted catégories insérées</p>";
    $stmt->close();
}

// Step 5: Final verification
echo "<h3>✅ Vérification finale :</h3>";

$check_columns = ['reclamation_type', 'category', 'priority', 'admin_comment'];
$all_good = true;

foreach ($check_columns as $col) {
    if (columnExists($conn, 'reclamations', $col)) {
        echo "<p style='color: green;'>✅ $col : OK</p>";
    } else {
        echo "<p style='color: red;'>❌ $col : MANQUANT</p>";
        $all_good = false;
    }
}

if ($all_good) {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🎉 Migration Terminée!</h3>";
    echo "<p>✅ Toutes les colonnes sont présentes</p>";
    echo "<p>✅ Table des catégories créée</p>";
    echo "<p>✅ Index ajoutés</p>";
    echo "<p><a href='admin_reclamations.php' class='btn btn-primary'>Accéder aux Réclamations</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>⚠️ Migration Incomplète</h3>";
    echo "<p>Certaines colonnes n'ont pas pu être ajoutées</p>";
    echo "</div>";
}

$conn->close();
?>
