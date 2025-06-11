<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];

// Include the database connection
require_once 'db.php';

// Get selected session or default to 'automne'
$selected_session = isset($_GET['session']) ? $_GET['session'] : 'automne';

// Get selected result type or default to 'normal'
$selected_result_type = isset($_GET['result_type']) ? $_GET['result_type'] : 'normal';

// Validate session
if (!in_array($selected_session, ['automne', 'printemps'])) {
    $selected_session = 'automne';
}

// Validate result type
if (!in_array($selected_result_type, ['normal', 'rattrapage'])) {
    $selected_result_type = 'normal';
}

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

// Fetch notes for the logged-in student for selected session and result type
// NOUVEAU CODE (corrigé)
$query = "
    SELECT
        n.nom_module AS default_name,
        n.note,
        n.session,
        n.result_type,
        ma.nom_module AS arabic_name,
        n.adding_date,
        EXISTS (
            SELECT 1
            FROM reclamations r
            WHERE r.apoL_a01_code = n.apoL_a01_code
              AND r.default_name = n.nom_module
        ) AS reclamation_sent,
        TIMESTAMPDIFF(HOUR, n.adding_date, NOW()) AS hours_since_addition
    FROM notes_print n
    LEFT JOIN mod_arabe ma ON n.code_module = ma.code_module
    WHERE n.apoL_a01_code = ? AND n.session = ? AND n.result_type = ?
    ORDER BY n.adding_date DESC
";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("sss", $student['apoL_a01_code'], $selected_session, $selected_result_type);
$stmt->execute();
$result = $stmt->get_result();

// Fetch notes as an array
$notes = [];
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}

$stmt->close();

// Get available sessions and result types for this student
$availability_query = "
    SELECT DISTINCT session, result_type
    FROM notes_print
    WHERE apoL_a01_code = ?
    ORDER BY session, result_type
";
$availability_stmt = $conn->prepare($availability_query);
$availability_stmt->bind_param("s", $student['apoL_a01_code']);
$availability_stmt->execute();
$availability_result = $availability_stmt->get_result();

$available_combinations = [];
$available_sessions = [];
$available_result_types = [];

while ($row = $availability_result->fetch_assoc()) {
    $available_combinations[] = $row;
    if (!in_array($row['session'], $available_sessions)) {
        $available_sessions[] = $row['session'];
    }
    if (!in_array($row['result_type'], $available_result_types)) {
        $available_result_types[] = $row['result_type'];
    }
}
$availability_stmt->close();

// Get result types available for current session
$current_session_result_types = [];
foreach ($available_combinations as $combo) {
    if ($combo['session'] === $selected_session) {
        $current_session_result_types[] = $combo['result_type'];
    }
}

// Get unique modules for reclamation dropdown
$modules_query = "SELECT DISTINCT nom_module FROM notes_print WHERE apoL_a01_code = ? AND session = ? AND result_type = ? ORDER BY nom_module";
$modules_stmt = $conn->prepare($modules_query);
$modules_stmt->bind_param("sss", $student['apoL_a01_code'], $selected_session, $selected_result_type);
$modules_stmt->execute();
$modules_result = $modules_stmt->get_result();

$modules = [];
while ($row = $modules_result->fetch_assoc()) {
    $modules[] = $row['nom_module'];
}
$modules_stmt->close();

