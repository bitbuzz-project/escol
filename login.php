<?php
session_start();

// If the user is already logged in, redirect to the dashboard
if (isset($_SESSION['student'])) {
    header("Location: dashboard.php"); // Redirect to the dashboard page or other page
    exit(); // Ensure that no further code is executed
}

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $apogee = $_POST['apogee'];
    $birthdate_input = $_POST['birthdate']; // Example: "1987-04-06"
    $birthdate = date('d/m/Y', strtotime($birthdate_input)); // Converts to "06/04/1987"
    // Query to check if apogee and birthdate match
    $query = $conn->prepare("SELECT apoL_a01_code, apoL_a02_nom, apoL_a03_prenom FROM students_base WHERE apoL_a01_code = ? AND apoL_a04_naissance = ?");
    if (!$query) {
        die("Query preparation failed: " . $conn->error); // Debugging step
    }
    $query->bind_param('ss', $apogee, $birthdate);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['student'] = $result->fetch_assoc(); // Store student data in session
        header("Location: dashboard.php"); // Redirect to dashboard
        exit();
    } else {
        $error = "Invalid Apogee or Birthdate.";
    }
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
            max-width: 100%; /* Full width for smaller screens */
            width: 400px; /* Default width for larger screens */
            padding: 20px;
            background: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-top: 12px;
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
    <p>خطوات تسجيل الدخول  :</p>
    <ol>
      <li>ادخل رقم الأبوجي APOGEE</li>
      <li>ادخل تاريخ الازدياد على الشكل التالي السنة/الشهر/اليوم, مثال (25/02/1999)</li>
      <li>اضغط على زر "الدخول"</li>
    </ol>
  </div>
</div>

    <!-- Login Card -->
    <div class="login-container">
     
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
