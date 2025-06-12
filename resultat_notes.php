<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];
$page_title = "Mes Notes - Session Normale";

// Allow admin to access student interface
$allow_admin_access = true;

require 'db.php';

// Display success/error messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['success_message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['error_message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    unset($_SESSION['error_message']);
}

// Fetch notes for the logged-in student from notes table
$query = "
    SELECT
        n.nom_module,
        n.note,
        n.validite,
        n.code_module,
        n.adding_date,
        ma.nom_module AS arabic_name,
        EXISTS (
            SELECT 1
            FROM reclamations r
            WHERE r.apoL_a01_code = n.apoL_a01_code
              AND r.default_name = n.nom_module
        ) AS reclamation_sent,
        TIMESTAMPDIFF(HOUR, n.adding_date, NOW()) AS hours_since_addition
    FROM notes n
    LEFT JOIN mod_arabe ma ON n.code_module = ma.code_module
    WHERE n.apoL_a01_code = ?
    ORDER BY n.adding_date DESC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("s", $student['apoL_a01_code']);
$stmt->execute();
$result = $stmt->get_result();

$notes = [];
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}
$stmt->close();

// Get statistics
$total_notes = count($notes);
$numeric_notes = array_filter($notes, function($note) {
    return is_numeric($note['note']);
});
$passed_notes = array_filter($numeric_notes, function($note) {
    return (float)$note['note'] >= 10;
});
$failed_notes = array_filter($numeric_notes, function($note) {
    return (float)$note['note'] < 10;
});

$average = 0;
if (!empty($numeric_notes)) {
    $sum = array_sum(array_column($numeric_notes, 'note'));
    $average = round($sum / count($numeric_notes), 2);
}

// Get unique modules for reclamation dropdown
$modules_for_reclamation = array_unique(array_column($notes, 'nom_module'));

// Professors list (same as your other pages)
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
    'ذ.يونسي', 'ذة. افقير', 'ذة. الحافضي', 'ذة.ابا تراب', 'ذة.افقير',
    'ذة.الرطيمات', 'ذة.الصالحي', 'ذة.العلمي', 'ذة.القشتول', 'ذة.بنقاسم',
    'ذة.سميح', 'ذة.فضيل', 'ذة.فلاح', 'ذة.لبنى المصباحي', 'ذة.منال نوحي',
    'ذة.نوري', 'ذة.يحياوي', 'ذة.الرطيمات'
];

$conn->close();