$professors = [
    'pr.ait laaguid', 'pr.aloui', 'pr.badr dahbi', 'pr.belbesbes', 'pr.belkadi',
    'pr.benbounou', 'pr.benmansour', 'pr.boudiab', 'pr.bouhmidi', 'pr.bouzekraoui',
    'pr.brouksy', 'pr.echcharyf', 'pr.el idrissi', 'pr.es-sehab', 'pr.karim',
    'pr.maatouk', 'pr.majidi', 'Pr.meftah', 'pr.moussadek', 'pr.ouakasse',
    'pr.oualji', 'pr.qorchi', 'pr.rafik', 'pr.setta', 'ذ,جفري', 'ذ. الشداوي',
    'ذ. العمراني', 'ذ. أوهاروش', 'ذ. رحو', 'ذ. عباد', 'ذ. قصبي', 'ذ. نعناني',
    'ذ.إ.الحافظي', 'ذ.البوشيخي', 'ذ.البوهالي', 'ذ.الحجاجي', 'ذ.الذهبي',
    'ذ.الرقاي', 'ذ.السكتاني', 'ذ.السيتر', 'ذ.الشداوي', 'ذ.الشرغاوي',
    'ذ.الشيكر', 'ذ.الصابونجي', 'ذ.الطيبي', 'ذ.العاشيري', 'ذ.القاسمي',
    'ذ.المصبحي', 'ذ.المليحي', 'ذ.النوحي', 'ذ.بنقاسم', 'ذ.بوذياب',
    'ذ.حسون', 'ذ.حميدا', 'ذ.خربوش', 'ذ.خلوقي', 'ذ.رحو', 'ذ.شحشي',
    'ذ.طالب', 'ذ.عباد', 'ذ.عراش', 'ذ.قصبي', 'ذ.قيبال', 'ذ.كموني',
    'ذ.كواعروس', 'ذ.مكاوي', 'ذ.ملوكي', 'ذ.مهم', 'ذ.نعناني', 'ذ.هروال',
    'ذ.يونسي', 'ذ.الرقاي', 'ذة. افقير', 'ذة. الحافضي', 'ذة.ابا تراب',
    'ذة.افقير', 'ذة.الرطيمات', 'ذة.الصالحي', 'ذة.العلمي', 'ذة.القشتول',
    'ذة.بنقاسم', 'ذة.سميح', 'ذة.فضيل', 'ذة.فلاح', 'ذة.لبنى المصباحي',
    'ذة.منال نوحي', 'ذة.نوري', 'ذة.يحياوي', 'ذة.الرطيمات'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat Print</title>
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
            font-size: 0.8em;
            display: block;
            opacity: 0.8;
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
        .session-selector {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .result-type-selector {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .session-btn, .result-type-btn {
            margin: 0.25rem;
            border-radius: 20px;
            padding: 0.5rem 1.5rem;
            border: 2px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .session-btn:hover, .result-type-btn:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateY(-2px);
        }
        .session-btn.active, .result-type-btn.active {
            background: rgba(255,255,255,0.9);
            color: #667eea;
            font-weight: bold;
        }
        .result-type-btn.active {
            color: #f5576c;
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
                <span class="navbar-brand">Résultats Print</span>
            </div>
        </nav>

        <!-- Session Selector -->
        <div class="session-selector">
            <div class="text-center">
                <h4 class="mb-3">
                    <i style="font-size: 1.5rem;">📅</i>
                    Choisir la Session
                </h4>
                <p class="mb-3">Sélectionnez la session pour voir vos résultats</p>

                <div class="d-flex justify-content-center flex-wrap">
                    <?php if (in_array('automne', $available_sessions)): ?>
                        <a href="?session=automne&result_type=<?= $selected_result_type ?>" class="session-btn <?= $selected_session === 'automne' ? 'active' : '' ?>">
                            🍂 Session d'Automne
                        </a>
                    <?php endif; ?>

                    <?php if (in_array('printemps', $available_sessions)): ?>
                        <a href="?session=printemps&result_type=<?= $selected_result_type ?>" class="session-btn <?= $selected_session === 'printemps' ? 'active' : '' ?>">
                            🌸 Session de Printemps
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($available_sessions)): ?>
                    <p class="text-center mt-3">
                        <small class="opacity-75">Aucune session disponible pour le moment</small>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Result Type Selector -->
        <?php if (!empty($current_session_result_types)): ?>
        <div class="result-type-selector">
            <div class="text-center">
                <h4 class="mb-3">
                    <i style="font-size: 1.5rem;">📊</i>
                    Type de Résultat
                </h4>
                <p class="mb-3">Choisissez entre session normale et rattrapage</p>

                <div class="d-flex justify-content-center flex-wrap">
                    <?php if (in_array('normal', $current_session_result_types)): ?>
                        <a href="?session=<?= $selected_session ?>&result_type=normal" class="result-type-btn <?= $selected_result_type === 'normal' ? 'active' : '' ?>">
                            📝 Session Normale
                        </a>
                    <?php endif; ?>

                    <?php if (in_array('rattrapage', $current_session_result_types)): ?>
                        <a href="?session=<?= $selected_session ?>&result_type=rattrapage" class="result-type-btn <?= $selected_result_type === 'rattrapage' ? 'active' : '' ?>">
                            🔄 Session de Rattrapage
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Results Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mt-4">
                    Notes Session de <?= $selected_session === 'printemps' ? 'Printemps' : 'Automne' ?> -
                    <?php if ($selected_result_type === 'rattrapage'): ?>
                        <span class="badge bg-warning text-dark">Rattrapage</span>
                    <?php else: ?>
                        <span class="badge bg-primary">Normale</span>
                    <?php endif; ?>
                </h2>
                <b>2024-2025</b>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Options
                </button>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">Sessions disponibles</h6></li>
                    <?php foreach ($available_sessions as $session): ?>
                        <li>
                            <a class="dropdown-item <?= $session === $selected_session ? 'active' : '' ?>"
                               href="?session=<?= $session ?>&result_type=<?= $selected_result_type ?>">
                                <?= $session === 'printemps' ? '🌸 Printemps' : '🍂 Automne' ?>
                            </a>
                        </li>
                    <?php endforeach; ?>

                    <?php if (!empty($current_session_result_types) && count($current_session_result_types) > 1): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Types de résultat</h6></li>
                    <?php foreach ($current_session_result_types as $type): ?>
                        <li>
                            <a class="dropdown-item <?= $type === $selected_result_type ? 'active' : '' ?>"
                               href="?session=<?= $selected_session ?>&result_type=<?= $type ?>">
                                <?= $type === 'rattrapage' ? '🔄 Rattrapage' : '📝 Normale' ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Notes Table -->
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Nom du Module</th>
                    <th>Note</th>
                    <th>Type</th>
                    <th>Date d'ajout</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($notes)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            <div class="py-4">
                                <i style="font-size: 3rem;">📋</i>
                                <h5 class="mt-3">لا توجد نتائج المرجو الإعادة لاحقا</h5>
                                <p>Aucun résultat disponible pour la session de <?= $selected_session === 'printemps' ? 'Printemps' : 'Automne' ?>
                                (<?= $selected_result_type === 'rattrapage' ? 'Rattrapage' : 'Normale' ?>)</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($notes as $index => $note): ?>
                        <tr>
                            <td><?= htmlspecialchars($note['arabic_name'] ?? $note['default_name']) ?></td>
                            <td>
                                <span class="badge <?= is_numeric($note['note']) && $note['note'] >= 10 ? 'bg-success' : 'bg-danger' ?> fs-6">
                                    <?= htmlspecialchars($note['note']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($note['result_type'] === 'rattrapage'): ?>
                                    <span class="badge bg-warning text-dark">Rattrapage</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Normale</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= htmlspecialchars(date('d/m/Y H:i', strtotime($note['adding_date']))) ?></small>
                                <?php if ($note['hours_since_addition'] <= 48): ?>
                                    <span class="badge bg-info">Nouveau</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($note['reclamation_sent']): ?>
                                    <span class="badge bg-warning">Réclamation envoyée</span>
                                <?php elseif ($note['hours_since_addition'] <= 48): ?>
                                    <button class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#reclamationModal"
                                            data-module="<?= htmlspecialchars($note['default_name']) ?>">
                                        Réclamer
                                    </button>
                                <?php else: ?>
                                    <small class="text-muted">Délai expiré</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Reclamation Modal -->
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
                            <input type="hidden" name="session_type" value="<?= htmlspecialchars($selected_session) ?>">
                            <input type="hidden" name="result_type" value="<?= htmlspecialchars($selected_result_type) ?>">

                            <div class="mb-3">
                                <label for="default_name" class="form-label">اسم الوحدة</label>
                                <select name="default_name" id="moduleSelect" class="form-select" required>
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
                                    <?php for($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
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
                                    $amphitheaters = [
                                        'Amphi 2', 'Amphi 3', 'Amphi 4', 'Amphi 5', 'Amphi 6', 'Amphi 7',
                                        'Amphi 8', 'Amphi 9', 'Amphi 10', 'Amphi 11', 'Amphi 12', 'Amphi 13',
                                        'Amphi 14', 'Amphi 15', 'Amphi 16', 'Amphi 17', 'Amphi 18', 'Amphi 19', 'BIB'
                                    ];
                                    foreach ($amphitheaters as $amphi) {
                                        echo "<option value=\"{$amphi}\">{$amphi}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="info" class="form-label">معلومات توضيحية (إختياري)</label>
                                <textarea class="form-control text-end" name="info" rows="4"></textarea>
                                <small class="form-text text-muted">يرجى تقديم التفاصيل الدقيقة حول الشكاية لضمان معالجتها بشكل سريع.</small>
                            </div>

                            <!-- Display current result type context -->
                            <div class="alert alert-info" role="alert">
                                <small>
                                    <strong>السياق:</strong>
                                    الشكوى خاصة بنتائج <?= $selected_result_type === 'rattrapage' ? 'الاستدراك' : 'الدورة العادية' ?>
                                    لدورة <?= $selected_session === 'printemps' ? 'الربيع' : 'الخريف' ?>
                                </small>
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

        <!-- Information Notice -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card bg-light border-secondary">
                    <div class="card-body">
                        <?php if (!empty($notes)): ?>
                        <div class="text-center mt-3">
                            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#reclamationModal">
                                Reclamer
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Result Type Specific Notice -->
                        <?php if ($selected_result_type === 'rattrapage'): ?>
                        <div class="alert alert-warning mb-3" role="alert">
                            <h6 class="alert-heading">⚠️ نتائج الاستدراك</h6>
                            <p class="mb-0">
                                هذه نتائج دورة الاستدراك. تأكد من مراجعة النتائج بعناية قبل تقديم أي شكوى.
                            </p>
                        </div>
                        <?php endif; ?>

                        <h5 class="card-title text-center text-danger">Notification importante</h5>
                        <p class="card-text text-center">
                            <strong>
                                Les réclamations concernant chaque module dont les résultats ont été annoncés sont reçues via la même plateforme dans un délai ne dépassant pas 48 heures.
                            </strong>
                        </p>
                        <p class="card-text text-center">
                            <strong>
                                يتم استقبال الشكايات الخاصة بكل وحدة تم الاعلان عن نتائجها، وذلك على نفس المنصة في اجال لا يتعدى 48 ساعة
                            </strong>
                        </p>

                        <!-- Additional info for rattrapage -->
                        <?php if ($selected_result_type === 'rattrapage'): ?>
                        <div class="alert alert-info mt-3" role="alert">
                            <small>
                                <strong>ملاحظة خاصة بالاستدراك:</strong>
                                نتائج دورة الاستدراك نهائية ولا يمكن تعديلها إلا في حالات استثنائية محددة.
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Card -->
        <?php if (!empty($notes)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">📊 إحصائيات النتائج</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-primary"><?= count($notes) ?></h4>
                                    <small>إجمالي المواد</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <?php
                                    $passed = array_filter($notes, function($note) {
                                        return is_numeric($note['note']) && $note['note'] >= 10;
                                    });
                                    ?>
                                    <h4 class="text-success"><?= count($passed) ?></h4>
                                    <small>مواد ناجحة</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <?php
                                    $failed = array_filter($notes, function($note) {
                                        return is_numeric($note['note']) && $note['note'] < 10;
                                    });
                                    ?>
                                    <h4 class="text-danger"><?= count($failed) ?></h4>
                                    <small>مواد راسبة</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <?php
                                $numeric_notes = array_filter($notes, function($note) {
                                    return is_numeric($note['note']);
                                });
                                $average = !empty($numeric_notes) ?
                                    round(array_sum(array_column($numeric_notes, 'note')) / count($numeric_notes), 2) : 0;
                                ?>
                                <h4 class="text-info"><?= $average ?></h4>
                                <small>المعدل</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('toggleSidebar');
    const closeButton = document.getElementById('closeSidebar');

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            sidebar.classList.add('show');
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', () => {
            sidebar.classList.remove('show');
        });
    }

    // Handle reclamation modal pre-fill
    document.addEventListener('DOMContentLoaded', function() {
        const reclamationModal = document.getElementById('reclamationModal');
        if (reclamationModal) {
            reclamationModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const module = button.getAttribute('data-module');

                if (module) {
                    const moduleSelect = document.getElementById('moduleSelect');
                    if (moduleSelect) {
                        moduleSelect.value = module;
                    }
                }
            });
        }
    });

    // Add URL parameter tracking for better UX
    function updateUrlParameters(session, resultType) {
        const url = new URL(window.location);
        url.searchParams.set('session', session);
        url.searchParams.set('result_type', resultType);
        window.history.pushState({}, '', url);
    }

    // Show loading state for result type changes
    document.querySelectorAll('.result-type-btn, .session-btn').forEach(link => {
        link.addEventListener('click', function(e) {
            const spinner = document.createElement('div');
            spinner.className = 'spinner-border spinner-border-sm me-2';
            this.prepend(spinner);
            this.style.opacity = '0.7';
        });
    });
</script>
</body>
</html>
