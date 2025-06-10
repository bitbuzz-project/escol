<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];

// Include the database connection
require_once 'db.php';

// Fetch notes from notes_exc table
$query = "
    SELECT 
        ne.nom_module, 
        ne.note, 
        ne.prof
    FROM notes_exc ne
    WHERE ne.apoL_a01_code = ?
";

if (!isset($student['apoL_a01_code'])) {
    die("Error: 'apoL_a01_code' is not set.");
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("s", $student['apoL_a01_code']); // Bind the parameter
$stmt->execute();
$result = $stmt->get_result();

// Fetch notes as an array
$notes = [];
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

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
        .table {
            margin-top: 20px;
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
        <h2 class="mt-4">Notes Session d'automne - normale</h2>
        <b>2024-2025</b>
        <table class="table table-striped table-bordered mt-3">
        <thead class="table-dark">
            <tr>
                <th>Nom du Module</th>
                <th>Note</th>
                <th>Professeur</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($notes)): ?>
                <tr>
                    <td colspan="3" class="text-center text-muted">لا توجد نتائج المرجو الإعادة لاحقا</td>
                </tr>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                    <tr>
                        <td><?= htmlspecialchars($note['nom_module']) ?></td>
                        <td><?= htmlspecialchars($note['note']) ?></td>
                        <td><?= htmlspecialchars($note['prof']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

        <div class="row mt-4">

</div>
    </div>
</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>
