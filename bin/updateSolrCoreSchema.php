#!/usr/bin/php
<?php

include (__DIR__."/../config/config.php");

// Desired Solr field definitions
$desiredFields = [
    ['name' => 'uuid',                 'type' => 'string',  'multiValued' => false, 'indexed' => true, 'stored' => true],
    ['name' => 'dateTime',             'type' => 'pdate',   'multiValued' => false, 'indexed' => true, 'stored' => true],
    ['name' => 'source',               'type' => 'string',  'multiValued' => false, 'indexed' => true, 'stored' => true],
    ['name' => 'documentIdentifier',   'type' => 'strings', 'multiValued' => true,  'indexed' => true, 'stored' => true],
    ['name' => 'associatedIdentifier', 'type' => 'strings', 'multiValued' => true,  'indexed' => true, 'stored' => true],
    ['name' => 'tags',                 'type' => 'strings', 'multiValued' => true,  'indexed' => true, 'stored' => true],
];

$schemaUrl = $config['solrUrl'] . $config['solrCore'] . '/schema';

// Fetch existing fields from Solr
$ch = curl_init($schemaUrl . '/fields');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    fwrite(STDERR, "Error: Can't fetch schema fields from Solr (HTTP $httpCode).\n");
    fwrite(STDERR, $response . "\n");
    die();
}

$existing = json_decode($response, true);
$existingNames = array_column($existing['fields'], 'name');

// Add or replace each desired field
foreach ($desiredFields as $field) {
    $command = in_array($field['name'], $existingNames) ? 'replace-field' : 'add-field';
    $body = json_encode([$command => $field]);

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
