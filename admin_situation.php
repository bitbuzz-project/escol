<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];
$apogee = $student['apoL_a01_code']; // Get the apogee code from session
$page_title = "Situation Administrative";

require 'db.php'; // Include database connection

// Function to safely execute queries and return results
function executeQuery($conn, $query, $params = []) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    $stmt->close();
    return $data;
}

// Get detailed student information
$student_details = executeQuery($conn, "SELECT * FROM students_base WHERE apoL_a01_code = ?", [$apogee]);
$student_info = !empty($student_details) ? $student_details[0] : $student;

// Fetch all administrative data (fillière) for the connected student's apogee
$fillieres = executeQuery($conn, "SELECT filliere FROM administative WHERE apogee = ?", [$apogee]);

// Get academic statistics
$academic_stats = [];

// Count total notes across all tables
$notes_tables = ['notes', 'notes_ratt', 'notes_print', 'notes_exc'];
$total_notes = 0;
$session_breakdown = [];

foreach ($notes_tables as $table) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check && $table_check->num_rows > 0) {
        if ($table === 'notes_print') {
            // Get session breakdown for notes_print
            $session_data = executeQuery($conn, "
                SELECT session, result_type, COUNT(*) as count
                FROM `$table`
                WHERE apoL_a01_code = ?
                GROUP BY session, result_type
            ", [$apogee]);

            foreach ($session_data as $data) {
                $key = ucfirst($data['session']) . ' - ' . ucfirst($data['result_type']);
                $session_breakdown[$key] = $data['count'];
                $total_notes += $data['count'];
            }
        } else {
            $count_result = executeQuery($conn, "SELECT COUNT(*) as count FROM `$table` WHERE apoL_a01_code = ?", [$apogee]);
            $count = !empty($count_result) ? $count_result[0]['count'] : 0;

            $session_name = str_replace('notes_', '', $table);
            $session_name = $session_name === 'notes' ? 'Session Normale' : ucfirst($session_name);

            if ($count > 0) {
                $session_breakdown[$session_name] = $count;
                $total_notes += $count;
            }
        }
    }
}

// Get semester registrations
$semester_registrations = [];
$semester_tables = ['s1', 's2', 's3', 's4', 's5', 's6'];

