<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
if (isset($_SESSION['success_message'])) {
    echo '
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($_SESSION['success_message']) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    unset($_SESSION['success_message']);
}
?>

<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];

// Include the database connection
require_once 'db.php';


// Display success message
if (isset($_SESSION['success_message'])) {
    echo '
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($_SESSION['success_message']) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    unset($_SESSION['success_message']);
}

// Display error message
if (isset($_SESSION['error_message'])) {
    echo '
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($_SESSION['error_message']) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    unset($_SESSION['error_message']);
}



// Fetch notes for the logged-in student
// Fetch notes for the logged-in student
$query = "
    SELECT 
        n.nom_module AS default_name,
        n.note,
        ma.nom_module AS arabic_name,
        EXISTS (
            SELECT 1
            FROM reclamations r
            WHERE r.apoL_a01_code = n.apoL_a01_code
              AND r.default_name = n.nom_module
        ) AS reclamation_sent
    FROM notes_ratt n
    LEFT JOIN mod_arabe ma ON n.code_module = ma.code_module
    WHERE n.apoL_a01_code = ?
";

if (!isset($student['apoL_a01_code'])) {
    die("Error: 'apoL_a01_code' is not set.");
}

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("s", $student['apoL_a01_code']); // Bind the parameter (assuming it's a string)
$stmt->execute();
$result = $stmt->get_result();

// Fetch notes as an array
$notes = [];
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}

$stmt->close();


$professors = [
    'pr.ait laaguid', 
    'pr.aloui', 
    'pr.badr dahbi', 
    'pr.belbesbes', 
    'pr.belkadi', 
    'pr.benbounou', 
    'pr.benmansour', 
    'pr.boudiab', 
    'pr.bouhmidi', 
    'pr.bouzekraoui', 
    'pr.brouksy', 
    'pr.echcharyf', 
    'pr.el idrissi', 
    'pr.es-sehab', 
    'pr.karim', 
    'pr.maatouk', 
    'pr.majidi', 
    'Pr.meftah', 
    'pr.moussadek', 
    'pr.ouakasse', 
    'pr.oualji', 
    'pr.qorchi', 
    'pr.rafik', 
    'pr.setta', 
    'ذ,جفري', 
    'ذ. الشداوي', 
    'ذ. العمراني', 
    'ذ. أوهاروش', 
    'ذ. رحو', 
    'ذ. عباد', 
    'ذ. قصبي', 
    'ذ. نعناني', 
    'ذ.إ.الحافظي', 
    'ذ.البوشيخي', 
    'ذ.البوهالي', 
    'ذ.الحجاجي', 
    'ذ.الذهبي', 
    'ذ.الرقاي', 
    'ذ.السكتاني', 
    'ذ.السيتر', 
    'ذ.الشداوي', 
    'ذ.الشرغاوي', 
    'ذ.الشيكر', 
    'ذ.الصابونجي', 
    'ذ.الطيبي', 
    'ذ.العاشيري', 
    'ذ.القاسمي', 
    'ذ.المصبحي', 
    'ذ.المليحي', 
    'ذ.النوحي', 
    'ذ.بنقاسم', 
    'ذ.بوذياب', 
    'ذ.حسون', 
    'ذ.حميدا', 
    'ذ.خربوش', 
    'ذ.خلوقي', 
    'ذ.رحو', 
    'ذ.شحشي', 
    'ذ.طالب', 
    'ذ.عباد', 
    'ذ.عراش', 
    'ذ.قصبي', 
    'ذ.قيبال', 
    'ذ.كموني', 
    'ذ.كواعروس', 
    'ذ.مكاوي', 
    'ذ.ملوكي', 
    'ذ.مهم', 
    'ذ.نعناني', 
    'ذ.هروال', 
    'ذ.يونسي', 
    'ذ.الرقاي', 
    'ذة. افقير', 
    'ذة. الحافضي', 
    'ذة.ابا تراب', 
    'ذة.افقير', 
    'ذة.الرطيمات', 
    'ذة.الصالحي', 
    'ذة.العلمي', 
    'ذة.القشتول', 
    'ذة.بنقاسم', 
    'ذة.سميح', 
    'ذة.فضيل', 
    'ذة.فلاح', 
    'ذة.لبنى المصباحي', 
    'ذة.منال نوحي', 
    'ذة.نوري', 
    'ذة.يحياوي', 
    'ذة.الرطيمات'
];

