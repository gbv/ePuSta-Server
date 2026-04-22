#!/usr/bin/php
<?php

include (__DIR__."/../config/config.php");

$coresAdminUrl = $config['solrUrl'] . 'admin/cores';
$coreName = $config['solrCore'];

// Check if core already exists
$ch = curl_init($coresAdminUrl . '?action=STATUS&core=' . urlencode($coreName));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    fwrite(STDERR, "Error: Can't connect to Solr Admin API (HTTP $httpCode).\n");
    fwrite(STDERR, $response . "\n");
    die();
}

$status = json_decode($response, true);

if (!empty($status['status'][$coreName]['name'])) {
    echo "Core '$coreName' already exists.\n";
    die();
}

// Create the core (config files must already be in place under instanceDir)
$params = http_build_query([
    'action'      => 'CREATE',
    'name'        => $coreName,
    'instanceDir' => $coreName,
    'config'      => 'solrconfig.xml',
    'schema'      => 'schema.xml',
]);

$ch = curl_init($coresAdminUrl . '?' . $params);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && empty($result['error'])) {
    echo "Core '$coreName' created successfully.\n";
} else {
    fwrite(STDERR, "Error creating core '$coreName' (HTTP $httpCode).\n");
    fwrite(STDERR, $response . "\n");
}
