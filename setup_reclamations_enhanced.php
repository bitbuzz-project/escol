<?php
require 'db.php';

echo "<h2>Enhanced Reclamations System Setup</h2>";

// Step 1: Update reclamations table
echo "<h3>Mise à jour de la table reclamations...</h3>";

$alter_queries = [
    "ALTER TABLE reclamations
     ADD COLUMN IF NOT EXISTS reclamation_type ENUM('notes', 'correction', 'autre') DEFAULT 'notes' AFTER status",

    "ALTER TABLE reclamations
     ADD COLUMN IF NOT EXISTS category VARCHAR(100) AFTER reclamation_type",

    "ALTER TABLE reclamations
     ADD COLUMN IF NOT EXISTS priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal' AFTER category",

    "ALTER TABLE reclamations
     ADD COLUMN IF NOT EXISTS admin_comment TEXT AFTER info",

    "ALTER TABLE reclamations
     ADD COLUMN IF NOT EXISTS session_type VARCHAR(50) AFTER admin_comment",

    "ALTER TABLE reclamations
     ADD COLUMN IF NOT EXISTS result_type VARCHAR(50) AFTER session_type",

    "ALTER TABLE reclamations
     ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
];

foreach ($alter_queries as $query) {
    if ($conn->query($query)) {
        echo "<p style='color: green;'>✓ Colonne ajoutée avec succès</p>";
    } else {
        echo "<p style='color: orange;'>⚠ " . $conn->error . "</p>";
    }
}

// Step 2: Add indexes
echo "<h3>Ajout des index...</h3>";

$index_queries = [
    "ALTER TABLE reclamations ADD INDEX IF NOT EXISTS idx_type (reclamation_type)",
    "ALTER TABLE reclamations ADD INDEX IF NOT EXISTS idx_status (status)",
    "ALTER TABLE reclamations ADD INDEX IF NOT EXISTS idx_category (category)",
    "ALTER TABLE reclamations ADD INDEX IF NOT EXISTS idx_priority (priority)",
    "ALTER TABLE reclamations ADD INDEX IF NOT EXISTS idx_created_at (created_at)"
];

foreach ($index_queries as $query) {
    if ($conn->query($query)) {
        echo "<p style='color: green;'>✓ Index ajouté</p>";
    } else {
        echo "<p style='color: orange;'>⚠ " . $conn->error . "</p>";
    }
}

// Step 3: Update existing records
echo "<h3>Mise à jour des enregistrements existants...</h3>";

$update_query = "UPDATE reclamations SET reclamation_type = 'notes' WHERE reclamation_type IS NULL";
if ($conn->query($update_query)) {
    $affected = $conn->affected_rows;
    echo "<p style='color: green;'>✓ $affected enregistrements mis à jour</p>";
}

// Step 4: Create categories table
echo "<h3>Création de la table des catégories...</h3>";

