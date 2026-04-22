#!/usr/bin/php
<?php

include (__DIR__."/../config/config.php");

$coresAdminUrl = $config['solrUrl'] . 'admin/cores';
$coreName      = $config['solrCore'];
$schemaUrl     = $config['solrUrl'] . $coreName . '/schema';
$configUrl     = $config['solrUrl'] . $coreName . '/config';

$desiredProperties = [
    'updateHandler.autoCommit.maxTime'      => 15000,
    'updateHandler.autoCommit.openSearcher' => false,
    'query.filterCache.size'                => 512,
    'query.filterCache.initialSize'         => 512,
    'query.filterCache.autowarmCount'       => 0,
    'query.queryResultCache.size'           => 512,
    'query.queryResultCache.initialSize'    => 512,
    'query.queryResultCache.autowarmCount'  => 0,
    'query.documentCache.size'              => 512,
    'query.documentCache.initialSize'       => 512,
    'query.documentCache.autowarmCount'     => 0,
    'query.enableLazyFieldLoading'          => true,
    'query.queryResultWindowSize'           => 20,
    'query.queryResultMaxDocsCached'        => 200,
    'query.useColdSearcher'                 => false,
    'query.maxBooleanClauses'               => 1024,
];

$desiredHandlers = [
    [
        'name'     => '/select',
        'class'    => 'solr.SearchHandler',
        'defaults' => ['echoParams' => 'explicit', 'rows' => 10],
    ],
    [
        'name'     => '/query',
        'class'    => 'solr.SearchHandler',
        'defaults' => ['echoParams' => 'explicit', 'wt' => 'json', 'indent' => 'true'],
    ],
];

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

// Apply config properties
$body = json_encode(['set-property' => $desiredProperties]);

$ch = curl_init($configUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);
if ($httpCode === 200 && empty($result['errors'])) {
    echo "set-property -> OK\n";
} else {
    fwrite(STDERR, "set-property -> FAILED (HTTP $httpCode)\n");
    fwrite(STDERR, $response . "\n");
}

// Update request handlers
foreach ($desiredHandlers as $handler) {
    $body = json_encode(['update-requesthandler' => $handler]);

    $ch = curl_init($configUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($httpCode === 200 && empty($result['errors'])) {
        echo "update-requesthandler: " . $handler['name'] . " -> OK\n";
    } else {
        fwrite(STDERR, "update-requesthandler: " . $handler['name'] . " -> FAILED (HTTP $httpCode)\n");
        fwrite(STDERR, $response . "\n");
    }
}
