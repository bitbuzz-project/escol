<?php
session_start();
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    header("Location: login.php");
    exit();
}

$admin = $_SESSION['student'];
$page_title = "Gestion des Réclamations";

require 'db.php';

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($query);
    return $result && $result->num_rows > 0;
}

// Check table structure first - but don't close connection
$has_new_columns = columnExists($conn, 'reclamations', 'reclamation_type');

if (!$has_new_columns) {
    include 'admin_header.php';
    echo "<div class='container-fluid'>";
    echo "<div class='alert alert-warning'>";
    echo "<h4>⚠️ Migration Requise</h4>";
    echo "<p>Votre table reclamations n'a pas encore été mise à jour avec les nouvelles colonnes.</p>";
    echo "<p><a href='mariadb_compatible_migration.php' class='btn btn-primary'>Exécuter la Migration</a></p>";
    echo "<p><a href='admin_dashboard.php' class='btn btn-secondary'>Retour au Tableau de Bord</a></p>";
    echo "</div>";
    echo "</div>";
    include 'footer.php';
    $conn->close(); // Close here after showing the message
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $reclamation_id = (int)$_POST['reclamation_id'];
        $new_status = $_POST['new_status'];
        $admin_comment = $_POST['admin_comment'] ?? '';

        $update_sql = "UPDATE reclamations SET status = ?, admin_comment = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param('ssi', $new_status, $admin_comment, $reclamation_id);
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Statut de la réclamation mis à jour avec succès.";
            } else {
                $_SESSION['error_message'] = "Erreur lors de la mise à jour du statut.";
            }
            $update_stmt->close();
        }
        header("Location: admin_reclamations.php");
        exit();
    }
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($filter_status)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

if (!empty($filter_type) && columnExists($conn, 'reclamations', 'reclamation_type')) {
    $where_conditions[] = "r.reclamation_type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(r.apoL_a01_code LIKE ? OR s.apoL_a02_nom LIKE ? OR s.apoL_a03_prenom LIKE ? OR r.default_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get reclamations with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build dynamic query based on available columns
$select_columns = [
    'r.id',
    'r.apoL_a01_code',
    'r.default_name',
    'r.note',
    'r.prof',
    'r.groupe',
    'r.class',
    'r.info',
    'r.Semestre',
    'r.status',
    'r.created_at',
    's.apoL_a02_nom',
    's.apoL_a03_prenom'
];

// Add optional columns if they exist
if (columnExists($conn, 'reclamations', 'reclamation_type')) {
    $select_columns[] = 'r.reclamation_type';
}
if (columnExists($conn, 'reclamations', 'category')) {
    $select_columns[] = 'r.category';
}
if (columnExists($conn, 'reclamations', 'priority')) {
    $select_columns[] = 'r.priority';
}
if (columnExists($conn, 'reclamations', 'admin_comment')) {
    $select_columns[] = 'r.admin_comment';
}
if (columnExists($conn, 'reclamations', 'updated_at')) {
    $select_columns[] = 'r.updated_at';
}

// Add "is recent" calculation
$select_columns[] = 'CASE WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END as is_recent';

$query = "
    SELECT " . implode(', ', $select_columns) . "
    FROM reclamations r
    LEFT JOIN students_base s ON r.apoL_a01_code = s.apoL_a01_code
    $where_clause
    ORDER BY r.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Erreur de préparation de la requête: " . $conn->error);
}

if (!empty($params)) {
    if (!$stmt->bind_param($param_types, ...$params)) {
        die("Erreur lors du binding des paramètres: " . $stmt->error);
    }
}

if (!$stmt->execute()) {
    die("Erreur d'exécution de la requête: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    die("Erreur lors de la récupération des résultats: " . $stmt->error);
}

$reclamations = [];
while ($row = $result->fetch_assoc()) {
    $reclamations[] = $row;
}
$stmt->close();

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM reclamations r
    LEFT JOIN students_base s ON r.apoL_a01_code = s.apoL_a01_code
    $where_clause
";

$count_stmt = $conn->prepare($count_query);
if (!$count_stmt) {
    die("Erreur de préparation de la requête de comptage: " . $conn->error);
}

if (!empty($params)) {
    if (!$count_stmt->bind_param($param_types, ...$params)) {
        die("Erreur lors du binding des paramètres de comptage: " . $count_stmt->error);
    }
}

if (!$count_stmt->execute()) {
    die("Erreur d'exécution de la requête de comptage: " . $count_stmt->error);
}

$count_result = $count_stmt->get_result();
if (!$count_result) {
    die("Erreur lors de la récupération du résultat de comptage: " . $count_stmt->error);
}

$total_reclamations = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_reclamations / $limit);
$count_stmt->close();

