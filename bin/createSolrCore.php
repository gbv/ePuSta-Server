#!/usr/bin/php
<?php

include (__DIR__."/../config/config.php");

$coresAdminUrl   = $config['solrUrl'] . 'admin/cores';
$configsAdminUrl = $config['solrUrl'] . 'admin/configs';
$coreName        = $config['solrCore'];
$configSetName   = 'configset_v1';
$configSetDir    = __DIR__ . '/../solr/configset_v1';

// Check if configset already exists, upload if not
$ch = curl_init($configsAdminUrl . '?action=LIST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    fwrite(STDERR, "Error: Can't fetch configsets from Solr at $configsAdminUrl (HTTP $httpCode).\n");
    fwrite(STDERR, $response . "\n");
    die();
}

$configSets = json_decode($response, true);

if (in_array($configSetName, $configSets['configSets'] ?? [])) {
    echo "ConfigSet '$configSetName' already exists.\n";
} else {
    $tmpFile = tempnam(sys_get_temp_dir(), 'solr_configset_') . '.zip';
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::CREATE);
    $zip->addFile($configSetDir . '/managed-schema', 'managed-schema');
    $zip->addFile($configSetDir . '/solrconfig.xml', 'solrconfig.xml');
    $zip->close();

    $ch = curl_init($configsAdminUrl . '?action=UPLOAD&name=' . urlencode($configSetName));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($tmpFile));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/octet-stream']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    unlink($tmpFile);

    $result = json_decode($response, true);
    if ($httpCode === 200 && empty($result['errors'])) {
        echo "ConfigSet '$configSetName' uploaded successfully.\n";
    } else {
        fwrite(STDERR, "Error uploading configset '$configSetName' (HTTP $httpCode).\n");
        fwrite(STDERR, $response . "\n");
        die();
    }
}

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

// Create the core using the uploaded configset
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
