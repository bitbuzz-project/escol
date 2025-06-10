<?php
session_start();
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    header("Location: login.php");
    exit();
}

$admin = $_SESSION['student'];
$page_title = "Importer des Étudiants";

require 'db.php';

// Process import if form is submitted
$import_results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Increase execution time and memory for large imports
    set_time_limit(600); // 10 minutes
    ini_set('memory_limit', '512M');

    try {
        $json_data = '';

        // Check if file was uploaded
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $json_data = file_get_contents($_FILES['json_file']['tmp_name']);
        }
        // Or if JSON was pasted
        elseif (!empty($_POST['json_text'])) {
            $json_data = $_POST['json_text'];
        }
        else {
            throw new Exception("Aucune donnée JSON fournie.");
        }

        // Parse JSON
        $data = json_decode($json_data, true);

        if (!$data) {
            throw new Exception("Format JSON invalide: " . json_last_error_msg());
        }

        // Check if it has the expected structure
        if (!isset($data['results']) || !isset($data['results'][0]['items'])) {
            throw new Exception("Structure JSON non reconnue. Assurez-vous que le JSON contient 'results' -> 'items'.");
        }

        $students = $data['results'][0]['items'];

        if (empty($students)) {
            throw new Exception("Aucun étudiant trouvé dans les données JSON.");
        }

        // First, update the students_base table structure to accommodate new fields
        $alter_queries = [
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS cod_etu VARCHAR(20)",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS cod_etp VARCHAR(20)",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS cod_anu VARCHAR(10)",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS cod_dip VARCHAR(20)",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS cod_sex_etu VARCHAR(5)",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS lib_vil_nai_etu VARCHAR(100)",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS cin_ind VARCHAR(20)",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS lib_etp VARCHAR(200)",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS lic_etp VARCHAR(200)",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE students_base ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($alter_queries as $query) {
            $conn->query($query); // Ignore errors for existing columns
        }

        // Start transaction for better performance and data integrity
        $conn->autocommit(FALSE);

        $imported_count = 0;
        $updated_count = 0;
        $skipped_count = 0;
        $batch_size = 100;
        $processed = 0;
        $total_students = count($students);

        echo "<div class='progress-container' style='position: fixed; top: 20px; right: 20px; z-index: 1000; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
        echo "<div>Traitement en cours...</div>";
        echo "<div class='progress' style='width: 300px; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden;'>";
        echo "<div class='progress-bar' id='progressBar' style='height: 100%; background: #28a745; width: 0%; transition: width 0.3s;'></div>";
        echo "</div>";
        echo "<div id='progressText'>0 / {$total_students} étudiants traités</div>";
        echo "</div>";

        // Flush output to show progress
        if (ob_get_level()) ob_flush();
        flush();

        foreach ($students as $index => $student) {
            try {
                // Extract and clean required fields
                $apogee = trim(strval($student['cod_etu'] ?? ''));
                $nom = trim(strval($student['lib_nom_pat_ind'] ?? ''));
                $prenom = trim(strval($student['lib_pr1_ind'] ?? ''));

                // Convert date format from dd/mm/yy to dd/mm/yyyy
                $date_naissance = '';
                if (isset($student['date_nai_ind']) && !empty($student['date_nai_ind'])) {
                    $date_raw = trim(strval($student['date_nai_ind']));
                    $date_parts = explode('/', $date_raw);
                    if (count($date_parts) === 3) {
                        $day = str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
                        $month = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
                        $year = $date_parts[2];

                        // Convert 2-digit year to 4-digit year
                        if (strlen($year) === 2) {
                            $year = (int)$year;
                            // Assume years 00-30 are 2000s, 31-99 are 1900s
                            $year = $year <= 30 ? $year + 2000 : $year + 1900;
                        }

                        $date_naissance = "{$day}/{$month}/{$year}";
                    }
                }

                // Skip if essential data is missing
                if (empty($apogee) || empty($nom) || empty($prenom)) {
                    $skipped_count++;
                    $errors[] = "Étudiant ignoré (ligne " . ($index + 1) . ") - données manquantes: " . ($nom ?: 'Nom manquant') . " " . ($prenom ?: 'Prénom manquant');
                    continue;
                }

                // Prepare all additional parameters as string variables
                $cod_etu = trim(strval($student['cod_etu'] ?? ''));
                $cod_etp = trim(strval($student['cod_etp'] ?? ''));
                $cod_anu = trim(strval($student['cod_anu'] ?? ''));
                $cod_dip = trim(strval($student['cod_dip'] ?? ''));
                $cod_sex_etu = trim(strval($student['cod_sex_etu'] ?? ''));
                $lib_vil_nai_etu = trim(strval($student['lib_vil_nai_etu'] ?? ''));
                $cin_ind = trim(strval($student['cin_ind'] ?? ''));
                $lib_etp = trim(strval($student['lib_etp'] ?? ''));
                $lic_etp = trim(strval($student['lic_etp'] ?? ''));

                // Escape all values for security
                $apogee_esc = $conn->real_escape_string($apogee);
                $nom_esc = $conn->real_escape_string($nom);
                $prenom_esc = $conn->real_escape_string($prenom);
                $date_naissance_esc = $conn->real_escape_string($date_naissance);
                $cod_etu_esc = $conn->real_escape_string($cod_etu);
                $cod_etp_esc = $conn->real_escape_string($cod_etp);
                $cod_anu_esc = $conn->real_escape_string($cod_anu);
                $cod_dip_esc = $conn->real_escape_string($cod_dip);
                $cod_sex_etu_esc = $conn->real_escape_string($cod_sex_etu);
                $lib_vil_nai_etu_esc = $conn->real_escape_string($lib_vil_nai_etu);
                $cin_ind_esc = $conn->real_escape_string($cin_ind);
                $lib_etp_esc = $conn->real_escape_string($lib_etp);
                $lic_etp_esc = $conn->real_escape_string($lic_etp);

                // Check if student already exists
                $check_sql = "SELECT COUNT(*) FROM students_base WHERE apoL_a01_code = '$apogee_esc'";
                $check_result = $conn->query($check_sql);
                $exists = $check_result->fetch_row()[0] > 0;

                // Build the SQL query
                $sql = "INSERT INTO students_base
                        (apoL_a01_code, apoL_a02_nom, apoL_a03_prenom, apoL_a04_naissance,
                         cod_etu, cod_etp, cod_anu, cod_dip, cod_sex_etu, lib_vil_nai_etu,
                         cin_ind, lib_etp, lic_etp)
                        VALUES ('$apogee_esc', '$nom_esc', '$prenom_esc', '$date_naissance_esc',
                                '$cod_etu_esc', '$cod_etp_esc', '$cod_anu_esc', '$cod_dip_esc',
                                '$cod_sex_etu_esc', '$lib_vil_nai_etu_esc', '$cin_ind_esc',
                                '$lib_etp_esc', '$lic_etp_esc')
                        ON DUPLICATE KEY UPDATE
                        apoL_a02_nom = '$nom_esc',
                        apoL_a03_prenom = '$prenom_esc',
                        apoL_a04_naissance = '$date_naissance_esc',
                        cod_etu = '$cod_etu_esc',
                        cod_etp = '$cod_etp_esc',
                        cod_anu = '$cod_anu_esc',
                        cod_dip = '$cod_dip_esc',
                        cod_sex_etu = '$cod_sex_etu_esc',
                        lib_vil_nai_etu = '$lib_vil_nai_etu_esc',
                        cin_ind = '$cin_ind_esc',
                        lib_etp = '$lib_etp_esc',
                        lic_etp = '$lic_etp_esc',
                        updated_at = CURRENT_TIMESTAMP";

                if ($conn->query($sql)) {
                    if ($exists) {
                        $updated_count++;
                    } else {
                        $imported_count++;
                    }
                } else {
                    $errors[] = "Erreur lors de l'insertion de {$nom} {$prenom} (ligne " . ($index + 1) . "): " . $conn->error;
                    $skipped_count++;
                }

                $processed++;

                // Commit batch and update progress
                if ($processed % $batch_size === 0) {
                    $conn->commit();
                    $conn->autocommit(FALSE);

                    $progress = round(($processed / $total_students) * 100);
                    echo "<script>
                        if (document.getElementById('progressBar')) {
                            document.getElementById('progressBar').style.width = '{$progress}%';
                            document.getElementById('progressText').textContent = '{$processed} / {$total_students} étudiants traités';
                        }
                    </script>";

                    if (ob_get_level()) ob_flush();
                    flush();

                    // Reset execution time
                    set_time_limit(300);
                }

            } catch (Exception $e) {
                $errors[] = "Erreur pour l'étudiant {$nom} {$prenom} (ligne " . ($index + 1) . "): " . $e->getMessage();
                $skipped_count++;
            }
        }

        // Final commit
        $conn->commit();
        $conn->autocommit(TRUE);

        echo "<script>
            if (document.getElementById('progressBar')) {
                document.getElementById('progressBar').style.width = '100%';
                document.getElementById('progressText').textContent = 'Terminé! {$total_students} étudiants traités';
                setTimeout(function() {
                    document.querySelector('.progress-container').style.display = 'none';
                }, 3000);
            }
        </script>";

        $import_results = [
            'total' => $total_students,
            'imported' => $imported_count,
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'success' => true
        ];

        // Log admin action
        $description = "Import JSON: {$imported_count} nouveaux, {$updated_count} mis à jour, {$skipped_count} ignorés sur {$total_students} total";
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log_sql = "INSERT INTO admin_logs (admin_id, action, description, ip_address) VALUES ('" .
                   $conn->real_escape_string($admin['apoL_a01_code']) . "', 'IMPORT_STUDENTS', '" .
                   $conn->real_escape_string($description) . "', '" .
                   $conn->real_escape_string($ip) . "')";
        $conn->query($log_sql);

    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn)) {
            $conn->rollback();
            $conn->autocommit(TRUE);
        }

        $errors[] = $e->getMessage();
        $import_results['success'] = false;

        echo "<script>
            if (document.querySelector('.progress-container')) {
                document.querySelector('.progress-container').style.display = 'none';
            }
        </script>";
    }
}