// Get statistics - Build dynamic stats query
$stats_select = [
    'COUNT(*) as total',
    "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending",
    "SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress",
    "SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved",
    "SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected",
    "SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as recent"
];

if (columnExists($conn, 'reclamations', 'reclamation_type')) {
    $stats_select[] = "SUM(CASE WHEN reclamation_type = 'notes' THEN 1 ELSE 0 END) as notes_type";
    $stats_select[] = "SUM(CASE WHEN reclamation_type = 'correction' THEN 1 ELSE 0 END) as correction_type";
    $stats_select[] = "SUM(CASE WHEN reclamation_type = 'autre' THEN 1 ELSE 0 END) as autre_type";
} else {
    // Default values if column doesn't exist
    $stats_select[] = "0 as notes_type";
    $stats_select[] = "0 as correction_type";
    $stats_select[] = "0 as autre_type";
}

$stats_query = "SELECT " . implode(', ', $stats_select) . " FROM reclamations";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'rejected' => 0,
    'notes_type' => 0, 'correction_type' => 0, 'autre_type' => 0, 'recent' => 0
];

$conn->close();

include 'admin_header.php';
?>

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
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i>⚠️</i> Gestion des Réclamations</h2>
    <div>
        <button class="btn btn-success" onclick="exportReclamations()">
            <i>📊</i> Exporter
        </button>
        <a href="admin_dashboard.php" class="btn btn-secondary">
            <i>🏠</i> Tableau de bord
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #17a2b8, #138496);">
            <div class="card-body text-white">
                <h3><?= $stats['total'] ?></h3>
                <p class="mb-0">Total</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
            <div class="card-body text-white">
                <h3><?= $stats['pending'] ?></h3>
                <p class="mb-0">En attente</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #007bff, #0056b3);">
            <div class="card-body text-white">
                <h3><?= $stats['in_progress'] ?></h3>
                <p class="mb-0">En cours</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #28a745, #20c997);">
            <div class="card-body text-white">
                <h3><?= $stats['resolved'] ?></h3>
                <p class="mb-0">Résolues</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #dc3545, #c82333);">
            <div class="card-body text-white">
                <h3><?= $stats['rejected'] ?></h3>
                <p class="mb-0">Rejetées</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #6f42c1, #5a67d8);">
            <div class="card-body text-white">
                <h3><?= $stats['recent'] ?></h3>
                <p class="mb-0">Récentes</p>
            </div>
        </div>
    </div>
</div>

<!-- Type Statistics (only if new columns exist) -->
<?php if (columnExists($conn, 'reclamations', 'reclamation_type')): ?>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center bg-light">
            <div class="card-body">
                <h4 class="text-primary"><?= $stats['notes_type'] ?></h4>
                <p class="mb-0">Réclamations Notes</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center bg-light">
            <div class="card-body">
                <h4 class="text-warning"><?= $stats['correction_type'] ?></h4>
                <p class="mb-0">Corrections d'erreurs</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center bg-light">
            <div class="card-body">
                <h4 class="text-info"><?= $stats['autre_type'] ?></h4>
                <p class="mb-0">Autres types</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i>🔍</i> Filtres et Recherche</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>En attente</option>
                    <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                    <option value="resolved" <?= $filter_status === 'resolved' ? 'selected' : '' ?>>Résolue</option>
                    <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejetée</option>
                </select>
            </div>

            <?php if (columnExists($conn, 'reclamations', 'reclamation_type')): ?>
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">Tous les types</option>
                    <option value="notes" <?= $filter_type === 'notes' ? 'selected' : '' ?>>Réclamation Notes</option>
                    <option value="correction" <?= $filter_type === 'correction' ? 'selected' : '' ?>>Correction d'erreur</option>
                    <option value="autre" <?= $filter_type === 'autre' ? 'selected' : '' ?>>Autre</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-4">
                <label class="form-label">Recherche</label>
                <input type="text" name="search" class="form-control" placeholder="Code Apogée, nom, module..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                </div>
            </div>
        </form>
        <div class="mt-2">
            <a href="admin_reclamations.php" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
        </div>
    </div>
