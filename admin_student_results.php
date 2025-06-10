<?php
session_start();
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    header("Location: login.php");
    exit();
}

$admin = $_SESSION['student'];
$page_title = "Gestion des Résultats";

require 'db.php';

// Process import if form is submitted
$import_results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Increase execution time and memory for large imports
    set_time_limit(600); // 10 minutes
    ini_set('memory_limit', '512M');

    try {
        $table_choice = $_POST['table_choice'] ?? '';
        $file_data = '';

        // Validate table choice
        if (!in_array($table_choice, ['notes', 'notes_print'])) {
            throw new Exception("Table invalide sélectionnée.");
        }

        // Check if file was uploaded
        if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));

            // Validate file type based on table choice
            if ($table_choice === 'notes' && $file_extension !== 'json') {
                throw new Exception("Pour la table 'notes', veuillez télécharger un fichier JSON.");
            }

            if ($table_choice === 'notes_print' && $file_extension !== 'ods') {
                throw new Exception("Pour la table 'notes_print', veuillez télécharger un fichier ODS.");
            }

            if ($table_choice === 'notes' && $file_extension === 'json') {
                // Handle JSON file for notes table
                $file_data = file_get_contents($_FILES['import_file']['tmp_name']);
                $results = processJsonImport($conn, $file_data, $table_choice);
            } elseif ($table_choice === 'notes_print' && $file_extension === 'ods') {
                // Handle ODS file for notes_print table
                $results = processOdsImport($conn, $_FILES['import_file']['tmp_name'], $table_choice);
            }
        } else {
            throw new Exception("Aucun fichier téléchargé ou erreur de téléchargement.");
        }

        $import_results = $results;

        // Log admin action
        $description = "Import de résultats dans {$table_choice}: {$results['imported']} nouveaux, {$results['updated']} mis à jour, {$results['skipped']} ignorés sur {$results['total']} total";
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log_sql = "INSERT INTO admin_logs (admin_id, action, description, ip_address) VALUES (?, 'IMPORT_RESULTS', ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        if ($log_stmt) {
            $log_stmt->bind_param('sss', $admin['apoL_a01_code'], $description, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        $import_results['success'] = false;
    }
}

function processJsonImport($conn, $json_data, $table) {
    $data = json_decode($json_data, true);

    if (!$data) {
        throw new Exception("Format JSON invalide: " . json_last_error_msg());
    }

    // Expected JSON structure for notes:
    // [{"apoL_a01_code": "12345", "code_module": "MOD001", "nom_module": "Module Name", "note": "15.5", "validite": "V"}]

    if (!is_array($data)) {
        throw new Exception("Le JSON doit contenir un tableau de résultats.");
    }

    $imported_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $total_count = count($data);

    // Start transaction
    $conn->autocommit(FALSE);

    foreach ($data as $index => $record) {
        try {
            // Validate required fields
            $required_fields = ['apoL_a01_code', 'nom_module', 'note'];
            foreach ($required_fields as $field) {
                if (!isset($record[$field]) || empty(trim($record[$field]))) {
                    throw new Exception("Champ requis manquant: {$field}");
                }
            }

            $apogee = trim($record['apoL_a01_code']);
            $code_module = trim($record['code_module'] ?? '');
            $nom_module = trim($record['nom_module']);
            $note = trim($record['note']);
            $validite = trim($record['validite'] ?? '');

            // Check if record already exists
            $check_sql = "SELECT COUNT(*) FROM {$table} WHERE apoL_a01_code = ? AND nom_module = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param('ss', $apogee, $nom_module);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_row()[0] > 0;
            $check_stmt->close();

            if ($exists) {
                // Update existing record
                $update_sql = "UPDATE {$table} SET note = ?, validite = ?, code_module = ? WHERE apoL_a01_code = ? AND nom_module = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('sssss', $note, $validite, $code_module, $apogee, $nom_module);

                if ($update_stmt->execute()) {
                    $updated_count++;
                } else {
                    throw new Exception("Erreur lors de la mise à jour");
                }
                $update_stmt->close();
            } else {
                // Insert new record
                $insert_sql = "INSERT INTO {$table} (apoL_a01_code, code_module, nom_module, note, validite) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param('sssss', $apogee, $code_module, $nom_module, $note, $validite);

                if ($insert_stmt->execute()) {
                    $imported_count++;
                } else {
                    throw new Exception("Erreur lors de l'insertion");
                }
                $insert_stmt->close();
            }

        } catch (Exception $e) {
            $skipped_count++;
            // Continue processing other records
        }
    }

    // Commit transaction
    $conn->commit();
    $conn->autocommit(TRUE);

    return [
        'success' => true,
        'total' => $total_count,
        'imported' => $imported_count,
        'updated' => $updated_count,
        'skipped' => $skipped_count
    ];
}

