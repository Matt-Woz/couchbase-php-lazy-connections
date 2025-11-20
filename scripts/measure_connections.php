<?php

$nodesFile = __DIR__ . "/../config/nodes.json";
$data = json_decode(file_get_contents($nodesFile), true);

$nodes = array_keys($data);

$username = "Administrator";
$password = "password";

$results = [];

foreach ($nodes as $node) {
    $url = "http://{$node}:8091/metrics";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

    $data = curl_exec($ch);

    if ($data === false) {
        $results[$node] = ["error" => curl_error($ch)];
        continue;
    }

    if (preg_match('/kv_curr_connections\s+(\d+)/', $data, $m)) {
        $results[$node] = [
            "kv_curr_connections" => (int)$m[1]
        ];
    } else {
        $results[$node] = ["kv_curr_connections" => null];
    }

    curl_close($ch);
}

echo json_encode($results, JSON_PRETTY_PRINT);
