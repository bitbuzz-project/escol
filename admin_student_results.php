<?php
session_start();
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    header("Location: login.php");
    exit();
}



$admin = $_SESSION['student'];
$page_title = "Gestion des Résultats";

require 'db.php';
require 'libraries/phpspreadsheet/vendor/autoload.php'; // Adjust this path based on where you placed it

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
// Process import if form is submitted
$import_results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    // --- END DEBUGGING LINES --
    // Increase execution time and memory for large imports
// Increase limits for large file handling
    ini_set('memory_limit', '1024M');          // 1GB memory
    ini_set('max_execution_time', 1800);       // 30 minutes
    ini_set('upload_max_filesize', '100M');    // 100MB upload
    ini_set('post_max_size', '100M');          // 100MB POST
    ini_set('max_input_time', 1800);

    try {
        $table_choice = $_POST['table_choice'] ?? '';
        $session_choice = $_POST['session_choice'] ?? '';
        $result_type_choice = $_POST['result_type_choice'] ?? ''; // New: Retrieve result type

        // Validate table choice
        if (!in_array($table_choice, ['notes', 'notes_print'])) {
            throw new Exception("Table invalide sélectionnée.");
        }

        // Validate session choice and result type for notes_print
        if ($table_choice === 'notes_print') {
            if (!in_array($session_choice, ['automne', 'printemps'])) {
                throw new Exception("Session invalide sélectionnée pour notes_print.");
            }
            // New validation for result_type
            if (!in_array($result_type_choice, ['normal', 'rattrapage'])) {
                throw new Exception("Type de résultat invalide sélectionné pour notes_print.");
            }
        }

        // Check if file was uploaded and validate extension
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
    $file_data = file_get_contents($_FILES['import_file']['tmp_name']);
    $results = processJsonImportForApogeeFormat($conn, $file_data, $table_choice);
} elseif ($table_choice === 'notes_print' && $file_extension === 'ods') {
                // Pass the new $result_type_choice to processOdsImport
                $results = processOdsImportXmlDirect($conn, $_FILES['import_file']['tmp_name'], $table_choice, $session_choice, $result_type_choice);

            }
        } else {
            throw new Exception("Aucun fichier téléchargé ou erreur de téléchargement.");
        }

        $import_results = $results;

        // Log admin action - update description to include result_type
        $session_info = ($table_choice === 'notes_print' ? " - Session: $session_choice, Type: $result_type_choice" : ""); // Modified
        $description = "Import de résultats dans {$table_choice}{$session_info}: {$results['imported']} nouveaux, {$results['updated']} mis à jour, {$results['skipped']} ignorés sur {$results['total']} total";
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
    // Memory-efficient JSON processing for large files
    $temp_file = tempnam(sys_get_temp_dir(), 'json_import_');
    file_put_contents($temp_file, $json_data);

    $imported_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $total_count = 0;

    // Progress tracking
    echo "<div class='progress-container' style='position: fixed; top: 20px; right: 20px; z-index: 1000; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
    echo "<div style='font-weight: bold;'>🔄 Import JSON Large File</div>";
    echo "<div class='progress' style='width: 350px; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden;'>";
    echo "<div class='progress-bar' id='progressBar' style='height: 100%; background: #28a745; width: 0%; transition: width 0.3s;'></div>";
    echo "</div>";
    echo "<div id='progressText'>Lecture du fichier...</div>";
    echo "</div>";

    if (ob_get_level()) ob_flush();
    flush();

    try {
        // Parse JSON in chunks to avoid memory issues
        $json_content = file_get_contents($temp_file);
        $data = json_decode($json_content, true);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Format JSON invalide: " . json_last_error_msg());
        }

        $total_count = count($data);

        if ($total_count === 0) {
            throw new Exception("Aucune donnée trouvée dans le fichier JSON.");
        }

        // Start transaction
        $conn->autocommit(FALSE);

        // Prepare statements once for better performance
        $check_sql = "SELECT COUNT(*) FROM `$table` WHERE apoL_a01_code = ? AND nom_module = ?";
        $check_stmt = $conn->prepare($check_sql);

        $update_sql = "UPDATE `$table` SET note = ?, validite = ?, code_module = ? WHERE apoL_a01_code = ? AND nom_module = ?";
        $update_stmt = $conn->prepare($update_sql);

        $insert_sql = "INSERT INTO `$table` (apoL_a01_code, code_module, nom_module, note, validite) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        $batch_size = 500; // Process in batches
        $processed = 0;

        foreach ($data as $index => $record) {
            try {
                // Validate required fields
                $apogee = trim($record['apoL_a01_code'] ?? '');
                $code_module = trim($record['code_module'] ?? '');
                $nom_module = trim($record['nom_module'] ?? '');
                $note = trim($record['note'] ?? '');
                $validite = trim($record['validite'] ?? '');

                if (empty($apogee) || empty($nom_module) || empty($note)) {
                    $skipped_count++;
                    continue;
                }

                // Check if record exists
                $check_stmt->bind_param('ss', $apogee, $nom_module);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $exists = $check_result->fetch_row()[0] > 0;

                if ($exists) {
                    // Update existing record
                    $update_stmt->bind_param('sssss', $note, $validite, $code_module, $apogee, $nom_module);
                    if ($update_stmt->execute()) {
                        $updated_count++;
                    } else {
                        $skipped_count++;
                    }
                } else {
                    // Insert new record
                    $insert_stmt->bind_param('sssss', $apogee, $code_module, $nom_module, $note, $validite);
                    if ($insert_stmt->execute()) {
                        $imported_count++;
                    } else {
                        $skipped_count++;
                    }
                }

                $processed++;

                // Commit in batches and update progress
                if ($processed % $batch_size === 0) {
                    $conn->commit();
                    $conn->autocommit(FALSE);

                    $progress = round(($processed / $total_count) * 100);
                    echo "<script>
                        if (document.getElementById('progressBar')) {
                            document.getElementById('progressBar').style.width = '{$progress}%';
                            document.getElementById('progressText').textContent = 'Traité: {$processed}/{$total_count} - Importés: {$imported_count}, Mis à jour: {$updated_count}';
                        }
                    </script>";

                    if (ob_get_level()) ob_flush();
                    flush();

                    // Reset execution time
                    set_time_limit(1800);

                    // Memory cleanup
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }

            } catch (Exception $e) {
                $skipped_count++;
                error_log("Error processing record: " . $e->getMessage());
            }
        }

        // Final commit
        $conn->commit();
        $conn->autocommit(TRUE);

        // Close statements
        $check_stmt->close();
        $update_stmt->close();
        $insert_stmt->close();

        echo "<script>
            if (document.getElementById('progressBar')) {
                document.getElementById('progressBar').style.width = '100%';
                document.getElementById('progressText').textContent = 'Terminé! {$total_count} enregistrements traités';
                setTimeout(function() {
                    document.querySelector('.progress-container').style.display = 'none';
                }, 3000);
            }
        </script>";

    } finally {
        // Clean up temp file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    }

    return [
        'success' => true,
        'total' => $total_count,
        'imported' => $imported_count,
        'updated' => $updated_count,
        'skipped' => $skipped_count
    ];
}

