<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit();
}

$student = $_SESSION['student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Retrieve form data
        $apoL_a01_code = $student['apoL_a01_code'];
        $reclamation_type = $_POST['reclamation_type'] ?? 'notes';
        $default_name = $_POST['default_name'] ?? '';

        // Fix: Use 'note' field from form as category for notes type
        $category = $_POST['category'] ?? $_POST['note'] ?? '';

        $info = $_POST['info'] ?? '';
        $session_type = $_POST['session_type'] ?? '';
        $result_type = $_POST['result_type'] ?? '';

        // Type-specific fields
        $note = '';
        $prof = '';
        $groupe = '';
        $class = '';
        $semestre = '';
        $priority = $_POST['priority'] ?? 'normal';

        // Additional fields for correction type
        $current_info = $_POST['current_info'] ?? '';
        $correct_info = $_POST['correct_info'] ?? '';
        $documents = isset($_POST['documents']) ? implode(',', $_POST['documents']) : '';

        // Validate required fields based on type
        switch ($reclamation_type) {
            case 'notes':
                $note = $_POST['note'] ?? $category; // Use category as note for notes type
                $prof = $_POST['prof'] ?? '';
                $groupe = $_POST['groupe'] ?? '';
                $class = $_POST['class'] ?? '';
                $semestre = $_POST['semestre'] ?? '';

                // Fix: Better validation for notes type
                if (empty($default_name)) {
                    throw new Exception('Le module est requis pour une réclamation de notes.');
                }
                if (empty($note)) {
                    throw new Exception('Le type de problème est requis pour une réclamation de notes.');
                }
                // Set category to note value for consistency
                $category = $note;
                break;

            case 'correction':
                if (empty($category) || empty($correct_info)) {
                    throw new Exception('Type d\'erreur et information correcte sont requis pour une correction.');
                }

                // For correction type, store the correction details in info
                $correction_details = "Type d'erreur: " . $category . "\n";
                if (!empty($current_info)) {
                    $correction_details .= "Information actuelle: " . $current_info . "\n";
                }
                $correction_details .= "Information correcte: " . $correct_info . "\n";
                if (!empty($documents)) {
                    $correction_details .= "Documents fournis: " . $documents . "\n";
                }
                $info = $correction_details . "\n" . $info;
                $default_name = "Correction: " . $category;
                break;

            case 'autre':
                if (empty($category) || empty($default_name)) {
                    throw new Exception('Type de demande et objet sont requis pour ce type de réclamation.');
                }
                break;

            default:
                throw new Exception('Type de réclamation non valide.');
        }

        // Check if the student has already made a reclamation for this module/object recently
        $check_interval = ($reclamation_type === 'notes') ? '48 HOUR' : '24 HOUR';

        $queryCheck = "
            SELECT COUNT(*) as count
            FROM reclamations
            WHERE apoL_a01_code = ?
            AND default_name = ?
            AND reclamation_type = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL $check_interval)
        ";

        $stmtCheck = $conn->prepare($queryCheck);
        $stmtCheck->bind_param("sss", $apoL_a01_code, $default_name, $reclamation_type);
        $stmtCheck->execute();
        $stmtCheck->bind_result($count);
        $stmtCheck->fetch();
        $stmtCheck->close();

        if ($count > 0) {
            $timeframe = ($reclamation_type === 'notes') ? '48 heures' : '24 heures';
            throw new Exception("Vous avez déjà soumis une réclamation similaire dans les dernières $timeframe!");
        }

        // Apply business rules validation
        validateReclamationRules($reclamation_type, $apoL_a01_code, $conn);

        // Insert new reclamation with enhanced data
        $query = "
            INSERT INTO reclamations (
                apoL_a01_code, default_name, note, prof, groupe, class, info,
                Semestre, reclamation_type, category, priority, session_type, result_type,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ";

        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->bind_param(
                "sssssssssssss",
                $apoL_a01_code, $default_name, $note, $prof, $groupe, $class, $info,
                $semestre, $reclamation_type, $category, $priority, $session_type, $result_type
            );

            if ($stmt->execute()) {
                $reclamation_id = $conn->insert_id;

                // Log the reclamation creation
                $log_query = "
                    INSERT INTO admin_logs (admin_id, action, description, ip_address, created_at)
                    VALUES (?, 'NEW_RECLAMATION', ?, ?, NOW())
                ";
                $log_stmt = $conn->prepare($log_query);
                if ($log_stmt) {
                    $description = "Nouvelle réclamation ID: $reclamation_id - Type: $reclamation_type - Étudiant: $apoL_a01_code";
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $log_stmt->bind_param('sss', $apoL_a01_code, $description, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                // Set success message based on type
                $type_messages = [
                    'notes' => 'Votre réclamation concernant les notes a été envoyée avec succès!',
                    'correction' => 'Votre demande de correction a été envoyée avec succès!',
                    'autre' => 'Votre demande a été envoyée avec succès!'
                ];

                $_SESSION['success_message'] = $type_messages[$reclamation_type] . " Numéro de référence: #$reclamation_id";

                // Send notification email to admin (if email system is configured)
                try {
                    sendReclamationNotification($reclamation_id, $reclamation_type, $apoL_a01_code, $default_name);
                } catch (Exception $e) {
                    // Email notification failed, but reclamation was created successfully
                    error_log("Failed to send email notification: " . $e->getMessage());
                }

            } else {
                throw new Exception('Erreur lors de l\'enregistrement de votre réclamation.');
            }
            $stmt->close();
        } else {
            throw new Exception('Erreur de préparation de la requête.');
        }

        // Redirect based on source page
        $redirect_page = determineRedirectPage($reclamation_type, $_POST);
        header("Location: $redirect_page");
        exit();

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        $redirect_page = determineRedirectPage($reclamation_type ?? 'notes', $_POST);
        header("Location: $redirect_page");
        exit();
    }
} else {
    $_SESSION['error_message'] = 'Requête invalide.';
    header("Location: dashboard.php");
    exit();
}