function processOdsImport($conn, $file_path, $table) {
    // Parse ODS file using PHP's built-in ZIP functionality
    // ODS files are essentially ZIP archives containing XML files

    $zip = new ZipArchive();
    if ($zip->open($file_path) !== TRUE) {
        throw new Exception("Impossible d'ouvrir le fichier ODS. Fichier corrompu?");
    }

    // Extract content.xml which contains the spreadsheet data
    $content_xml = $zip->getFromName('content.xml');
    $zip->close();

    if ($content_xml === FALSE) {
        throw new Exception("Impossible de lire le contenu du fichier ODS.");
    }

    // Parse the XML content
    $dom = new DOMDocument();
    $dom->loadXML($content_xml);

    // Get all table rows
    $rows = $dom->getElementsByTagName('table-row');

    if ($rows->length === 0) {
        throw new Exception("Aucune donnée trouvée dans le fichier ODS.");
    }

    $data = [];
    $headers = [];
    $row_index = 0;

    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName('table-cell');
        $row_data = [];

        foreach ($cells as $cell) {
            // Get cell value
            $value = '';
            $textNodes = $cell->getElementsByTagName('p');
            if ($textNodes->length > 0) {
                $value = trim($textNodes->item(0)->nodeValue);
            }
            $row_data[] = $value;
        }

        // Skip empty rows
        if (empty(array_filter($row_data))) {
            continue;
        }

        // First non-empty row is headers
        if (empty($headers)) {
            $headers = $row_data;
            // Validate headers
            $expected_headers = ['apoL_a01_code', 'code_module', 'nom_module', 'note'];
            $missing_headers = array_diff($expected_headers, $headers);
            if (!empty($missing_headers)) {
                throw new Exception("Colonnes manquantes dans le fichier ODS: " . implode(', ', $missing_headers));
            }
            continue;
        }

        // Map row data to headers
        $record = [];
        for ($i = 0; $i < count($headers) && $i < count($row_data); $i++) {
            $record[$headers[$i]] = $row_data[$i];
        }

        // Only add records with required data
        if (!empty($record['apoL_a01_code']) && !empty($record['nom_module'])) {
            $data[] = $record;
        }

        $row_index++;
    }

    if (empty($data)) {
        throw new Exception("Aucune donnée valide trouvée dans le fichier ODS.");
    }

    // Process the data similar to JSON import
    $imported_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $total_count = count($data);

    // Start transaction
    $conn->autocommit(FALSE);

    foreach ($data as $index => $record) {
        try {
            // Validate required fields
            $apogee = trim($record['apoL_a01_code'] ?? '');
            $code_module = trim($record['code_module'] ?? '');
            $nom_module = trim($record['nom_module'] ?? '');
            $note = trim($record['note'] ?? '');

            if (empty($apogee) || empty($nom_module) || empty($note)) {
                $skipped_count++;
                continue;
            }

            // Check if record already exists
            $check_sql = "SELECT COUNT(*) FROM {$table} WHERE apoL_a01_code = ? AND nom_module = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param('ss', $apogee, $nom_module);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_row()[0] > 0;
            $check_stmt->close();

            if ($exists) {
                // Update existing record
                $update_sql = "UPDATE {$table} SET note = ?, code_module = ? WHERE apoL_a01_code = ? AND nom_module = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('ssss', $note, $code_module, $apogee, $nom_module);

                if ($update_stmt->execute()) {
                    $updated_count++;
                } else {
                    $skipped_count++;
                }
                $update_stmt->close();
            } else {
                // Insert new record
                $insert_sql = "INSERT INTO {$table} (apoL_a01_code, code_module, nom_module, note) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param('ssss', $apogee, $code_module, $nom_module, $note);

                if ($insert_stmt->execute()) {
                    $imported_count++;
                } else {
                    $skipped_count++;
                }
                $insert_stmt->close();
            }

        } catch (Exception $e) {
            $skipped_count++;
            // Continue processing other records
        }
    }

    // Commit transaction
    $conn->commit();
    $conn->autocommit(TRUE);

    return [
        'success' => true,
        'total' => $total_count,
        'imported' => $imported_count,
        'updated' => $updated_count,
        'skipped' => $skipped_count
    ];
}

