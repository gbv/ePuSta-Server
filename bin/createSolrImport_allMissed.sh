#!/bin/bash

dir=`dirname "$0"`
if [ -f "$dir/../config/config" ]; then
    source $dir/../config/config
else
    echo "Error can't find configfile ($dir/../config/config)"
    exit 1
fi 

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