$categories_table = "
CREATE TABLE IF NOT EXISTS reclamation_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('notes', 'correction', 'autre') NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_type_category (type, category),
    INDEX idx_type (type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

if ($conn->query($categories_table)) {
    echo "<p style='color: green;'>✓ Table reclamation_categories créée</p>";
}

// Step 5: Insert default categories
echo "<h3>Insertion des catégories par défaut...</h3>";

$categories = [
    // Notes categories
    ['notes', 'zero', 'Note zéro non justifiée'],
    ['notes', 'absent', 'Marqué absent alors que présent'],
    ['notes', 'note_manquante', 'Note manquante dans le système'],
    ['notes', 'erreur_calcul', 'Erreur dans le calcul de la note'],
    ['notes', 'note_incorrecte', 'Note incorrecte affichée'],

    // Correction categories
    ['correction', 'nom_prenom', 'Erreur dans le nom ou prénom'],
    ['correction', 'date_naissance', 'Erreur dans la date de naissance'],
    ['correction', 'code_apogee', 'Problème avec le code Apogée'],
    ['correction', 'filiere', 'Erreur d\'affectation de filière'],
    ['correction', 'cin', 'Erreur dans le numéro CIN'],
    ['correction', 'lieu_naissance', 'Erreur dans le lieu de naissance'],

    // Other categories
    ['autre', 'probleme_technique', 'Problème technique avec la plateforme'],
    ['autre', 'demande_info', 'Demande d\'information'],
    ['autre', 'attestation', 'Demande d\'attestation'],
    ['autre', 'reinscription', 'Problème de réinscription'],
    ['autre', 'stage', 'Demande de stage'],
    ['autre', 'transfert', 'Demande de transfert'],
    ['autre', 'bourse', 'Question concernant les bourses'],
    ['autre', 'emploi_temps', 'Problème d\'emploi du temps'],
    ['autre', 'acces_compte', 'Problème d\'accès au compte']
];

$insert_category_sql = "INSERT IGNORE INTO reclamation_categories (type, category, description) VALUES (?, ?, ?)";
$stmt = $conn->prepare($insert_category_sql);

if ($stmt) {
    $inserted = 0;
    foreach ($categories as $cat) {
        $stmt->bind_param('sss', $cat[0], $cat[1], $cat[2]);
        if ($stmt->execute()) {
            $inserted++;
        }
    }
    echo "<p style='color: green;'>✓ $inserted catégories insérées</p>";
    $stmt->close();
}

// Step 6: Create reclamation statistics view
echo "<h3>Création de la vue des statistiques...</h3>";

$stats_view = "
CREATE OR REPLACE VIEW reclamation_stats AS
SELECT
    reclamation_type,
    category,
    status,
    COUNT(*) as count,
    AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(updated_at, NOW()))) as avg_resolution_hours,
    DATE(created_at) as date_created
FROM reclamations
GROUP BY reclamation_type, category, status, DATE(created_at)
ORDER BY date_created DESC, reclamation_type, status
";

if ($conn->query($stats_view)) {
    echo "<p style='color: green;'>✓ Vue des statistiques créée</p>";
}

// Step 7: Create procedure for reclamation summary
echo "<h3>Création des procédures stockées...</h3>";

$procedure_summary = "
DROP PROCEDURE IF EXISTS GetReclamationSummary;

CREATE PROCEDURE GetReclamationSummary(
    IN start_date DATE,
    IN end_date DATE
)
BEGIN
    SELECT
        reclamation_type,
        status,
        COUNT(*) as total_count,
        COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_count,
        COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_count,
        AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_resolution_hours,
        MIN(created_at) as first_reclamation,
        MAX(created_at) as last_reclamation
    FROM reclamations
    WHERE DATE(created_at) BETWEEN start_date AND end_date
    GROUP BY reclamation_type, status
    ORDER BY reclamation_type, status;
END
";

if ($conn->query($procedure_summary)) {
    echo "<p style='color: green;'>✓ Procédure GetReclamationSummary créée</p>";
}

// Step 8: Create triggers for automatic logging
echo "<h3>Création des triggers...</h3>";

$trigger_insert = "
DROP TRIGGER IF EXISTS reclamation_insert_log;

CREATE TRIGGER reclamation_insert_log
AFTER INSERT ON reclamations
FOR EACH ROW
BEGIN
    INSERT INTO admin_logs (admin_id, action, description, ip_address, created_at)
    VALUES (
        NEW.apoL_a01_code,
        'NEW_RECLAMATION',
        CONCAT('Nouvelle réclamation ID: ', NEW.id, ' - Type: ', NEW.reclamation_type, ' - Catégorie: ', IFNULL(NEW.category, 'N/A')),
        'system',
        NOW()
    );
END
";

if ($conn->query($trigger_insert)) {
    echo "<p style='color: green;'>✓ Trigger d'insertion créé</p>";
}

$trigger_update = "
DROP TRIGGER IF EXISTS reclamation_status_log;

CREATE TRIGGER reclamation_status_log
AFTER UPDATE ON reclamations
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO admin_logs (admin_id, action, description, ip_address, created_at)
        VALUES (
            'SYSTEM',
            'RECLAMATION_STATUS_CHANGED',
            CONCAT('Réclamation ID: ', NEW.id, ' - Statut changé de ', OLD.status, ' à ', NEW.status),
            'system',
            NOW()
        );
    END IF;
END
";

