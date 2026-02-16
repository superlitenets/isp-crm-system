<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/HuaweiOLT.php';

$jobId = (int)($argv[1] ?? 0);
if (!$jobId) {
    echo "Usage: php tr069_bulk_worker.php <job_id>\n";
    exit(1);
}

$db = getDbConnection();

$stmt = $db->prepare("SELECT * FROM background_jobs WHERE id = ? AND status = 'pending'");
$stmt->execute([$jobId]);
$job = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$job) {
    echo "Job {$jobId} not found or already processed\n";
    exit(1);
}

$params = json_decode($job['params'], true);
$oltId = (int)($params['olt_id'] ?? 0);
$profileId = (int)($params['profile_id'] ?? 3);
$targetSlot = isset($params['target_slot']) ? (int)$params['target_slot'] : null;

$db->prepare("UPDATE background_jobs SET status = 'running', started_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$jobId]);

try {
    $huaweiOLT = new \App\HuaweiOLT($db);

    $sql = "SELECT id, frame, slot, port, onu_id, sn, name FROM huawei_onus WHERE olt_id = ? AND is_authorized = true AND onu_id IS NOT NULL";
    $sqlParams = [$oltId];
    if ($targetSlot !== null) {
        $sql .= " AND slot = ?";
        $sqlParams[] = $targetSlot;
    }
    $sql .= " ORDER BY slot, port, onu_id";
    $stmt = $db->prepare($sql);
    $stmt->execute($sqlParams);
    $onus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $total = count($onus);
    $db->prepare("UPDATE background_jobs SET total = ? WHERE id = ?")->execute([$total, $jobId]);

    if (empty($onus)) {
        $slotLabel = $targetSlot !== null ? " on slot {$targetSlot}" : '';
        $db->prepare("UPDATE background_jobs SET status = 'completed', message = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute(["No authorized ONUs found{$slotLabel}", $jobId]);
        exit(0);
    }

    $slotGroups = [];
    foreach ($onus as $onu) {
        $key = ($onu['frame'] ?? 0) . '/' . $onu['slot'];
        $slotGroups[$key][] = $onu;
    }

    $bound = 0;
    $failed = 0;
    $errors = [];
    $processed = 0;

    foreach ($slotGroups as $slotKey => $slotOnus) {
        try {
            $scriptLines = [];
            $scriptLines[] = "interface gpon {$slotKey}";
            foreach ($slotOnus as $onu) {
                $port = $onu['port'];
                $onuPortId = $onu['onu_id'];
                $scriptLines[] = "ont tr069-server-config {$port} {$onuPortId} profile-id 0";
                $scriptLines[] = "ont tr069-server-config {$port} {$onuPortId} profile-id {$profileId}";
            }
            $scriptLines[] = "quit";

            $slotTimeout = max(120000, count($slotOnus) * 3000);
            $result = $huaweiOLT->executeCommand($oltId, implode("\r\n", $scriptLines), false, $slotTimeout);
            $out = $result['output'] ?? '';

            if ($result['success'] && !preg_match('/Failure|Error:|failed|Invalid/i', $out)) {
                $bound += count($slotOnus);
            } elseif ($result['success'] && preg_match('/already|repeatedly/i', $out)) {
                $bound += count($slotOnus);
            } else {
                foreach ($slotOnus as $onu) {
                    $failed++;
                    if (count($errors) < 10) {
                        $errors[] = ($onu['name'] ?: $onu['sn']) . ": " . substr($out, 0, 100);
                    }
                }
            }
        } catch (\Exception $e) {
            foreach ($slotOnus as $onu) {
                $failed++;
                if (count($errors) < 10) {
                    $errors[] = ($onu['name'] ?: $onu['sn']) . ": " . $e->getMessage();
                }
            }
        }

        $processed += count($slotOnus);
        $db->prepare("UPDATE background_jobs SET progress = ?, message = ? WHERE id = ?")
            ->execute([$processed, "Processing slot {$slotKey}: {$processed}/{$total} ONUs", $jobId]);

        if ($processed < $total) {
            usleep(500000);
        }
    }

    $slotLabel = $targetSlot !== null ? " (Slot {$targetSlot})" : " (All slots)";
    $summary = "TR-069 profile {$profileId} bound to {$bound}/{$total} ONUs{$slotLabel}";
    if ($failed > 0) {
        $summary .= " | {$failed} failed";
    }
    $summary .= ' | ONUs will auto-register in GenieACS on next Inform (no reboot)';
    if (!empty($errors)) {
        $summary .= ' | Errors: ' . implode('; ', array_slice($errors, 0, 5));
    }

    $resultData = json_encode([
        'bound' => $bound,
        'failed' => $failed,
        'total' => $total,
        'errors' => array_slice($errors, 0, 10)
    ]);

    $status = $failed === 0 ? 'completed' : 'completed_with_errors';
    $db->prepare("UPDATE background_jobs SET status = ?, progress = ?, message = ?, result = ?::jsonb, completed_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$status, $total, $summary, $resultData, $jobId]);

    $huaweiOLT->addLog([
        'olt_id' => $oltId,
        'action' => 'setup_tr069_full',
        'status' => $failed > 0 ? 'partial' : 'success',
        'message' => $summary,
        'user_id' => $job['user_id']
    ]);

    echo "Job {$jobId} completed: {$summary}\n";

} catch (\Exception $e) {
    $db->prepare("UPDATE background_jobs SET status = 'failed', message = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$e->getMessage(), $jobId]);
    echo "Job {$jobId} failed: " . $e->getMessage() . "\n";
    exit(1);
}
