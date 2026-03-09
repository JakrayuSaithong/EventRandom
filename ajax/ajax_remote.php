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

    // ─── GET ALL ACTIVE EVENTS (for remote to pick) ───
    case 'get_events':
        $sql = "SELECT e.id, e.title, e.allow_duplicate, e.created_by,
                (SELECT COUNT(*) FROM random_participants WHERE event_id = e.id AND is_eligible = 1) AS participant_count,
                (SELECT COUNT(*) FROM random_prizes WHERE event_id = e.id) AS prize_count
                FROM random_events e 
                WHERE e.created_by = ? AND e.status = 'active'
                ORDER BY e.created_at DESC";
        $results = dbQuery($conn, $sql, [$empCode]);
        jsonSuccess(['events' => $results ?? []]);
        break;

    // ─── GET PRIZES FOR AN EVENT ───
    case 'get_prizes':
        $eventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM random_winners w WHERE w.prize_id = p.id AND w.is_revoked = 0) AS awarded_count
                FROM random_prizes p 
                JOIN random_events e ON p.event_id = e.id
                WHERE p.event_id = ? AND e.created_by = ?
                ORDER BY p.sort_order";
        $results = dbQuery($conn, $sql, [$eventId, $empCode]);
        jsonSuccess(['prizes' => $results ?? []]);
        break;

    // ─── SEND DRAW COMMAND ───
    case 'draw':
        $eventId  = (int)($_POST['event_id'] ?? 0);
        $prizeId  = (int)($_POST['prize_id'] ?? 0);
        $drawCount = max(1, (int)($_POST['draw_count'] ?? 1));
        $drawMode  = in_array($_POST['draw_mode'] ?? '', ['one_by_one', 'all_at_once']) 
                     ? $_POST['draw_mode'] : 'one_by_one';
        
        // Verify ownership
        $event = dbQuery($conn, "SELECT * FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$event) jsonError('Event not found');

        // Verify prize exists and belongs to event
        $prize = dbQuery($conn, "SELECT * FROM random_prizes WHERE id = ? AND event_id = ?", [$prizeId, $eventId]);
        if (!$prize) jsonError('Prize not found');

        // Calculate remaining slots for this prize
        $awarded = dbQuery($conn, "SELECT COUNT(*) AS cnt FROM random_winners WHERE prize_id = ? AND is_revoked = 0", [$prizeId]);
        $awardedCount = $awarded[0]['cnt'] ?? 0;
        $remaining = $prize[0]['quantity'] - $awardedCount;

        if ($remaining <= 0) jsonError('รางวัลนี้ถูกสุ่มครบแล้ว');
        if ($drawCount > $remaining) jsonError("เหลือรางวัลอีก {$remaining} สิทธิ์เท่านั้น");

        // Get eligible participants
        $allowDup = $event[0]['allow_duplicate'];
        if ($allowDup) {
            $eligible = dbQuery($conn, 
                "SELECT id, name FROM random_participants WHERE event_id = ? AND is_eligible = 1", 
                [$eventId]);
        } else {
            // Exclude those who already won any prize in this event
            $eligible = dbQuery($conn, 
                "SELECT id, name FROM random_participants 
                 WHERE event_id = ? AND is_eligible = 1 
                 AND id NOT IN (SELECT participant_id FROM random_winners WHERE event_id = ? AND is_revoked = 0)", 
                [$eventId, $eventId]);
        }

        if (!$eligible || count($eligible) < $drawCount) {
            jsonError('ผู้มีสิทธิ์ไม่เพียงพอสำหรับการสุ่ม (' . count($eligible ?? []) . ' คน)');
        }

        // Random pick
        $indices = array_rand($eligible, min($drawCount, count($eligible)));
        if (!is_array($indices)) $indices = [$indices];
        
        $winners = [];
        foreach ($indices as $idx) {
            $participant = $eligible[$idx];
            // Insert winner
            $r = dbExecute($conn, 
                "INSERT INTO random_winners (event_id, prize_id, participant_id) VALUES (?, ?, ?)", 
                [$eventId, $prizeId, $participant['id']]);
            if ($r !== false) {
                $winners[] = [
                    'participant_id' => $participant['id'],
                    'name' => $participant['name']
                ];
            }
        }

        // Insert command for display page
        $r = dbExecute($conn, 
            "INSERT INTO random_commands (event_id, command, prize_id, draw_count, draw_mode, status) 
             VALUES (?, 'start', ?, ?, ?, 'pending')", 
            [$eventId, $prizeId, $drawCount, $drawMode]);

        $cmdId = dbLastInsertId($conn);

        jsonSuccess([
            'winners' => $winners, 
            'command_id' => $cmdId,
            'prize_name' => $prize[0]['name']
        ], 'สุ่มสำเร็จ!');
        break;

    // ─── GET DRAW STATUS ───
    case 'draw_status':
        $cmdId = (int)($_GET['command_id'] ?? 0);
        $cmd = dbQuery($conn, "SELECT * FROM random_commands WHERE id = ?", [$cmdId]);
        if (!$cmd) jsonError('Command not found');
        jsonSuccess(['command' => $cmd[0]]);
        break;

    // ─── SELECT PRIZE (notify Display page) ───
    case 'select_prize':
        $eventId = (int)($_POST['event_id'] ?? 0);
        $prizeId = (int)($_POST['prize_id'] ?? 0);

        // Verify ownership
        $event = dbQuery($conn, "SELECT * FROM random_events WHERE id = ? AND created_by = ?", [$eventId, $empCode]);
        if (!$event) jsonError('Event not found');

        $prize = dbQuery($conn, "SELECT * FROM random_prizes WHERE id = ? AND event_id = ?", [$prizeId, $eventId]);
        if (!$prize) jsonError('Prize not found');

        // Calculate remaining
        $awarded = dbQuery($conn, "SELECT COUNT(*) AS cnt FROM random_winners WHERE prize_id = ? AND is_revoked = 0", [$prizeId]);
        $awardedCount = $awarded[0]['cnt'] ?? 0;
        $remaining = $prize[0]['quantity'] - $awardedCount;

        // Insert select command (display will pick this up)
        dbExecute($conn, 
            "INSERT INTO random_commands (event_id, command, prize_id, draw_count, draw_mode, status) 
             VALUES (?, 'select', ?, 0, 'one_by_one', 'pending')", 
            [$eventId, $prizeId]);

        jsonSuccess([
            'event_title' => $event[0]['title'],
            'prize_name' => $prize[0]['name'],
            'remaining' => $remaining,
            'total' => $prize[0]['quantity']
        ], 'Prize selected');
        break;

    default:
        jsonError('Invalid action');
}
?>