function validateAndPreviewJson($json_data, $max_preview = 3) {
    try {
        // Try to clean first
        $cleaned = cleanApogeeJsonFormat($json_data);
        $data = json_decode($cleaned, true);

        if (!$data) {
            return [
                'valid' => false,
                'error' => 'JSON invalide: ' . json_last_error_msg(),
                'preview' => null
            ];
        }

        if (!isset($data['results'][0]['items'])) {
            return [
                'valid' => false,
                'error' => 'Structure incorrecte: manque results[0].items',
                'preview' => null
            ];
        }

        $items = $data['results'][0]['items'];
        $preview = array_slice($items, 0, $max_preview);

        return [
            'valid' => true,
            'error' => null,
            'preview' => $preview,
            'total_count' => count($items)
        ];

    } catch (Exception $e) {
        return [
            'valid' => false,
            'error' => $e->getMessage(),
            'preview' => null
        ];
    }
}

function processJsonImportForApogeeFormat($conn, $json_data, $table) {
    $imported_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $total_count = 0;

    // Progress tracking
    echo "<div class='progress-container' style='position: fixed; top: 20px; right: 20px; z-index: 1000; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
    echo "<div style='font-weight: bold;'>🔄 Import JSON Apogée Format</div>";
    echo "<div class='progress' style='width: 350px; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden;'>";
    echo "<div class='progress-bar' id='progressBar' style='height: 100%; background: #28a745; width: 0%; transition: width 0.3s;'></div>";
    echo "</div>";
    echo "<div id='progressText'>Validation et nettoyage JSON...</div>";
    echo "</div>";

    if (ob_get_level()) ob_flush();
    flush();

    try {
        // STEP 1: Clean and fix JSON syntax issues
        $cleaned_json = cleanApogeeJsonFormat($json_data);

        // STEP 2: Parse JSON
        $data = json_decode($cleaned_json, true);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Format JSON invalide après nettoyage: " . json_last_error_msg());
        }

        // STEP 3: Extract items from the Apogée format
        if (!isset($data['results']) || !isset($data['results'][0]['items'])) {
            throw new Exception("Structure JSON non reconnue. Format attendu: {results: [{items: [...]}]}");
        }

        $items = $data['results'][0]['items'];
        $total_count = count($items);

        if ($total_count === 0) {
            throw new Exception("Aucune donnée trouvée dans les items.");
        }

        echo "<script>
            document.getElementById('progressText').textContent = 'Traitement de {$total_count} enregistrements...';
        </script>";
        if (ob_get_level()) ob_flush();
        flush();

        // STEP 4: Start transaction
        $conn->autocommit(FALSE);

        // Prepare statements
        $check_sql = "SELECT COUNT(*) FROM `$table` WHERE apoL_a01_code = ? AND nom_module = ?";
        $check_stmt = $conn->prepare($check_sql);

        $update_sql = "UPDATE `$table` SET note = ?, validite = ?, code_module = ? WHERE apoL_a01_code = ? AND nom_module = ?";
        $update_stmt = $conn->prepare($update_sql);

        $insert_sql = "INSERT INTO `$table` (apoL_a01_code, code_module, nom_module, note, validite) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        $batch_size = 500;
        $processed = 0;

        // STEP 5: Process each item
        foreach ($items as $index => $item) {
            try {
                // Map Apogée fields to your database fields
                $apogee = trim(strval($item['cod_etu'] ?? ''));
                $code_module = trim(strval($item['cod_elp'] ?? ''));
                $nom_module = trim(strval($item['lib_elp'] ?? ''));
                $note = trim(strval($item['not_elp'] ?? ''));
                $validite = trim(strval($item['cod_tre'] ?? ''));

                // Validate required fields
                if (empty($apogee) || empty($nom_module)) {
                    $skipped_count++;
                    continue;
                }

                // Handle note formatting (ensure it's a valid number)
                if (!empty($note) && !is_numeric($note)) {
                    // Try to fix common issues like comma instead of dot
                    $note = str_replace(',', '.', $note);
                    if (!is_numeric($note)) {
                        $note = '0'; // Default to 0 if still not numeric
                    }
                }

                // Check if record exists
                $check_stmt->bind_param('ss', $apogee, $nom_module);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $exists = $check_result->fetch_row()[0] > 0;

                if ($exists) {
                    // Update existing record
                    $update_stmt->bind_param('sssss', $note, $validite, $code_module, $apogee, $nom_module);
                    if ($update_stmt->execute()) {
                        $updated_count++;
                    } else {
                        $skipped_count++;
                    }
                } else {
                    // Insert new record
                    $insert_stmt->bind_param('sssss', $apogee, $code_module, $nom_module, $note, $validite);
                    if ($insert_stmt->execute()) {
                        $imported_count++;
                    } else {
                        $skipped_count++;
                    }
                }

                $processed++;

                // Batch commit and progress update
                if ($processed % $batch_size === 0) {
                    $conn->commit();
                    $conn->autocommit(FALSE);

                    $progress = round(($processed / $total_count) * 100);
                    echo "<script>
                        if (document.getElementById('progressBar')) {
                            document.getElementById('progressBar').style.width = '{$progress}%';
                            document.getElementById('progressText').textContent = 'Traité: {$processed}/{$total_count} - Importés: {$imported_count}, Mis à jour: {$updated_count}';
                        }
                    </script>";

                    if (ob_get_level()) ob_flush();
                    flush();

                    set_time_limit(1800);

                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }

            } catch (Exception $e) {
                $skipped_count++;
                error_log("Error processing Apogée record: " . $e->getMessage());
            }
        }

        // Final commit
        $conn->commit();
        $conn->autocommit(TRUE);

        // Close statements
        $check_stmt->close();
        $update_stmt->close();
        $insert_stmt->close();

        echo "<script>
            if (document.getElementById('progressBar')) {
                document.getElementById('progressBar').style.width = '100%';
                document.getElementById('progressText').textContent = 'Terminé! {$total_count} enregistrements traités';
                setTimeout(function() {
                    document.querySelector('.progress-container').style.display = 'none';
                }, 3000);
            }
        </script>";

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    return [
        'success' => true,
        'total' => $total_count,
        'imported' => $imported_count,
        'updated' => $updated_count,
        'skipped' => $skipped_count
    ];
}

