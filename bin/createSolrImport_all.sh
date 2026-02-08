#!/bin/bash

print_help () {
    echo "createSolrImport_all.sh - uses createSolrImport.php for mass creation of solr-json files.";
    echo "Usage: createSolrImport_all.sh [-s missed|outdated|all] [-h] "
    echo "  -s - select for wich epusta logfiles the solrimport will created "
    echo "    missed   - all with missed json files "
    echo "    outdated - all with missed and outdated json files"
    echo "    all      - all files"
    echo "  -h - print help"
} 

dir=`dirname "$0"`
if [ -f "$dir/../config/config" ]; then
    source $dir/../config/config
else
    echo "Error can't find configfile ($dir/../config/config)"
    exit 1
fi 

while getopts ":hs" arg; do
    case $arg in
        s) 
            selected=${OPTARG}
            case $selected in 
                missed | outdated | all )
                    # Do nothing 
                    ;;
                *)  
                    echo "Error: value of -s need to be one of missed, outdated, all"
                    ;;
            esac
            ;;
        h | *) 
            print_help
            ;;
    esac
done

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
            echo "Processing: $filename -> $destfile (new)"
            if [ ${filename: -3} == ".gz" ]; then gzip -d $filename; fi
            $epustaServerBin/createSolrImport.php --file=$filename2 --level=PROD > $solrImports/$destfile
            if [ ${filename: -3} == ".gz" ]; then gzip $filename2; fi 
        elif [ $filename -nt $solrImports/$destfile ] && [ "$selected" = "outdated" ]; then
            echo "Processing: $filename -> $destfile (overwrite)"
            if [ ${filename: -3} == ".gz" ]; then gzip -d $filename; fi
            $epustaServerBin/createSolrImport.php --file=$filename2 --level=PROD > $solrImports/$destfile
            if [ ${filename: -3} == ".gz" ]; then gzip $filename2; fi
        elif [ "$selected" = "all" ]; then
            echo "Processing: $filename -> $destfile (overwrite)"
            if [ ${filename: -3} == ".gz" ]; then gzip -d $filename; fi
            $epustaServerBin/createSolrImport.php --file=$filename2 --level=PROD > $solrImports/$destfile
            if [ ${filename: -3} == ".gz" ]; then gzip $filename2; fi
        else
            echo "$filename allready parsed."
        fi
    fi
done
