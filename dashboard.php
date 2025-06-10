<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];
$page_title = "Tableau de bord";

require 'db.php';

// Function to count records safely
function countRecords($conn, $table, $where_clause = '1=1', $params = []) {
    $query = "SELECT COUNT(*) as count FROM `$table` WHERE $where_clause";
    $stmt = $conn->prepare($query);

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['count'] ?? 0;
}

// Get statistics for the current student
$apogee = $student['apoL_a01_code'];

// Count total notes across all tables
$notes_count = 0;
$notes_tables = ['notes', 'notes_ratt', 'notes_print', 'notes_exc'];
foreach ($notes_tables as $table) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check && $table_check->num_rows > 0) {
        $notes_count += countRecords($conn, $table, 'apoL_a01_code = ?', [$apogee]);
    }
}

// Count notes by session for notes_print
$notes_print_sessions = [];
$sessions_query = "SELECT session, COUNT(*) as count FROM notes_print WHERE apoL_a01_code = ? GROUP BY session";
$sessions_stmt = $conn->prepare($sessions_query);
if ($sessions_stmt) {
    $sessions_stmt->bind_param('s', $apogee);
    $sessions_stmt->execute();
    $sessions_result = $sessions_stmt->get_result();
    while ($row = $sessions_result->fetch_assoc()) {
        $notes_print_sessions[$row['session']] = $row['count'];
    }
    $sessions_stmt->close();
}

// Count reclamations
$reclamations_count = countRecords($conn, 'reclamations', 'apoL_a01_code = ?', [$apogee]);

// Count pending reclamations
$pending_reclamations = countRecords($conn, 'reclamations', 'apoL_a01_code = ? AND status = ?', [$apogee, 'pending']);

// Get filières count
$filieres_count = countRecords($conn, 'administative', 'apogee = ?', [$apogee]);

// Get recent notes (last 5)
$recent_notes = [];
foreach ($notes_tables as $table) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check && $table_check->num_rows > 0) {
        $session_field = $table === 'notes_print' ? ', session' : '';
        $query = "SELECT nom_module, note, adding_date{$session_field}, '$table' as source FROM `$table` WHERE apoL_a01_code = ? ORDER BY adding_date DESC LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $apogee);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recent_notes[] = $row;
        }
        $stmt->close();
    }
}

// Sort recent notes by date
usort($recent_notes, function($a, $b) {
    return strtotime($b['adding_date']) - strtotime($a['adding_date']);
});

// Get only top 5
$recent_notes = array_slice($recent_notes, 0, 5);

// Calculate average grade
$total_numeric_notes = 0;
$numeric_count = 0;
foreach ($recent_notes as $note) {
    if (is_numeric($note['note']) && $note['note'] > 0) {
        $total_numeric_notes += $note['note'];
        $numeric_count++;
    }
}
$average_grade = $numeric_count > 0 ? round($total_numeric_notes / $numeric_count, 2) : 0;

// Get recent reclamations
$recent_reclamations = [];
$query = "SELECT default_name, note, prof, status, created_at FROM reclamations WHERE apoL_a01_code = ? ORDER BY created_at DESC LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $apogee);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_reclamations[] = $row;
}
$stmt->close();

// Get academic info
$filiere_info = [];
$query = "SELECT filliere FROM administative WHERE apogee = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $apogee);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $filiere_info[] = $row['filliere'];
}
$stmt->close();

$conn->close();

include 'header.php';
?>

