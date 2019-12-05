#!/bin/bash

source ../config/config

for filename in $epustaLogs/*.log; do
    basename="$(basename $filename .log)";
    destfile=$basename.json
    if [ ! -f "$solrImports/$destfile" ]; then
        echo "Processing: $filename -> $destfile"
        $epustaServerBin/createSolrImport.php --file=$filename --level=PROD > $solrImports/$destfile
    else
        echo "$filename allready parsed."
    fi
done
