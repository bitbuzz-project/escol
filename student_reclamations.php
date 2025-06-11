<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];
$page_title = "Mes Réclamations";

// Allow admin to access student interface
$allow_admin_access = true;

require 'db.php';

// Get student's reclamations
$apogee = $student['apoL_a01_code'];

// Fetch reclamations with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$query = "
    SELECT
        r.*,
        CASE
            WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1
            ELSE 0
        END as is_recent
    FROM reclamations r
    WHERE r.apoL_a01_code = ?
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param('sii', $apogee, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$reclamations = [];
while ($row = $result->fetch_assoc()) {
    $reclamations[] = $row;
}
$stmt->close();

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM reclamations WHERE apoL_a01_code = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param('s', $apogee);
$count_stmt->execute();
$total_reclamations = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_reclamations / $limit);
$count_stmt->close();

// Get statistics
$stats_query = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM reclamations
    WHERE apoL_a01_code = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param('s', $apogee);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Get available modules for notes reclamations
$modules_query = "SELECT DISTINCT nom_module FROM notes_print WHERE apoL_a01_code = ? ORDER BY nom_module";
$modules_stmt = $conn->prepare($modules_query);
$modules_stmt->bind_param('s', $apogee);
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
    'ذ.يونسي', 'ذة. افقير', 'ذة. الحافضي', 'ذة.ابا تراب', 'ذة.افقير',
    'ذة.الرطيمات', 'ذة.الصالحي', 'ذة.العلمي', 'ذة.القشتول', 'ذة.بنقاسم',
    'ذة.سميح', 'ذة.فضيل', 'ذة.فلاح', 'ذة.لبنى المصباحي', 'ذة.منال نوحي',
    'ذة.نوري', 'ذة.يحياوي', 'ذة.الرطيمات'
];

$conn->close();

include 'header.php';
?>