$modules = [


];

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
        .sub-text {
    font-size: 0.8em;  /* Makes the text smaller */
    display: block;  /* Places it on a new line */
    opacity: 0.8;  /* Slightly faded for better styling */
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
        <h2 class="mt-4">Notes Session d'automne - Rattrapage</h2>
        <b>2024-2025</b>
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Nom du Module</th>
                    <th>Note</th>
                   
          
                </tr>
            </thead>
            <tbody>
            <tbody>
<?php if (empty($notes)): ?>
    <tr>
        <td colspan="4" class="text-center text-muted">لا توجد نتائج المرجو الإعادة لاحقا</td>
    </tr>
<?php else: ?>
    <?php foreach ($notes as $index => $note): ?>
        <tr>
            <td><?= htmlspecialchars($note['arabic_name'] ?? $note['default_name']) ?></td>
            <td><?= htmlspecialchars($note['note']) ?></td>
   
        </tr>
        
        <!-- Modal for reclamation -->
      <!-- Modal for Reclamation -->

        </div>
    <?php endforeach; ?>
<?php endif; ?>
</tbody>
>

</tbody>

        </table>
              
 
        <div class="modal fade" id="reclamationModal" tabindex="-1" aria-labelledby="reclamationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reclamationModalLabel">إرسال شكوى</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="submit_reclamation.php" dir="rtl">
                <div class="modal-body">
                    <input type="hidden" name="apoL_a01_code" value="<?= htmlspecialchars($student['apoL_a01_code']) ?>">

                    <div class="mb-3">
                        <label for="default_name" class="form-label">اسم الوحدة</label>
                        <select name="default_name" class="form-select" required>
                            <option value="" disabled selected>اختر الوحدة</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?= htmlspecialchars($module) ?>"><?= htmlspecialchars($module) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="note" class="form-label">نوع المشكلة</label>
                        <select name="note" class="form-select" required>
                            <option value="" disabled selected>اختر نوع المشكلة</option>
                            <option value="zero">Zero</option>
                            <option value="absent">Absent</option>
                            <option value="other">لم اجد النتيجة</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="prof" class="form-label">الأستاذ</label>
                        <select name="prof" class="form-select" required>
                            <option value="" disabled selected>اختر الأستاذ</option>
                            <?php foreach ($professors as $professor): ?>
                                <option value="<?= htmlspecialchars($professor) ?>"><?= htmlspecialchars($professor) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
    <label for="groupe" class="form-label">المجموعة</label>
    <select class="form-control text-end" name="groupe" required>
        <option value="" disabled selected>اختر المجموعة</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
        <option value="5">5</option>
        <option value="6">6</option>
        <option value="7">7</option>
        <option value="8">8</option>
    </select>
</div>

<div class="mb-3">
    <label for="semestre" class="form-label">السداسي</label>
    <select class="form-control text-end" name="semestre" required>
        <option value="" disabled selected>اختر السداسي</option>
        <option value="1">1</option>
        <option value="3">3</option>
    </select>
</div>

                    <div class="mb-3">
                        <label for="class" class="form-label">مدرج الامتحان</label>
                        <select name="class" class="form-select" required>
                            <option value="" disabled selected>اختر مدرج الامتحان</option>
                            <?php
                            $groups = ['Amphi 2', 
            'Amphi 3', 
            'Amphi 4', 
            'Amphi 5', 
            'Amphi 6', 
            'Amphi 7', 
            'Amphi 8', 
            'Amphi 9', 
            'Amphi 10', 
            'Amphi 11', 
            'Amphi 12', 
            'Amphi 13', 
            'Amphi 14', 
            'Amphi 15', 
            'Amphi 16', 
            'Amphi 17', 
            'Amphi 18', 
            'Amphi 19',
            'BIB'];
                            foreach ($groups as $group) {
                                echo "<option value=\"{$group}\">{$group}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="info" class="form-label">معلومات توضيحية (إختياري)</label>
                        <textarea class="form-control text-end" name="info" rows="4"></textarea>
                        <small class="form-text text-muted">يرجى تقديم التفاصيل الدقيقة حول الشكاية لضمان معالجتها بشكل سريع.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">إرسال</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </form>
        </div>
    </div>
</div>
         
        <div class="row mt-4">
    <div class="col-12">
        <div class="card bg-light border-secondary">
            <div class="card-body">
            <div class="text-center mt-3">
    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#reclamationModal">
        Reclamer
    </button>
</div>
                <h5 class="card-title text-center text-danger">Notification importante</h5>
               
                <p class="card-text text-center">
                <strong>
                Les réclamations concernant chaque module dont les résultats ont été annoncés sont reçues via la même plateforme dans un délai ne dépassant pas 48 heures.                </p>
                <p class="card-text text-center">
                <strong>
                يتم استقبال الشكايات الخاصة بكل وحدة تم الاعلان عن نتائجها، وذلك على نفس المنصة في اجال لا يتعدى 48 ساعة</strong>
                </p>

                
            </div>
        </div>
    </div>
</div>
    </div>
</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>
