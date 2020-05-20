#!/bin/bash

dir=`dirname "$0"`
if [ -f "$dir/../config/config" ]; then
    source $dir/../config/config
else
    echo "Error can't find configfile ($dir/../config/config)"
    exit 1
fi 

for filename in $epustaLogs/*; do
    if [ -f $filename ]; then
        command="$epustaServerBin/createSolrImport.php --file=$filename --level=PROD"
        if [ ${filename: -3} == ".gz" ]; then
            basename="$(basename $filename .log.gz)";
            filename2=${filename: 0 : -3};
        elif [ ${filename: -4} == ".log" ]; then
            basename="$(basename $filename .log)";
            filename2=$filename;
        else
            echo "Error: Wrong Filenamepatern - skip processing."
        fi
        destfile=$basename.json
        if [ ! -f "$solrImports/$destfile" ]; then
            echo "Processing: $filename -> $destfile"
            if [ ${filename: -3} == ".gz" ]; then gzip -d $filename; fi
            $epustaServerBin/createSolrImport.php --file=$filename2 --level=PROD > $solrImports/$destfile
            if [ ${filename: -3} == ".gz" ]; then gzip $filename2; fi
        else
            echo "$filename allready parsed."
        fi
    fi
done