include 'header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i style="font-size: 1.5rem;">📊</i> Mes Notes - Session Normale</h2>
                    <p class="text-muted">Année universitaire 2024-2025</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i style="font-size: 0.9rem;">🏠</i> Tableau de bord
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                <div class="card-body text-white">
                    <i style="font-size: 2.5rem;">📊</i>
                    <h3 class="mt-2 mb-1"><?= $total_notes ?></h3>
                    <p class="card-text mb-0">Total Modules</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #28a745, #20c997);">
                <div class="card-body text-white">
                    <i style="font-size: 2.5rem;">✅</i>
                    <h3 class="mt-2 mb-1"><?= count($passed_notes) ?></h3>
                    <p class="card-text mb-0">Modules Réussis</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                <div class="card-body text-white">
                    <i style="font-size: 2.5rem;">❌</i>
                    <h3 class="mt-2 mb-1"><?= count($failed_notes) ?></h3>
                    <p class="card-text mb-0">Modules Échoués</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                <div class="card-body text-white">
                    <i style="font-size: 2.5rem;">📈</i>
                    <h3 class="mt-2 mb-1"><?= $average ?>/20</h3>
                    <p class="card-text mb-0">Moyenne Générale</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i style="font-size: 1.2rem;">📋</i> Mes Notes Détaillées</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($notes)): ?>
                <div class="text-center py-5">
                    <i style="font-size: 3rem; color: #6c757d;">📋</i>
                    <h5 class="mt-3 text-muted">Aucune note disponible</h5>
                    <p class="text-muted">لا توجد نتائج المرجو الإعادة لاحقا</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Module</th>
                                <th>Code</th>
                                <th>Note</th>
                                <th>Validité</th>
                                <th>Date d'ajout</th>


                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notes as $note): ?>
                                <tr class="<?= $note['hours_since_addition'] <= 48 ? 'table-warning' : '' ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($note['arabic_name'] ?? $note['nom_module']) ?></strong>
                                        <?php if ($note['hours_since_addition'] <= 24): ?>
                                            <span class="badge bg-info">Nouveau</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($note['code_module']) ?></small>
                                    </td>
                                    <td>
                                        <?php if (is_numeric($note['note'])): ?>
                                            <span class="badge <?= (float)$note['note'] >= 10 ? 'bg-success' : 'bg-danger' ?> fs-6">
                                                <?= htmlspecialchars($note['note']) ?>/20
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary fs-6">
                                                <?= htmlspecialchars($note['note']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($note['validite'])): ?>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($note['validite']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($note['adding_date'])) ?></small>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progress Chart -->
    <?php if (!empty($notes)): ?>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">📊 Répartition des Notes</h6>
                </div>
                <div class="card-body">
                    <canvas id="notesChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">📈 Progression</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Taux de Réussite</span>
                            <span><?= $total_notes > 0 ? round((count($passed_notes) / count($numeric_notes)) * 100, 1) : 0 ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?= $total_notes > 0 ? (count($passed_notes) / count($numeric_notes)) * 100 : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Modules Évalués</span>
                            <span><?= count($numeric_notes) ?> / <?= $total_notes ?></span>
                        </div>
                    <div class="progress">
    <div class="progress-bar bg-info" style="width: <?= $total_notes > 0 ? (count($numeric_notes) / $total_notes) * 100 : 0 ?>%"></div>
