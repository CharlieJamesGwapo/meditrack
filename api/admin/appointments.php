<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/NoShowSweeper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    NoShowSweeper::sweep($db);  // self-healing: stale scheduled → no_show

    $filter_date   = sanitizeInput($_GET['date'] ?? '');
    $filter_status = sanitizeInput($_GET['status'] ?? '');
    $page          = max(1, (int) ($_GET['page'] ?? 1));
    $offset        = ($page - 1) * ITEMS_PER_PAGE;

    $where  = "WHERE 1=1";
    $params = [];

    if (!empty($filter_date)) {
        $where .= " AND a.appointment_date = :date";
        $params[':date'] = $filter_date;
    }
    if (!empty($filter_status)) {
        $where .= " AND a.status = :status";
        $params[':status'] = $filter_status;
    }

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM appointments a $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $total_pages = (int) ceil($total / ITEMS_PER_PAGE);

    // Fetch page
    $params[':limit']  = ITEMS_PER_PAGE;
    $params[':offset'] = $offset;

    $stmt = $db->prepare("
        SELECT a.id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.status, a.reason_for_visit, a.checked_in_at, a.completed_at, a.cancelled_at, a.created_at,
               p.id as patient_id, p.full_name as patient_name, p.contact_number as patient_contact,
               d.id as doctor_id, d.full_name as doctor_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        $where
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    sendJSON([
        'success'      => true,
        'appointments' => $appointments,
        'pagination'   => [
            'total'       => $total,
            'page'        => $page,
            'total_pages' => $total_pages,
            'per_page'    => ITEMS_PER_PAGE
        ]
    ]);

} catch (Exception $e) {
    error_log("admin appointments error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load appointments'], 500);
}
