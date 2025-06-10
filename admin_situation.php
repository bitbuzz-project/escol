<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];
$apogee = $student['apoL_a01_code']; // Get the apogee code from session

require 'db.php'; // Include database connection

// Fetch all administrative data (fillière) for the connected student's apogee
$query = "SELECT filliere FROM administative WHERE apogee = ?";
$stmt = $conn->prepare($query);

// Check if the statement was prepared correctly
if ($stmt === false) {
    die('Error preparing statement: ' . $conn->error);
}

$stmt->bind_param("s", $apogee);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all fillieres into an array
$fillieres = [];
while ($row = $result->fetch_assoc()) {
    $fillieres[] = $row['filliere'];
}

// Close the prepared statement
$stmt->close();

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Situation Administrative</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">Situation Administrative</h1>
    <h3 class="text-center">Pour : <?= htmlspecialchars($student['apoL_a03_prenom']) . " " . htmlspecialchars($student['apoL_a02_nom']) ?></h3>

    <center><span>Session d'Automne Ordinaire 2024/2025</span></center>

    <!-- Display Filières Information -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title">Filière(s)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($fillieres)): ?>
                <p class="text-danger">Aucune situation administrative trouvée pour cet étudiant.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($fillieres as $filliere): ?>
                        <li><strong>Filière :</strong> <?= htmlspecialchars($filliere) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-center mt-3">
        <a href="dashboard.php" class="btn btn-primary">Retour au tableau de bord</a>
    </div>
</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
