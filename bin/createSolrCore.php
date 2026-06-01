#!/usr/bin/php
<?php

include (__DIR__."/../config/config.php");

$coresAdminUrl = $config['solrUrl'] . 'admin/cores';
$coreName      = $config['solrCore'];
$configSetName = 'configset_v1';

// Check if core already exists
$ch = curl_init($coresAdminUrl . '?action=STATUS&core=' . urlencode($coreName));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    fwrite(STDERR, "Error: Can't connect to Solr Admin API at $coresAdminUrl (HTTP $httpCode).\n");
    fwrite(STDERR, $response . "\n");
    die();
}

$status = json_decode($response, true);

if (!empty($status['status'][$coreName]['name'])) {
    echo "Core '$coreName' already exists.\n";
    die();
}

// Create the core using configset_v1
// The configset must exist at $SOLR_HOME/configsets/configset_v1/conf/ before running this script.
$params = http_build_query([
    'action'    => 'CREATE',
    'name'      => $coreName,
    'configSet' => $configSetName,
]);

$ch = curl_init($coresAdminUrl . '?' . $params);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode !== 200 || !empty($result['error'])) {
    fwrite(STDERR, "Error creating core '$coreName' (HTTP $httpCode).\n");
    fwrite(STDERR, $response . "\n");
    die();
}

echo "Core '$coreName' created successfully.\n";