<div class="container-fluid">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i style="font-size: 1.5rem;">⚠️</i> Mes Réclamations</h2>
                    <p class="text-muted">Gérez vos réclamations et demandes</p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReclamationModal">
                        <i style="font-size: 0.9rem;">➕</i> Nouvelle Réclamation
                    </button>
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
                    <h3><?= $stats['total'] ?></h3>
                    <p class="mb-0">Total</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                <div class="card-body text-white">
                    <h3><?= $stats['pending'] ?></h3>
                    <p class="mb-0">En attente</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #007bff, #0056b3);">
                <div class="card-body text-white">
                    <h3><?= $stats['in_progress'] ?></h3>
                    <p class="mb-0">En cours</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #28a745, #20c997);">
                <div class="card-body text-white">
                    <h3><?= $stats['resolved'] ?></h3>
                    <p class="mb-0">Résolues</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Reclamations List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i style="font-size: 1.2rem;">📋</i> Mes Réclamations</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($reclamations)): ?>
                <div class="text-center py-5">
                    <i style="font-size: 3rem; color: #6c757d;">📋</i>
                    <h5 class="mt-3 text-muted">Aucune réclamation</h5>
                    <p class="text-muted">Vous n'avez pas encore soumis de réclamation</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReclamationModal">
                        Créer ma première réclamation
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Type</th>
                                <th>Objet/Module</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reclamations as $reclamation): ?>
                                <tr class="<?= $reclamation['is_recent'] ? 'table-warning' : '' ?>">
                                    <td>
                                        <?php
                                        $type_badges = [
                                            'notes' => 'bg-primary',
                                            'correction' => 'bg-warning',
                                            'autre' => 'bg-info'
                                        ];
                                        $type_labels = [
                                            'notes' => 'Notes',
                                            'correction' => 'Correction',
                                            'autre' => 'Autre'
                                        ];
                                        $type = $reclamation['reclamation_type'] ?? 'notes';
                                        ?>
                                        <span class="badge <?= $type_badges[$type] ?? 'bg-secondary' ?>">
                                            <?= $type_labels[$type] ?? 'Non défini' ?>
                                        </span>
                                        <?php if ($reclamation['is_recent']): ?>
                                            <span class="badge bg-success">Nouveau</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($reclamation['default_name']) ?></strong>
                                        <?php if (!empty($reclamation['category'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($reclamation['category']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending' => 'bg-warning',
                                            'in_progress' => 'bg-primary',
                                            'resolved' => 'bg-success',
                                            'rejected' => 'bg-danger'
                                        ];
                                        $status_labels = [
                                            'pending' => 'En attente',
                                            'in_progress' => 'En cours',
                                            'resolved' => 'Résolue',
                                            'rejected' => 'Rejetée'
                                        ];
                                        ?>
                                        <span class="badge <?= $status_badges[$reclamation['status']] ?? 'bg-secondary' ?>">
                                            <?= $status_labels[$reclamation['status']] ?? $reclamation['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($reclamation['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline-info btn-sm" title="Voir détails"
                                                data-bs-toggle="modal" data-bs-target="#detailModal"
                                                onclick="showReclamationDetails(<?= htmlspecialchars(json_encode($reclamation)) ?>)">
                                            <i>👁️</i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">Précédent</a>
                    </li>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Suivant</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
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
                            <ul>
                                <li><strong>Notes:</strong> 48 heures après publication</li>
                                <li><strong>Corrections:</strong> Aucun délai strict</li>
                                <li><strong>Autres demandes:</strong> Traitement selon urgence</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>📋 Types de réclamations:</h6>
                            <ul>
                                <li><strong>Notes:</strong> Problèmes avec les résultats</li>
                                <li><strong>Corrections:</strong> Erreurs dans les données personnelles</li>
                                <li><strong>Autres:</strong> Demandes diverses</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Reclamation Modal -->
<div class="modal fade" id="newReclamationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle Réclamation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Type Selection -->
                <div id="typeSelection">
                    <h6>Choisissez le type de réclamation:</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 reclamation-type-card" data-type="notes">
                                <div class="card-body text-center">
                                    <i style="font-size: 3rem; color: #007bff;">📊</i>
                                    <h6 class="mt-2">Réclamation Notes</h6>
                                    <p class="small text-muted">Problèmes avec vos notes ou résultats</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 reclamation-type-card" data-type="correction">
                                <div class="card-body text-center">
                                    <i style="font-size: 3rem; color: #ffc107;">✏️</i>
                                    <h6 class="mt-2">Demande de Correction</h6>
                                    <p class="small text-muted">Erreurs dans vos informations personnelles</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 reclamation-type-card" data-type="autre">
                                <div class="card-body text-center">
                                    <i style="font-size: 3rem; color: #28a745;">📝</i>
                                    <h6 class="mt-2">Autre Demande</h6>
                                    <p class="small text-muted">Attestations, informations, etc.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Forms for each type -->
                <div id="formsContainer" style="display: none;">
                    <!-- Notes Form -->
                    <form id="notesForm" style="display: none;" method="POST" action="submit_reclamation.php">
                        <input type="hidden" name="reclamation_type" value="notes">
                        <input type="hidden" name="apoL_a01_code" value="<?= htmlspecialchars($student['apoL_a01_code']) ?>">

                        <h6>📊 Réclamation concernant les notes</h6>

                        <div class="mb-3">
                            <label class="form-label">Module concerné *</label>
                            <select name="default_name" class="form-select" required>
                                <option value="">Choisir un module</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?= htmlspecialchars($module) ?>"><?= htmlspecialchars($module) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Type de problème *</label>
                            <select name="note" class="form-select" required>
                                <option value="">Choisir le problème</option>
                                <option value="zero">Note zéro non justifiée</option>
                                <option value="absent">Marqué absent alors que présent</option>
                                <option value="note_manquante">Note manquante</option>
                                <option value="erreur_calcul">Erreur de calcul</option>
                                <option value="note_incorrecte">Note incorrecte</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Professeur</label>
                                <select name="prof" class="form-select">
                                    <option value="">Choisir le professeur</option>
                                    <?php foreach ($professors as $prof): ?>
                                        <option value="<?= htmlspecialchars($prof) ?>"><?= htmlspecialchars($prof) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Groupe</label>
                                <select name="groupe" class="form-select">
                                    <option value="">Groupe</option>
                                    <?php for($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Semestre</label>
                                <select name="semestre" class="form-select">
                                    <option value="">Semestre</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Salle d'examen</label>
                            <select name="class" class="form-select">
                                <option value="">Choisir la salle</option>
                                <?php
                                $salles = ['Amphi 2', 'Amphi 3', 'Amphi 4', 'Amphi 5', 'Amphi 6', 'Amphi 7', 'Amphi 8', 'Amphi 9', 'Amphi 10', 'Amphi 11', 'Amphi 12', 'Amphi 13', 'Amphi 14', 'Amphi 15', 'Amphi 16', 'Amphi 17', 'Amphi 18', 'Amphi 19', 'BIB'];
                                foreach ($salles as $salle): ?>
                                    <option value="<?= $salle ?>"><?= $salle ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Informations complémentaires</label>
                            <textarea name="info" class="form-control" rows="3" placeholder="Décrivez votre problème en détail..."></textarea>
                        </div>
                    </form>

                    <!-- Correction Form -->
                    <form id="correctionForm" style="display: none;" method="POST" action="submit_reclamation.php">
                        <input type="hidden" name="reclamation_type" value="correction">
                        <input type="hidden" name="apoL_a01_code" value="<?= htmlspecialchars($student['apoL_a01_code']) ?>">

                        <h6>✏️ Demande de correction</h6>

                        <div class="mb-3">
                            <label class="form-label">Type d'erreur *</label>
                            <select name="category" class="form-select" required>
                                <option value="">Choisir le type d'erreur</option>
                                <option value="nom_prenom">Erreur dans le nom ou prénom</option>
                                <option value="date_naissance">Erreur dans la date de naissance</option>
                                <option value="code_apogee">Problème avec le code Apogée</option>
                                <option value="filiere">Erreur d'affectation de filière</option>
                                <option value="cin">Erreur dans le numéro CIN</option>
                                <option value="lieu_naissance">Erreur dans le lieu de naissance</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Information actuelle (incorrecte)</label>
                            <input type="text" name="current_info" class="form-control" placeholder="Que dit le système actuellement?">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Information correcte *</label>
                            <input type="text" name="correct_info" class="form-control" required placeholder="Quelle devrait être la bonne information?">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Documents justificatifs</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="documents[]" value="cin" id="doc_cin">
                                <label class="form-check-label" for="doc_cin">Carte d'identité nationale</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="documents[]" value="acte_naissance" id="doc_acte">
                                <label class="form-check-label" for="doc_acte">Acte de naissance</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="documents[]" value="bac" id="doc_bac">
                                <label class="form-check-label" for="doc_bac">Diplôme du baccalauréat</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Informations complémentaires</label>
                            <textarea name="info" class="form-control" rows="3" placeholder="Ajoutez toute information utile..."></textarea>
                        </div>
                    </form>

                    <!-- Other Form -->
                    <form id="autreForm" style="display: none;" method="POST" action="submit_reclamation.php">
                        <input type="hidden" name="reclamation_type" value="autre">
                        <input type="hidden" name="apoL_a01_code" value="<?= htmlspecialchars($student['apoL_a01_code']) ?>">

                        <h6>📝 Autre demande</h6>

                        <div class="mb-3">
                            <label class="form-label">Type de demande *</label>
                            <select name="category" class="form-select" required>
                                <option value="">Choisir le type de demande</option>
                                <option value="attestation">Demande d'attestation</option>
                                <option value="probleme_technique">Problème technique</option>
                                <option value="demande_info">Demande d'information</option>
                                <option value="reinscription">Problème de réinscription</option>
                                <option value="stage">Demande de stage</option>
                                <option value="transfert">Demande de transfert</option>
                                <option value="bourse">Question concernant les bourses</option>
                                <option value="emploi_temps">Problème d'emploi du temps</option>
                                <option value="acces_compte">Problème d'accès au compte</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Objet de la demande *</label>
                            <input type="text" name="default_name" class="form-control" required placeholder="Résumez votre demande en quelques mots">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Priorité</label>
                            <select name="priority" class="form-select">
                                <option value="normal">Normale</option>
                                <option value="high">Haute</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description détaillée *</label>
                            <textarea name="info" class="form-control" rows="4" required placeholder="Décrivez votre demande en détail..."></textarea>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="backToTypeSelection" style="display: none;">← Retour</button>
                <button type="button" class="btn btn-success" id="submitReclamation" style="display: none;">Envoyer la réclamation</button>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la réclamation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
.reclamation-type-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.reclamation-type-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: #007bff;
}

.reclamation-type-card.selected {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.card {
    border-radius: 12px !important;
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

.form-label {
    font-weight: 600;
    color: #495057;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
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

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    let selectedType = null;

    // Type selection handling
    document.querySelectorAll('.reclamation-type-card').forEach(card => {
        card.addEventListener('click', function() {
            selectedType = this.getAttribute('data-type');

            // Remove selected class from all cards
            document.querySelectorAll('.reclamation-type-card').forEach(c => c.classList.remove('selected'));

            // Add selected class to clicked card
            this.classList.add('selected');

            // Hide type selection and show form
            document.getElementById('typeSelection').style.display = 'none';
            document.getElementById('formsContainer').style.display = 'block';

            // Show appropriate form
            document.querySelectorAll('#formsContainer form').forEach(f => f.style.display = 'none');
            document.getElementById(selectedType + 'Form').style.display = 'block';

            // Show back button and submit button
            document.getElementById('backToTypeSelection').style.display = 'inline-block';
            document.getElementById('submitReclamation').style.display = 'inline-block';
        });
    });

    // Back to type selection
    document.getElementById('backToTypeSelection').addEventListener('click', function() {
        selectedType = null;

        // Show type selection and hide forms
        document.getElementById('typeSelection').style.display = 'block';
        document.getElementById('formsContainer').style.display = 'none';

        // Hide buttons
        this.style.display = 'none';
        document.getElementById('submitReclamation').style.display = 'none';

        // Remove selected class
        document.querySelectorAll('.reclamation-type-card').forEach(c => c.classList.remove('selected'));
    });

    // Submit reclamation
    document.getElementById('submitReclamation').addEventListener('click', function() {
        if (selectedType) {
            const form = document.getElementById(selectedType + 'Form');

            // Basic validation
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (isValid) {
                // Show loading state
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Envoi en cours...';
                this.disabled = true;

                // Submit form
                form.submit();
            } else {
                alert('Veuillez remplir tous les champs requis.');
            }
        }
    });

    // Reset modal when closed
    const modal = document.getElementById('newReclamationModal');
    modal.addEventListener('hidden.bs.modal', function() {
        selectedType = null;
        document.getElementById('typeSelection').style.display = 'block';
        document.getElementById('formsContainer').style.display = 'none';
        document.getElementById('backToTypeSelection').style.display = 'none';
        document.getElementById('submitReclamation').style.display = 'none';
        document.querySelectorAll('.reclamation-type-card').forEach(c => c.classList.remove('selected'));

        // Reset forms
        document.querySelectorAll('#formsContainer form').forEach(f => {
            f.reset();
            f.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
        });

        // Reset submit button
        const submitBtn = document.getElementById('submitReclamation');
        submitBtn.innerHTML = 'Envoyer la réclamation';
        submitBtn.disabled = false;
    });
});

function showReclamationDetails(reclamation) {
    const modalBody = document.getElementById('detailModalBody');

    const typeLabels = {
        'notes': 'Réclamation Notes',
        'correction': 'Demande de Correction',
        'autre': 'Autre Demande'
    };

    const statusLabels = {
        'pending': 'En attente',
        'in_progress': 'En cours de traitement',
        'resolved': 'Résolue',
        'rejected': 'Rejetée'
    };

    const priorityLabels = {
        'low': 'Basse',
        'normal': 'Normale',
        'high': 'Haute',
        'urgent': 'Urgente'
    };

    let typeDisplay = typeLabels[reclamation.reclamation_type] || 'Non défini';
    let statusDisplay = statusLabels[reclamation.status] || reclamation.status;
    let priorityDisplay = priorityLabels[reclamation.priority] || 'Normale';

    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Informations générales</h6>
                <table class="table table-sm">
                    <tr>
                        <th>ID:</th>
                        <td>#${reclamation.id}</td>
                    </tr>
                    <tr>
                        <th>Type:</th>
                        <td><span class="badge bg-primary">${typeDisplay}</span></td>
                    </tr>
                    <tr>
                        <th>Statut:</th>
                        <td><span class="badge bg-info">${statusDisplay}</span></td>
                    </tr>
                    <tr>
                        <th>Priorité:</th>
                        <td><span class="badge bg-secondary">${priorityDisplay}</span></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Dates</h6>
                <table class="table table-sm">
                    <tr>
                        <th>Créée le:</th>
                        <td>${new Date(reclamation.created_at).toLocaleString('fr-FR')}</td>
                    </tr>
                    ${reclamation.updated_at ? `<tr><th>Mise à jour:</th><td>${new Date(reclamation.updated_at).toLocaleString('fr-FR')}</td></tr>` : ''}
                </table>
            </div>
        </div>

        <hr>

        <div class="row">
            <div class="col-12">
                <h6>Détails de la réclamation</h6>
                <table class="table table-sm">
                    <tr>
                        <th style="width: 30%;">Objet/Module:</th>
                        <td>${reclamation.default_name || 'N/A'}</td>
                    </tr>
                    ${reclamation.category ? `<tr><th>Catégorie:</th><td>${reclamation.category}</td></tr>` : ''}
                    ${reclamation.note ? `<tr><th>Type de problème:</th><td>${reclamation.note}</td></tr>` : ''}
                    ${reclamation.prof ? `<tr><th>Professeur:</th><td>${reclamation.prof}</td></tr>` : ''}
                    ${reclamation.groupe ? `<tr><th>Groupe:</th><td>${reclamation.groupe}</td></tr>` : ''}
                    ${reclamation.class ? `<tr><th>Salle:</th><td>${reclamation.class}</td></tr>` : ''}
                    ${reclamation.Semestre ? `<tr><th>Semestre:</th><td>${reclamation.Semestre}</td></tr>` : ''}
                    ${reclamation.session_type ? `<tr><th>Session:</th><td>${reclamation.session_type}</td></tr>` : ''}
                    ${reclamation.result_type ? `<tr><th>Type de résultat:</th><td>${reclamation.result_type}</td></tr>` : ''}
                </table>
            </div>
        </div>

        ${reclamation.info ? `
            <div class="row">
                <div class="col-12">
                    <h6>Informations complémentaires</h6>
                    <div class="alert alert-light">
                        ${reclamation.info.replace(/\n/g, '<br>')}
                    </div>
                </div>
            </div>
        ` : ''}

        ${reclamation.admin_comment ? `
            <div class="row">
                <div class="col-12">
                    <h6>Réponse de l'administration</h6>
                    <div class="alert alert-info">
                        ${reclamation.admin_comment.replace(/\n/g, '<br>')}
                    </div>
                </div>
            </div>
        ` : ''}
    `;
}
</script>

<?php include 'footer.php'; ?>
