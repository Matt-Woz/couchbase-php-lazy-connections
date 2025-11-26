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

    $latencyRecords = [];
    foreach ($handles as $ch) {
        $raw = curl_multi_getcontent($ch);

        if (preg_match('/===JSON===(.*?)===ENDJSON===/s', $raw, $m)) {
            $json = json_decode(trim($m[1]), true);

            if ($json && isset($json["latency_records"])) {
                foreach ($json["latency_records"] as $rec) {
                    $latencyRecords[] = $rec;
                }
            }
        }

        curl_multi_remove_handle($multiHandle, $ch);
    }

    curl_multi_close($multiHandle);

    return [
        "latency_records" => $latencyRecords,
        "avg_latency_ms" => count($latencyRecords)
            ? array_sum(array_column($latencyRecords, "latency_ms")) / count($latencyRecords)
            : null
    ];
}

function getConnections() {
    return json_decode(shell_exec("php " . __DIR__ . "/measure_connections.php"), true);
}

foreach ($experiments as $experiment) {
    echo "Running experiment: {$experiment['name']}\n";

    $combinedResult = [
        "experiment" => $experiment,
        "results" => [],
        "timestamp" => date("c"),
    ];

    foreach ($experiment["concurrency_levels"] as $concurrency) {
        // Randomise bootstrap node
        $bootstrapPool = $experiment["bootstrap_node"];
        $bootstrapIndex = $bootstrapPool[array_rand($bootstrapPool)];

        $nodeCount = count(json_decode(file_get_contents(__DIR__ . '/../config/nodes.json'), true));

        switch ($experiment["op_nodes"]) {
            case "bootstrap":
                $opIndexes = [$bootstrapIndex];
                break;

            case "all":
                $opIndexes = range(0, $nodeCount - 1);
                break;

            case "not_bootstrap":
                $others = array_values(array_diff(range(0, $nodeCount - 1), [$bootstrapIndex]));
                $opIndexes = [$others[array_rand($others)]];
                break;

            case "":
            case "none":
                $opIndexes = [];
                break;

            default:
                $opIndexes = array_map('intval', explode(",", $experiment["op_nodes"]));
                break;
        }

        echo "  Bootstrap=$bootstrapIndex  OpNodes=" . implode(",", $opIndexes)
            . "  Concurrency=$concurrency\n";
        foreach (["false", "true"] as $lazyValue) {
            resetFpmWorkers();
            $params = [
                "lazy" => $lazyValue,
                "bootstrap_node" => $bootstrapIndex,
                "op_nodes" => implode(",", $opIndexes),
                "op" => $experiment["op"],
                "bucket" => $experiment["bucket"],
                "concurrency" => $concurrency
            ];

            $metricsBefore = getConnections();
            $lat = runRequests($params);
            $metricsAfter = getConnections();

            $metricsDelta = calculateDelta($metricsBefore, $metricsAfter);

            $combinedResult["results"]["lazy_{$lazyValue}"][$concurrency] = [
                "bootstrap_node" => $bootstrapIndex,
                "op_nodes" => $opIndexes,
                "metrics_before" => $metricsBefore,
                "metrics_after" => $metricsAfter,
                "metrics_delta" => $metricsDelta,
                "latency" => $lat,
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
