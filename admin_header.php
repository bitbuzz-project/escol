<?php
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    header("Location: login.php");
    exit();
}
$admin = $_SESSION['student'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Admin Dashboard' ?></title>
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
            width: 280px;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            flex-shrink: 0;
            transition: transform 0.3s ease-in-out;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
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
        }
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.3);
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
        .admin-badge {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #dc3545;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-align: center;
            margin: 10px 15px;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
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
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Admin Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="close-sidebar" id="closeSidebar">&times;</button>

        <!-- Admin Logo/Header -->
        <div class="px-3 text-center py-3">
            <img src="images/logo.png" alt="Logo" class="img-fluid mb-2" style="max-width: 120px;">
            <h5 class="mb-0">Admin Panel</h5>
        </div>

        <!-- Admin Badge -->
        <div class="admin-badge">
            🛡️ ADMINISTRATEUR
        </div>

        <!-- Admin Info Card -->
        <div class="card mx-3 mb-3" style="background: rgba(255, 255, 255, 0.1); border: none;">
            <div class="card-body text-center py-2">
                <h6 class="text-white mb-1"><?= htmlspecialchars($admin['apoL_a03_prenom']) . " " . htmlspecialchars($admin['apoL_a02_nom']) ?></h6>
                <small class="text-light">Code: <?= htmlspecialchars($admin['apoL_a01_code']) ?></small>
            </div>
        </div>

        <!-- Navigation Links -->
        <div class="d-grid gap-1 px-2">
            <a href="admin_dashboard.php" class="btn btn-link text-start">
                <i>📊</i> Tableau de bord
            </a>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Gestion des étudiants</div>
                <a href="admin_students.php" class="btn btn-link text-start">
                    <i>👥</i> Liste des étudiants
                </a>
                <a href="admin_add_student.php" class="btn btn-link text-start">
                    <i>➕</i> Ajouter étudiant
                </a>
                <a href="admin_import_students.php" class="btn btn-link text-start">
                    <i>📥</i> Importer JSON
                </a>
                <a href="admin_student_results.php" class="btn btn-link text-start">
                    <i>📋</i> Gérer les résultats
                </a>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Réclamations & Support</div>
                <a href="admin_reclamations.php" class="btn btn-link text-start">
                    <i>⚠️</i> Gestion des réclamations
                    <?php
                    // Get pending reclamations count
                    require_once 'db.php';
                    $pending_query = "SELECT COUNT(*) as count FROM reclamations WHERE status = 'pending'";
                    $pending_result = $conn->query($pending_query);
                    $pending_count = $pending_result ? $pending_result->fetch_assoc()['count'] : 0;
                    if ($pending_count > 0):
                    ?>
                        <span class="notification-badge"><?= $pending_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_reclamations.php?status=pending" class="btn btn-link text-start">
                    <i>🔔</i> Réclamations en attente
                </a>
                <a href="admin_reclamations.php?type=notes" class="btn btn-link text-start">
                    <i>📊</i> Réclamations notes
                </a>
                <a href="admin_reclamations.php?type=correction" class="btn btn-link text-start">
                    <i>✏️</i> Demandes de correction
                </a>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Gestion académique</div>
                <a href="admin_courses.php" class="btn btn-link text-start">
                    <i>📚</i> Gestion des cours
                </a>
                <a href="admin_fillieres.php" class="btn btn-link text-start">
                    <i>🎓</i> Gestion des filières
                </a>
                <a href="admin_sessions.php" class="btn btn-link text-start">
                    <i>📅</i> Sessions d'examens
                </a>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Rapports & Statistiques</div>
                <a href="admin_reports.php" class="btn btn-link text-start">
                    <i>📈</i> Rapports
                </a>
                <a href="admin_statistics.php" class="btn btn-link text-start">
                    <i>📊</i> Statistiques
                </a>
                <a href="admin_reclamations_stats.php" class="btn btn-link text-start">
                    <i>📋</i> Stats réclamations
                </a>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Système</div>
                <a href="admin_settings.php" class="btn btn-link text-start">
                    <i>⚙️</i> Paramètres
                </a>
                <a href="admin_backup.php" class="btn btn-link text-start">
                    <i>💾</i> Sauvegarde
                </a>
                <a href="admin_logs.php" class="btn btn-link text-start">
                    <i>📝</i> Journaux d'activité
                </a>
            </div>
        </div>

        <!-- Logout Button -->
        <div class="px-3 mt-4">
            <a href="logout.php" class="btn btn-warning btn-block w-100">
                <i>🚪</i> Se déconnecter
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <!-- Navbar for mobile -->
        <nav class="navbar navbar-dark bg-danger d-md-none">
            <div class="container-fluid">
                <button class="btn btn-outline-light" id="toggleSidebar">☰ Menu Admin</button>
                <span class="navbar-brand"><?= isset($page_title) ? $page_title : 'Admin Dashboard' ?></span>
            </div>
        </nav>

        <!-- Quick Notifications Bar -->
        <?php if ($pending_count > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
            <strong>🔔 Nouvelles réclamations!</strong>
            Vous avez <?= $pending_count ?> réclamation<?= $pending_count > 1 ? 's' : '' ?> en attente de traitement.
            <a href="admin_reclamations.php?status=pending" class="alert-link">Voir maintenant</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Auto-refresh script for notifications -->
        <script>
        // Auto-refresh pending count every 30 seconds
        setInterval(function() {
            fetch('check_pending_reclamations.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.notification-badge');
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count;
                        } else {
                            // Add badge if it doesn't exist
                            const reclamationsLink = document.querySelector('a[href="admin_reclamations.php"]');
                            if (reclamationsLink) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notification-badge';
                                newBadge.textContent = data.count;
                                reclamationsLink.appendChild(newBadge);
                            }
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(error => console.log('Error checking notifications:', error));
        }, 30000);
        </script>
