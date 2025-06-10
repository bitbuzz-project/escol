<?php
session_start();
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    header("Location: login.php");
    exit();
}

$admin = $_SESSION['student'];
$page_title = "Tableau de bord Admin";

require 'db.php';

// Get statistics
$stats = [];

// Count total students
$result = $conn->query("SELECT COUNT(*) as total FROM apogeL_a");
$stats['total_students'] = $result->fetch_assoc()['total'];

// Count total filières
$result = $conn->query("SELECT COUNT(DISTINCT filliere) as total FROM administative");
$stats['total_fillieres'] = $result->fetch_assoc()['total'];

// Count students with results
$result = $conn->query("SELECT COUNT(DISTINCT apogee) as total FROM rsultat");
$stats['students_with_results'] = $result->fetch_assoc()['total'];

// Get recent activities (last 10 student logins or registrations)
$recent_students = [];
$result = $conn->query("SELECT apoL_a01_code, apoL_a02_nom, apoL_a03_prenom FROM apogeL_a ORDER BY apoL_a01_code DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_students[] = $row;
}

$conn->close();

include 'admin_header.php';
?>

<!-- Welcome Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-danger border-0 shadow" role="alert">
            <h4 class="alert-heading">
                <i>👋</i> Bienvenue, <?= htmlspecialchars($admin['apoL_a03_prenom']) ?>!
            </h4>
            <p class="mb-0">Vous êtes connecté en tant qu'administrateur. Gérez votre plateforme étudiante depuis ce panneau de contrôle.</p>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #17a2b8, #138496);">
            <div class="card-body text-white text-center">
                <i style="font-size: 2.5rem;">👥</i>
                <h2 class="mt-2 mb-1"><?= $stats['total_students'] ?></h2>
                <p class="card-text">Étudiants Total</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #28a745, #20c997);">
            <div class="card-body text-white text-center">
                <i style="font-size: 2.5rem;">🎓</i>
                <h2 class="mt-2 mb-1"><?= $stats['total_fillieres'] ?></h2>
                <p class="card-text">Filières</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
            <div class="card-body text-white text-center">
                <i style="font-size: 2.5rem;">📊</i>
                <h2 class="mt-2 mb-1"><?= $stats['students_with_results'] ?></h2>
                <p class="card-text">Avec Résultats</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #6f42c1, #5a67d8);">
            <div class="card-body text-white text-center">
                <i style="font-size: 2.5rem;">📈</i>
                <h2 class="mt-2 mb-1">2024-25</h2>
                <p class="card-text">Session Actuelle</p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i>⚡</i> Actions Rapides</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="admin_add_student.php" class="btn btn-outline-primary btn-block w-100">
                            <i>➕</i> Ajouter Étudiant
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="admin_student_results.php" class="btn btn-outline-success btn-block w-100">
                            <i>📋</i> Gérer Résultats
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="admin_reports.php" class="btn btn-outline-info btn-block w-100">
                            <i>📈</i> Voir Rapports
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="admin_backup.php" class="btn btn-outline-warning btn-block w-100">
                            <i>💾</i> Sauvegarde
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Students and System Info -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i>👥</i> Étudiants Récents</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_students)): ?>
                    <p class="text-muted">Aucun étudiant trouvé.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_students as $student): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <?= strtoupper(substr($student['apoL_a03_prenom'], 0, 1)) ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($student['apoL_a03_prenom'] . ' ' . $student['apoL_a02_nom']) ?></h6>
                                        <small class="text-muted">Code: <?= htmlspecialchars($student['apoL_a01_code']) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="admin_students.php" class="btn btn-outline-primary btn-sm">Voir tous les étudiants</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i>ℹ️</i> Informations Système</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <strong>Version PHP:</strong><br>
                        <span class="text-muted"><?= phpversion() ?></span>
                    </div>
                    <div class="col-6">
                        <strong>Serveur:</strong><br>
                        <span class="text-muted"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></span>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <strong>Date:</strong><br>
                        <span class="text-muted"><?= date('d/m/Y') ?></span>
                    </div>
                    <div class="col-6">
                        <strong>Heure:</strong><br>
                        <span class="text-muted"><?= date('H:i:s') ?></span>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <span class="badge bg-success">Système Opérationnel</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
