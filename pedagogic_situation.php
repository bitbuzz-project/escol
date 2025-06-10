<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];
$apogee = $student['apoL_a01_code']; // Get the apogee code from session

require 'db.php'; // Include database connection

// Fetch modules and group for Semestre 1
$queryS1 = "SELECT mod_name, groupe FROM s1 WHERE apogee = ?";
$stmtS1 = $conn->prepare($queryS1);
$stmtS1->bind_param("s", $apogee);
$stmtS1->execute();
$resultS1 = $stmtS1->get_result();

$modulesS1 = [];
while ($row = $resultS1->fetch_assoc()) {
    $modulesS1[] = [
        'mod_name' => $row['mod_name'],
        'groupe' => $row['groupe']
    ];
}
$stmtS1->close();


// Fetch modules and group for Semestre 2 with Arabic names if available
// Fetch modules for Semestre 2 with Arabic module names if available
$queryS2 = "
    SELECT 
        S2.cod_elp, 
        COALESCE(mod_arabe.nom_module, S2.mod_name) AS mod_name
    FROM S2
    LEFT JOIN mod_arabe ON S2.cod_elp = mod_arabe.code_module
    WHERE S2.apogee = ?
";

$stmtS2 = $conn->prepare($queryS2);
$stmtS2->bind_param("s", $apogee);
$stmtS2->execute();
$resultS2 = $stmtS2->get_result();

$modulesS2 = [];
while ($row = $resultS2->fetch_assoc()) {
    $modulesS2[] = [
        'mod_name' => $row['mod_name'], // Arabic name if available, else default
        'cod_elp' => $row['cod_elp'] // Fetching cod_elp
    ];
}
$stmtS2->close();


// Fetch modules and group for Semestre 4 with Arabic names if available

$queryS4 = "
    SELECT 
        S4.cod_elp, 
        COALESCE(mod_arabe.nom_module, S4.mod_name) AS mod_name
    FROM S4
    LEFT JOIN mod_arabe ON S4.cod_elp = mod_arabe.code_module
    WHERE S4.apogee = ?
";

$stmtS4 = $conn->prepare($queryS4);
$stmtS4->bind_param("s", $apogee);
$stmtS4->execute();
$resultS4 = $stmtS4->get_result();

$modulesS4 = [];
while ($row = $resultS4->fetch_assoc()) {
    $modulesS4[] = [
        'mod_name' => $row['mod_name'], // Arabic name if available, else default
        'cod_elp' => $row['cod_elp'] // Fetching cod_elp
    ];
}
$stmtS4->close();


// Fetch modules and group for Semestre 4 with Arabic names if available

$queryS6 = "
    SELECT 
        S6.cod_elp, 
        COALESCE(mod_arabe.nom_module, S6.mod_name) AS mod_name
    FROM S6
    LEFT JOIN mod_arabe ON S6.cod_elp = mod_arabe.code_module
    WHERE S6.apogee = ?
";

$stmtS6 = $conn->prepare($queryS6);
$stmtS6->bind_param("s", $apogee);
$stmtS6->execute();
$resultS6 = $stmtS6->get_result();

$modulesS6 = [];
while ($row = $resultS6->fetch_assoc()) {
    $modulesS6[] = [
        'mod_name' => $row['mod_name'], // Arabic name if available, else default
        'cod_elp' => $row['cod_elp'] // Fetching cod_elp
    ];
}
$stmtS6->close();



// Fetch modules and group for Semestre 3
$queryS3 = "SELECT mod_name, groupe FROM s3 WHERE apogee = ?";
$stmtS3 = $conn->prepare($queryS3);
$stmtS3->bind_param("s", $apogee);
$stmtS3->execute();
$resultS3 = $stmtS3->get_result();

$modulesS3 = [];
while ($row = $resultS3->fetch_assoc()) {
    $modulesS3[] = [
        'mod_name' => $row['mod_name'],
        'groupe' => $row['groupe']
    ];
}
$stmtS3->close();

// Fetch modules and group for Semestre 5
$queryS5 = "SELECT mod_name, groupe FROM s5 WHERE apogee = ?";
$stmtS5 = $conn->prepare($queryS5);
$stmtS5->bind_param("s", $apogee);
$stmtS5->execute();
$resultS5 = $stmtS5->get_result();

$modulesS5 = [];
while ($row = $resultS5->fetch_assoc()) {
    $modulesS5[] = [
        'mod_name' => $row['mod_name'],
        'groupe' => $row['groupe']
    ];
}
$stmtS5->close();


// Fetch recherche info
$queryRech = "SELECT Filière, Assigned_Prof, Groupe FROM rech_groupes WHERE apogee = ?";
$stmtRech = $conn->prepare($queryRech);
$stmtRech->bind_param("s", $apogee);
$stmtRech->execute();
$resultRech = $stmtRech->get_result();

