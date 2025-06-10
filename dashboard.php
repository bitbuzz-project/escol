<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];
$page_title = "Tableau de bord";

include 'header.php';
?>

<!-- Dashboard Content -->
<div class="row mt-4">
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

<?php include 'footer.php'; ?>
