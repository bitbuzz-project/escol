<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student_session = $_SESSION['student'];
$page_title = "Mon Profil";

// Allow admin to access student interface
$allow_admin_access = true;

require 'db.php';

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

// Fetch complete student details
$query = "SELECT * FROM `$student_table` WHERE apoL_a01_code = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

$stmt->bind_param("s", $student_session['apoL_a01_code']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Student record not found.");
}

$student = $result->fetch_assoc();
$stmt->close();

// Fetch administrative information (filières)
$admin_info = [];
$admin_query = "SELECT filliere FROM administative WHERE apogee = ?";
$admin_stmt = $conn->prepare($admin_query);
if ($admin_stmt) {
    $admin_stmt->bind_param("s", $student['apoL_a01_code']);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    while ($row = $admin_result->fetch_assoc()) {
        $admin_info[] = $row['filliere'];
    }
    $admin_stmt->close();
}

// Count total notes from all tables
$notes_count = 0;
$notes_tables = ['notes', 'notes_ratt', 'notes_print', 'notes_exc'];

foreach ($notes_tables as $table) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check && $table_check->num_rows > 0) {
        $count_query = "SELECT COUNT(*) as count FROM `$table` WHERE apoL_a01_code = ?";
        $count_stmt = $conn->prepare($count_query);
        if ($count_stmt) {
            $count_stmt->bind_param("s", $student['apoL_a01_code']);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            if ($count_row = $count_result->fetch_assoc()) {
                $notes_count += $count_row['count'];
            }
            $count_stmt->close();
        }
    }
}

// Count reclamations
$reclamations_count = 0;
$reclamations_query = "SELECT COUNT(*) as count FROM reclamations WHERE apoL_a01_code = ?";
$reclamations_stmt = $conn->prepare($reclamations_query);
if ($reclamations_stmt) {
    $reclamations_stmt->bind_param("s", $student['apoL_a01_code']);
    $reclamations_stmt->execute();
    $reclamations_result = $reclamations_stmt->get_result();
    if ($reclamations_row = $reclamations_result->fetch_assoc()) {
        $reclamations_count = $reclamations_row['count'];
    }
    $reclamations_stmt->close();
}

$conn->close();