// ADD THIS FUNCTION to clean and fix JSON syntax issues:

function cleanApogeeJsonFormat($json_data) {
    // Fix common JSON syntax issues in Apogée exports

    // 1. Fix decimal numbers: replace comma with dot in numeric values
    $json_data = preg_replace('/("not_elp"\s*:\s*)(\d+),(\d+)/', '$1$2.$3', $json_data);

    // 2. Fix any other numeric fields that might have commas
    $json_data = preg_replace('/:\s*(\d+),(\d+)(?=\s*[,}\]])/', ':$1.$2', $json_data);

    // 3. Ensure proper closing of arrays and objects
    // Count opening and closing brackets to detect missing ones
    $open_brackets = substr_count($json_data, '[');
    $close_brackets = substr_count($json_data, ']');
    $open_braces = substr_count($json_data, '{');
    $close_braces = substr_count($json_data, '}');

    // Add missing closing brackets/braces
    while ($close_brackets < $open_brackets) {
        $json_data .= ']';
        $close_brackets++;
    }

    while ($close_braces < $open_braces) {
        $json_data .= '}';
        $close_braces++;
    }

    // 4. Remove any trailing commas before closing brackets/braces
    $json_data = preg_replace('/,(\s*[}\]])/', '$1', $json_data);

    // 5. Ensure the JSON ends properly
    $json_data = rtrim($json_data);

    return $json_data;
}