</div>

                    </div>
                    <div class="text-center">
                        <h5 class="text-primary">Moyenne: <?= $average ?>/20</h5>
                        <?php
                        // Determine performance message
                        if ($average >= 16) {
                            $performance_msg = "🏆 Excellent travail!";
                        } elseif ($average >= 14) {
                            $performance_msg = "🌟 Très bien!";
                        } elseif ($average >= 12) {
                            $performance_msg = "👍 Bien!";
                        } elseif ($average >= 10) {
                            $performance_msg = "✅ Passable";
                        } else {
                            $performance_msg = "📚 Besoin d'amélioration";
                        }
                        ?>
                        <p class="text-muted"><?= $performance_msg ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Important Notice -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light border-warning">
                <div class="card-body">
                    <h5 class="card-title text-center text-danger">📢 Notice Importante</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>⏰ Délais de réclamation:</h6>
                            <p class="card-text">
                                <strong>Les réclamations concernant chaque module dont les résultats ont été annoncés sont reçues via la même plateforme dans un délai ne dépassant pas 48 heures.</strong>
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h6>يتم استقبال الشكايات:</h6>
                            <p class="card-text">
                                <strong>يتم استقبال الشكايات الخاصة بكل وحدة تم الاعلان عن نتائجها، وذلك على نفس المنصة في اجال لا يتعدى 48 ساعة</strong>
                            </p>
                        </div>
                    </div>
                    <?php if (!empty($notes)): ?>
                    <div class="text-center mt-3">
                        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#reclamationModal">
                            <i>⚠️</i> Soumettre une Réclamation
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reclamation Modal -->
<div class="modal fade" id="reclamationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إرسال شكوى - Soumettre une Réclamation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="submit_reclamation.php" dir="rtl">
                <div class="modal-body">
                    <input type="hidden" name="apoL_a01_code" value="<?= htmlspecialchars($student['apoL_a01_code']) ?>">
                    <input type="hidden" name="reclamation_type" value="notes">

                    <div class="mb-3">
                        <label for="default_name" class="form-label">اسم الوحدة - Module</label>
                        <select name="default_name" id="moduleSelect" class="form-select" required>
                            <option value="" disabled selected>اختر الوحدة - Choisir le module</option>
                            <?php foreach ($modules_for_reclamation as $module): ?>
                                <option value="<?= htmlspecialchars($module) ?>"><?= htmlspecialchars($module) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="note" class="form-label">نوع المشكلة - Type de problème</label>
                        <select name="note" class="form-select" required>
                            <option value="" disabled selected>اختر نوع المشكلة</option>
                            <option value="zero">Note zéro non justifiée</option>
                            <option value="absent">Marqué absent alors que présent</option>
                            <option value="note_manquante">Note manquante</option>
                            <option value="erreur_calcul">Erreur de calcul</option>
                            <option value="note_incorrecte">Note incorrecte</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="prof" class="form-label">الأستاذ - Professeur</label>
                        <select name="prof" class="form-select">
                            <option value="">اختر الأستاذ - Choisir le professeur</option>
                            <?php foreach ($professors as $professor): ?>
                                <option value="<?= htmlspecialchars($professor) ?>"><?= htmlspecialchars($professor) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="groupe" class="form-label">المجموعة - Groupe</label>
                            <select name="groupe" class="form-select">
                                <option value="">اختر المجموعة</option>
                                <?php for($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="semestre" class="form-label">السداسي - Semestre</label>
                            <select name="semestre" class="form-select">
                                <option value="">اختر السداسي</option>
                                <?php for($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="class" class="form-label">مدرج الامتحان - Salle d'examen</label>
                        <select name="class" class="form-select">
                            <option value="">اختر مدرج الامتحان</option>
                            <?php
                            $amphitheaters = ['Amphi 2', 'Amphi 3', 'Amphi 4', 'Amphi 5', 'Amphi 6', 'Amphi 7', 'Amphi 8', 'Amphi 9', 'Amphi 10', 'Amphi 11', 'Amphi 12', 'Amphi 13', 'Amphi 14', 'Amphi 15', 'Amphi 16', 'Amphi 17', 'Amphi 18', 'Amphi 19', 'BIB'];
                            foreach ($amphitheaters as $amphi): ?>
                                <option value="<?= $amphi ?>"><?= $amphi ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="info" class="form-label">معلومات توضيحية - Informations complémentaires</label>
                        <textarea name="info" class="form-control" rows="3" placeholder="يرجى تقديم التفاصيل الدقيقة حول الشكاية..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">إرسال - Envoyer</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق - Fermer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chart.js for statistics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Initialize notes distribution chart
<?php if (!empty($notes)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('notesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Réussis (≥10)', 'Échoués (<10)', 'Non évalués'],
                datasets: [{
                    data: [<?= count($passed_notes) ?>, <?= count($failed_notes) ?>, <?= $total_notes - count($numeric_notes) ?>],
                    backgroundColor: ['#28a745', '#dc3545', '#6c757d'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
<?php endif; ?>

// Handle reclamation modal pre-fill
document.addEventListener('DOMContentLoaded', function() {
    const reclamationModal = document.getElementById('reclamationModal');
    if (reclamationModal) {
        reclamationModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const module = button ? button.getAttribute('data-module') : null;

            if (module) {
                const moduleSelect = document.getElementById('moduleSelect');
                if (moduleSelect) {
                    moduleSelect.value = module;
                }
            }
        });
    }
});
</script>

<!-- Custom Styles -->
<style>
.card {
    border-radius: 12px !important;
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.badge {
    font-size: 0.75rem;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
    border-left: 4px solid #ffc107;
}

.progress {
    height: 8px;
    border-radius: 4px;
}

@media print {
    .btn, .modal, nav, .sidebar {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        margin-bottom: 20px !important;
    }
}

/* Animation for new records */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.table-warning {
    animation: slideIn 0.5s ease;
}
</style>

<?php include 'footer.php'; ?>
