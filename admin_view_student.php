<?php
session_start();
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    header("Location: login.php");
    exit();
}

$admin = $_SESSION['student'];
$page_title = "Détails de l'Étudiant";

require 'db.php';

// Get student ID from URL
$student_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($student_id)) {
    $_SESSION['error_message'] = "ID étudiant manquant.";
    header("Location: admin_students.php");
    exit();
}

// Function to find the correct student table
function findStudentTable($conn) {
    $possible_tables = ['students_base', 'students', 'etudiant'];

    foreach ($possible_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            // Check if the table has the required columns
            $columns_result = $conn->query("SHOW COLUMNS FROM `$table`");
            $columns = [];
            while ($row = $columns_result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }

            // Check for required columns
            if (in_array('apoL_a01_code', $columns) &&
                in_array('apoL_a02_nom', $columns) &&
                in_array('apoL_a03_prenom', $columns)) {
                return $table;
            }
        }
    }
    return null;
}

// Find the correct table
$student_table = findStudentTable($conn);

if (!$student_table) {
    die("Error: No suitable student table found in the database.");
}

// Fetch student details
$query = "SELECT * FROM `$student_table` WHERE apoL_a01_code = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Étudiant non trouvé.";
    header("Location: admin_students.php");
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Fetch administrative information (filières)
$admin_info = [];
$admin_query = "SELECT filliere FROM administative WHERE apogee = ?";
$admin_stmt = $conn->prepare($admin_query);
if ($admin_stmt) {
    $admin_stmt->bind_param("s", $student_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    while ($row = $admin_result->fetch_assoc()) {
        $admin_info[] = $row['filliere'];
    }
    $admin_stmt->close();
}

// Fetch notes from different tables
$notes_tables = ['notes', 'notes_ratt', 'notes_print', 'notes_exc'];
$all_notes = [];

foreach ($notes_tables as $table) {
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check && $table_check->num_rows > 0) {
        $notes_query = "SELECT nom_module, note, validite, adding_date FROM `$table` WHERE apoL_a01_code = ?";
        $notes_stmt = $conn->prepare($notes_query);
        if ($notes_stmt) {
            $notes_stmt->bind_param("s", $student_id);
            $notes_stmt->execute();
            $notes_result = $notes_stmt->get_result();
            while ($row = $notes_result->fetch_assoc()) {
                $row['table_source'] = $table;
                $all_notes[] = $row;
            }
            $notes_stmt->close();
        }
    }
}

// Fetch reclamations
$reclamations = [];
$reclamations_query = "SELECT default_name, note, prof, groupe, class, info, created_at, status FROM reclamations WHERE apoL_a01_code = ? ORDER BY created_at DESC";
$reclamations_stmt = $conn->prepare($reclamations_query);
if ($reclamations_stmt) {
    $reclamations_stmt->bind_param("s", $student_id);
    $reclamations_stmt->execute();
    $reclamations_result = $reclamations_stmt->get_result();
    while ($row = $reclamations_result->fetch_assoc()) {
        $reclamations[] = $row;
    }
    $reclamations_stmt->close();
}

$conn->close();

include 'admin_header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i>👤</i> Détails de l'Étudiant</h2>
    <div>
        <a href="admin_edit_student.php?id=<?= urlencode($student['apoL_a01_code']) ?>" class="btn btn-warning">
            <i>✏️</i> Modifier
        </a>
        <a href="admin_students.php" class="btn btn-secondary">
            <i>👥</i> Retour à la liste
        </a>
    </div>
</div>

<!-- Student Basic Information -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i>📋</i> Informations Personnelles</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th>Code Apogée:</th>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($student['apoL_a01_code']) ?></span></td>
                            </tr>
                            <tr>
                                <th>Nom:</th>
                                <td><?= htmlspecialchars($student['apoL_a02_nom']) ?></td>
                            </tr>
                            <tr>
                                <th>Prénom:</th>
                                <td><?= htmlspecialchars($student['apoL_a03_prenom']) ?></td>
                            </tr>
                            <tr>
                                <th>Date de Naissance:</th>
                                <td><?= htmlspecialchars($student['apoL_a04_naissance'] ?? 'N/A') ?></td>
                            </tr>
                            <?php if (isset($student['cod_sex_etu'])): ?>
                            <tr>
                                <th>Sexe:</th>
                                <td><?= htmlspecialchars($student['cod_sex_etu']) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <?php if (isset($student['cod_etu'])): ?>
                            <tr>
                                <th>Code Étudiant:</th>
                                <td><?= htmlspecialchars($student['cod_etu']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($student['cin_ind'])): ?>
                            <tr>
                                <th>CIN:</th>
                                <td><?= htmlspecialchars($student['cin_ind']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($student['lib_vil_nai_etu'])): ?>
                            <tr>
                                <th>Ville de Naissance:</th>
                                <td><?= htmlspecialchars($student['lib_vil_nai_etu']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($student['created_at'])): ?>
                            <tr>
                                <th>Date de Création:</th>
                                <td><?= htmlspecialchars($student['created_at']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($student['updated_at'])): ?>
                            <tr>
                                <th>Dernière MAJ:</th>
                                <td><?= htmlspecialchars($student['updated_at']) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Academic Information -->
