<?php
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}
$student = $_SESSION['student'];
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<!-- header.php -->
<div class="d-flex flex-column flex-shrink-0 bg-dark" style="width: 250px; height: 100vh;">
    <button class="btn btn-outline-light d-md-none my-2 ms-2" id="toggleSidebar">☰ Menu</button>
    <div class="p-3">
        <img src="images/logo.png" alt="Logo" class="img-fluid my-3" style="max-width: 150px;">
        <div class="card bg-light text-dark mb-3">
            <div class="card-body text-center">
                <h5 class="card-title mb-2"><?= htmlspecialchars($student['apoL_a03_prenom']) . " " . htmlspecialchars($student['apoL_a02_nom']) ?></h5>
                <p class="card-text text-muted">Code Apogee: <?= htmlspecialchars($student['apoL_a01_code']) ?></p>
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="admin_situation.php" class="nav-link text-white">Mes inscriptions</a>
            </li>
            <li class="nav-item">
                <a href="pedagogic_situation.php" class="nav-link text-white">Situation pédagogique</a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link text-danger">Se déconnecter</a>
            </li>
        </ul>
    </div>
</div>
