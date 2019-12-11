#!/usr/bin/php
<?php

include (__DIR__."/../config/config.php");

function getSources() {
    global $config;

    $useragent='Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)';

    $solrcall=$config['solrUrl'].$config['solrCore']."/select?q=*:*&stats=true&stats.field=source&rows=0&stats.calcdistinct=true";

    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_URL,$solrcall); 
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $useragent); 
    $result= curl_exec ($ch); 

    $arr = json_decode($result,true);

    return $arr['stats']['stats_fields']['source']['distinctValues'];
}

function importSolrJSON($filepath) {
    global $config;
    $cmd="/opt/solr/bin/post -c ".$config['solrCore']." ".$filepath;
    echo $cmd;
    shell_exec($cmd);
}

$sources = getSources();

foreach (new DirectoryIterator($config['solrImports']) as $fileInfo) {
    if($fileInfo->isDot()) continue;
    if($fileInfo->isDir()) continue;
    $filename=$fileInfo->getFilename();
    $filepath=$fileInfo->getPathname();
    $source=basename($filename,'.json').".log";
    if (! in_array($source,$sources) ) {
        echo "import ".$filename."\n";
        importSolrJSON($filepath);
    } 
}

?>