</div>

<!-- Reclamations Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i>📋</i> Liste des Réclamations (<?= $total_reclamations ?> total)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($reclamations)): ?>
            <div class="text-center py-5">
                <i style="font-size: 3rem; color: #6c757d;">📋</i>
                <h5 class="mt-3 text-muted">Aucune réclamation trouvée</h5>
                <p class="text-muted">Aucune réclamation ne correspond aux critères sélectionnés</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Étudiant</th>
                            <?php if (isset($reclamations[0]['reclamation_type'])): ?>
                            <th>Type</th>
                            <?php endif; ?>
                            <th>Module/Objet</th>
                            <th>Problème</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reclamations as $reclamation): ?>
                            <tr class="<?= $reclamation['is_recent'] ? 'table-warning' : '' ?>">
                                <td>
                                    <span class="badge bg-secondary">#<?= $reclamation['id'] ?></span>
                                    <?php if ($reclamation['is_recent']): ?>
                                        <span class="badge bg-warning">Nouveau</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars(($reclamation['apoL_a03_prenom'] ?? '') . ' ' . ($reclamation['apoL_a02_nom'] ?? '')) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($reclamation['apoL_a01_code']) ?></small>
                                    </div>
                                </td>
                                <?php if (isset($reclamation['reclamation_type'])): ?>
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
                                </td>
                                <?php endif; ?>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($reclamation['default_name']) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($reclamation['note'])): ?>
                                        <span class="badge bg-info"><?= htmlspecialchars($reclamation['note']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
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
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" title="Voir détails"
                                                data-bs-toggle="modal" data-bs-target="#detailModal"
                                                onclick="showReclamationDetails(<?= htmlspecialchars(json_encode($reclamation)) ?>)">
                                            <i>👁️</i>
                                        </button>
                                        <button class="btn btn-outline-success" title="Modifier statut"
                                                data-bs-toggle="modal" data-bs-target="#statusModal"
                                                onclick="editReclamationStatus(<?= $reclamation['id'] ?>, '<?= $reclamation['status'] ?>')">
                                            <i>✏️</i>
                                        </button>
                                    </div>
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
                    <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= urlencode($filter_status) ?>&type=<?= urlencode($filter_type) ?>&search=<?= urlencode($search) ?>">Précédent</a>
                </li>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>&type=<?= urlencode($filter_type) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= urlencode($filter_status) ?>&type=<?= urlencode($filter_type) ?>&search=<?= urlencode($search) ?>">Suivant</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">Détails de la Réclamation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">Modifier le Statut</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="reclamation_id" id="statusReclamationId">

                    <div class="mb-3">
                        <label for="statusSelect" class="form-label">Nouveau statut</label>
                        <select name="new_status" id="statusSelect" class="form-select" required>
                            <option value="pending">En attente</option>
                            <option value="in_progress">En cours de traitement</option>
                            <option value="resolved">Résolue</option>
                            <option value="rejected">Rejetée</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="adminComment" class="form-label">Commentaire administrateur (optionnel)</label>
                        <textarea name="admin_comment" id="adminComment" class="form-control" rows="3"
                                  placeholder="Ajoutez un commentaire sur cette réclamation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showReclamationDetails(reclamation) {
    const modalBody = document.getElementById('detailModalBody');

    const typeLabels = {
        'notes': 'Réclamation Notes',
        'correction': 'Correction d\'erreur',
        'autre': 'Autre'
    };

    const statusLabels = {
        'pending': 'En attente',
        'in_progress': 'En cours',
        'resolved': 'Résolue',
        'rejected': 'Rejetée'
    };

    let typeDisplay = 'Non défini';
    if (reclamation.reclamation_type) {
        typeDisplay = typeLabels[reclamation.reclamation_type] || reclamation.reclamation_type;
    }

    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Informations Étudiant</h6>
                <table class="table table-sm">
                    <tr>
                        <th>Code Apogée:</th>
                        <td>${reclamation.apoL_a01_code}</td>
                    </tr>
                    <tr>
                        <th>Nom:</th>
                        <td>${(reclamation.apoL_a03_prenom || '') + ' ' + (reclamation.apoL_a02_nom || '')}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Détails Réclamation</h6>
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
                        <td><span class="badge bg-info">${statusLabels[reclamation.status] || reclamation.status}</span></td>
                    </tr>
                </table>
            </div>
        </div>

        <hr>

        <div class="row">
            <div class="col-12">
                <h6>Détails de la Réclamation</h6>
                <table class="table table-sm">
                    <tr>
                        <th style="width: 30%;">Module/Objet:</th>
                        <td>${reclamation.default_name || 'N/A'}</td>
                    </tr>
                    ${reclamation.note ? `<tr><th>Type de problème:</th><td>${reclamation.note}</td></tr>` : ''}
                    ${reclamation.category ? `<tr><th>Catégorie:</th><td>${reclamation.category}</td></tr>` : ''}
                    ${reclamation.prof ? `<tr><th>Professeur:</th><td>${reclamation.prof}</td></tr>` : ''}
                    ${reclamation.groupe ? `<tr><th>Groupe:</th><td>${reclamation.groupe}</td></tr>` : ''}
                    ${reclamation.class ? `<tr><th>Salle d'examen:</th><td>${reclamation.class}</td></tr>` : ''}
                    ${reclamation.Semestre ? `<tr><th>Semestre:</th><td>${reclamation.Semestre}</td></tr>` : ''}
                    ${reclamation.priority ? `<tr><th>Priorité:</th><td>${reclamation.priority}</td></tr>` : ''}
                    <tr>
                        <th>Date de création:</th>
                        <td>${new Date(reclamation.created_at).toLocaleString('fr-FR')}</td>
                    </tr>
                    ${reclamation.updated_at ? `<tr><th>Dernière mise à jour:</th><td>${new Date(reclamation.updated_at).toLocaleString('fr-FR')}</td></tr>` : ''}
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
                    <h6>Commentaire Administrateur</h6>
                    <div class="alert alert-info">
                        ${reclamation.admin_comment.replace(/\n/g, '<br>')}
                    </div>
                </div>
            </div>
        ` : ''}
    `;
}