include 'admin_header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i>📊</i> Gestion des Résultats</h2>
    <a href="admin_dashboard.php" class="btn btn-secondary">
        <i>🏠</i> Retour au tableau de bord
    </a>
</div>

<!-- Results Display -->
<?php if (!empty($import_results)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <?php if ($import_results['success']): ?>
                <div class="alert alert-success">
                    <h5><i>✅</i> Import terminé avec succès!</h5>
                    <ul class="mb-0">
                        <li><strong><?= $import_results['imported'] ?></strong> nouveaux résultats importés</li>
                        <li><strong><?= $import_results['updated'] ?></strong> résultats mis à jour</li>
                        <li><strong><?= $import_results['skipped'] ?></strong> résultats ignorés</li>
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
                <h6><i>⚠️</i> Erreurs rencontrées:</h6>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Import Form -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i>📋</i> Importer des Résultats</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="table_choice" class="form-label"><i>🗂️</i> Choisir la table de destination</label>
                                <select class="form-control" id="table_choice" name="table_choice" required onchange="updateFileRequirements()">
                                    <option value="" disabled selected>Sélectionnez une table</option>
                                    <option value="notes">Notes (Session normale) - Format JSON</option>
                                    <option value="notes_print">Notes Print (Session printemps) - Format ODS</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="import_file" class="form-label"><i>📁</i> Fichier à importer</label>
                                <input type="file" class="form-control" id="import_file" name="import_file" required>
                                <div class="form-text" id="file_help">Sélectionnez d'abord une table pour voir les formats acceptés</div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i>🚀</i> Lancer l'Import
                            </button>
                            <button type="button" class="btn btn-outline-info ms-2" onclick="showFormatExamples()">
                                <i>📖</i> Voir les formats
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
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i>📄</i> Format JSON (pour table notes)</h5>
            </div>
            <div class="card-body">
                <p><strong>Structure attendue:</strong></p>
                <pre class="bg-light p-3 rounded"><code>[
  {
    "apoL_a01_code": "12345678",
    "code_module": "MOD001",
    "nom_module": "Droit constitutionnel",
    "note": "15.5",
    "validite": "V"
  },
  {
    "apoL_a01_code": "12345679",
    "code_module": "MOD002",
    "nom_module": "Droit civil",
    "note": "12.0",
    "validite": "V"
  }
]</code></pre>
                <div class="alert alert-info mt-2">
                    <small>
                        <strong>Champs requis:</strong> apoL_a01_code, nom_module, note<br>
                        <strong>Champs optionnels:</strong> code_module, validite
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i>📊</i> Format ODS (pour table notes_print)</h5>
            </div>
            <div class="card-body">
                <p><strong>Colonnes attendues dans le fichier ODS:</strong></p>
                <table class="table table-sm table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Colonne A</th>
                            <th>Colonne B</th>
                            <th>Colonne C</th>
                            <th>Colonne D</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>apoL_a01_code</td>
                            <td>code_module</td>
                            <td>nom_module</td>
                            <td>note</td>
                        </tr>
                        <tr class="table-light">
                            <td>12345678</td>
                            <td>MOD001</td>
                            <td>Histoire du droit</td>
                            <td>14.5</td>
                        </tr>
                    </tbody>
                </table>
                <div class="alert alert-info mt-2">
                    <small>
                        <strong>Note:</strong> Le fichier ODS doit contenir les colonnes dans l'ordre exact:
                        apoL_a01_code, code_module, nom_module, note. La première ligne doit contenir les en-têtes.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics and Management -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i>📈</i> Statistiques des Tables</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    // Get statistics for both tables
                    $tables_stats = [];
                    $tables = ['notes', 'notes_print'];

                    foreach ($tables as $table) {
                        $result = $conn->query("SELECT COUNT(*) as total FROM `$table`");
                        $total = $result ? $result->fetch_assoc()['total'] : 0;

                        $result = $conn->query("SELECT COUNT(DISTINCT apoL_a01_code) as students FROM `$table`");
                        $students = $result ? $result->fetch_assoc()['students'] : 0;

                        $tables_stats[$table] = ['total' => $total, 'students' => $students];
                    }
                    ?>

                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Table: notes</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?= $tables_stats['notes']['total'] ?></h4>
                                        <small>Total notes</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?= $tables_stats['notes']['students'] ?></h4>
                                        <small>Étudiants</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Table: notes_print</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?= $tables_stats['notes_print']['total'] ?></h4>
                                        <small>Total notes</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?= $tables_stats['notes_print']['students'] ?></h4>
                                        <small>Étudiants</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="admin_reports.php" class="btn btn-outline-primary">
                        <i>📊</i> Voir rapports détaillés
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateFileRequirements() {
    const tableChoice = document.getElementById('table_choice').value;
    const fileInput = document.getElementById('import_file');
    const helpText = document.getElementById('file_help');

    if (tableChoice === 'notes') {
        fileInput.setAttribute('accept', '.json');
        helpText.textContent = 'Formats acceptés: .json uniquement';
        helpText.className = 'form-text text-success';
    } else if (tableChoice === 'notes_print') {
        fileInput.setAttribute('accept', '.ods');
        helpText.textContent = 'Formats acceptés: .ods uniquement';
        helpText.className = 'form-text text-success';
    } else {
        fileInput.removeAttribute('accept');
        helpText.textContent = 'Sélectionnez d\'abord une table pour voir les formats acceptés';
        helpText.className = 'form-text';
    }
}

function showFormatExamples() {
    const tableChoice = document.getElementById('table_choice').value;

    if (tableChoice === 'notes') {
        alert('Exemple JSON pour table notes:\n\n' +
              '[\n' +
              '  {\n' +
              '    "apoL_a01_code": "12345678",\n' +
              '    "code_module": "MOD001",\n' +
              '    "nom_module": "Nom du module",\n' +
              '    "note": "15.5",\n' +
              '    "validite": "V"\n' +
              '  }\n' +
              ']');
    } else if (tableChoice === 'notes_print') {
        alert('Format ODS pour table notes_print:\n\n' +
              'Colonnes dans l\'ordre:\n' +
              '1. apoL_a01_code (Code Apogée)\n' +
              '2. code_module (Code du module)\n' +
              '3. nom_module (Nom du module)\n' +
              '4. note (Note de l\'étudiant)\n\n' +
              'La première ligne doit contenir les en-têtes exactes.');
    } else {
        alert('Veuillez d\'abord sélectionner une table.');
    }
}

// Form validation
document.getElementById('importForm').addEventListener('submit', function(e) {
    const tableChoice = document.getElementById('table_choice').value;
    const fileInput = document.getElementById('import_file');

    if (!tableChoice) {
        e.preventDefault();
        alert('Veuillez sélectionner une table de destination.');
        return;
    }

    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Veuillez sélectionner un fichier à importer.');
        return;
    }

    const fileName = fileInput.files[0].name;
    const fileExtension = fileName.split('.').pop().toLowerCase();

    if (tableChoice === 'notes' && fileExtension !== 'json') {
        e.preventDefault();
        alert('Pour la table notes, veuillez sélectionner un fichier JSON.');
        return;
    }

    if (tableChoice === 'notes_print' && fileExtension !== 'ods') {
        e.preventDefault();
        alert('Pour la table notes_print, veuillez sélectionner un fichier ODS.');
        return;
    }

    // Show loading indication
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm"></i> Import en cours...';
    submitBtn.disabled = true;
});
</script>

<?php include 'footer.php'; ?>
