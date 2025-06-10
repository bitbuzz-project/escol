<?php
session_start();
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    header("Location: login.php");
    exit();
}

$admin = $_SESSION['student'];
$page_title = "Rapports et Statistiques";

require 'db.php';

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

// Get current academic year
$current_year = '2024-2025';

// === STUDENT STATISTICS ===
$student_stats = [];

// Total students
$total_students = executeQuery($conn, "SELECT COUNT(*) as count FROM students_base");
$student_stats['total'] = $total_students[0]['count'] ?? 0;

// Students by gender
$gender_stats = executeQuery($conn, "
    SELECT
        cod_sex_etu,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM students_base WHERE cod_sex_etu IS NOT NULL), 2) as percentage
    FROM students_base
    WHERE cod_sex_etu IS NOT NULL
    GROUP BY cod_sex_etu
");

// Students by filière
$filiere_stats = executeQuery($conn, "
    SELECT
        filliere,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM administative), 2) as percentage
    FROM administative
    GROUP BY filliere
    ORDER BY count DESC
");

// Students by birth year
$birth_year_stats = executeQuery($conn, "
    SELECT
        CASE
            WHEN apoL_a04_naissance LIKE '%/98' OR apoL_a04_naissance LIKE '%/1998' THEN '1998'
            WHEN apoL_a04_naissance LIKE '%/99' OR apoL_a04_naissance LIKE '%/1999' THEN '1999'
            WHEN apoL_a04_naissance LIKE '%/00' OR apoL_a04_naissance LIKE '%/2000' THEN '2000'
            WHEN apoL_a04_naissance LIKE '%/01' OR apoL_a04_naissance LIKE '%/2001' THEN '2001'
            WHEN apoL_a04_naissance LIKE '%/02' OR apoL_a04_naissance LIKE '%/2002' THEN '2002'
            WHEN apoL_a04_naissance LIKE '%/03' OR apoL_a04_naissance LIKE '%/2003' THEN '2003'
            WHEN apoL_a04_naissance LIKE '%/04' OR apoL_a04_naissance LIKE '%/2004' THEN '2004'
            ELSE 'Autre'
        END as birth_year,
        COUNT(*) as count
    FROM students_base
    WHERE apoL_a04_naissance IS NOT NULL
    GROUP BY birth_year
    ORDER BY birth_year DESC
");

// === ACADEMIC PERFORMANCE STATISTICS ===
$performance_stats = [];

// Notes statistics from different sessions
$notes_tables = ['notes', 'notes_ratt', 'notes_print', 'notes_exc'];
foreach ($notes_tables as $table) {
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check && $table_check->num_rows > 0) {
        $session_name = str_replace('notes_', '', $table);
        $session_name = $session_name === 'notes' ? 'normal' : $session_name;

        // Total notes in this session
        $total_notes = executeQuery($conn, "SELECT COUNT(*) as count FROM `$table`");

        // Pass rate (notes >= 10)
        $pass_rate = executeQuery($conn, "
            SELECT
                COUNT(CASE WHEN CAST(note AS DECIMAL(4,2)) >= 10 THEN 1 END) as passed,
                COUNT(*) as total,
                ROUND(COUNT(CASE WHEN CAST(note AS DECIMAL(4,2)) >= 10 THEN 1 END) * 100.0 / COUNT(*), 2) as pass_rate
            FROM `$table`
            WHERE note REGEXP '^[0-9]+(\.[0-9]+)?$'
        ");

        // Average grade
        $avg_grade = executeQuery($conn, "
            SELECT
                ROUND(AVG(CAST(note AS DECIMAL(4,2))), 2) as average_grade
            FROM `$table`
            WHERE note REGEXP '^[0-9]+(\.[0-9]+)?$'
        ");

        $performance_stats[$session_name] = [
            'total_notes' => $total_notes[0]['count'] ?? 0,
            'passed' => $pass_rate[0]['passed'] ?? 0,
            'total_graded' => $pass_rate[0]['total'] ?? 0,
            'pass_rate' => $pass_rate[0]['pass_rate'] ?? 0,
            'average_grade' => $avg_grade[0]['average_grade'] ?? 0
        ];
    }
}

// === RECLAMATIONS STATISTICS ===
$reclamations_stats = [];

// Total reclamations
$total_reclamations = executeQuery($conn, "SELECT COUNT(*) as count FROM reclamations");
$reclamations_stats['total'] = $total_reclamations[0]['count'] ?? 0;

// Reclamations by status
$reclamations_by_status = executeQuery($conn, "
    SELECT
        status,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM reclamations), 2) as percentage
    FROM reclamations
    GROUP BY status
");

// Reclamations by type
$reclamations_by_type = executeQuery($conn, "
    SELECT
        note as type,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM reclamations), 2) as percentage
    FROM reclamations
    GROUP BY note
    ORDER BY count DESC