<!-- Enhanced Dashboard Content -->
<div class="container-fluid px-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-1">
                                <i style="font-size: 1.5rem;">👋</i>
                                Bienvenue, <?= htmlspecialchars($student['apoL_a03_prenom']) ?>!
                            </h2>
                            <p class="mb-0 opacity-75">
                                Voici votre tableau de bord académique - Session 2024/2025
                            </p>
                            <small class="opacity-50">
                                Code Apogée: <?= htmlspecialchars($student['apoL_a01_code']) ?>
                            </small>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="rounded-circle bg-white bg-opacity-20 d-inline-flex align-items-center justify-content-center"
                                 style="width: 80px; height: 80px; font-size: 2rem;">
                                <?= strtoupper(substr($student['apoL_a03_prenom'], 0, 1) . substr($student['apoL_a02_nom'], 0, 1)) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100 hover-card">
                <div class="card-body text-center p-4" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 12px;">
                    <i style="font-size: 3rem; margin-bottom: 15px;">📊</i>
                    <h3 class="mb-1"><?= $notes_count ?></h3>
                    <p class="mb-0">Notes Total</p>
                    <?php if ($average_grade > 0): ?>
                        <small class="opacity-75">Moyenne: <?= $average_grade ?>/20</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100 hover-card">
                <div class="card-body text-center p-4" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border-radius: 12px;">
                    <i style="font-size: 3rem; margin-bottom: 15px;">🎓</i>
                    <h3 class="mb-1"><?= $filieres_count ?></h3>
                    <p class="mb-0">Filières</p>
                    <small class="opacity-75">Inscriptions actives</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100 hover-card">
                <div class="card-body text-center p-4" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; border-radius: 12px;">
                    <i style="font-size: 3rem; margin-bottom: 15px;">⚠️</i>
                    <h3 class="mb-1"><?= $reclamations_count ?></h3>
                    <p class="mb-0">Réclamations</p>
                    <?php if ($pending_reclamations > 0): ?>
                        <small class="opacity-75"><?= $pending_reclamations ?> en attente</small>
                    <?php else: ?>
                        <small class="opacity-75">Toutes traitées</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100 hover-card">
                <div class="card-body text-center p-4" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; border-radius: 12px;">
                    <i style="font-size: 3rem; margin-bottom: 15px;">📅</i>
                    <h3 class="mb-1">2024-25</h3>
                    <p class="mb-0">Session Actuelle</p>
                    <small class="text-muted">Année universitaire</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Results Cards Section -->
        <div class="col-lg-8">
            <div class="row mb-4">
                <!-- Normal Session -->
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm hover-card h-100">
                        <div class="card-body d-flex flex-column p-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0">
                                    <i style="font-size: 1.5rem;">📊</i> Session Normale
                                </h5>
                                <span class="badge bg-light text-dark">Automne 2024</span>
                            </div>
                            <p class="card-text flex-grow-1 opacity-75">
                                Résultats de la session d'automne normale 2024-2025
                            </p>
                            <a href="resultat.php" class="btn btn-light btn-sm">
                                <i style="font-size: 0.9rem;">👀</i> Voir les détails
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Automne Session from notes_print -->
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm hover-card h-100">
                        <div class="card-body d-flex flex-column p-4" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0">
                                    <i style="font-size: 1.5rem;">🍂</i> Session Automne
                                </h5>
                                <span class="badge bg-light text-dark">
                                    <?= $notes_print_sessions['automne'] ?? 0 ?> notes
                                </span>
                            </div>
                            <p class="card-text flex-grow-1 opacity-75">
                                Session d'automne - Licence Fondamentale
                            </p>
                            <a href="resultat_print.php?session=automne" class="btn btn-light btn-sm">
                                <i style="font-size: 0.9rem;">👀</i> Voir les détails
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Spring Session from notes_print -->
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm hover-card h-100 position-relative">
                        <div class="ribbon">Nouveau</div>
                        <div class="card-body d-flex flex-column p-4" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0">
                                    <i style="font-size: 1.5rem;">🌸</i> Session Printemps
                                </h5>
                                <span class="badge bg-light text-dark">
                                    <?= $notes_print_sessions['printemps'] ?? 0 ?> notes
                                </span>
                            </div>
                            <p class="card-text flex-grow-1 opacity-75">
                                Session de printemps - Licence Fondamentale
                            </p>
                            <a href="resultat_print.php?session=printemps" class="btn btn-light btn-sm">
                                <i style="font-size: 0.9rem;">👀</i> Voir les détails
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Rattrapage Session -->
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm hover-card h-100">
                        <div class="card-body d-flex flex-column p-4" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0">
                                    <i style="font-size: 1.5rem;">🔄</i> Rattrapage
                                </h5>
                                <span class="badge bg-secondary text-white">Automne 2024</span>
                            </div>
                            <p class="card-text flex-grow-1">
                                Résultats de rattrapage - Licence Fondamentale
                            </p>
                            <a href="resultat_ratt.php" class="btn btn-dark btn-sm">
                                <i style="font-size: 0.9rem;">👀</i> Voir les détails
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Excellence Center -->
                <div class="col-md-12 mb-3">
                    <div class="card border-0 shadow-sm hover-card h-100">
                        <div class="card-body d-flex flex-column p-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0">
                                    <i style="font-size: 1.5rem;">🏆</i> Centre d'Excellence
                                </h5>
                                <span class="badge bg-warning text-dark">خاص بمسالك التميز</span>
                            </div>
                            <p class="card-text flex-grow-1 opacity-75">
                                Centre D'excellence - مسالك التميز
                            </p>
                            <a href="resultat_exc.php" class="btn btn-light btn-sm">
                                <i style="font-size: 0.9rem;">👀</i> Voir les détails
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Notes Section -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 p-4">
                    <h5 class="mb-0">
                        <i style="font-size: 1.2rem;">📈</i> Notes Récentes
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_notes)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="border-0 px-4 py-3">Module</th>
                                        <th class="border-0 px-4 py-3">Note</th>
                                        <th class="border-0 px-4 py-3">Session</th>
                                        <th class="border-0 px-4 py-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_notes as $note): ?>
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="fw-bold"><?= htmlspecialchars($note['nom_module']) ?></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="badge <?= is_numeric($note['note']) && $note['note'] >= 10 ? 'bg-success' : 'bg-danger' ?> fs-6">
                                                    <?= htmlspecialchars($note['note']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="badge bg-info">
                                                    <?php
                                                    $source_display = str_replace('notes_', '', $note['source']);
                                                    if ($note['source'] === 'notes_print' && isset($note['session'])) {
                                                        $source_display = $note['session'] === 'printemps' ? '🌸 Printemps' : '🍂 Automne';
                                                    } elseif ($note['source'] === 'notes') {
                                                        $source_display = 'Normal';
                                                    } elseif ($note['source'] === 'notes_ratt') {
                                                        $source_display = 'Rattrapage';
                                                    } elseif ($note['source'] === 'notes_exc') {
                                                        $source_display = 'Excellence';
                                                    }
                                                    echo $source_display;
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <small class="text-muted">
                                                    <?= date('d/m/Y', strtotime($note['adding_date'])) ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 text-center border-top">
                            <a href="resultat.php" class="btn btn-outline-primary btn-sm">
                                Voir toutes les notes
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i style="font-size: 3rem; color: #6c757d;">📊</i>
                            <h6 class="mt-3 text-muted">Aucune note disponible</h6>
                            <p class="text-muted">Les résultats apparaîtront ici une fois publiés</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Section -->
        <div class="col-lg-4">
            <!-- Session Statistics -->
            <?php if (!empty($notes_print_sessions)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 p-4">
                    <h5 class="mb-0">
                        <i style="font-size: 1.2rem;">📊</i> Statistiques par Session
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php foreach ($notes_print_sessions as $session => $count): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <span style="font-size: 1.5rem; margin-right: 10px;">
                                    <?= $session === 'printemps' ? '🌸' : '🍂' ?>
                                </span>
                                <div>
                                    <h6 class="mb-0"><?= ucfirst($session) ?></h6>
                                    <small class="text-muted">Session 2024-25</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary fs-6"><?= $count ?></span>
                                <br><small class="text-muted">notes</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 p-4">
                    <h5 class="mb-0">
                        <i style="font-size: 1.2rem;">⚡</i> Actions Rapides
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-2">
                        <a href="profile.php" class="btn btn-outline-primary">
                            <i style="font-size: 1rem;">👤</i> Mon Profil
                        </a>
                        <a href="admin_situation.php" class="btn btn-outline-success">
                            <i style="font-size: 1rem;">📋</i> Mes Inscriptions
                        </a>
                        <a href="pedagogic_situation.php" class="btn btn-outline-info">
                            <i style="font-size: 1rem;">📚</i> Situation Pédagogique
                        </a>
                    </div>
                </div>
            </div>

            <!-- Academic Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 p-4">
                    <h5 class="mb-0">
                        <i style="font-size: 1.2rem;">🎓</i> Informations Académiques
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($filiere_info)): ?>
                        <div class="mb-3">
                            <small class="text-muted text-uppercase">Filières d'inscription</small>
                            <?php foreach ($filiere_info as $filiere): ?>
                                <div class="mt-2">
                                    <span class="badge bg-primary rounded-pill">
                                        <?= htmlspecialchars($filiere) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <small class="text-muted text-uppercase">Session actuelle</small>
                        <div class="mt-2">
                            <span class="badge bg-success rounded-pill">2024-2025</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reclamations -->
            <?php if (!empty($recent_reclamations)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 p-4">
                    <h5 class="mb-0">
                        <i style="font-size: 1.2rem;">⚠️</i> Réclamations Récentes
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($recent_reclamations as $reclamation): ?>
                        <div class="p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= htmlspecialchars($reclamation['default_name']) ?></h6>
                                    <small class="text-muted">
                                        Prof: <?= htmlspecialchars($reclamation['prof']) ?>
                                    </small>
                                </div>
                                <span class="badge <?= $reclamation['status'] === 'resolved' ? 'bg-success' : 'bg-warning' ?> ms-2">
                                    <?= htmlspecialchars($reclamation['status']) ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                <?= date('d/m/Y', strtotime($reclamation['created_at'])) ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Help & Support -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 p-4">
                    <h5 class="mb-0">
                        <i style="font-size: 1.2rem;">💬</i> Aide & Support
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-center">
                        <i style="font-size: 2rem; color: #28a745;">📧</i>
                        <h6 class="mt-2">Besoin d'aide?</h6>
                        <p class="text-muted small">
                            Contactez le support pour toute question
                        </p>
                        <a href="mailto:support@fsjs.ac.ma" class="btn btn-outline-success btn-sm">
                            Contacter le Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
.hover-card {
    transition: all 0.3s ease;
}

.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
}

.ribbon {
    position: absolute;
    top: 15px;
    right: -8px;
    background: #dc3545;
    color: white;
    padding: 4px 25px;
    font-size: 0.8rem;
    font-weight: bold;
    transform: rotate(15deg);
    z-index: 2;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.card {
    border-radius: 12px !important;
    overflow: hidden;
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.badge {
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .container-fluid {
        padding-left: 15px !important;
        padding-right: 15px !important;
    }

    .ribbon {
        display: none;
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

<!-- Add some JavaScript for enhanced interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling to internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Add loading states to buttons
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function() {
            if (this.href && !this.href.includes('mailto:')) {
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + this.innerHTML;
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>
