<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $apoL_a01_code = $student['apoL_a01_code'];
    $default_name = $_POST['default_name'];
    $note = $_POST['note'];
    $prof = $_POST['prof'];
    $semestre = $_POST['semestre'];
    $class = $_POST['class'];
    $groupe = $_POST['groupe'];
    $info = $_POST['info'];

    // Check if the student has already made a reclamation for this module
    $queryCheck = "SELECT COUNT(*) FROM reclamations WHERE apoL_a01_code = ? AND default_name = ?";
    $stmtCheck = $conn->prepare($queryCheck);
    $stmtCheck->bind_param("ss", $apoL_a01_code, $default_name);
    $stmtCheck->execute();
    $stmtCheck->bind_result($count);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($count > 0) {
        $_SESSION['error_message'] = 'Vous avez déjà soumis une réclamation pour ce module!';
        header("Location: resultat_ratt.php");
        exit();
    }

    // Insert new reclamation
    $query = "INSERT INTO reclamations (apoL_a01_code, default_name, note, prof, groupe, class, info, Semestre)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("ssssssss", $apoL_a01_code, $default_name, $note, $prof, $groupe, $class, $info, $semestre);
    

        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Réclamation envoyée avec succès!';
        } else {
            $_SESSION['error_message'] = 'Erreur lors de l\'envoi de votre réclamation.';
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = 'Erreur de préparation de la requête.';
    }

    header("Location: resultat_ratt.php");
    exit();
} else {
    $_SESSION['error_message'] = 'Requête invalide.';
    header("Location: resultat_ratt.php");
    exit();
}
?>
