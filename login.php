<?php
session_start();

// If the user is already logged in, redirect appropriately
if (isset($_SESSION['student'])) {
    if ($_SESSION['student']['apoL_a01_code'] === '16005333') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $apogee = $_POST['apogee'];
    $birthdate_input = $_POST['birthdate'];
    $birthdate = date('d/m/Y', strtotime($birthdate_input)); // Converts to "06/04/1987"

    // Query to check if apogee and birthdate match
    $query = $conn->prepare("SELECT apoL_a01_code, apoL_a02_nom, apoL_a03_prenom FROM students_base WHERE apoL_a01_code = ? AND apoL_a04_naissance = ?");
    if (!$query) {
        die("Query preparation failed: " . $conn->error);
    }

    $query->bind_param('ss', $apogee, $birthdate);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['student'] = $result->fetch_assoc();

        // Check if this is the admin user
        if ($apogee === '16005333') {
            // Log admin login (optional)
            $log_query = $conn->prepare("INSERT INTO admin_logs (admin_id, action, description, ip_address) VALUES (?, 'LOGIN', 'Admin login successful', ?)");
            if ($log_query) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $log_query->bind_param('ss', $apogee, $ip);
                $log_query->execute();
                $log_query->close();
            }

            header("Location: admin_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid Apogee or Birthdate.";
    }

    $query->close();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كلية العلوم القانونية والسياسية سطات</title>

    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f8f9fa;
            margin: 0;
        }
        .card-container {
            width: 80%;
            max-width: 600px;
            margin-bottom: 20px;
        }
        .login-container {
            max-width: 100%;
            width: 400px;
            padding: 20px;
            background: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-top: 12px;
        }
        .admin-notice {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #dc3545;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: bold;
            border: 2px solid rgba(220, 53, 69, 0.2);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <!-- Title and Card at the top -->
    <h3>كلية العلوم القانونية والسياسية سطات</h3>

    <div class="card">
        <div class="card-header">
            دليل الاستعمال
        </div>
        <div class="card-body">
            <p>خطوات تسجيل الدخول :</p>
            <ol>
                <li>ادخل رقم الأبوجي APOGEE</li>
                <li>ادخل تاريخ الازدياد على الشكل التالي السنة/الشهر/اليوم, مثال (25/02/1999)</li>
                <li>اضغط على زر "الدخول"</li>
            </ol>
        </div>
    </div>

    <!-- Login Card -->
    <div class="login-container">
        <!-- Admin Notice -->
        <div class="admin-notice">
            🛡️ للمسؤولين: استخدم الرقم 16005333 للوصول إلى لوحة الإدارة
            <br><small>Admin: Use code 16005333 to access admin panel</small>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <div style="display: flex; justify-content: space-between;">
                    <label class="form-label">رقم أبوجي :</label>
                    <label for="apoL_a01_code" class="form-label">: Num APPOGEE</label>
                </div>
                <input type="text" class="form-control" id="apogee" name="apogee" required>
            </div>
            <div class="mb-3">
                <div style="display: flex; justify-content: space-between;">
                    <label class="form-label">تاريخ الازدياد:</label>
                    <label for="apoL_a04_naissance" class="form-label">: Date de naissance</label>
                </div>
                <input type="date" class="form-control" id="birthdate" name="birthdate" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">دخول</button>
        </form>
    </div>

    <div class="container-fluid d-flex justify-content-center align-items-center">
        <h6 class="display-9 py-4 text-center">كلية العلوم القانونية والسياسية سطات - 2024</h6>
    </div>

    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
