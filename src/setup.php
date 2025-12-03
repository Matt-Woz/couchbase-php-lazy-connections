<pre>
<?php

ini_set("couchbase.log_stderr", true);

use Couchbase\Cluster;
use Couchbase\ClusterOptions;
require_once "vendor/autoload.php";

$data = json_decode(file_get_contents('/config/nodes.json'), true);

$bootstrapNode = array_key_first($data);

$opts = new ClusterOptions();
$opts = $opts->credentials("Administrator", "password");

$connStr = "couchbase://$bootstrapNode";
printf("Connecting to $bootstrapNode with connstring: $connStr\n");

$cluster = Cluster::connect($connStr, $opts);

$bucket = $_GET['bucket'] ?? 'default';

printf("Getting default collection on bucket '$bucket'\n");
$collection = $cluster->bucket($bucket)->defaultCollection();

foreach ($data as $host => $id) {
    printf("\nUpserting '$id' for host '$host'\n");
    $res = $collection->upsert($id, ["host" => $host]);
    print_r($res);
}