function processOdsImportXmlDirect($conn, $file_path, $table, $session, $result_type) {
    $imported_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $total_count = 0;
    $processed_rows = 0;

    if (!extension_loaded('zip')) {
        throw new Exception("L'extension ZIP n'est pas installée.");
    }

    try {
        // Ouvrir le fichier ODS comme un ZIP
        $zip = new ZipArchive();
        if ($zip->open($file_path) !== TRUE) {
            throw new Exception("Impossible d'ouvrir le fichier ODS.");
        }

        $content_xml = $zip->getFromName('content.xml');
        $zip->close();

        if ($content_xml === FALSE) {
            throw new Exception("Impossible de lire le contenu du fichier ODS.");
        }

        // Interface de progression améliorée
        echo "<div class='progress-container' style='position: fixed; top: 20px; right: 20px; z-index: 1000; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); max-width: 400px;'>";
        echo "<div style='font-weight: bold; margin-bottom: 5px;'>🔄 Traitement ODS Direct (XML)</div>";
        echo "<div class='progress' style='width: 350px; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden; margin-bottom: 5px;'>";
        echo "<div class='progress-bar' id='progressBar' style='height: 100%; background: linear-gradient(90deg, #007bff, #0056b3); width: 0%; transition: width 0.3s;'></div>";
        echo "</div>";
        echo "<div id='progressText' style='font-size: 12px; line-height: 1.3;'>📊 Analyse du XML...</div>";
        echo "<div id='progressStats' style='font-size: 11px; color: #666; margin-top: 3px;'>⏳ Initialisation...</div>";
        echo "</div>";

        echo "<style>
        .progress-container {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            border: 1px solid #dee2e6;
        }
        .progress-bar {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }
        </style>";

        if (ob_get_level()) ob_flush();
        flush();

        // Utiliser XMLReader pour un traitement mémoire-efficace
        $reader = new XMLReader();
        $reader->XML($content_xml);

        $headers = [];
        $header_found = false;
        $row_data = [];
        $cell_index = 0;
        $row_index = 0;
        $total_rows_estimate = substr_count($content_xml, '<table:table-row');

        // Obtenir les enregistrements existants
        $existing_records_lookup = [];
        $check_existing_sql = "SELECT apoL_a01_code, nom_module FROM `{$table}` WHERE session = ? AND result_type = ?";
        $check_existing_stmt = $conn->prepare($check_existing_sql);
        $check_existing_stmt->bind_param('ss', $session, $result_type);
        $check_existing_stmt->execute();
        $result = $check_existing_stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $existing_records_lookup[$row['apoL_a01_code'] . '_' . $row['nom_module']] = true;
        }
        $check_existing_stmt->close();

        echo "<?xml version='1.0' encoding='UTF-8'?>";
        echo "<init>";
        echo "<total_rows_estimate>{$total_rows_estimate}</total_rows_estimate>";
        echo "<existing_records>" . count($existing_records_lookup) . "</existing_records>";
        echo "</init>";

        $conn->autocommit(FALSE);
        $batch_size = 500;
        $records_batch = [];

        // Parcourir le XML ligne par ligne
        while ($reader->read()) {
            switch ($reader->nodeType) {
                case XMLReader::ELEMENT:
                    if ($reader->localName === 'table-row') {
                        $row_data = [];
                        $cell_index = 0;
                        $row_index++;
                    } elseif ($reader->localName === 'table-cell') {
                        // Gérer les cellules répétées
                        $repeated = $reader->getAttribute('table:number-columns-repeated');
                        $repeated = $repeated ? min(intval($repeated), 20) : 1;

                        // Obtenir la valeur de la cellule
                        $cell_xml = $reader->readOuterXML();
                        $cell_value = '';

                        // Extraire le texte entre les balises <text:p>
                        if (preg_match('/<text:p[^>]*>(.*?)<\/text:p>/s', $cell_xml, $matches)) {
                            $cell_value = trim(strip_tags($matches[1]));
                        }

                        // Ajouter les cellules répétées
                        for ($i = 0; $i < $repeated; $i++) {
                            if ($cell_index < 10) { // Limiter à 10 colonnes
                                $row_data[$cell_index] = $cell_value;
                                $cell_index++;
                            }
                        }
                    }
                    break;

                case XMLReader::END_ELEMENT:
                    if ($reader->localName === 'table-row') {
                        // Traiter la ligne complète
                        $has_data = !empty(array_filter($row_data, function($cell) {
                            return !empty(trim($cell));
                        }));

                        if ($has_data) {
                            $processed_rows++;

                            if (!$header_found) {
                                // Première ligne non-vide = en-têtes
                                $headers = array_slice($row_data, 0, 5);
                                $headers = array_map('trim', $headers);

                                // Valider les en-têtes
                                $expected_headers = ['apoL_a01_code', 'code_module', 'nom_module', 'note'];
                                $missing_headers = array_diff($expected_headers, $headers);
                                if (!empty($missing_headers)) {
                                    throw new Exception("Colonnes manquantes: " . implode(', ', $missing_headers));
                                }

                                $header_found = true;

                                echo "<?xml version='1.0' encoding='UTF-8'?>";
                                echo "<headers_found>";
                                echo "<headers>" . implode(',', $headers) . "</headers>";
                                echo "</headers_found>";

                                echo "<script>
                                    document.getElementById('progressText').innerHTML = '✅ En-têtes trouvés';
                                    document.getElementById('progressStats').innerHTML = 'Colonnes: " . implode(', ', $headers) . "';
                                </script>";

                                if (ob_get_level()) ob_flush();
                                flush();
                            } else {
                                // Ligne de données
                                $record = [];
                                for ($i = 0; $i < min(count($headers), count($row_data)); $i++) {
                                    $record[$headers[$i]] = trim($row_data[$i] ?? '');
                                }

                                $apogee = $record['apoL_a01_code'] ?? '';
                                $code_module = $record['code_module'] ?? '';
                                $nom_module = $record['nom_module'] ?? '';
                                $note = $record['note'] ?? '';

                                if (!empty($apogee) && !empty($nom_module) && !empty($note)) {
                                    $total_count++;

                                    $unique_key = $apogee . '_' . $nom_module;
                                    $record_data = [
                                        'apogee' => $apogee,
                                        'code_module' => $code_module,
                                        'nom_module' => $nom_module,
                                        'note' => $note,
                                        'session' => $session,
                                        'result_type' => $result_type,
                                        'exists' => isset($existing_records_lookup[$unique_key])
                                    ];

                                    $records_batch[] = $record_data;

                                    // Traitement par lots
                                    if (count($records_batch) >= $batch_size) {
                                        $batch_results = processBatchRecords($conn, $table, $records_batch);
                                        $imported_count += $batch_results['imported'];
                                        $updated_count += $batch_results['updated'];
                                        $skipped_count += $batch_results['skipped'];

                                        echo "<?xml version='1.0' encoding='UTF-8'?>";
                                        echo "<batch_processed>";
                                        echo "<imported>{$batch_results['imported']}</imported>";
                                        echo "<updated>{$batch_results['updated']}</updated>";
                                        echo "<total_imported>{$imported_count}</total_imported>";
                                        echo "<total_updated>{$updated_count}</total_updated>";
                                        echo "</batch_processed>";

                                        $records_batch = [];

                                        if (ob_get_level()) ob_flush();
                                        flush();
                                    }
                                } else {
                                    $skipped_count++;
                                }
                            }

                            // Mise à jour du progrès
                            if ($processed_rows % 50 === 0) {
                                $progress = $total_rows_estimate > 0 ? round(($processed_rows / $total_rows_estimate) * 100) : 0;

                                echo "<?xml version='1.0' encoding='UTF-8'?>";
                                echo "<progress>";
                                echo "<percent>{$progress}</percent>";
                                echo "<processed>{$processed_rows}</processed>";
                                echo "<valid>{$total_count}</valid>";
                                echo "<imported>{$imported_count}</imported>";
                                echo "<updated>{$updated_count}</updated>";
                                echo "<skipped>{$skipped_count}</skipped>";
                                echo "</progress>";

                                echo "<script>
                                    if (document.getElementById('progressBar')) {
                                        document.getElementById('progressBar').style.width = '{$progress}%';
                                        document.getElementById('progressText').innerHTML = '📊 Lignes: {$processed_rows} | Valides: {$total_count}';
                                        document.getElementById('progressStats').innerHTML = '📈 Importés: {$imported_count} | Mis à jour: {$updated_count} | Ignorés: {$skipped_count}';
                                    }
                                </script>";

                                if (ob_get_level()) ob_flush();
                                flush();
                                set_time_limit(300);
                            }
                        }
                    }
                    break;
            }

            // Nettoyage mémoire périodique
            if ($row_index % 1000 === 0 && $row_index > 0) {
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }

        // Traiter les enregistrements restants
        if (!empty($records_batch)) {
            $batch_results = processBatchRecords($conn, $table, $records_batch);
            $imported_count += $batch_results['imported'];
            $updated_count += $batch_results['updated'];
            $skipped_count += $batch_results['skipped'];
        }

        $conn->commit();
        $conn->autocommit(TRUE);

        $reader->close();

        // Message de fin
        echo "<?xml version='1.0' encoding='UTF-8'?>";
        echo "<completion>";
        echo "<status>success</status>";
        echo "<total>{$total_count}</total>";
        echo "<imported>{$imported_count}</imported>";
        echo "<updated>{$updated_count}</updated>";
        echo "<skipped>{$skipped_count}</skipped>";
        echo "<processed_rows>{$processed_rows}</processed_rows>";
        echo "</completion>";

        echo "<script>
            if (document.getElementById('progressBar')) {
                document.getElementById('progressBar').style.width = '100%';
                document.getElementById('progressBar').style.background = '#28a745';
                document.getElementById('progressText').innerHTML = '✅ Terminé! {$total_count} enregistrements traités';
                document.getElementById('progressStats').innerHTML = '📈 Importés: {$imported_count} | Mis à jour: {$updated_count} | Ignorés: {$skipped_count}';
                setTimeout(function() {
                    const container = document.querySelector('.progress-container');
                    if (container) {
                        container.style.opacity = '0.8';
                        setTimeout(() => container.style.display = 'none', 5000);
                    }
                }, 3000);
            }
        </script>";

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    return [
        'success' => true,
        'total' => $total_count,
        'imported' => $imported_count,
        'updated' => $updated_count,
        'skipped' => $skipped_count
    ];
}

function processBatchRecords($conn, $table, $records_batch) {
    $imported = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($records_batch as $record) {
        try {
            if ($record['exists']) {
                // Mettre à jour
                $update_sql = "UPDATE `{$table}` SET note = ?, code_module = ? WHERE apoL_a01_code = ? AND nom_module = ? AND session = ? AND result_type = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param('ssssss', $record['note'], $record['code_module'], $record['apogee'], $record['nom_module'], $record['session'], $record['result_type']);
                if ($stmt->execute()) {
                    $updated++;
                } else {
                    $skipped++;
                }
                $stmt->close();
            } else {
                // Insérer
                $insert_sql = "INSERT INTO `{$table}` (apoL_a01_code, code_module, nom_module, note, session, result_type) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param('ssssss', $record['apogee'], $record['code_module'], $record['nom_module'], $record['note'], $record['session'], $record['result_type']);
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $skipped++;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $skipped++;
            error_log("Erreur traitement record: " . $e->getMessage());
        }
    }

    return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
}

function processBatch($conn, $table, $records_batch) {
    $imported = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($records_batch as $record) {
        try {
            if ($record['exists']) {
                // Update existing record
                $update_sql = "UPDATE `{$table}` SET note = ?, code_module = ? WHERE apoL_a01_code = ? AND nom_module = ? AND session = ? AND result_type = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param('ssssss', $record['note'], $record['code_module'], $record['apogee'], $record['nom_module'], $record['session'], $record['result_type']);
                if ($stmt->execute()) {
                    $updated++;
                } else {
                    $skipped++;
                }
                $stmt->close();
            } else {
                // Insert new record
                $insert_sql = "INSERT INTO `{$table}` (apoL_a01_code, code_module, nom_module, note, session, result_type) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param('ssssss', $record['apogee'], $record['code_module'], $record['nom_module'], $record['note'], $record['session'], $record['result_type']);
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $skipped++;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $skipped++;
        }
    }

    return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
}

/**
 * Helper function for batch inserts
 * @param mysqli $conn The database connection
 * @param string $table The table name
 * @param array $records An array of records to insert
 * @return int Number of rows inserted
 */
function executeBatchInsert($conn, $table, $records) {
    if (empty($records)) return 0;

    $insertedRows = 0;
    $insert_values_placeholders = [];
    $insert_params = [];
    $types = '';

    foreach ($records as $record) {
        $insert_values_placeholders[] = '(?, ?, ?, ?, ?, ?)';
        $insert_params[] = $record['apogee'];
        $insert_params[] = $record['code_module'];
        $insert_params[] = $record['nom_module'];
        $insert_params[] = $record['note'];
        $insert_params[] = $record['session'];
        $insert_params[] = $record['result_type'];
        $types .= 'ssssss'; // 6 string parameters
    }

    $insert_sql = "INSERT INTO `{$table}` (apoL_a01_code, code_module, nom_module, note, session, result_type) VALUES " . implode(', ', $insert_values_placeholders);
    $stmt = $conn->prepare($insert_sql);

    if ($stmt) {
        // Use the splat operator (...) to pass array elements as separate arguments to bind_param
        $stmt->bind_param($types, ...$insert_params);
        if ($stmt->execute()) {
            $insertedRows = $stmt->affected_rows;
        } else {
            error_log("Batch insert failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare batch insert statement: " . $conn->error);
    }
    return $insertedRows;
}

/**
 * Helper function for batch updates.
 * This function performs updates individually within the batch helper.
 * For very large datasets and if your table has a UNIQUE index on (apoL_a01_code, nom_module, session, result_type),
 * consider using a single `INSERT ... ON DUPLICATE KEY UPDATE` statement for better performance.
 * @param mysqli $conn The database connection
 * @param string $table The table name
 * @param array $records An array of records to update
 * @return int Number of rows updated
 */
function executeBatchUpdate($conn, $table, $records) {
    if (empty($records)) return 0;

    $updatedRows = 0;
    foreach ($records as $record) {
        $update_sql = "UPDATE `{$table}` SET note = ?, code_module = ? WHERE apoL_a01_code = ? AND nom_module = ? AND session = ? AND result_type = ?";
        $stmt = $conn->prepare($update_sql);
        if ($stmt) {
            $stmt->bind_param('ssssss', $record['note'], $record['code_module'], $record['apogee'], $record['nom_module'], $record['session'], $record['result_type']);
            if ($stmt->execute()) {
                $updatedRows++;
            } else {
                error_log("Update failed for record " . $record['apogee'] . ": " . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare update statement for record " . $record['apogee'] . ": " . $conn->error);
        }
    }
    return $updatedRows;
}

function logMemoryUsage($stage) {
    $memory = round(memory_get_usage() / 1024 / 1024, 2);
    $peak = round(memory_get_peak_usage() / 1024 / 1024, 2);
    error_log("Memory usage at $stage: {$memory}MB (Peak: {$peak}MB)");
}

function processOdsFileDirectly($conn, $file_path, $table, $session) {
    // Use XMLReader for memory-efficient parsing
    if (!extension_loaded('zip')) {
        throw new Exception("L'extension ZIP n'est pas installée sur ce serveur.");
    }

    $zip = new ZipArchive();
    if ($zip->open($file_path) !== TRUE) {
        throw new Exception("Impossible d'ouvrir le fichier ODS. Fichier corrompu?");
    }

    $content_xml = $zip->getFromName('content.xml');
    $zip->close();

    if ($content_xml === FALSE) {
        throw new Exception("Impossible de lire le contenu du fichier ODS.");
    }

    // Parse XML efficiently using SimpleXML for better performance
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
    $max_rows = 50000; // Limit to prevent memory issues

    echo "<script>
        if (document.getElementById('progressText')) {
            document.getElementById('progressText').textContent = 'Lecture du fichier ODS...';
        }
    </script>";
    if (ob_get_level()) ob_flush();
    flush();

    foreach ($rows as $row) {
        if ($row_index >= $max_rows) break;

        $cells = $row->getElementsByTagName('table-cell');
        $row_data = [];

        foreach ($cells as $cell) {
            // Get cell value
            $value = '';
            $textNodes = $cell->getElementsByTagName('p');
            if ($textNodes->length > 0) {
                $value = trim($textNodes->item(0)->nodeValue);
            }

            // Handle repeated cells
            $repeated = $cell->getAttribute('table:number-columns-repeated');
            $repeated = $repeated ? min(intval($repeated), 20) : 1;

            for ($i = 0; $i < $repeated; $i++) {
                $row_data[] = $value;
            }
        }

        // Skip empty rows
        if (empty(array_filter($row_data))) {
            continue;
        }

        // First non-empty row is headers
        if (empty($headers)) {
            $headers = array_slice($row_data, 0, 10);
            $headers = array_map('trim', $headers);

            // Validate headers
            $required_headers = ['apoL_a01_code', 'nom_module', 'note'];
            $missing_headers = array_diff($required_headers, $headers);
            if (!empty($missing_headers)) {
                throw new Exception("Colonnes manquantes: " . implode(', ', $missing_headers));
            }
        } else {
            // Map row data to headers
            $record = [];
            for ($i = 0; $i < min(count($headers), count($row_data)); $i++) {
                $record[$headers[$i]] = trim($row_data[$i]);
            }

            // Only add records with required data
            if (!empty($record['apoL_a01_code']) && !empty($record['nom_module'])) {
                $data[] = $record;
            }
        }

        $row_index++;

        // Update progress every 1000 rows
        if ($row_index % 1000 === 0) {
            echo "<script>
                if (document.getElementById('progressText')) {
                    document.getElementById('progressText').textContent = 'Lecture: {$row_index} lignes traitées';
                }
            </script>";
            if (ob_get_level()) ob_flush();
            flush();

            set_time_limit(300);
        }
    }

    if (empty($data)) {
        throw new Exception("Aucune donnée valide trouvée dans le fichier ODS.");
    }

    echo "<script>
        if (document.getElementById('progressText')) {
            document.getElementById('progressText').textContent = 'Insertion en base de données...';
        }
    </script>";
    if (ob_get_level()) ob_flush();
    flush();

    // Process the data
    return insertDataToDatabase($conn, $data, $table, $session);
}

function insertDataToDatabase($conn, $data, $table, $session) {
    $imported_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $total_count = count($data);
    $batch_size = 100; // Increased batch size for better performance

    // Start transaction
    $conn->autocommit(FALSE);

    // Prepare statements once for better performance
    $check_sql = "SELECT COUNT(*) FROM {$table} WHERE apoL_a01_code = ? AND nom_module = ? AND session = ?";
    $check_stmt = $conn->prepare($check_sql);

    $update_sql = "UPDATE {$table} SET note = ?, code_module = ? WHERE apoL_a01_code = ? AND nom_module = ? AND session = ?";
    $update_stmt = $conn->prepare($update_sql);

    $insert_sql = "INSERT INTO {$table} (apoL_a01_code, code_module, nom_module, note, session) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);

    foreach ($data as $index => $record) {
        try {
            $apogee = trim($record['apoL_a01_code'] ?? '');
            $code_module = trim($record['code_module'] ?? '');
            $nom_module = trim($record['nom_module'] ?? '');
            $note = trim($record['note'] ?? '');

            if (empty($apogee) || empty($nom_module) || empty($note)) {
                $skipped_count++;
                continue;
            }

            // Check if record exists
            $check_stmt->bind_param('sss', $apogee, $nom_module, $session);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $exists = $check_result->fetch_row()[0] > 0;

            if ($exists) {
                $update_stmt->bind_param('sssss', $note, $code_module, $apogee, $nom_module, $session);
                if ($update_stmt->execute()) {
                    $updated_count++;
                } else {
                    $skipped_count++;
                }
            } else {
                $insert_stmt->bind_param('sssss', $apogee, $code_module, $nom_module, $note, $session);
                if ($insert_stmt->execute()) {
                    $imported_count++;
                } else {
                    $skipped_count++;
                }
            }

            // Batch commit and progress update
            if (($index + 1) % $batch_size === 0) {
                $conn->commit();
                $conn->autocommit(FALSE);

                $progress = round((($index + 1) / $total_count) * 100);
                echo "<script>
                    if (document.getElementById('progressBar')) {
                        document.getElementById('progressBar').style.width = '{$progress}%';
                        document.getElementById('progressText').textContent = '" . ($index + 1) . " / {$total_count} étudiants traités';
                    }
                </script>";

                if (ob_get_level()) ob_flush();
                flush();

                set_time_limit(300);
            }

        } catch (Exception $e) {
            $skipped_count++;
            error_log("Error processing record: " . $e->getMessage());
        }
    }

    // Final commit
    $conn->commit();
    $conn->autocommit(TRUE);

    // Close statements
    $check_stmt->close();
    $update_stmt->close();
    $insert_stmt->close();

    echo "<script>
        if (document.getElementById('progressBar')) {
            document.getElementById('progressBar').style.width = '100%';
            document.getElementById('progressText').textContent = 'Terminé! {$total_count} étudiants traités';
            setTimeout(function() {
                document.querySelector('.progress-container').style.display = 'none';
            }, 3000);
        }
    </script>";

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

<!-- File Size Warning -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h6><i>ℹ️</i> Conseils pour l'import de gros fichiers:</h6>
            <ul class="mb-0">
                <li><strong>Taille maximale:</strong> 50MB par fichier</li>
                <li><strong>Temps de traitement:</strong> Environ 1-2 minutes par 1000 enregistrements</li>
                <li><strong>Mémoire:</strong> Le système peut traiter jusqu'à 50,000 lignes en une fois</li>
                <li><strong>Format optimal:</strong> Supprimez les colonnes inutiles pour accélérer le traitement</li>
            </ul>
        </div>
    </div>
</div>

<!-- Import Form -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i>📋</i> Importer des Résultats</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data" id="importForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="table_choice" class="form-label"><i>🗂️</i> Choisir la table de destination</label>
                                <select class="form-control" id="table_choice" name="table_choice" required onchange="updateFileRequirements()">
                                    <option value="" disabled selected>Sélectionnez une table</option>
                                    <option value="notes">Notes (Session normale) - Format JSON</option>
                                    <option value="notes_print">Notes Print (Sessions Automne/Printemps) - Format ODS</option>
                                </select>
                            </div>
                        </div>

                        <!-- Session Choice (only for notes_print) -->
<div class="col-md-4" id="session_choice_container" style="display: none;">
                            <div class="mb-3">
                                <label for="session_choice" class="form-label"><i>📅</i> Choisir la session</label>
                                <select class="form-control" id="session_choice" name="session_choice">
                                    <option value="" disabled selected>Sélectionnez une session</option>
                                    <option value="automne">Session d'Automne</option>
                                    <option value="printemps">Session de Printemps</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4" id="result_type_container" style="display: none;">
                            <div class="mb-3">
                                <label for="result_type_choice" class="form-label"><i>⚖️</i> Type de Résultat</label>
                                <select class="form-control" id="result_type_choice" name="result_type_choice">
                                    <option value="" disabled selected>Sélectionnez le type</option>
                                    <option value="normal">Session Normale</option>
                                    <option value="rattrapage">Rattrapage</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="import_file" class="form-label"><i>📁</i> Fichier à importer</label>
                                <input type="file" class="form-control" id="import_file" name="import_file" required>
                                <div class="form-text" id="file_help">Sélectionnez d'abord une table pour voir les formats acceptés</div>
                                <div class="mt-2">
                                    <small class="text-muted">Taille max: 50MB</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i>🚀</i> Lancer l'Import
                            </button>
                            <button type="button" class="btn btn-outline-info ms-2" onclick="showFormatExamples()">
                                <i>📖</i> Voir les formats
                            </button>
                            <button type="button" class="btn btn-outline-warning ms-2" onclick="showOptimizationTips()">
                                <i>⚡</i> Conseils d'optimisation
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
                <div class="alert alert-warning mt-2">
                    <small>
                        <strong>Important:</strong> La session sera automatiquement assignée selon votre choix (Automne/Printemps).
                        Les données seront stockées séparément pour chaque session.
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
                    // Get statistics for tables including session breakdown
                    $tables_stats = [];

                    // Notes table
                    $result = $conn->query("SELECT COUNT(*) as total FROM `notes`");
                    $total_notes = $result ? $result->fetch_assoc()['total'] : 0;

                    $result = $conn->query("SELECT COUNT(DISTINCT apoL_a01_code) as students FROM `notes`");
                    $students_notes = $result ? $result->fetch_assoc()['students'] : 0;

                    // Notes_print by session
                    $result = $conn->query("SELECT session, COUNT(*) as total, COUNT(DISTINCT apoL_a01_code) as students FROM `notes_print` GROUP BY session");
                    $notes_print_sessions = [];
                    while ($result && $row = $result->fetch_assoc()) {
                        $notes_print_sessions[$row['session']] = [
                            'total' => $row['total'],
                            'students' => $row['students']
                        ];
                    }
                    ?>

                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Table: notes</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?= $total_notes ?></h4>
                                        <small>Total notes</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?= $students_notes ?></h4>
                                        <small>Étudiants</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Notes Print - Automne</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?= $notes_print_sessions['automne']['total'] ?? 0 ?></h4>
                                        <small>Total notes</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?= $notes_print_sessions['automne']['students'] ?? 0 ?></h4>
                                        <small>Étudiants</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Notes Print - Printemps</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?= $notes_print_sessions['printemps']['total'] ?? 0 ?></h4>
                                        <small>Total notes</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?= $notes_print_sessions['printemps']['students'] ?? 0 ?></h4>
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
    const sessionContainer = document.getElementById('session_choice_container');
    const sessionSelect = document.getElementById('session_choice');
    const resultTypeContainer = document.getElementById('result_type_container');
    const resultTypeSelect = document.getElementById('result_type_choice');

    if (tableChoice === 'notes') {
        fileInput.setAttribute('accept', '.json');
        helpText.textContent = 'Formats acceptés: .json uniquement';
        helpText.className = 'form-text text-success';
        sessionContainer.style.display = 'none';
        sessionSelect.removeAttribute('required');
        resultTypeContainer.style.display = 'none';
        resultTypeSelect.removeAttribute('required');
    } else if (tableChoice === 'notes_print') {
        fileInput.setAttribute('accept', '.ods');
        helpText.textContent = 'Formats acceptés: .ods uniquement';
        helpText.className = 'form-text text-success';
        sessionContainer.style.display = 'block';
        sessionSelect.setAttribute('required', 'required');
        resultTypeContainer.style.display = 'block';
        resultTypeSelect.setAttribute('required', 'required');
    } else {
        fileInput.removeAttribute('accept');
        helpText.textContent = 'Sélectionnez d\'abord une table pour voir les formats acceptés';
        helpText.className = 'form-text';
        sessionContainer.style.display = 'none';
        sessionSelect.removeAttribute('required');
        resultTypeContainer.style.display = 'none';
        resultTypeSelect.removeAttribute('required');
    }
}

// FIXED: Proper form submission handling
document.addEventListener('DOMContentLoaded', function() {
    updateFileRequirements();

    const form = document.getElementById('importForm');
    let formSubmitted = false;

    form.addEventListener('submit', function(e) {
        const tableChoice = document.getElementById('table_choice').value;
        const sessionChoice = document.getElementById('session_choice').value;
        const resultTypeChoice = document.getElementById('result_type_choice').value;
        const fileInput = document.getElementById('import_file');
        const submitBtn = document.getElementById('submitBtn');

        console.log('Form validation:', {
            tableChoice: tableChoice,
            sessionChoice: sessionChoice,
            resultTypeChoice: resultTypeChoice,
            fileSelected: fileInput.files.length > 0
        });

        // Validation
        if (!tableChoice) {
            e.preventDefault();
            alert('Veuillez sélectionner une table de destination.');
            return false;
        }

        if (tableChoice === 'notes_print' && (!sessionChoice || !resultTypeChoice)) {
            e.preventDefault();
            alert('Veuillez sélectionner une session ET un type de résultat pour notes_print.');
            return false;
        }

        if (!fileInput.files.length) {
            e.preventDefault();
            alert('Veuillez sélectionner un fichier à importer.');
            return false;
        }

        const file = fileInput.files[0];
        const fileName = file.name;
        const fileExtension = fileName.split('.').pop().toLowerCase();
        const fileSize = file.size;
        const maxSize = 50 * 1024 * 1024; // 50MB

        const maxSize = 100 * 1024 * 1024; // 100MB instead of 50MB

        // Check file size
        if (fileSize > maxSize) {
            e.preventDefault();
            alert('Le fichier est trop volumineux. Taille maximale autorisée: 100MB\n' +
                  'Taille actuelle: ' + Math.round(fileSize / (1024 * 1024)) + 'MB');
            return false;
        }

        // Check file extension
        if (tableChoice === 'notes' && fileExtension !== 'json') {
            e.preventDefault();
            alert('Pour la table notes, veuillez sélectionner un fichier JSON.');
            return false;
        }

        if (tableChoice === 'notes_print' && fileExtension !== 'ods') {
            e.preventDefault();
            alert('Pour la table notes_print, veuillez sélectionner un fichier ODS.');
            return false;
        }

        // Large file warning
        if (fileSize > 20 * 1024 * 1024) { // Files larger than 20MB
            const confirmed = confirm(
                'Fichier volumineux détecté (' + Math.round(fileSize / (1024 * 1024)) + 'MB)\n\n' +
                'L\'import peut prendre plusieurs minutes.\n' +
                'Continuer l\'import?'
            );

            if (!confirmed) {
                e.preventDefault();
                return false;
            }
        }

        // Mark form as submitted to prevent beforeunload warning
        formSubmitted = true;

        // Show loading indication
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Import en cours...';
        submitBtn.disabled = true;

        // Show warning message
        const warningDiv = document.createElement('div');
        warningDiv.className = 'alert alert-warning mt-3';
        warningDiv.innerHTML = '<strong>⚠️ Import en cours...</strong><br>' +
                              'Ne fermez pas cette page pendant l\'import.';
        form.parentNode.appendChild(warningDiv);

        // Don't prevent default - let form submit normally
        return true;
    });

    // FIXED: Only prevent leaving if form was not properly submitted
    window.addEventListener('beforeunload', function(e) {
        // Only show warning if form elements have been modified but not submitted
        const tableChoice = document.getElementById('table_choice').value;
        const fileInput = document.getElementById('import_file');

        if (!formSubmitted && (tableChoice || fileInput.files.length > 0)) {
            const message = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter?';
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    });
});

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
              'IMPORTANT: Choisissez la session (Automne/Printemps) avant l\'import.');
    } else {
        alert('Veuillez d\'abord sélectionner une table.');
    }
}

function showOptimizationTips() {
    alert('Conseils pour optimiser l\'import:\n\n' +
          '1. Supprimez les colonnes inutiles\n' +
          '2. Limitez à 50,000 lignes par fichier\n' +
          '3. Fermez les autres applications\n' +
          '4. Vérifiez la connexion internet');
}

// File input change handler
document.getElementById('import_file').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const fileSize = Math.round(file.size / (1024 * 1024) * 100) / 100;
        const helpText = document.getElementById('file_help');
        const originalText = helpText.textContent;

        helpText.innerHTML = originalText + '<br><small class="text-info">Fichier sélectionné: ' +
                           file.name + ' (' + fileSize + ' MB)</small>';

        if (file.size > 10 * 1024 * 1024) {
            helpText.innerHTML += '<br><small class="text-warning">⚠️ Fichier volumineux</small>';
        }
    }
});
</script>

<?php include 'footer.php'; ?>