include 'header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i style="font-size: 1.5rem;">👤</i> Mon Profil</h2>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i style="font-size: 0.9rem;">🏠</i> Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>

    <!-- Student Photo and Basic Info -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 120px; height: 120px; font-size: 3rem;">
                        <?= strtoupper(substr($student['apoL_a03_prenom'], 0, 1) . substr($student['apoL_a02_nom'], 0, 1)) ?>
                    </div>
                    <h4 class="card-title"><?= htmlspecialchars($student['apoL_a03_prenom'] . ' ' . $student['apoL_a02_nom']) ?></h4>
                    <p class="card-text text-muted">Code Apogée: <?= htmlspecialchars($student['apoL_a01_code']) ?></p>

                    <?php if ($student['apoL_a01_code'] === '16005333'): ?>
                        <span class="badge bg-warning text-dark fs-6">🛡️ ADMINISTRATEUR</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                        <div class="card-body text-white">
                            <i style="font-size: 2rem;">📊</i>
                            <h3 class="mt-2 mb-1"><?= $notes_count ?></h3>
                            <p class="card-text mb-0">Notes Total</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #28a745, #20c997);">
                        <div class="card-body text-white">
                            <i style="font-size: 2rem;">🎓</i>
                            <h3 class="mt-2 mb-1"><?= count($admin_info) ?></h3>
                            <p class="card-text mb-0">Filières</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                        <div class="card-body text-white">
                            <i style="font-size: 2rem;">⚠️</i>
                            <h3 class="mt-2 mb-1"><?= $reclamations_count ?></h3>
                            <p class="card-text mb-0">Réclamations</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Information -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">📋</i> Informations Personnelles</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" style="width: 40%;">Nom complet:</th>
                                    <td><strong><?= htmlspecialchars($student['apoL_a03_prenom'] . ' ' . $student['apoL_a02_nom']) ?></strong></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Code Apogée:</th>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($student['apoL_a01_code']) ?></span></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Date de naissance:</th>
                                    <td><?= htmlspecialchars($student['apoL_a04_naissance'] ?? 'Non renseignée') ?></td>
                                </tr>
                                <?php if (isset($student['cod_sex_etu']) && !empty($student['cod_sex_etu'])): ?>
                                <tr>
                                    <th class="text-muted">Genre:</th>
                                    <td><?= htmlspecialchars($student['cod_sex_etu'] == 'M' ? 'Masculin' : 'Féminin') ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (isset($student['lib_vil_nai_etu']) && !empty($student['lib_vil_nai_etu'])): ?>
                                <tr>
                                    <th class="text-muted">Ville de naissance:</th>
                                    <td><?= htmlspecialchars($student['lib_vil_nai_etu']) ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <?php if (isset($student['cod_etu']) && !empty($student['cod_etu'])): ?>
                                <tr>
                                    <th class="text-muted" style="width: 40%;">Code étudiant:</th>
                                    <td><?= htmlspecialchars($student['cod_etu']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (isset($student['cin_ind']) && !empty($student['cin_ind'])): ?>
                                <tr>
                                    <th class="text-muted">CIN:</th>
                                    <td><?= htmlspecialchars($student['cin_ind']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (isset($student['cod_anu']) && !empty($student['cod_anu'])): ?>
                                <tr>
                                    <th class="text-muted">Année universitaire:</th>
                                    <td><?= htmlspecialchars($student['cod_anu']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (isset($student['created_at']) && !empty($student['created_at'])): ?>
                                <tr>
                                    <th class="text-muted">Membre depuis:</th>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($student['created_at']))) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (isset($student['updated_at']) && !empty($student['updated_at'])): ?>
                                <tr>
                                    <th class="text-muted">Dernière mise à jour:</th>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($student['updated_at']))) ?></td>
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
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">🎓</i> Formation</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($admin_info)): ?>
                        <h6>Filières d'inscription:</h6>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($admin_info as $filiere): ?>
                                <li class="list-group-item d-flex align-items-center">
                                    <i style="font-size: 1rem; margin-right: 10px;">📚</i>
                                    <?= htmlspecialchars($filiere) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted text-center">
                            <i style="font-size: 2rem;">📚</i><br>
                            Aucune filière enregistrée
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">🏫</i> Informations Académiques</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($student['lib_etp']) && !empty($student['lib_etp'])): ?>
                        <div class="mb-3">
                            <h6 class="text-muted">Étape de formation:</h6>
                            <p class="mb-1"><?= htmlspecialchars($student['lib_etp']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($student['lic_etp']) && !empty($student['lic_etp'])): ?>
                        <div class="mb-3">
                            <h6 class="text-muted">Licence d'étape:</h6>
                            <p class="mb-1"><?= htmlspecialchars($student['lic_etp']) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="row text-center">
                        <?php if (isset($student['cod_etp']) && !empty($student['cod_etp'])): ?>
                        <div class="col-6">
                            <small class="text-muted">Code Étape</small><br>
                            <span class="badge bg-info"><?= htmlspecialchars($student['cod_etp']) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($student['cod_dip']) && !empty($student['cod_dip'])): ?>
                        <div class="col-6">
                            <small class="text-muted">Code Diplôme</small><br>
                            <span class="badge bg-secondary"><?= htmlspecialchars($student['cod_dip']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($student['lib_etp']) && empty($student['lic_etp']) && empty($student['cod_etp']) && empty($student['cod_dip'])): ?>
                        <p class="text-muted text-center">
                            <i style="font-size: 2rem;">🏫</i><br>
                            Informations académiques non disponibles
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">⚡</i> Actions Rapides</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="resultat.php" class="btn btn-primary w-100">
                                <i style="font-size: 1rem;">📊</i> Voir mes notes
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="pedagogic_situation.php" class="btn btn-success w-100">
                                <i style="font-size: 1rem;">📚</i> Situation pédagogique
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="admin_situation.php" class="btn btn-info w-100">
                                <i style="font-size: 1rem;">📋</i> Mes inscriptions
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="dashboard.php" class="btn btn-outline-secondary w-100">
                                <i style="font-size: 1rem;">🏠</i> Tableau de bord
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">📞</i> Besoin d'aide?</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="p-3">
                                <i style="font-size: 2rem; color: #28a745;">📧</i>
                                <h6 class="mt-2">Support Email</h6>
                                <p class="text-muted">support@fsjs.ac.ma</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <i style="font-size: 2rem; color: #17a2b8;">📱</i>
                                <h6 class="mt-2">Secrétariat</h6>
                                <p class="text-muted">+212 523 XXX XXX</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <i style="font-size: 2rem; color: #ffc107;">🕒</i>
                                <h6 class="mt-2">Horaires</h6>
                                <p class="text-muted">Lun-Ven: 8h-17h</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.table th {
    border: none;
    font-weight: 600;
    padding: 0.5rem 0;
}

.table td {
    border: none;
    padding: 0.5rem 0;
}

.list-group-item {
    border: none;
    padding: 0.75rem 0;
}

@media print {
    .btn, .card-header, nav, .sidebar {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        margin-bottom: 20px !important;
    }
}
</style>

<?php include 'footer.php'; ?>
