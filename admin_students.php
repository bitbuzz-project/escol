<?php
session_start();
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    header("Location: login.php");
    exit();
}

$admin = $_SESSION['student'];
$page_title = "Gestion des Étudiants";

require 'db.php';

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE apoL_a01_code LIKE ? OR apoL_a02_nom LIKE ? OR apoL_a03_prenom LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

// Get students with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$query = "SELECT * FROM apogeL_a $where_clause ORDER BY apoL_a02_nom, apoL_a03_prenom LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param("sss", ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM apogeL_a $where_clause";
$count_stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $count_stmt->bind_param("sss", ...$params);
}

$count_stmt->execute();
$total_students = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_students / $limit);

$stmt->close();
$count_stmt->close();
$conn->close();

include 'admin_header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i>👥</i> Gestion des Étudiants</h2>
    <a href="admin_add_student.php" class="btn btn-danger">
        <i>➕</i> Ajouter Étudiant
    </a>
</div>

<!-- Search and Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text"><i>🔍</i></span>
                    <input type="text" class="form-control" name="search"
                           placeholder="Rechercher par code Apogée, nom ou prénom..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Rechercher</button>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #17a2b8, #138496);">
            <div class="card-body text-white">
                <h3><?= $total_students ?></h3>
                <p class="mb-0">Total Étudiants</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #28a745, #20c997);">
            <div class="card-body text-white">
                <h3><?= count($students) ?></h3>
                <p class="mb-0">Affichés</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
            <div class="card-body text-white">
                <h3><?= $total_pages ?></h3>
                <p class="mb-0">Pages</p>
            </div>
        </div>
    </div>
</div>

<!-- Students Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i>📋</i> Liste des Étudiants</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <div class="text-center py-5">
                <i style="font-size: 3rem; color: #6c757d;">👤</i>
                <h5 class="mt-3 text-muted">Aucun étudiant trouvé</h5>
                <p class="text-muted">Essayez de modifier vos critères de recherche</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Code Apogée</th>
                            <th>Nom Complet</th>
                            <th>Date Naissance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?= htmlspecialchars($student['apoL_a01_code']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                            <?= strtoupper(substr($student['apoL_a03_prenom'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($student['apoL_a03_prenom'] . ' ' . $student['apoL_a02_nom']) ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($student['apoL_a04_naissance']) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="admin_view_student.php?id=<?= $student['apoL_a01_code'] ?>"
                                           class="btn btn-outline-info" title="Voir détails">
                                            <i>👁️</i>
                                        </a>
                                        <a href="admin_edit_student.php?id=<?= $student['apoL_a01_code'] ?>"
                                           class="btn btn-outline-primary" title="Modifier">
                                            <i>✏️</i>
                                        </a>
                                        <a href="admin_student_results.php?id=<?= $student['apoL_a01_code'] ?>"
                                           class="btn btn-outline-success" title="Résultats">
                                            <i>📊</i>
                                        </a>
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
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Précédent</a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Suivant</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php include 'footer.php'; ?>