include 'admin_header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i>📥</i> Importer des Étudiants (JSON)</h2>
            <a href="admin_students.php" class="btn btn-secondary">
                <i>👥</i> Retour à la liste
            </a>
        </div>
    </div>
</div>

<!-- Results Display -->
<?php if (!empty($import_results)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <?php if ($import_results['success']): ?>
                <div class="alert alert-success">
                    <h5><i>✅</i> Import terminé avec succès!</h5>
                    <ul class="mb-0">
                        <li><strong><?= $import_results['imported'] ?></strong> nouveaux étudiants importés</li>
                        <li><strong><?= $import_results['updated'] ?></strong> étudiants mis à jour</li>
                        <li><strong><?= $import_results['skipped'] ?></strong> étudiants ignorés</li>
                        <li><strong><?= $import_results['total'] ?></strong> total traités</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <h5><i>❌</i> Erreur lors de l'import</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Errors Display -->
<?php if (!empty($errors)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning">
                <h6><i>⚠️</i> Erreurs rencontrées (<?= count($errors) ?> erreurs):</h6>
                <div style="max-height: 200px; overflow-y: auto;">
                    <ul class="mb-0">
                        <?php foreach (array_slice($errors, 0, 20) as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($errors) > 20): ?>
                            <li><em>... et <?= count($errors) - 20 ?> erreurs supplémentaires</em></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Import Form -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i>📋</i> Formulaire d'Import</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i>📁</i> Option 1: Télécharger un fichier JSON</h6>
                            <div class="mb-3">
                                <label for="json_file" class="form-label">Sélectionner le fichier JSON</label>
                                <input type="file" class="form-control" id="json_file" name="json_file" accept=".json">
                                <div class="form-text">Taille maximale: 10MB</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i>📝</i> Option 2: Coller le contenu JSON</h6>
                            <div class="mb-3">
                                <label for="json_text" class="form-label">Contenu JSON</label>
                                <textarea class="form-control" id="json_text" name="json_text" rows="8"
                                          placeholder='{"results":[{"items":[...]}]}'></textarea>
                                <div class="form-text">Collez votre JSON ici</div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i>🚀</i> Lancer l'Import
                            </button>
                            <button type="button" class="btn btn-outline-secondary ms-2" onclick="showSampleFormat()">
                                <i>📖</i> Voir le format attendu
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Format Information -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i>ℹ️</i> Informations sur le Format</h5>
            </div>
            <div class="card-body">
                <h6>Structure JSON attendue:</h6>
                <pre class="bg-light p-3 rounded"><code>{
  "results": [
    {
      "items": [
        {
          "cod_etu": "10000904",
          "lib_nom_pat_ind": "CHAABI",
          "lib_pr1_ind": "MOHAMED",
          "date_nai_ind": "10/08/92",
          "cod_etp": "JFDAV3",
          "cod_anu": "2024",
          "cod_dip": "JFDA",
          "cod_sex_etu": "M",
          "lib_vil_nai_etu": "DAWAR EL HADADA",
          "cin_ind": "WA198036",
          "lib_etp": "3ème Année Droit Privé en Arabe",
          "lic_etp": "3ème Année Droit Privé(A)"
        }
      ]
    }
  ]
}</code></pre>

                <div class="alert alert-warning mt-3">
                    <strong>Notes importantes:</strong>
                    <ul class="mb-0">
                        <li>Les champs <code>cod_etu</code>, <code>lib_nom_pat_ind</code> et <code>lib_pr1_ind</code> sont obligatoires</li>
                        <li>Les dates au format <code>dd/mm/yy</code> ou <code>dd/mm/yyyy</code> sont acceptées</li>
                        <li>Les étudiants existants seront mis à jour automatiquement</li>
                        <li>L'import peut traiter plusieurs milliers d'étudiants en une fois</li>
                        <li>Un indicateur de progression s'affiche pendant l'import</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showSampleFormat() {
    const sample = {
        "results": [
            {
                "items": [
                    {
                        "cod_etu": "10000904",
                        "lib_nom_pat_ind": "CHAABI",
                        "lib_pr1_ind": "MOHAMED",
                        "date_nai_ind": "10/08/92",
                        "cod_etp": "JFDAV3",
                        "cod_anu": "2024",
                        "cod_dip": "JFDA",
                        "cod_sex_etu": "M",
                        "lib_vil_nai_etu": "DAWAR EL HADADA",
                        "cin_ind": "WA198036",
                        "lib_etp": "3ème Année Droit Privé en Arabe",
                        "lic_etp": "3ème Année Droit Privé(A)"
                    }
                ]
            }
        ]
    };

    document.getElementById('json_text').value = JSON.stringify(sample, null, 2);
}

// Clear the other input when one is used
document.getElementById('json_file').addEventListener('change', function() {
    if (this.files.length > 0) {
        document.getElementById('json_text').value = '';
    }
});

document.getElementById('json_text').addEventListener('input', function() {
    if (this.value.trim() !== '') {
        document.getElementById('json_file').value = '';
    }
});
</script>

<?php include 'footer.php'; ?>
