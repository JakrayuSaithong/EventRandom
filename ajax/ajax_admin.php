<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!checkSession()) {
    jsonError('Unauthorized', 401);
}

$empCode = getEmpCode();
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ─── GET ALL EVENTS ───
    case 'get_events':
        $sql = "SELECT e.*, 
                (SELECT COUNT(*) FROM random_participants WHERE event_id = e.id AND is_eligible = 1) AS participant_count,
                (SELECT COUNT(*) FROM random_prizes WHERE event_id = e.id) AS prize_count,
                (SELECT COUNT(*) FROM random_winners WHERE event_id = e.id AND is_revoked = 0) AS winner_count
                FROM random_events e 
                WHERE e.created_by = ? AND e.status = 'active'
                ORDER BY e.created_at DESC";
        $results = dbQuery($conn, $sql, [$empCode]);
        if ($results === false) jsonError('Database error');
        jsonSuccess(['events' => $results]);
        break;

    // ─── GET SINGLE EVENT ───
    case 'get_event':
        $id = (int)($_POST['id'] ?? 0);
        $sql = "SELECT * FROM random_events WHERE id = ? AND created_by = ?";
        $results = dbQuery($conn, $sql, [$id, $empCode]);
        if (!$results) jsonError('Event not found');
        jsonSuccess(['event' => $results[0]]);
        break;

    // ─── CREATE EVENT ───
    case 'create_event':
        $title = sanitizeInput($_POST['title'] ?? '');
        $allowDup = (int)($_POST['allow_duplicate'] ?? 0);
        $displaySeconds = max(3, min(60, (int)($_POST['display_seconds'] ?? 8)));
        
        if (empty($title)) jsonError('กรุณาใส่ชื่อรายการ');

        $sql = "INSERT INTO random_events (title, allow_duplicate, display_seconds, created_by) VALUES (?, ?, ?, ?)";
        $result = dbExecute($conn, $sql, [$title, $allowDup, $displaySeconds, $empCode]);
        if ($result === false) jsonError('Failed to create event');
        
        $newId = dbLastInsertId($conn);
        jsonSuccess(['event_id' => $newId], 'สร้างรายการสำเร็จ');
        break;

    // ─── UPDATE EVENT ───
    case 'update_event':
        $id = (int)($_POST['id'] ?? 0);
        $title = sanitizeInput($_POST['title'] ?? '');
        $allowDup = (int)($_POST['allow_duplicate'] ?? 0);
        $displaySeconds = max(3, min(60, (int)($_POST['display_seconds'] ?? 8)));
        
        if (empty($title)) jsonError('กรุณาใส่ชื่อรายการ');

        $sql = "UPDATE random_events SET title = ?, allow_duplicate = ?, display_seconds = ?, updated_at = GETDATE() 
                WHERE id = ? AND created_by = ?";
        $result = dbExecute($conn, $sql, [$title, $allowDup, $displaySeconds, $id, $empCode]);
        if ($result === false) jsonError('Failed to update event');
        jsonSuccess([], 'อัปเดตสำเร็จ');
        break;

    // ─── DELETE EVENT ───
    case 'delete_event':
        $id = (int)($_POST['id'] ?? 0);
        $sql = "UPDATE random_events SET status = 'archived' WHERE id = ? AND created_by = ?";
        $result = dbExecute($conn, $sql, [$id, $empCode]);
        if ($result === false) jsonError('Failed to delete event');
        jsonSuccess([], 'ลบรายการสำเร็จ');
        break;

    // ─── ADD PARTICIPANTS (bulk) ───
    case 'add_participants':
        $eventId = (int)($_POST['event_id'] ?? 0);
        $names = $_POST['names'] ?? '';
        
        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');

        $nameList = preg_split('/[\n,]+/', $names);
        $nameList = array_filter(array_map('trim', $nameList));
        
        if (empty($nameList)) jsonError('กรุณาใส่รายชื่อ');

        $added = 0;
        foreach ($nameList as $name) {
            $name = sanitizeInput($name);
            if (!empty($name)) {
                $r = dbExecute($conn, "INSERT INTO random_participants (event_id, name) VALUES (?, ?)", [$eventId, $name]);
                if ($r !== false) $added++;
            }
        }
        jsonSuccess(['added' => $added], "เพิ่มรายชื่อ {$added} คนสำเร็จ");
        break;

    // ─── BATCH DISQUALIFY ───
    case 'batch_disqualify':
        $eventId = (int)($_POST['event_id'] ?? 0);
        $names = $_POST['names'] ?? '';

        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');

        $nameList = preg_split('/[\n,]+/', $names);
        $nameList = array_filter(array_map('trim', $nameList));

        if (empty($nameList)) jsonError('กรุณาใส่รายชื่อ');

        $disqualified = 0;
        foreach ($nameList as $name) {
            $name = sanitizeInput($name);
            if (!empty($name)) {
                $r = dbExecute($conn, 
                    "UPDATE random_participants SET is_eligible = 0 WHERE event_id = ? AND name = ? AND is_eligible = 1", 
                    [$eventId, $name]);
                if ($r !== false && $r > 0) $disqualified++;
            }
        }
        $notFound = count($nameList) - $disqualified;
        $msg = "ตัดสิทธิ์ {$disqualified} คนสำเร็จ";
        if ($notFound > 0) $msg .= " (ไม่พบ {$notFound} ชื่อ)";
        jsonSuccess(['disqualified' => $disqualified, 'not_found' => $notFound], $msg);
        break;

    // ─── GET PARTICIPANTS ───
    case 'get_participants':
        $eventId = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');

        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM random_winners w WHERE w.participant_id = p.id AND w.is_revoked = 0) AS win_count
                FROM random_participants p WHERE p.event_id = ? ORDER BY p.name";
        $results = dbQuery($conn, $sql, [$eventId]);
        jsonSuccess(['participants' => $results ?? []]);
        break;

    // ─── TOGGLE PARTICIPANT ELIGIBILITY ───
    case 'toggle_participant':
        $id = (int)($_POST['id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);
        
        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');

        $sql = "UPDATE random_participants SET is_eligible = CASE WHEN is_eligible = 1 THEN 0 ELSE 1 END WHERE id = ? AND event_id = ?";
        $result = dbExecute($conn, $sql, [$id, $eventId]);
        if ($result === false) jsonError('Operation failed');
        jsonSuccess([], 'อัปเดตสิทธิ์สำเร็จ');
        break;

    // ─── DELETE PARTICIPANT ───
    case 'delete_participant':
        $id = (int)($_POST['id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);
        
        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');

        $sql = "DELETE FROM random_participants WHERE id = ? AND event_id = ?";
        $result = dbExecute($conn, $sql, [$id, $eventId]);
        if ($result === false) jsonError('Failed to delete');
        jsonSuccess([], 'ลบรายชื่อสำเร็จ');
        break;

    // ─── ADD PRIZE ───
    case 'add_prize':
        $eventId = (int)($_POST['event_id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        
        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');
        if (empty($name)) jsonError('กรุณาใส่ชื่อรางวัล');

        $maxOrder = dbQuery($conn, "SELECT ISNULL(MAX(sort_order), 0) + 1 AS next_order FROM random_prizes WHERE event_id = ?", [$eventId]);
        $sortOrder = $maxOrder[0]['next_order'] ?? 1;

        $sql = "INSERT INTO random_prizes (event_id, name, quantity, sort_order) VALUES (?, ?, ?, ?)";
        $result = dbExecute($conn, $sql, [$eventId, $name, $quantity, $sortOrder]);
        if ($result === false) jsonError('Failed to add prize');
        jsonSuccess(['prize_id' => dbLastInsertId($conn)], 'เพิ่มรางวัลสำเร็จ');
        break;

    // ─── UPDATE PRIZE ───
    case 'update_prize':
        $id = (int)($_POST['id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');
        if (empty($name)) jsonError('กรุณาใส่ชื่อรางวัล');

        $sql = "UPDATE random_prizes SET name = ?, quantity = ? WHERE id = ? AND event_id = ?";
        $result = dbExecute($conn, $sql, [$name, $quantity, $id, $eventId]);
        if ($result === false) jsonError('Failed to update prize');
        jsonSuccess([], 'อัปเดตรางวัลสำเร็จ');
        break;

    // ─── GET PRIZES ───
    case 'get_prizes':
        $eventId = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');

        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM random_winners w WHERE w.prize_id = p.id AND w.is_revoked = 0) AS awarded_count
                FROM random_prizes p WHERE p.event_id = ? ORDER BY p.sort_order";
        $results = dbQuery($conn, $sql, [$eventId]);
        jsonSuccess(['prizes' => $results ?? []]);
        break;

    // ─── DELETE PRIZE ───
    case 'delete_prize':
        $id = (int)($_POST['id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);
        
        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');

        $winnerCheck = dbQuery($conn, "SELECT COUNT(*) as cnt FROM random_winners WHERE prize_id = ? AND is_revoked = 0", [$id]);
        if ($winnerCheck && $winnerCheck[0]['cnt'] > 0) {
            jsonError('ไม่สามารถลบรางวัลที่มีผู้ได้รับแล้ว');
        }

        $sql = "DELETE FROM random_prizes WHERE id = ? AND event_id = ?";
        $result = dbExecute($conn, $sql, [$id, $eventId]);
        if ($result === false) jsonError('Failed to delete');
        jsonSuccess([], 'ลบรางวัลสำเร็จ');
        break;

    // ─── GET WINNERS ───
    case 'get_winners':
        $eventId = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');

        $sql = "SELECT w.*, p.name AS participant_name, p.emp_code AS participant_emp_code, 
                pr.name AS prize_name
                FROM random_winners w
                JOIN random_participants p ON w.participant_id = p.id
                JOIN random_prizes pr ON w.prize_id = pr.id
                WHERE w.event_id = ?
                ORDER BY w.created_at DESC";
        $results = dbQuery($conn, $sql, [$eventId]);
        jsonSuccess(['winners' => $results ?? []]);
        break;

    // ─── REVOKE WINNER ───
    case 'revoke_winner':
        $id = (int)($_POST['id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);
        
        $check = dbQuery($conn, "SELECT id FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$check) jsonError('Event not found');

        $sql = "UPDATE random_winners SET is_revoked = CASE WHEN is_revoked = 1 THEN 0 ELSE 1 END WHERE id = ? AND event_id = ?";
        $result = dbExecute($conn, $sql, [$id, $eventId]);
        if ($result === false) jsonError('Operation failed');
        jsonSuccess([], 'อัปเดตสำเร็จ');
        break;

    default:
        jsonError('Invalid action');
}
?>
