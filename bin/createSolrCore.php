#!/usr/bin/php
<?php

include (__DIR__."/../config/config.php");

$coresAdminUrl = $config['solrUrl'] . 'admin/cores';
$coreName      = $config['solrCore'];
$schemaUrl     = $config['solrUrl'] . $coreName . '/schema';

$desiredFields = [
    ['name' => 'uuid',                 'type' => 'string',  'multiValued' => false, 'indexed' => true, 'stored' => true],
    ['name' => 'dateTime',             'type' => 'pdate',   'multiValued' => false, 'indexed' => true, 'stored' => true],
    ['name' => 'source',               'type' => 'string',  'multiValued' => false, 'indexed' => true, 'stored' => true],
    ['name' => 'documentIdentifier',   'type' => 'strings', 'multiValued' => true,  'indexed' => true, 'stored' => true],
    ['name' => 'associatedIdentifier', 'type' => 'strings', 'multiValued' => true,  'indexed' => true, 'stored' => true],
    ['name' => 'tags',                 'type' => 'strings', 'multiValued' => true,  'indexed' => true, 'stored' => true],
];

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

// Create the core using the _default configSet
$params = http_build_query([
    'action'    => 'CREATE',
    'name'      => $coreName,
    'configSet' => '_default',
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

// Fetch existing schema fields
$ch = curl_init($schemaUrl . '/fields');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    fwrite(STDERR, "Error: Can't fetch schema fields (HTTP $httpCode).\n");
    fwrite(STDERR, $response . "\n");
    die();
}

$existing      = json_decode($response, true);
$existingNames = array_column($existing['fields'], 'name');

// Add or replace each desired field
foreach ($desiredFields as $field) {
    $command = in_array($field['name'], $existingNames) ? 'replace-field' : 'add-field';
    $body    = json_encode([$command => $field]);

    $ch = curl_init($schemaUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($httpCode === 200 && empty($result['errors'])) {
        echo "$command: " . $field['name'] . " -> OK\n";
    } else {
        fwrite(STDERR, "$command: " . $field['name'] . " -> FAILED (HTTP $httpCode)\n");
        fwrite(STDERR, $response . "\n");
    }
}
