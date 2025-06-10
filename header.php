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
            width: 250px;
            background-color: #343a40;
            color: white;
            flex-shrink: 0;
            transition: transform 0.3s ease-in-out;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            margin-bottom: 10px;
            display: block;
            padding: 10px 15px;
            border-radius: 4px;
        }
        .sidebar a:hover {
            background-color: #495057;
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
            font-size: 20px;
            background: none;
            border: none;
            color: white;
        }
        .sub-text {
            font-size: 0.8em;
            display: block;
            opacity: 0.8;
        }
        @media (max-width: 768px) {
            .close-sidebar {
                display: block;
            }
        }
        .table {
            margin-top: 20px;
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
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="close-sidebar" id="closeSidebar">&times;</button>
        <div class="px-3 text-center">
            <img src="images/logo.png" alt="Logo" class="img-fluid my-3" style="max-width: 150px;">
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

        <div class="card mx-3 mb-4">
            <div class="card-body text-center">
                <h5 style="color:black" class="card-title mb-2"><?= htmlspecialchars($student['apoL_a03_prenom']) . " " . htmlspecialchars($student['apoL_a02_nom']) ?></h5>
                <p class="card-text text-muted">Code Apogee: <?= htmlspecialchars($student['apoL_a01_code']) ?></p>
            </div>
        </div>
        <div class="d-grid gap-2 px-3">
            <a href="admin_situation.php" class="btn btn-secondary btn-block">Mes inscriptions</a>
            <a href="pedagogic_situation.php" class="btn btn-secondary btn-block">Situation pédagogique</a>
            <a href="resultat_print.php" class="btn btn-secondary btn-block">
                Resultat <br>Session de printemps <br><span class="sub-text">(Licence Fondamentale)</span>
            </a>
            <a href="resultat.php" class="btn btn-secondary btn-block">
                Resultat <br><span class="sub-text">(Licence Fondamentale)</span>
            </a>
            <a href="resultat_ratt.php" class="btn btn-secondary btn-block">
                Resultat Session Rattrapage <br><span class="sub-text">(Licence Fondamentale)</span>
            </a>
            <a href="resultat_exc.php" class="btn btn-secondary btn-block">
                Resultat <br><span class="sub-text">(Centre D'excellence)</span>
            </a>
            <a href="logout.php" class="btn btn-danger btn-block">Se déconnecter</a>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <!-- Navbar for mobile -->
        <nav class="navbar navbar-dark bg-dark d-md-none">
            <div class="container-fluid">
                <button class="btn btn-outline-light" id="toggleSidebar">☰ Menu</button>
                <span class="navbar-brand"><?= isset($page_title) ? $page_title : 'Tableau de bord' ?></span>
            </div>
        </nav>
