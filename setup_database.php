<?php
require 'db.php';

echo "<h2>Database Setup Script</h2>";

// Create tables if they don't exist
$tables = [
    'students_base' => "
        CREATE TABLE IF NOT EXISTS students_base (
            apoL_a01_code VARCHAR(20) PRIMARY KEY,
            apoL_a02_nom VARCHAR(100) NOT NULL,
            apoL_a03_prenom VARCHAR(100) NOT NULL,
            apoL_a04_naissance VARCHAR(20),
            cod_etu VARCHAR(20),
            cod_etp VARCHAR(20),
            cod_anu VARCHAR(10),
            cod_dip VARCHAR(20),
            cod_sex_etu VARCHAR(5),
            lib_vil_nai_etu VARCHAR(100),
            cin_ind VARCHAR(20),
            lib_etp VARCHAR(200),
            lic_etp VARCHAR(200),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_cod_etu (cod_etu),
            INDEX idx_cin (cin_ind),
            INDEX idx_cod_etp (cod_etp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'administative' => "
        CREATE TABLE IF NOT EXISTS administative (
            id INT AUTO_INCREMENT PRIMARY KEY,
            apogee VARCHAR(20),
            filliere VARCHAR(100),
            annee_scolaire VARCHAR(20) DEFAULT '2024-2025',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_apogee (apogee)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'notes' => "
        CREATE TABLE IF NOT EXISTS notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            apoL_a01_code VARCHAR(20),
            code_module VARCHAR(20),
            nom_module VARCHAR(200),
            note DECIMAL(4,2),
            validite VARCHAR(50),
            adding_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_apogee (apoL_a01_code),
            INDEX idx_module (code_module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'reclamations' => "
        CREATE TABLE IF NOT EXISTS reclamations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            apoL_a01_code VARCHAR(20),
            default_name VARCHAR(200),
            note VARCHAR(100),
            prof VARCHAR(100),
            groupe VARCHAR(10),
            class VARCHAR(50),
            info TEXT,
            Semestre VARCHAR(10),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'pending',
            INDEX idx_apogee (apoL_a01_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'admin_logs' => "
        CREATE TABLE IF NOT EXISTS admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id VARCHAR(20),
            action VARCHAR(100),
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin (admin_id),
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

// Create tables
foreach ($tables as $table_name => $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Table '$table_name' created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating table '$table_name': " . $conn->error . "</p>";
    }
}

// Insert admin user
$admin_sql = "INSERT IGNORE INTO students_base (apoL_a01_code, apoL_a02_nom, apoL_a03_prenom, apoL_a04_naissance)
              VALUES ('16005333', 'Admin', 'System', '06/04/1987')";

if ($conn->query($admin_sql)) {
    echo "<p style='color: green;'>✓ Admin user added successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error adding admin user: " . $conn->error . "</p>";
}

// Insert some test students
$test_students = [
    ['12345678', 'Alami', 'Mohammed', '15/03/2000'],
    ['12345679', 'Benali', 'Fatima', '22/07/1999'],
    ['12345680', 'Chakir', 'Ahmed', '10/11/2001'],
    ['12345681', 'Driouech', 'Zineb', '05/09/2000'],
    ['12345682', 'El Fassi', 'Youssef', '18/12/1998']
];

$student_sql = "INSERT IGNORE INTO students_base (apoL_a01_code, apoL_a02_nom, apoL_a03_prenom, apoL_a04_naissance) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($student_sql);

if ($stmt) {
    foreach ($test_students as $student) {
        $stmt->bind_param("ssss", $student[0], $student[1], $student[2], $student[3]);
        $stmt->execute();
    }
    echo "<p style='color: green;'>✓ Test students added successfully</p>";
    $stmt->close();
} else {
    echo "<p style='color: red;'>✗ Error preparing student insert statement</p>";
}

// Insert some test filières
$test_filieres = [
    ['12345678', 'Droit Public'],
    ['12345679', 'Droit Privé'],
    ['12345680', 'Sciences Politiques'],
    ['12345681', 'Droit International'],
    ['12345682', 'Droit des Affaires']
];

$filiere_sql = "INSERT IGNORE INTO administative (apogee, filliere) VALUES (?, ?)";
$stmt = $conn->prepare($filiere_sql);

if ($stmt) {
    foreach ($test_filieres as $filiere) {
        $stmt->bind_param("ss", $filiere[0], $filiere[1]);
        $stmt->execute();
    }
    echo "<p style='color: green;'>✓ Test filières added successfully</p>";
    $stmt->close();
} else {
    echo "<p style='color: red;'>✗ Error preparing filière insert statement</p>";
}

// Check what we have now
echo "<h3>Current Database Status:</h3>";

// Count records in each table
foreach (array_keys($tables) as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "<p><strong>$table:</strong> $count records</p>";
    }
}

echo "<hr>";
echo "<p><strong>Setup complete!</strong> You can now:</p>";
echo "<ul>";
echo "<li>Login as admin with: 16005333 / 1987-04-06</li>";
echo "<li>Login as test student with: 12345678 / 2000-03-15</li>";
echo "<li>Access the admin dashboard</li>";
echo "<li>Manage students</li>";
echo "</ul>";

echo "<p><a href='login.php'>Go to Login Page</a></p>";

$conn->close();
?>
