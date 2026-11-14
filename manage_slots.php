<?php
require_once __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

require_admin();
$data = read_json_request();
$action = $data['action'] ?? '';

try {
    $conn = get_db();

    if ($action === 'list') {
        $stmt = $conn->prepare(
            "SELECT id, appointment_date, start_time, end_time, status
             FROM availability_slots
             WHERE appointment_date >= :today
             ORDER BY appointment_date, start_time"
        );
        $stmt->execute([':today' => date('Y-m-d')]);
        json_response(['success' => true, 'slots' => $stmt->fetchAll()]);
    }

    if ($action === 'delete') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            json_response(['success' => false, 'message' => 'Choose a valid available slot.'], 400);
        }

        $stmt = $conn->prepare("DELETE FROM availability_slots WHERE id = :id AND status = 'Available'");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            json_response(['success' => false, 'message' => 'That slot is no longer available to remove.'], 409);
        }
        json_response(['success' => true]);
    }

    if ($action !== 'add') {
        json_response(['success' => false, 'message' => 'Choose a valid slot action.'], 400);
    }

    $date = $data['date'] ?? '';
    $start = $data['startTime'] ?? '';
    $end = $data['endTime'] ?? '';
    $parsedDate = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validDate = $parsedDate !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $parsedDate->format('Y-m-d') === $date
        && ($date > date('Y-m-d') || ($date === date('Y-m-d') && $start >= date('H:i')));
    $validTime = is_string($start) && is_string($end)
        && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
        && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
        && $start < $end;

    if (!$validDate || !$validTime) {
        json_response(['success' => false, 'message' => 'Choose a future date and a valid start and end time.'], 400);
    }

    $conn->beginTransaction();
    $overlap = $conn->prepare(
        "SELECT id FROM availability_slots
         WHERE appointment_date = :date
           AND status <> 'Closed'
           AND start_time < :end_time
           AND end_time > :start_time
         LIMIT 1"
    );
    $overlap->execute([':date' => $date, ':end_time' => $end, ':start_time' => $start]);
    if ($overlap->fetch()) {
        $conn->rollBack();
        json_response(['success' => false, 'message' => 'That time overlaps an existing slot.'], 409);
    }

    $insert = $conn->prepare(
        "INSERT INTO availability_slots (appointment_date, start_time, end_time, status)
         VALUES (:date, :start_time, :end_time, 'Available')"
    );
    $insert->execute([':date' => $date, ':start_time' => $start, ':end_time' => $end]);
    $conn->commit();
    json_response(['success' => true]);
} catch (PDOException $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Could not create appointment slot: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Could not save this slot. Please try again.'], 500);
}