<?php if (!empty($admin_info) || isset($student['lib_etp']) || isset($student['lic_etp'])): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i>🎓</i> Informations Académiques</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <?php if (!empty($admin_info)): ?>
                        <h6>Filières:</h6>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($admin_info as $filiere): ?>
                                <li class="list-group-item"><?= htmlspecialchars($filiere) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if (isset($student['lib_etp'])): ?>
                        <h6>Étape (Lib):</h6>
                        <p><?= htmlspecialchars($student['lib_etp']) ?></p>
                        <?php endif; ?>

                        <?php if (isset($student['lic_etp'])): ?>
                        <h6>Licence Étape:</h6>
                        <p><?= htmlspecialchars($student['lic_etp']) ?></p>
                        <?php endif; ?>

                        <?php if (isset($student['cod_etp'])): ?>
                        <p><strong>Code Étape:</strong> <?= htmlspecialchars($student['cod_etp']) ?></p>
                        <?php endif; ?>

                        <?php if (isset($student['cod_anu'])): ?>
                        <p><strong>Année Universitaire:</strong> <?= htmlspecialchars($student['cod_anu']) ?></p>
                        <?php endif; ?>

                        <?php if (isset($student['cod_dip'])): ?>
                        <p><strong>Code Diplôme:</strong> <?= htmlspecialchars($student['cod_dip']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Notes Section -->
<?php if (!empty($all_notes)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i>📊</i> Notes (<?= count($all_notes) ?> modules)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Module</th>
                                <th>Note</th>
                                <th>Validité</th>
                                <th>Date</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_notes as $note): ?>
                                <tr>
                                    <td><?= htmlspecialchars($note['nom_module']) ?></td>
                                    <td>
                                        <span class="badge <?=
                                            is_numeric($note['note']) && $note['note'] >= 10 ? 'bg-success' :
                                            (is_numeric($note['note']) ? 'bg-danger' : 'bg-secondary')
                                        ?>">
                                            <?= htmlspecialchars($note['note']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($note['validite'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($note['adding_date'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-<?=
                                            $note['table_source'] == 'notes' ? 'primary' :
                                            ($note['table_source'] == 'notes_ratt' ? 'warning' :
                                            ($note['table_source'] == 'notes_print' ? 'info' : 'success'))
                                        ?>">
                                            <?=
                                                $note['table_source'] == 'notes' ? 'Normal' :
                                                ($note['table_source'] == 'notes_ratt' ? 'Rattrapage' :
                                                ($note['table_source'] == 'notes_print' ? 'Printemps' : 'Excellence'))
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Reclamations Section -->
<?php if (!empty($reclamations)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i>⚠️</i> Réclamations (<?= count($reclamations) ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Module</th>
                                <th>Type</th>
                                <th>Professeur</th>
                                <th>Groupe</th>
                                <th>Salle</th>
                                <th>Date</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reclamations as $reclamation): ?>
                                <tr>
                                    <td><?= htmlspecialchars($reclamation['default_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?=
                                            $reclamation['note'] == 'zero' ? 'danger' :
                                            ($reclamation['note'] == 'absent' ? 'warning' : 'info')
                                        ?>">
                                            <?= htmlspecialchars($reclamation['note']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($reclamation['prof']) ?></td>
                                    <td><?= htmlspecialchars($reclamation['groupe']) ?></td>
                                    <td><?= htmlspecialchars($reclamation['class']) ?></td>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($reclamation['created_at']))) ?></td>
                                    <td>
                                        <span class="badge bg-<?=
                                            $reclamation['status'] == 'resolved' ? 'success' :
                                            ($reclamation['status'] == 'pending' ? 'warning' : 'danger')
                                        ?>">
                                            <?= htmlspecialchars($reclamation['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if (!empty($reclamation['info'])): ?>
                                <tr>
                                    <td colspan="7" class="bg-light">
                                        <small><strong>Info:</strong> <?= htmlspecialchars($reclamation['info']) ?></small>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i>⚙️</i> Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="admin_edit_student.php?id=<?= urlencode($student['apoL_a01_code']) ?>" class="btn btn-warning w-100">
                            <i>✏️</i> Modifier Étudiant
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="admin_student_results.php?id=<?= urlencode($student['apoL_a01_code']) ?>" class="btn btn-success w-100">
                            <i>📊</i> Gérer Notes
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-info w-100" onclick="window.print()">
                            <i>🖨️</i> Imprimer Fiche
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="admin_students.php" class="btn btn-secondary w-100">
                            <i>👥</i> Retour Liste
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    .btn, .card-header, nav, .sidebar {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        margin-bottom: 20px !important;
    }
    .content {
        padding: 0 !important;
    }
}
</style>

<?php include 'footer.php'; ?>
