#!/usr/bin/env php
<?php
require_once __DIR__ . '/../config/database.php';

$pollInterval = (int)($argv[1] ?? 5);
$runOnce = in_array('--once', $argv);

echo "[Job Runner] Starting" . ($runOnce ? " (single run)" : " (polling every {$pollInterval}s)") . "\n";

function processJobs() {
    try {
        $db = getDbConnection();
    } catch (Exception $e) {
        echo "[Job Runner] DB connection failed: " . $e->getMessage() . "\n";
        return;
    }

    $stmt = $db->prepare("SELECT id FROM background_jobs WHERE status = 'pending' ORDER BY created_at ASC");
    $stmt->execute();
    $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($jobs as $job) {
        $jobId = (int)$job['id'];
        echo "[Job Runner] Processing job #{$jobId}\n";
        
        $workerPath = __DIR__ . '/tr069_bulk_worker.php';
        if (!file_exists($workerPath)) {
            $db->prepare("UPDATE background_jobs SET status = 'failed', message = 'Worker script not found' WHERE id = ?")->execute([$jobId]);
            continue;
        }

        $logPath = '/tmp/tr069_job_' . $jobId . '.log';
        $output = [];
        $exitCode = 0;
        exec("php " . escapeshellarg($workerPath) . " " . $jobId . " 2>&1", $output, $exitCode);
        
        $outputStr = implode("\n", $output);
        file_put_contents($logPath, $outputStr);
        
        if ($exitCode !== 0) {
            echo "[Job Runner] Job #{$jobId} exited with code {$exitCode}\n";
        } else {
            echo "[Job Runner] Job #{$jobId} completed\n";
        }
    }
}

do {
    processJobs();

    if (!$runOnce) {
        sleep($pollInterval);
    }
} while (!$runOnce);

echo "[Job Runner] Done\n";