function editReclamationStatus(id, currentStatus) {
    document.getElementById('statusReclamationId').value = id;
    document.getElementById('statusSelect').value = currentStatus;
    document.getElementById('adminComment').value = '';
}

function exportReclamations() {
    // Create CSV data
    const data = [
        ['ID', 'Code Apogée', 'Nom', 'Prénom', 'Type', 'Module', 'Problème', 'Statut', 'Date']
    ];

    // Add reclamation data
    <?php foreach ($reclamations as $rec): ?>
    data.push([
        '<?= $rec['id'] ?>',
        '<?= $rec['apoL_a01_code'] ?>',
        '<?= addslashes($rec['apoL_a02_nom'] ?? '') ?>',
        '<?= addslashes($rec['apoL_a03_prenom'] ?? '') ?>',
        '<?= $rec['reclamation_type'] ?? 'N/A' ?>',
        '<?= addslashes($rec['default_name']) ?>',
        '<?= addslashes($rec['note'] ?? '') ?>',
        '<?= $rec['status'] ?>',
        '<?= date('d/m/Y H:i', strtotime($rec['created_at'])) ?>'
    ]);
    <?php endforeach; ?>

    // Convert to CSV
    const csvContent = data.map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'reclamations_<?= date('Y-m-d') ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Auto-refresh for new reclamations
setInterval(function() {
    const badge = document.querySelector('.badge.bg-warning');
    if (badge && badge.textContent === 'Nouveau') {
        console.log('Nouvelle réclamation détectée');
    }
}, 30000); // Check every 30 seconds
</script>

<!-- Additional CSS for better styling -->
<style>
.table-responsive {
    max-height: 600px;
    overflow-y: auto;
}

.badge {
    font-size: 0.75rem;
}

.card {
    border-radius: 12px !important;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

.modal-body h6 {
    color: #495057;
    font-weight: 600;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.alert-light {
    background-color: #f8f9fa;
    border-color: #e9ecef;
    color: #495057;
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
