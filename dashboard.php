<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <style>
        body {
            height: 100vh;
            overflow: hidden; /* Prevent scrollbars when sidebar is toggled */
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
    font-size: 0.8em;  /* Makes the text smaller */
    display: block;  /* Places it on a new line */
    opacity: 0.8;  /* Slightly faded for better styling */
}

        @media (max-width: 768px) {
            .close-sidebar {
                display: block;
            }
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
            <span class="navbar-brand">Tableau de bord</span>
        </div>
    </nav>
    <!-- <h2>Tableau de bord</h2>
    <p>Sélectionnez une option dans la barre latérale pour consulter vos informations.</p> -->

    <!-- Cards for Admin Situation and Pedagogic Situation -->
    <div class="row mt-4">
        <!-- Admin Situation Card -->
        <!-- <div class="col-md-6 mb-4">
            <div class="card text-white bg-primary h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <h5 class="card-title text-center">Mes inscriptions</h5>
                    <p class="card-text text-center">
                        Consultez et gérez les détails de vos inscriptions administratives.
                    </p>
                    <a href="admin_situation.php" class="btn btn-light btn-block mt-3">Voir les détails</a>
                </div>
            </div>
        </div> -->
        <!-- Pedagogic Situation Card -->
        <!-- <div class="col-md-6 mb-4">
            <div class="card text-white bg-secondary h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <h5 class="card-title text-center">Situation pédagogique</h5>
                    <p class="card-text text-center">
                        Consultez et gérez votre situation pédagogique et vos modules.
                    </p>
                    <a href="pedagogic_situation.php" class="btn btn-light btn-block mt-3">Voir les détails</a>
                </div>
            </div>
        </div> -->
        <div class="col-md-6 mb-4">
            <div class="card text-white bg-success h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <h5 class="card-title text-center">résultats</h5>
                    <p class="card-text text-center">
                    Résultats de la session d'automne - normale 2024-2025
                    </p>
                    <a href="resultat.php" class="btn btn-light btn-block mt-3">Voir les détails</a>
                </div>
            </div>
        </div>
        <style>
    .card-custom {
        background: linear-gradient(135deg,rgb(209, 149, 59),rgb(188, 59, 55)); /* More attractive green */
        color: white;
        border: none;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
        border-radius: 15px;
        position: relative;
        overflow: hidden;
    }
        .card-customresult {
        background: linear-gradient(135deg,rgb(62, 85, 211),rgb(7, 4, 199)); /* More attractive green */
        color: white;
        border: none;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
        border-radius: 15px;
        position: relative;
        overflow: hidden;
    }

    /* Properly positioned ribbon */
    .ribbon {
        position: absolute;
        top: 30px;
        left: -60px;
        background: #dc3545; /* Red */
        color: white;
        padding: 5px 50px;
        font-size: 0.9rem;
        font-weight: bold;
        transform: rotate(-45deg);
        text-align: center;
        box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.2);
    }
</style>

<div class="col-md-6 mb-4">
    <div class="card card-custom h-100 p-3">
        <!-- Perfectly Fitted Ribbon -->

        
        <div class="card-body d-flex flex-column justify-content-between">
            <h5 class="card-title text-center">Résultats - Rattrapage</h5>
            <p class="card-text text-center">
                Résultats de la session d'automne - Rattrapage 2024-2025
            </p>
            <a href="resultat_ratt.php" class="btn btn-light btn-block mt-3">Voir les détails</a>
        </div>
    </div>
</div>

        <div class="col-md-6 mb-4">
            <div class="card text-white bg-success bg-info h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <h5 class="card-title text-center">résultats</h5>
                    <p class="card-text text-center">
                    Résultats de la session d'automne - normale 2024-2025
                    </p>
                    <span><center><b>Center D'excellence</b></center></span>
                    <span><center><b>خاص بمسالك التميز</b></center></span>
                    <a href="resultat_exc.php" class="btn btn-light btn-block mt-3">Voir les détails</a>
                </div>
            </div>
        </div>
         
        <div class="col-md-6 mb-4">
    <div class="card card-customresult h-100 p-3">
        <!-- Perfectly Fitted Ribbon -->
        <div class="ribbon">Ajouté récemment</div>
        
        <div class="card-body d-flex flex-column justify-content-between">
         <h5 class="card-title text-center">résultats - Session de printemps</h5>

            <p class="card-text text-center">
                    Résultats de la Session de printemps - normale 2024-2025
                    </p>
                                        <span><center><b>Licence Fondamentale</b></center></span>

            <a href="resultat_print.php" class="btn btn-light btn-block mt-3">Voir les détails</a>
        </div>
    </div>
</div>
    </div>
    <!-- Notification Card with Arabic and French -->


</div>

</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('toggleSidebar');
    const closeButton = document.getElementById('closeSidebar');

    toggleButton.addEventListener('click', () => {
        sidebar.classList.add('show');
    });

    closeButton.addEventListener('click', () => {
        sidebar.classList.remove('show');
    });
</script>
</body>
</html>