/**
 * Determine the appropriate redirect page based on reclamation type and context
 */
function determineRedirectPage($type, $post_data) {
    switch ($type) {
        case 'notes':
            // Check if it's from a specific results page
            if (!empty($post_data['session_type']) && !empty($post_data['result_type'])) {
                return "resultat.php?session=" . urlencode($post_data['session_type']) .
                       "&result_type=" . urlencode($post_data['result_type']);
            }
            return "resultat.php";

        case 'correction':
        case 'autre':
            return "profile.php";

        default:
            return "dashboard.php";
    }
}

/**
 * Send email notification to administrators about new reclamation
 */
function sendReclamationNotification($reclamation_id, $type, $student_code, $subject) {
    // This function would integrate with your email system
    // For now, it's a placeholder that logs the notification

    $type_labels = [
        'notes' => 'Réclamation Notes',
        'correction' => 'Demande de Correction',
        'autre' => 'Autre Demande'
    ];

    $notification_data = [
        'id' => $reclamation_id,
        'type' => $type_labels[$type] ?? $type,
        'student' => $student_code,
        'subject' => $subject,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Log notification (replace with actual email sending)
    error_log("New reclamation notification: " . json_encode($notification_data));
}

/**
 * Validate business rules for reclamations
 */
function validateReclamationRules($type, $student_code, $conn) {
    // Check daily limits
    $daily_limits = [
        'notes' => 3,
        'correction' => 2,
        'autre' => 5
    ];

    $limit = $daily_limits[$type] ?? 3;

    $query = "
        SELECT COUNT(*) as count
        FROM reclamations
        WHERE apoL_a01_code = ?
        AND reclamation_type = ?
        AND DATE(created_at) = CURDATE()
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $student_code, $type);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count >= $limit) {
        throw new Exception("Limite quotidienne atteinte pour ce type de réclamation ($limit par jour).");
    }

    return true;
}

$conn->close();
?>
