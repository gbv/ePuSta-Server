<?php

$configfile=__DIR__."/config";

if (! is_file ($configfile) ) {
    die ("Error: Configfile(".$configfile.") didn't exists. \n");
}

$handle = fopen($configfile, "r");

$config=array();

if ($handle) {
    while (($line = fgets($handle)) !== false) {
        if ( substr( trim($line),0,1) == "#" ) continue;
        if ( trim($line) == "" ) continue;
        $strEqPos=strpos($line,"=");
        if ($strEqPos === false ) die ("Error: Can't parse config line ".$line."\n");
        $key=trim(substr($line,0,$strEqPos));
        $value=trim(substr($line,$strEqPos+1));
        $config[$key]=$value;
    }
    if (!feof($handle)) {
        echo "Fehler: unerwarteter fgets() Fehlschlag\n";
    }
    fclose($handle);
}

?>
