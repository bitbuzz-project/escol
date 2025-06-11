<?php
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}
$student = $_SESSION['student'];

// Check if this is admin trying to access student pages
if ($student['apoL_a01_code'] === '16005333') {
    // Redirect admin to admin dashboard if trying to access student pages
    if (!isset($allow_admin_access) || !$allow_admin_access) {
        header("Location: admin_dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Dashboard' ?></title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            height: 100vh;
            overflow: hidden;
        }
        .wrapper {
            display: flex;
            flex-direction: row;
            height: 100%;
        }
        .sidebar {
            width: 320px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex-shrink: 0;
            transition: transform 0.3s ease-in-out;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            margin-bottom: 8px;
            display: block;
            padding: 12px 20px;
            border-radius: 8px;
            margin-left: 15px;
            margin-right: 15px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
        }
        .content {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f8f9fa;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                transform: translateX(-100%);
                z-index: 10;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .content {
                padding: 15px;
            }
        }
        .close-sidebar {
            display: none;
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .close-sidebar {
                display: block;
            }
        }
        .admin-access-notice {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #dc3545;
            padding: 0.75rem;
            border-radius: 8px;
            margin: 10px 15px;
            text-align: center;
            font-weight: bold;
            border: 2px solid rgba(220, 53, 69, 0.2);
        }
        .sidebar-section {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 20px;
            padding-top: 15px;
        }
        .sidebar-section-title {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 20px;
            margin-bottom: 10px;
        }
        .sidebar a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .notification-badge {
            background-color: #ff4757;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            margin-left: 5px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .user-info-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            margin: 10px 15px;
            backdrop-filter: blur(10px);
            flex-shrink: 0;
        }
        .sidebar-brand {
            text-align: center;
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            flex-shrink: 0;
        }
        .sidebar-brand img {
            max-width: 120px;
            margin-bottom: 10px;
        }
        .sidebar-brand h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .logout-section {
            margin-top: auto;
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            flex-shrink: 0;
        }
        /* Custom scrollbar for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Enhanced Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="close-sidebar" id="closeSidebar">&times;</button>

        <!-- Brand/Logo Section -->
        <div class="sidebar-brand">
            <img src="images/logo.png" alt="FSJS Logo" class="img-fluid">
            <h4>FSJS Settat</h4>
            <small style="opacity: 0.8;">Portail Étudiant</small>
        </div>

        <!-- Admin Access Notice (if admin is accessing student interface) -->
        <?php if ($student['apoL_a01_code'] === '16005333'): ?>
            <div class="admin-access-notice">
                🛡️ Mode Admin
                <br><small>Vous naviguez en tant qu'administrateur</small>
                <div class="mt-2">
                    <a href="admin_dashboard.php" class="btn btn-warning btn-sm">
                        Retour Admin Panel
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- User Info Card -->
        <div class="user-info-card">
            <div class="card-body text-center py-3">
                <div class="rounded-circle bg-white bg-opacity-20 d-inline-flex align-items-center justify-content-center mb-2"
                     style="width: 50px; height: 50px; font-size: 1.5rem;">
                    <?= strtoupper(substr($student['apoL_a03_prenom'], 0, 1) . substr($student['apoL_a02_nom'], 0, 1)) ?>
                </div>
                <h6 class="mb-1"><?= htmlspecialchars($student['apoL_a03_prenom']) . " " . htmlspecialchars($student['apoL_a02_nom']) ?></h6>
                <small style="opacity: 0.8;">Code: <?= htmlspecialchars($student['apoL_a01_code']) ?></small>
            </div>
        </div>

        <!-- Navigation Links -->
        <div class="flex-grow-1" style="overflow-y: auto; padding-bottom: 20px;">
            <!-- Main Navigation -->
            <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <i>🏠</i> Tableau de bord
            </a>

            <!-- Academic Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Académique</div>
                <a href="admin_situation.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_situation.php' ? 'active' : '' ?>">
                    <i>📋</i> Mes inscriptions
                </a>
                <a href="pedagogic_situation.php" class="<?= basename($_SERVER['PHP_SELF']) == 'pedagogic_situation.php' ? 'active' : '' ?>">
                    <i>📚</i> Situation pédagogique
                </a>
                <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
                    <i>👤</i> Mon profil
                </a>
            </div>

            <!-- Results Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Résultats</div>
                <a href="resultat.php" class="<?= basename($_SERVER['PHP_SELF']) == 'resultat.php' ? 'active' : '' ?>">
                    <i>📊</i> Notes Modulaires
                    <small class="d-block" style="opacity: 0.7; font-size: 0.75rem;">Session Automne/Printemps</small>
                </a>
                <a href="resultat_ratt.php" class="<?= basename($_SERVER['PHP_SELF']) == 'resultat_ratt.php' ? 'active' : '' ?>">
                    <i>🔄</i> Session Rattrapage
                    <small class="d-block" style="opacity: 0.7; font-size: 0.75rem;">Licence Fondamentale</small>
                </a>
                <a href="resultat_exc.php" class="<?= basename($_SERVER['PHP_SELF']) == 'resultat_exc.php' ? 'active' : '' ?>">
                    <i>🏆</i> Centre d'Excellence
                    <small class="d-block" style="opacity: 0.7; font-size: 0.75rem;">مسالك التميز</small>
                </a>
            </div>

            <!-- Support Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Support & Services</div>
                <a href="student_reclamations.php" class="<?= basename($_SERVER['PHP_SELF']) == 'student_reclamations.php' ? 'active' : '' ?>">
                    <i>⚠️</i> Mes Réclamations
                    <?php
                    // Get pending reclamations count for current student
                    $pending_count = 0;
                    try {
                        // Create a new database connection for notifications
                        $servername = "localhost";
                        $username = "root";
                        $password = "";
                        $dbname = "peda";

                        $notification_conn = new mysqli($servername, $username, $password, $dbname);

                        if (!$notification_conn->connect_error) {
                            $notification_conn->set_charset("utf8");
                            $pending_query = "SELECT COUNT(*) as count FROM reclamations WHERE apoL_a01_code = ? AND status = 'pending'";
                            $pending_stmt = $notification_conn->prepare($pending_query);

                            if ($pending_stmt) {
                                $pending_stmt->bind_param('s', $student['apoL_a01_code']);
                                $pending_stmt->execute();
                                $pending_result = $pending_stmt->get_result();
                                $pending_count = $pending_result ? $pending_result->fetch_assoc()['count'] : 0;
                                $pending_stmt->close();
                            }
                            $notification_conn->close();
                        }
                    } catch (Exception $e) {
                        // Ignore database errors for notifications
                        $pending_count = 0;
                    }
                    if ($pending_count > 0):
                    ?>
                        <span class="notification-badge"><?= $pending_count ?></span>
                    <?php endif; ?>
                </a>

                <!-- Quick Reclamation Buttons -->

            </div>

            <!-- Information Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Informations</div>
                <a href="#" onclick="showContactInfo()">
                    <i>📞</i> Contact & Support
                </a>
                <a href="#" onclick="showAcademicCalendar()">
                    <i>📅</i> Calendrier Académique
                </a>
                <a href="#" onclick="showUsefulLinks()">
                    <i>🔗</i> Liens Utiles
                </a>
            </div>
        </div>

        <!-- Logout Section -->
        <div class="logout-section">
            <a href="logout.php" class="btn btn-outline-light w-80" style="border-color: rgba(255,255,255,0.3);">
                <i>🚪</i> Se déconnecter
            </a>
            <div class="text-center mt-2">
                <small style="opacity: 0.6;">Session 2024-2025</small>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <!-- Navbar for mobile -->
        <nav class="navbar navbar-light bg-white shadow-sm d-md-none mb-3">
            <div class="container-fluid">
                <button class="btn btn-outline-primary" id="toggleSidebar">
                    <span class="navbar-toggler-icon"></span> Menu
                </button>
                <span class="navbar-brand mb-0 h1"><?= isset($page_title) ? $page_title : 'Tableau de bord' ?></span>
                <div class="d-flex">
                    <a href="student_reclamations.php" class="btn btn-outline-warning btn-sm me-2">
                        ⚠️ Réclamations
                    </a>
                </div>
            </div>
        </nav>

        <!-- Breadcrumb for desktop -->
        <nav aria-label="breadcrumb" class="d-none d-md-block mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">🏠 Accueil</a></li>
                <?php if (isset($page_title) && $page_title !== 'Tableau de bord'): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($page_title) ?></li>
                <?php endif; ?>
            </ol>
        </nav>

<!-- Quick Reclamation Modal -->
<div class="modal fade" id="quickReclamationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickReclamationTitle">Nouvelle Réclamation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-center">
                    <i style="font-size: 3rem;">⚠️</i><br>
                    Pour créer une réclamation complète,<br>
                    rendez-vous sur la page dédiée.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <a href="student_reclamations.php" class="btn btn-primary">Aller aux réclamations</a>
            </div>
        </div>
    </div>
</div>

<!-- Contact Info Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contact & Support</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row text-center">
                    <div class="col-md-6 mb-3">
                        <i style="font-size: 2rem; color: #28a745;">📧</i>
                        <h6 class="mt-2">Email Support</h6>
                        <p class="text-muted">support@fsjs.ac.ma</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <i style="font-size: 2rem; color: #17a2b8;">📱</i>
                        <h6 class="mt-2">Secrétariat</h6>
                        <p class="text-muted">+212 523 XXX XXX</p>
                    </div>
                    <div class="col-12">
                        <i style="font-size: 2rem; color: #ffc107;">🕒</i>
                        <h6 class="mt-2">Horaires d'ouverture</h6>
                        <p class="text-muted">Lundi - Vendredi: 8h00 - 17h00</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openQuickReclamation(type) {
    const titles = {
        'notes': 'Réclamation Notes',
        'correction': 'Demande de Correction',
        'autre': 'Autre Demande'
    };

    document.getElementById('quickReclamationTitle').textContent = titles[type] || 'Nouvelle Réclamation';

    const modal = new bootstrap.Modal(document.getElementById('quickReclamationModal'));
    modal.show();
}

function showContactInfo() {
    const modal = new bootstrap.Modal(document.getElementById('contactModal'));
    modal.show();
}

function showAcademicCalendar() {
    alert('📅 Calendrier Académique\n\nSession d\'Automne: Septembre - Février\nSession de Printemps: Février - Juin\nRattrapage: Juin - Juillet');
}

function showUsefulLinks() {
    alert('🔗 Liens Utiles\n\n• Site officiel FSJS\n• Bibliothèque universitaire\n• Services étudiants\n• Orientation et carrières');
}

// Sidebar toggle functionality
const sidebar = document.getElementById('sidebar');
const toggleButton = document.getElementById('toggleSidebar');
const closeButton = document.getElementById('closeSidebar');

if (toggleButton) {
    toggleButton.addEventListener('click', () => {
        sidebar.classList.add('show');
    });
}

if (closeButton) {
    closeButton.addEventListener('click', () => {
        sidebar.classList.remove('show');
    });
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggleButton.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Auto-refresh notification badges
setInterval(function() {
    // You can add AJAX call here to refresh notification counts
}, 30000);
</script>