if ($conn->query($trigger_update)) {
    echo "<p style='color: green;'>✓ Trigger de mise à jour créé</p>";
}

// Step 9: Insert sample data for testing
echo "<h3>Insertion de données de test...</h3>";

$sample_reclamations = [
    [
        'apoL_a01_code' => '12345678',
        'reclamation_type' => 'notes',
        'default_name' => 'Droit Constitutionnel',
        'category' => 'zero',
        'note' => 'zero',
        'status' => 'pending',
        'priority' => 'normal',
        'info' => 'Test de réclamation pour note zéro'
    ],
    [
        'apoL_a01_code' => '12345679',
        'reclamation_type' => 'correction',
        'default_name' => 'Correction: nom_prenom',
        'category' => 'nom_prenom',
        'status' => 'in_progress',
        'priority' => 'high',
        'info' => 'Erreur dans l\'orthographe du nom'
    ],
    [
        'apoL_a01_code' => '12345680',
        'reclamation_type' => 'autre',
        'default_name' => 'Demande d\'attestation',
        'category' => 'attestation',
        'status' => 'resolved',
        'priority' => 'normal',
        'info' => 'Demande d\'attestation de scolarité'
    ]
];

$insert_sample_sql = "
INSERT IGNORE INTO reclamations
(apoL_a01_code, reclamation_type, default_name, category, note, status, priority, info, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
";

$stmt = $conn->prepare($insert_sample_sql);
if ($stmt) {
    $inserted_samples = 0;
    foreach ($sample_reclamations as $sample) {
        $stmt->bind_param(
            'ssssssss',
            $sample['apoL_a01_code'],
            $sample['reclamation_type'],
            $sample['default_name'],
            $sample['category'],
            $sample['note'] ?? '',
            $sample['status'],
            $sample['priority'],
            $sample['info']
        );
        if ($stmt->execute()) {
            $inserted_samples++;
        }
    }
    echo "<p style='color: green;'>✓ $inserted_samples réclamations d'exemple insérées</p>";
    $stmt->close();
}

// Step 10: Check current status
echo "<h3>État actuel du système...</h3>";

$stats_query = "
SELECT
    reclamation_type,
    status,
    COUNT(*) as count
FROM reclamations
GROUP BY reclamation_type, status
ORDER BY reclamation_type, status
";

$result = $conn->query($stats_query);
if ($result) {
    echo "<table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f8f9fa; border: 1px solid #ddd;'>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Type</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Statut</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Nombre</th>";
    echo "</tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr style='border: 1px solid #ddd;'>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($row['reclamation_type']) . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($row['count']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Final success message
echo "<hr>";
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>🎉 Configuration terminée avec succès!</h4>";
echo "<p><strong>Le système de réclamations amélioré est maintenant opérationnel:</strong></p>";
echo "<ul>";
echo "<li>✅ Table reclamations mise à jour avec nouveaux champs</li>";
echo "<li>✅ Table reclamation_categories créée avec catégories par défaut</li>";
echo "<li>✅ Vue des statistiques créée</li>";
echo "<li>✅ Procédures stockées créées</li>";
echo "<li>✅ Triggers automatiques activés</li>";
echo "<li>✅ Données de test insérées</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #cce7ff; border: 1px solid #99d6ff; color: #004085; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h5>📋 Prochaines étapes:</h5>";
echo "<ol>";
echo "<li><a href='admin_reclamations.php'>Accéder à la gestion des réclamations</a></li>";
echo "<li><a href='login.php'>Tester le système avec un compte étudiant</a></li>";
echo "<li><a href='admin_dashboard.php'>Retour au tableau de bord admin</a></li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h5>⚠️ Types de réclamations disponibles:</h5>";
echo "<ul>";
echo "<li><strong>Notes:</strong> zero, absent, note_manquante, erreur_calcul, note_incorrecte</li>";
echo "<li><strong>Correction:</strong> nom_prenom, date_naissance, code_apogee, filiere, cin, lieu_naissance</li>";
echo "<li><strong>Autre:</strong> probleme_technique, demande_info, attestation, reinscription, stage, transfert, bourse, emploi_temps, acces_compte</li>";
echo "</ul>";
echo "</div>";

$conn->close();
?>
