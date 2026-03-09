<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Display page — public access, no session required
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ─── POLL FOR NEW COMMANDS (global — any event) ───
    case 'poll':
        $sql = "SELECT TOP 1 c.*, p.name AS prize_name, p.quantity AS prize_total,
                e.title AS event_title, e.display_seconds,
                (SELECT COUNT(*) FROM random_winners w2 WHERE w2.prize_id = c.prize_id AND w2.is_revoked = 0) AS prize_awarded
                FROM random_commands c
                JOIN random_events e ON c.event_id = e.id
                LEFT JOIN random_prizes p ON c.prize_id = p.id
                WHERE c.status = 'pending'
                ORDER BY c.created_at ASC";
        $cmd = dbQuery($conn, $sql, []);

        if ($cmd && count($cmd) > 0) {
            $command = $cmd[0];
            $commandType = $command['command'];

            // Mark as processing
            dbExecute($conn, "UPDATE random_commands SET status = 'processing' WHERE id = ?", [$command['id']]);

            $responseData = [
                'has_command' => true,
                'command' => $command
            ];

            if ($commandType === 'start') {
                $winners = dbQuery($conn, 
                    "SELECT TOP (?) w.*, rp.name AS participant_name
                     FROM random_winners w 
                     JOIN random_participants rp ON w.participant_id = rp.id
                     WHERE w.event_id = ? AND w.prize_id = ?
                     ORDER BY w.created_at DESC",
                    [$command['draw_count'], $command['event_id'], $command['prize_id']]);

                $names = dbQuery($conn, 
                    "SELECT name FROM random_participants WHERE event_id = ? AND is_eligible = 1 ORDER BY NEWID()",
                    [$command['event_id']]);

                $responseData['winners'] = $winners ?? [];
                $responseData['participant_names'] = array_column($names ?? [], 'name');

                dbExecute($conn, "UPDATE random_commands SET status = 'completed' WHERE id = ?", [$command['id']]);
            } elseif ($commandType === 'select') {
                $remaining = ($command['prize_total'] ?? 0) - ($command['prize_awarded'] ?? 0);
                $responseData['remaining'] = $remaining;
                $responseData['total'] = $command['prize_total'] ?? 0;

                dbExecute($conn, "UPDATE random_commands SET status = 'completed' WHERE id = ?", [$command['id']]);
            }

            jsonSuccess($responseData);
        } else {
            jsonSuccess(['has_command' => false]);
        }
        break;

    // ─── MARK COMMAND COMPLETE ───
    case 'complete':
        $cmdId = (int)($_POST['command_id'] ?? 0);
        $result = dbExecute($conn, "UPDATE random_commands SET status = 'completed' WHERE id = ?", [$cmdId]);
        jsonSuccess([], 'Completed');
        break;

    default:
        jsonError('Invalid action');
}
?>