$rechercheInfo = [];
while ($row = $resultRech->fetch_assoc()) {
    $rechercheInfo[] = $row;
}
$stmtRech->close();


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Situation pédagogique</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">Situation pédagogique</h1>
    <h3 class="text-center">Mes inscriptions aux examens : <?= htmlspecialchars($student['apoL_a03_prenom']) . " " . htmlspecialchars($student['apoL_a02_nom']) ?></h3>
    <center><span>Session d'Automne Ordinaire 2024/2025</span></center>

    <!-- Accordion for all semesters -->
    <div class="accordion mt-4" id="modulesAccordion">

        <!-- Card for Semestre 1 -->
        <div class="card">
            <div class="card-header" id="headingS1">
                <h5 class="mb-0">
                    <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseS1" aria-expanded="true" aria-controls="collapseS1">
                        Semestre 1
                    </button>
                </h5>
            </div>
            <div id="collapseS1" class="collapse" aria-labelledby="headingS1" data-parent="#modulesAccordion">
                <div class="card-body">
                    <?php if (empty($modulesS1)): ?>
                        <p class="text-danger">Aucune donnée à afficher</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Groupe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modulesS1 as $module): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($module['mod_name']) ?></td>
                                        <td><?= htmlspecialchars($module['groupe']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Card for Semestre 2 -->
<div class="card">
    <div class="card-header" id="headingS2">
        <h5 class="mb-0">
            <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseS2" aria-expanded="true" aria-controls="collapseS2">
                Semestre 2
            </button>
        </h5>
    </div>
    <div id="collapseS2" class="collapse" aria-labelledby="headingS2" data-parent="#modulesAccordion">
        <div class="card-body">
            <?php if (empty($modulesS2)): ?>
                <p class="text-danger">Aucune donnée à afficher</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Code Module</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modulesS2 as $module): ?>
                            <tr>
                                <td><?= htmlspecialchars($module['mod_name']) ?></td>
                                <td><?= htmlspecialchars($module['cod_elp']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

        <!-- Card for Semestre 3 -->
        <div class="card">
            <div class="card-header" id="headingS3">
                <h5 class="mb-0">
                    <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseS3" aria-expanded="false" aria-controls="collapseS3">
                        Semestre 3
                    </button>
                </h5>
            </div>
            <div id="collapseS3" class="collapse" aria-labelledby="headingS3" data-parent="#modulesAccordion">
                <div class="card-body">
                    <?php if (empty($modulesS3)): ?>
                        <p class="text-danger">Aucune donnée à afficher</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Groupe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modulesS3 as $module): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($module['mod_name']) ?></td>
                                        <td><?= htmlspecialchars($module['groupe']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <!-- Card for Semestre 4 -->
    <div class="card">
    <div class="card-header" id="headingS4">
        <h5 class="mb-0">
            <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseS4" aria-expanded="true" aria-controls="collapseS4">
                Semestre 4
            </button>
        </h5>
    </div>
    <div id="collapseS4" class="collapse " aria-labelledby="headingS4" data-parent="#modulesAccordion">
        <div class="card-body">
            <?php if (empty($modulesS4)): ?>
                <p class="text-danger">Aucune donnée à afficher</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Code Module</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modulesS4 as $module): ?>
                            <tr>
                                <td><?= htmlspecialchars($module['mod_name']) ?></td>
                                <td><?= htmlspecialchars($module['cod_elp']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
        <!-- Card for Semestre 5 -->
        <div class="card">
            <div class="card-header" id="headingS5">
                <h5 class="mb-0">
                    <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseS5" aria-expanded="false" aria-controls="collapseS5">
                        Semestre 5
                    </button>
                </h5>
            </div>
            <div id="collapseS5" class="collapse" aria-labelledby="headingS5" data-parent="#modulesAccordion">
                <div class="card-body">
                    <?php if (empty($modulesS5)): ?>
                        <p class="text-danger">Aucune donnée à afficher</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Groupe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modulesS5 as $module): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($module['mod_name']) ?></td>
                                        <td><?= htmlspecialchars($module['groupe']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
     
        
          <!-- Card for Semestre 6 -->
    <div class="card">
    <div class="card-header" id="headingS6">
        <h5 class="mb-0">
            <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseS6" aria-expanded="true" aria-controls="collapseS6">
                Semestre 6
            </button>
        </h5>
    </div>
    <div id="collapseS6" class="collapse " aria-labelledby="headingS6" data-parent="#modulesAccordion">
        <div class="card-body">
            <?php if (empty($modulesS6)): ?>
                <p class="text-danger">Aucune donnée à afficher</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Code Module</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modulesS6 as $module): ?>
                            <tr>
                                <td><?= htmlspecialchars($module['mod_name']) ?></td>
                                <td><?= htmlspecialchars($module['cod_elp']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
    <!-- Recherche Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseRecherche">
                    مشروع نهاية السنة
                    </button>
                </h5>
            </div>
            <div id="collapseRecherche" class="collapse" data-parent="#modulesAccordion">
                <div class="card-body">
                    <?php if (empty($rechercheInfo)): ?>
                        <p class="text-danger">Aucune donnée à afficher</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr><th>Filière</th><th>Professeur Assigné</th><th>Groupe</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rechercheInfo as $rech): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($rech['Filière']) ?></td>
                                        <td><?= htmlspecialchars($rech['Assigned_Prof']) ?></td>
                                        <td><?= htmlspecialchars($rech['Groupe']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    
    </div>

    <div class="text-center mt-3">
        <a href="dashboard.php" class="btn btn-primary">Retour au tableau de bord</a>
    </div>
</div>

<!-- Include jQuery and Bootstrap JS Bundle -->
<script src="js/jquery.min.js"></script> <!-- jQuery -->
<script src="bootstrap/js/bootstrap.bundle.min.js"></script> <!-- Bootstrap JS -->
</body>
</html>

