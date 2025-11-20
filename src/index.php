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
$bootstrapIndex = isset($_GET['bootstrap_node']) ? intval($_GET['bootstrap_node']) : null;
$opNodesParam = $_GET['op_nodes'] ?? '';
$op = $_GET['op'] ?? 'none';
$bucket = $_GET['bucket'] ?? 'default';

if ($lazy === null) die("Parameter 'lazy' is required\n");
if ($bootstrapIndex === null) die("Parameter 'bootstrap_node' is required\n");
if (!isset($nodeConfigs[$bootstrapIndex])) die("Invalid bootstrap_node index\n");

$bootstrapNode = $nodeConfigs[$bootstrapIndex]['host'];

if ($opNodesParam === '') {
    $opIndexes = [$bootstrapIndex];
} elseif ($opNodesParam === 'all') {
    $opIndexes = array_keys($nodeConfigs);
} else {
    $opIndexes = array_map('intval', explode(',', $opNodesParam));
}

$opts = new ClusterOptions();
$opts = $opts->credentials("Administrator", "password");

$connStr = "couchbase://$bootstrapNode" . ($lazy ? "?enable_lazy_connections=true" : "");
echo "Connecting to $bootstrapNode with connstring: $connStr\n";

$cluster = Cluster::connect($connStr, $opts);

echo "Getting default collection on bucket '$bucket'\n";
$collection = $cluster->bucket($bucket)->defaultCollection();

if ($op === 'get') {
    foreach ($opIndexes as $i) {
        if (!isset($nodeConfigs[$i])) continue;

        $node = $nodeConfigs[$i];
        $collection->get($node['id']);
        echo "GET {$node['id']} (node {$node['host']}) done\n";
    }
} else if ($op == 'none') {
    echo "No operation performed\n";
} else {
    die("Unknown operation: $op\n");
}

echo "Completed\n";