");

// Recent reclamations
$recent_reclamations = executeQuery($conn, "
    SELECT
        r.apoL_a01_code,
        s.apoL_a03_prenom,
        s.apoL_a02_nom,
        r.default_name,
        r.note,
        r.status,
        r.created_at
    FROM reclamations r
    LEFT JOIN students_base s ON r.apoL_a01_code = s.apoL_a01_code
    ORDER BY r.created_at DESC
    LIMIT 10
");

// === SYSTEM STATISTICS ===
$system_stats = [];

// Database table sizes
$tables = ['students_base', 'administative', 'notes', 'notes_ratt', 'notes_print', 'notes_exc', 'reclamations'];
foreach ($tables as $table) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check && $table_check->num_rows > 0) {
        $count = executeQuery($conn, "SELECT COUNT(*) as count FROM `$table`");
        $system_stats['tables'][$table] = $count[0]['count'] ?? 0;
    }
}

// Admin activity (if admin_logs table exists)
$admin_activity = [];
$admin_logs_check = $conn->query("SHOW TABLES LIKE 'admin_logs'");
if ($admin_logs_check && $admin_logs_check->num_rows > 0) {
    $admin_activity = executeQuery($conn, "
        SELECT
            DATE(created_at) as date,
            COUNT(*) as actions
        FROM admin_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 10
    ");
}

$conn->close();

include 'admin_header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i>📈</i> Rapports et Statistiques</h2>
    <div>
        <button class="btn btn-primary" onclick="window.print()">
            <i>🖨️</i> Imprimer Rapport
        </button>
        <button class="btn btn-success" onclick="exportToCSV()">
            <i>📊</i> Exporter CSV
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #17a2b8, #138496);">
            <div class="card-body text-white">
                <i style="font-size: 2.5rem;">👥</i>
                <h2 class="mt-2 mb-1"><?= $student_stats['total'] ?></h2>
                <p class="card-text">Étudiants Total</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #28a745, #20c997);">
            <div class="card-body text-white">
                <i style="font-size: 2.5rem;">🎓</i>
                <h2 class="mt-2 mb-1"><?= count($filiere_stats) ?></h2>
                <p class="card-text">Filières Actives</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
            <div class="card-body text-white">
                <i style="font-size: 2.5rem;">📊</i>
                <h2 class="mt-2 mb-1"><?= array_sum(array_column($performance_stats, 'total_notes')) ?></h2>
                <p class="card-text">Notes Total</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #dc3545, #c82333);">
            <div class="card-body text-white">
                <i style="font-size: 2.5rem;">⚠️</i>
                <h2 class="mt-2 mb-1"><?= $reclamations_stats['total'] ?></h2>
                <p class="card-text">Réclamations</p>
            </div>
        </div>
    </div>
</div>

<!-- Student Demographics -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i>👥</i> Répartition par Genre</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($gender_stats)): ?>
                    <canvas id="genderChart" width="400" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($gender_stats as $stat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?= $stat['cod_sex_etu'] == 'M' ? 'Masculin' : 'Féminin' ?></span>
                                <span>
                                    <strong><?= $stat['count'] ?></strong>
                                    <small class="text-muted">(<?= $stat['percentage'] ?>%)</small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée de genre disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i>🎓</i> Étudiants par Filière</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($filiere_stats)): ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($filiere_stats as $stat): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><?= htmlspecialchars($stat['filliere']) ?></span>
                                    <span><strong><?= $stat['count'] ?></strong></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success"
                                         style="width: <?= $stat['percentage'] ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée de filière disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Academic Performance -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i>📊</i> Performance Académique par Session</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($performance_stats)): ?>
                    <div class="row">
                        <?php foreach ($performance_stats as $session => $stats): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-capitalize"><?= htmlspecialchars($session) ?></h6>
                                        <div class="mb-2">
                                            <span class="badge bg-primary"><?= $stats['total_notes'] ?> Notes</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Taux de Réussite</strong><br>
                                            <span class="text-<?= $stats['pass_rate'] >= 50 ? 'success' : 'danger' ?>">
                                                <?= $stats['pass_rate'] ?>%
                                            </span>
                                        </div>
                                        <div>
                                            <strong>Moyenne</strong><br>
                                            <span class="text-<?= $stats['average_grade'] >= 10 ? 'success' : 'warning' ?>">
                                                <?= $stats['average_grade'] ?>/20
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée de performance disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reclamations Analysis -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i>⚠️</i> Réclamations par Type</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($reclamations_by_type)): ?>
                    <?php foreach ($reclamations_by_type as $stat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-<?=
                                $stat['type'] == 'zero' ? 'danger' :
                                ($stat['type'] == 'absent' ? 'warning' : 'info')
                            ?>">
                                <?= htmlspecialchars($stat['type']) ?>
                            </span>
                            <span>
                                <strong><?= $stat['count'] ?></strong>
                                <small class="text-muted">(<?= $stat['percentage'] ?>%)</small>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Aucune réclamation trouvée</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i>📈</i> Statut des Réclamations</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($reclamations_by_status)): ?>
                    <?php foreach ($reclamations_by_status as $stat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-<?=
                                $stat['status'] == 'resolved' ? 'success' :
                                ($stat['status'] == 'pending' ? 'warning' : 'danger')
                            ?>">
                                <?= htmlspecialchars($stat['status']) ?>
                            </span>
                            <span>
                                <strong><?= $stat['count'] ?></strong>
                                <small class="text-muted">(<?= $stat['percentage'] ?>%)</small>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Aucune réclamation trouvée</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i>🕒</i> Réclamations Récentes</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_reclamations)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Module</th>
                                    <th>Type</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reclamations as $reclamation): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars(($reclamation['apoL_a03_prenom'] ?? '') . ' ' . ($reclamation['apoL_a02_nom'] ?? '')) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($reclamation['apoL_a01_code']) ?></small>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($reclamation['default_name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?=
                                                $reclamation['note'] == 'zero' ? 'danger' :
                                                ($reclamation['note'] == 'absent' ? 'warning' : 'info')
                                            ?>">
                                                <?= htmlspecialchars($reclamation['note']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?=
                                                $reclamation['status'] == 'resolved' ? 'success' :
                                                ($reclamation['status'] == 'pending' ? 'warning' : 'danger')
                                            ?>">
                                                <?= htmlspecialchars($reclamation['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($reclamation['created_at']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune réclamation récente</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i>💾</i> Informations Système</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Tailles des Tables</h6>
                        <?php if (!empty($system_stats['tables'])): ?>
                            <?php foreach ($system_stats['tables'] as $table => $count): ?>
                                <div class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($table) ?></span>
                                    <span class="badge bg-secondary"><?= number_format($count) ?> enregistrements</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6>Informations Générales</h6>
                        <div class="d-flex justify-content-between">
                            <span>Année Académique</span>
                            <span class="badge bg-primary"><?= $current_year ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Version PHP</span>
                            <span class="badge bg-info"><?= phpversion() ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Date du Rapport</span>
                            <span class="badge bg-success"><?= date('d/m/Y H:i') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Gender Distribution Chart
<?php if (!empty($gender_stats)): ?>
const genderCtx = document.getElementById('genderChart').getContext('2d');
new Chart(genderCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($stat) {
            return "'" . ($stat['cod_sex_etu'] == 'M' ? 'Masculin' : 'Féminin') . "'";
        }, $gender_stats)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($gender_stats, 'count')); ?>],
            backgroundColor: ['#007bff', '#e83e8c'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Export to CSV function
function exportToCSV() {
    const data = [
        ['Statistique', 'Valeur'],
        ['Total Étudiants', '<?= $student_stats['total'] ?>'],
        ['Total Filières', '<?= count($filiere_stats) ?>'],
        ['Total Notes', '<?= array_sum(array_column($performance_stats, 'total_notes')) ?>'],
        ['Total Réclamations', '<?= $reclamations_stats['total'] ?>'],
        // Add more data as needed
    ];

    const csvContent = data.map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'rapport_statistiques_<?= date('Y-m-d') ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Print styles
const style = document.createElement('style');
style.textContent = `
    @media print {
        .btn, .card-header {
            display: none !important;
        }
        .card {
            border: 1px solid #000 !important;
            margin-bottom: 20px !important;
        }
        body { font-size: 12px; }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'footer.php'; ?>
