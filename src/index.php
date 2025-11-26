<?php

use Couchbase\Cluster;
use Couchbase\ClusterOptions;
require_once "vendor/autoload.php";

$data = json_decode(file_get_contents('/config/nodes.json'), true);

$nodeConfigs = [];
foreach ($data as $host => $id) {
    $nodeConfigs[] = ['host' => $host, 'id' => $id];
}

$lazy = isset($_GET['lazy']) ? filter_var($_GET['lazy'], FILTER_VALIDATE_BOOLEAN) : null;
$bootstrapIndex = intval($_GET['bootstrap_node']);

$op = $_GET['op'] ?? 'none';
$bucket = $_GET['bucket'] ?? 'default';

if ($lazy === null) die("Parameter 'lazy' is required\n");
if ($bootstrapIndex === null) die("Parameter 'bootstrap_node' is required\n");
if (!isset($nodeConfigs[$bootstrapIndex])) die("Invalid bootstrap_node index\n");

$bootstrapNode = $nodeConfigs[$bootstrapIndex]['host'];

$opNodesParam = $_GET['op_nodes'] ?? '';
$opIndexes = ($opNodesParam === '') ? [] : array_map('intval', explode(',', $opNodesParam));

$opts = new ClusterOptions();
$opts = $opts->credentials("Administrator", "password");

$connStr = "couchbase://$bootstrapNode" . ($lazy ? "?enable_lazy_connections=true" : "");
echo "Connecting to $bootstrapNode with connstring: $connStr\n";

$cluster = Cluster::connect($connStr, $opts);

echo "Getting default collection on bucket '$bucket'\n";
$collection = $cluster->bucket($bucket)->defaultCollection();

$latencyRecords = [];
if ($op === 'get') {
    foreach ($opIndexes as $i) {
        if (!isset($nodeConfigs[$i])) continue;

        $node = $nodeConfigs[$i];
        $t0 = hrtime(true);
        $collection->get($node['id']);
        $t1 = hrtime(true);

        $latMs = ($t1 - $t0) / 1_000_000;
        $latencyRecords[] = [
            "node_index" => $i,
            "node_host"  => $node["host"],
            "doc_id"     => $node["id"],
            "latency_ms" => $latMs
        ];

        echo "GET {$node['id']} (node {$node['host']}) done\n";
    }
} else if ($op == 'none') {
    echo "No operation performed\n";
} else {
    die("Unknown operation: $op\n");
}

echo "Completed\n";

echo "\n===JSON===\n";
echo json_encode([
    "latency_records" => $latencyRecords,
    "avg_latency_ms" => count($latencyRecords)
        ? array_sum(array_column($latencyRecords, "latency_ms")) / count($latencyRecords)
        : null
]);
echo "\n===ENDJSON===\n";
