#!/usr/bin/php
<?php

require_once __DIR__.'/lib/reposas-loglinepaser.php';

function print_help() {
    echo "\nConvert a epusta logfile to a solr importable jsonfile. \n";
    echo "\nUsage: createSolrImport.php --file EPUSTALOGFILE [--level] [-h|--help] \n";
    echo "  --file - Path to the epusta-logfile (required)\n";
    echo "  --level - indecates witch logline are imported to the solrindex. Levels: \n";
    echo "            - PROD (Default) only loglines with an document identifier. \n";
    echo "            - DEBUG all loglines. \n";
    echo "  -h - print help\n";
    echo "  --help - print help\n\n";
    echo "\nExample: ./createSolrImport.php --file=../epustalogs/access-repper-2019-10-10.epusta.log --level=DEBUG. \n";
}

$longopts  = array(
    "file:",       // Required value
    "level::",     // Optional value
    "help"
);

$optind=0;

if (! $options = getopt("h", $longopts, $optind)) {
    echo "\nError: Can't parse Parameter:".$argv[$optind-1]."\n";
    print_help();
    die();
}

if ( ! $options
    || ! isset($options['file'])
    || isset($options['h'])
    || isset($options['help'])
) {
    echo "\nError: Parameter file is required.\n";
    print_help();
    die();
}
    
if (isset($options['level']) && ! in_array($options['level'] , array("PROD","DEBUG") )) {
    echo "\nError: Valid values for level: PROD, DEBUG\n";
    print_help();
    die();
}
    
$level = (isset($options['level'])) ? $options['level'] : 'PROD';
    
$filepath=$options['file'];
    
if (! file_exists($filepath) ) {
    echo "\nError: Logfile not found.\n";
    die();
}
    
$handle = fopen($filepath, "r");
$filename = basename ($filepath);
    

$reposasLoglineParser=new ReposasLogfileParser();

while (! feof($handle)) {
    if ($line = trim(fgets($handle))) {
        $logLine=new ReposasLogline();
        if ( $reposasLoglineParser->parse($line, $logLine)) {
            if (count($logLine->Identifier) == 0 && $level == 'PROD')  continue;
            $str='{ "uuid": "'.$logLine->UUID.'"';
            $str.=', "identifier":'.json_encode($logLine->Identifier);
            $time = new DateTime($logLine->Time);
            $str.=', "dateTime":"'.$time->format('Y-m-d\TH:i:s\Z').'"';
            $str.=', "subjects":'.json_encode($logLine->Subjects);
            $str.=', "source":'.$filename;
            $str.='}';
            echo $str."\n";
        } else {
            //die("Error: malformed Logline - abort Processing.\n");
        }
    }
}

?>
