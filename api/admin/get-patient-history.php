<?php
/**
 * GET /api/admin/get-patient-history.php?patient_id=N
 * Returns the full clinical history for a patient: profile, every appointment,
 * the joined vitals (triage), medical record, cert, referral per appointment,
 * and any follow-up linkage.
 *
 * Auth: admin or doctor. Doctor sees only patients who have an appointment
 * with them; admin sees everything.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !(hasRole('admin') || hasRole('doctor') || hasRole('staff'))) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$patient_id = (int) ($_GET['patient_id'] ?? 0);
if (!$patient_id) {
    sendJSON(['success' => false, 'message' => 'patient_id is required'], 400);
}

try {
    $db = (new Database())->getConnection();
    $userId = getCurrentUserId();
    $role   = getCurrentUserRole();

    // For doctor scope, find the doctor record and ensure the patient has at least one appt with them.
    $scopeFilter = '';
    $params = [':pid' => $patient_id];
    if ($role === 'doctor') {
        $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            sendJSON(['success' => false, 'message' => 'Doctor profile not found'], 404);
        }
        $doctor_id = (int) $doc['id'];

        $stmt = $db->prepare("SELECT 1 FROM appointments WHERE patient_id = :pid AND doctor_id = :did LIMIT 1");
        $stmt->execute([':pid' => $patient_id, ':did' => $doctor_id]);
        if (!$stmt->fetch()) {
            sendJSON(['success' => false, 'message' => 'You have not seen this patient'], 403);
        }
        $scopeFilter = ' AND a.doctor_id = :scope_did ';
        $params[':scope_did'] = $doctor_id;
    }

    // Patient profile
    $stmt = $db->prepare("
        SELECT p.id, p.full_name, p.date_of_birth, p.gender, p.contact_number, p.address,
               p.region, p.city, p.barangay, p.blood_group, p.allergies,
               p.emergency_contact_name, p.emergency_contact_number,
               u.email, u.username, u.status, u.created_at, u.last_login
          FROM patients p
          JOIN users u ON u.id = p.user_id
         WHERE p.id = :pid
         LIMIT 1
    ");
    $stmt->execute([':pid' => $patient_id]);
    $patient = $stmt->fetch();
    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient not found'], 404);
    }

    // Compute age from DOB
    $patient['age'] = null;
    if (!empty($patient['date_of_birth'])) {
        try {
            $dob = new DateTime($patient['date_of_birth']);
            $patient['age'] = (new DateTime())->diff($dob)->y;
        } catch (Exception $_) {}
    }

    // Full history rows
    $stmt = $db->prepare("
        SELECT a.id AS appointment_id, a.appointment_number, a.appointment_date, a.appointment_time,
               a.status, a.reason_for_visit, a.checked_in_at, a.completed_at, a.cancelled_at,
               a.cancelled_by, a.cancel_reason, a.created_at, a.is_followup, a.parent_appointment_id,
               d.id AS doctor_id, d.full_name AS doctor_name, d.specialization, d.license_number,
               t.chief_complaint AS triage_chief_complaint,
               t.blood_pressure  AS vital_bp,
               t.temperature     AS vital_temp,
               t.heart_rate      AS vital_hr,
               t.weight          AS vital_weight,
               t.height_cm       AS vital_height,
               t.oxygen_saturation AS vital_o2,
               t.notes           AS triage_notes,
               t.recorded_at     AS vitals_recorded_at,
               mr.id AS medical_record_id, mr.symptoms, mr.diagnosis, mr.prescription,
               mr.lab_tests_ordered, mr.notes AS record_notes, mr.follow_up_date,
               mc.id AS cert_id, mc.diagnosis AS cert_diagnosis, mc.rest_period_start,
               mc.rest_period_end, mc.rest_days, mc.requested_by AS cert_requested_by,
               mc.notes AS cert_notes, mc.issued_at AS cert_issued_at,
               r.id AS referral_id, r.specialty AS ref_specialty, r.specialty_other AS ref_specialty_other,
               r.suggested_specialist AS ref_suggested, r.reason AS ref_reason, r.urgency AS ref_urgency,
               r.issued_at AS ref_issued_at
          FROM appointments a
          JOIN doctors d ON d.id = a.doctor_id
     LEFT JOIN triage_assessments t ON t.appointment_id = a.id
     LEFT JOIN medical_records mr ON mr.appointment_id = a.id
     LEFT JOIN medical_certificates mc ON mc.appointment_id = a.id
     LEFT JOIN referrals r ON r.appointment_id = a.id
         WHERE a.patient_id = :pid
         $scopeFilter
         ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Reshape each row into nested objects
    $history = array_map(function ($row) {
        $vitals = $row['vital_bp'] !== null ? [
            'chief_complaint'   => $row['triage_chief_complaint'],
            'blood_pressure'    => $row['vital_bp'],
            'temperature'       => $row['vital_temp'],
            'heart_rate'        => $row['vital_hr'],
            'weight'            => $row['vital_weight'],
            'height_cm'         => $row['vital_height'],
            'oxygen_saturation' => $row['vital_o2'],
            'notes'             => $row['triage_notes'],
            'recorded_at'       => $row['vitals_recorded_at'],
        ] : null;

        $record = $row['medical_record_id'] ? [
            'id'                => (int) $row['medical_record_id'],
            'symptoms'          => $row['symptoms'],
            'diagnosis'         => $row['diagnosis'],
            'prescription'      => $row['prescription'],
            'lab_tests_ordered' => $row['lab_tests_ordered'],
            'notes'             => $row['record_notes'],
            'follow_up_date'    => $row['follow_up_date'],
        ] : null;

        $cert = $row['cert_id'] ? [
            'id'                => (int) $row['cert_id'],
            'diagnosis'         => $row['cert_diagnosis'],
            'rest_period_start' => $row['rest_period_start'],
            'rest_period_end'   => $row['rest_period_end'],
            'rest_days'         => (int) $row['rest_days'],
            'requested_by'      => $row['cert_requested_by'],
            'notes'             => $row['cert_notes'],
            'issued_at'         => $row['cert_issued_at'],
        ] : null;

        $referral = $row['referral_id'] ? [
            'id'                   => (int) $row['referral_id'],
            'specialty'            => $row['ref_specialty'],
            'specialty_other'      => $row['ref_specialty_other'],
            'suggested_specialist' => $row['ref_suggested'],
            'reason'               => $row['ref_reason'],
            'urgency'              => $row['ref_urgency'],
            'issued_at'            => $row['ref_issued_at'],
        ] : null;

        return [
            'appointment' => [
                'id'                    => (int) $row['appointment_id'],
                'appointment_number'    => $row['appointment_number'],
                'appointment_date'      => $row['appointment_date'],
                'appointment_time'      => $row['appointment_time'],
                'status'                => $row['status'],
                'reason_for_visit'      => $row['reason_for_visit'],
                'checked_in_at'         => $row['checked_in_at'],
                'completed_at'          => $row['completed_at'],
                'cancelled_at'          => $row['cancelled_at'],
                'cancelled_by'          => $row['cancelled_by'],
                'cancel_reason'         => $row['cancel_reason'],
                'is_followup'           => (int) $row['is_followup'],
                'parent_appointment_id' => $row['parent_appointment_id'] !== null ? (int) $row['parent_appointment_id'] : null,
                'created_at'            => $row['created_at'],
            ],
            'doctor' => [
                'id'             => (int) $row['doctor_id'],
                'full_name'      => $row['doctor_name'],
                'specialization' => $row['specialization'],
                'license_number' => $row['license_number'],
            ],
            'vitals'         => $vitals,
            'medical_record' => $record,
            'certificate'    => $cert,
            'referral'       => $referral,
        ];
    }, $rows);

    sendJSON([
        'success' => true,
        'patient' => $patient,
        'history' => $history,
        'count'   => count($history),
    ]);
} catch (Exception $e) {
    error_log("get-patient-history error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to load patient history'], 500);
}
