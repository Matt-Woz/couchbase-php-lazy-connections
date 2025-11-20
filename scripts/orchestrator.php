<?php

$resultsDir = __DIR__ . "/../results/" . date("Ymd_His");
mkdir($resultsDir, 0777, true);

$experiments = json_decode(file_get_contents(__DIR__ . '/../config/experiments.json'), true);

function calculateDelta($before, $after) {
    $delta = [];
    $overallDelta = 0;

    foreach ($before as $node => $metrics) {
        if (isset($after[$node])) {
            $nodeDelta = $after[$node]["kv_curr_connections"] - $metrics["kv_curr_connections"];
            $delta[$node] = ["kv_curr_connections" => $nodeDelta];
            $overallDelta += $nodeDelta;
        }
    }

    $delta["overall"] = $overallDelta;
    return $delta;
}

function resetFpmWorkers() {
    shell_exec("docker exec src-sdk-test pkill -9 -f 'php-fpm: pool'");
    usleep(200000);
}


function buildUrl($exp) {
    return "http://localhost:8080?"
        . "lazy={$exp['lazy']}"
        . "&bootstrap_node={$exp['bootstrap_node']}"
        . "&op_nodes={$exp['op_nodes']}"
        . "&op={$exp['op']}"
        . "&bucket={$exp['bucket']}";
}

function runRequests($exp) {
    $url = buildUrl($exp);
    $multiHandle = curl_multi_init();
    $handles = [];

    for ($j = 0; $j < $exp['concurrency']; $j++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_multi_add_handle($multiHandle, $ch);
        $handles[] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running);

    foreach ($handles as $ch) {
        curl_multi_remove_handle($multiHandle, $ch);
    }

    curl_multi_close($multiHandle);
}

function getConnections() {
    return shell_exec("php " . __DIR__ . "/measure_connections.php");
}

foreach ($experiments as $experiment) {
    echo "Running experiment: {$experiment['name']}\n";

    $combinedResult = [
        "experiment" => $experiment,
        "results" => [],
        "timestamp" => date("c"),
    ];

    foreach (["false", "true"] as $lazyValue) {
        foreach ($experiment["concurrency_levels"] as $concurrency) {
            echo "  - lazy=$lazyValue concurrency=$concurrency\n";
            resetFpmWorkers();
            $run = $experiment;
            unset($run["concurrency_levels"]);
            $run["lazy"] = $lazyValue;
            $run["concurrency"] = $concurrency;

            $metricsBefore = json_decode(getConnections(), true);

            runRequests($run);

            $metricsAfter = json_decode(getConnections(), true);
            $metricsDelta = calculateDelta($metricsBefore, $metricsAfter);

            $combinedResult["results"]["lazy_{$lazyValue}"][$concurrency] = [
                "metrics_before" => $metricsBefore,
                "metrics_after" => $metricsAfter,
                "metrics_delta" => $metricsDelta
            ];
        }
    }

    file_put_contents(
        "$resultsDir/{$experiment['name']}.json",
        json_encode($combinedResult, JSON_PRETTY_PRINT)
    );
}


echo "All experiments completed.\n";

echo "Generating charts...\n";

$cmd = "python plot_results.py $resultsDir";
exec($cmd);
