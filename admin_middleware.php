<?php
/**
 * Admin Middleware - Check if user has admin access
 * Include this file at the top of admin pages to protect them
 */

session_start();

function checkAdminAccess() {
    // Check if user is logged in
    if (!isset($_SESSION['student'])) {
        $_SESSION['error_message'] = 'Vous devez être connecté pour accéder à cette page.';
        header("Location: login.php");
        exit();
    }

    // Check if user is admin (apogee 16005333)
    if ($_SESSION['student']['apoL_a01_code'] !== '16005333') {
        $_SESSION['error_message'] = 'Accès refusé. Cette section est réservée aux administrateurs.';
        header("Location: dashboard.php");
        exit();
    }

    return $_SESSION['student'];
}

// Auto-check admin access when this file is included
$admin = checkAdminAccess();
?>