foreach ($semester_tables as $table) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check && $table_check->num_rows > 0) {
        $columns = $conn->query("SHOW COLUMNS FROM `$table`");
        $has_groupe = false;

        while ($column = $columns->fetch_assoc()) {
            if ($column['Field'] === 'groupe') {
                $has_groupe = true;
                break;
            }
        }

        if ($has_groupe) {
            $semester_data = executeQuery($conn, "
                SELECT mod_name, groupe
                FROM `$table`
                WHERE apogee = ?
            ", [$apogee]);
        } else {
            $semester_data = executeQuery($conn, "
                SELECT mod_name, cod_elp
                FROM `$table`
                WHERE apogee = ?
            ", [$apogee]);
        }

        if (!empty($semester_data)) {
            $semester_registrations[strtoupper($table)] = $semester_data;
        }
    }
}

// Get research project information
$research_info = executeQuery($conn, "
    SELECT Filière, Assigned_Prof, Groupe
    FROM rech_groupes
    WHERE apogee = ?
", [$apogee]);

// Get reclamations count
$reclamations_data = executeQuery($conn, "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM reclamations
    WHERE apoL_a01_code = ?
", [$apogee]);

$reclamations_stats = !empty($reclamations_data) ? $reclamations_data[0] : ['total' => 0, 'pending' => 0, 'resolved' => 0];

$conn->close();

include 'header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i style="font-size: 1.5rem;">📋</i> Situation Administrative</h1>
                    <h4 class="text-muted">Pour : <?= htmlspecialchars($student['apoL_a03_prenom']) . " " . htmlspecialchars($student['apoL_a02_nom']) ?></h4>
                    <span class="badge bg-primary">Session d'Automne Ordinaire 2024/2025</span>
                </div>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i style="font-size: 0.9rem;">🏠</i> Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>

    <!-- Student Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white">
                    <i style="font-size: 2.5rem;">📊</i>
                    <h3 class="mt-2 mb-1"><?= $total_notes ?></h3>
                    <p class="card-text mb-0">Notes Total</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white">
                    <i style="font-size: 2.5rem;">🎓</i>
                    <h3 class="mt-2 mb-1"><?= count($fillieres) ?></h3>
                    <p class="card-text mb-0">Filières</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-white">
                    <i style="font-size: 2.5rem;">📚</i>
                    <h3 class="mt-2 mb-1"><?= count($semester_registrations) ?></h3>
                    <p class="card-text mb-0">Semestres</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="card-body text-white">
                    <i style="font-size: 2.5rem;">⚠️</i>
                    <h3 class="mt-2 mb-1"><?= $reclamations_stats['total'] ?></h3>
                    <p class="card-text mb-0">Réclamations</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Details -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">👤</i> Informations Personnelles</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th class="text-muted" style="width: 40%;">Code Apogée:</th>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($student_info['apoL_a01_code']) ?></span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Nom complet:</th>
                            <td><?= htmlspecialchars($student_info['apoL_a03_prenom'] . ' ' . $student_info['apoL_a02_nom']) ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Date de naissance:</th>
                            <td><?= htmlspecialchars($student_info['apoL_a04_naissance'] ?? 'Non renseignée') ?></td>
                        </tr>
                        <?php if (isset($student_info['cin_ind']) && !empty($student_info['cin_ind'])): ?>
                        <tr>
                            <th class="text-muted">CIN:</th>
                            <td><?= htmlspecialchars($student_info['cin_ind']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($student_info['cod_sex_etu']) && !empty($student_info['cod_sex_etu'])): ?>
                        <tr>
                            <th class="text-muted">Genre:</th>
                            <td><?= htmlspecialchars($student_info['cod_sex_etu'] == 'M' ? 'Masculin' : 'Féminin') ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($student_info['lib_vil_nai_etu']) && !empty($student_info['lib_vil_nai_etu'])): ?>
                        <tr>
                            <th class="text-muted">Ville de naissance:</th>
                            <td><?= htmlspecialchars($student_info['lib_vil_nai_etu']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">🎓</i> Informations Académiques</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <?php if (isset($student_info['cod_etu']) && !empty($student_info['cod_etu'])): ?>
                        <tr>
                            <th class="text-muted" style="width: 40%;">Code Étudiant:</th>
                            <td><?= htmlspecialchars($student_info['cod_etu']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($student_info['cod_anu']) && !empty($student_info['cod_anu'])): ?>
                        <tr>
                            <th class="text-muted">Année universitaire:</th>
                            <td><?= htmlspecialchars($student_info['cod_anu']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($student_info['cod_etp']) && !empty($student_info['cod_etp'])): ?>
                        <tr>
                            <th class="text-muted">Code Étape:</th>
                            <td><span class="badge bg-info"><?= htmlspecialchars($student_info['cod_etp']) ?></span></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($student_info['lib_etp']) && !empty($student_info['lib_etp'])): ?>
                        <tr>
                            <th class="text-muted">Étape de formation:</th>
                            <td><?= htmlspecialchars($student_info['lib_etp']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($student_info['cod_dip']) && !empty($student_info['cod_dip'])): ?>
                        <tr>
                            <th class="text-muted">Code Diplôme:</th>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($student_info['cod_dip']) ?></span></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Filières Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">🎯</i> Filière(s) d'Inscription</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($fillieres)): ?>
                        <div class="text-center py-4">
                            <i style="font-size: 3rem; color: #6c757d;">📚</i>
                            <h5 class="mt-3 text-muted">Aucune filière trouvée</h5>
                            <p class="text-muted">Aucune inscription administrative trouvée pour cet étudiant.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($fillieres as $index => $filiere): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                                    <?= $index + 1 ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($filiere['filliere']) ?></h6>
                                                    <small class="text-muted">Année 2024-2025</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Performance by Session -->
    <?php if (!empty($session_breakdown)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">📊</i> Répartition des Notes par Session</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($session_breakdown as $session => $count): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light text-center">
                                    <div class="card-body">
                                        <h4 class="text-primary"><?= $count ?></h4>
                                        <p class="card-text mb-0"><?= htmlspecialchars($session) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Semester Registrations -->
    <?php if (!empty($semester_registrations)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">📚</i> Inscriptions par Semestre</h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="semesterAccordion">
                        <?php foreach ($semester_registrations as $semester => $modules): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $semester ?>" aria-expanded="false">
                                        <i style="font-size: 1rem; margin-right: 10px;">📖</i>
                                        <?= htmlspecialchars($semester) ?>
                                        <span class="badge bg-primary ms-2"><?= count($modules) ?> modules</span>
                                    </button>
                                </h2>
                                <div id="collapse<?= $semester ?>" class="accordion-collapse collapse" data-bs-parent="#semesterAccordion">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <?php foreach ($modules as $module): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="d-flex align-items-center">
                                                        <i style="font-size: 1rem; margin-right: 8px; color: #28a745;">📄</i>
                                                        <span><?= htmlspecialchars($module['mod_name']) ?></span>
                                                        <?php if (isset($module['groupe'])): ?>
                                                            <span class="badge bg-success ms-2">Groupe <?= htmlspecialchars($module['groupe']) ?></span>
                                                        <?php elseif (isset($module['cod_elp'])): ?>
                                                            <span class="badge bg-info ms-2"><?= htmlspecialchars($module['cod_elp']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Research Project Information -->
    <?php if (!empty($research_info)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">🔬</i> مشروع نهاية السنة - Projet de Fin d'Année</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($research_info as $research): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?= htmlspecialchars($research['Filière']) ?></h6>
                                        <p class="card-text">
                                            <strong>Professeur:</strong> <?= htmlspecialchars($research['Assigned_Prof']) ?><br>
                                            <strong>Groupe:</strong> <?= htmlspecialchars($research['Groupe']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reclamations Summary -->
    <?php if ($reclamations_stats['total'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">⚠️</i> Résumé des Réclamations</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="p-3">
                                <h3 class="text-primary"><?= $reclamations_stats['total'] ?></h3>
                                <p class="mb-0">Total</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <h3 class="text-warning"><?= $reclamations_stats['pending'] ?></h3>
                                <p class="mb-0">En attente</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <h3 class="text-success"><?= $reclamations_stats['resolved'] ?></h3>
                                <p class="mb-0">Résolues</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i style="font-size: 1.2rem;">⚡</i> Actions Rapides</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="resultat.php" class="btn btn-primary w-100">
                                <i style="font-size: 1rem;">📊</i> Voir les notes
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="pedagogic_situation.php" class="btn btn-success w-100">
                                <i style="font-size: 1rem;">📚</i> Situation pédagogique
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="profile.php" class="btn btn-info w-100">
                                <i style="font-size: 1rem;">👤</i> Mon profil
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
</div>

<!-- Custom Styles -->
<style>
.card {
    transition: transform 0.2s ease-in-out;
    border-radius: 12px !important;
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

.accordion-button {
    font-weight: 600;
}

.accordion-button:not(.collapsed) {
    background-color: #e3f2fd;
    color: #1976d2;
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

/* Animation for cards */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.5s ease forwards;
}

.card:nth-child(1) { animation-delay: 0.1s; }
.card:nth-child(2) { animation-delay: 0.2s; }
.card:nth-child(3) { animation-delay: 0.3s; }
.card:nth-child(4) { animation-delay: 0.4s; }
</style>

<?php include 'footer.php'; ?>
