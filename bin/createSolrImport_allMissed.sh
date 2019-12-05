#!/bin/bash

BASEDIR=/home/borchert/epusta/repper/
BINDIR=$BASEDIR/bin/
LOGDIR=$BASEDIR/epustalogs/
SOLRDIR=$BASEDIR/solrImports/

for filename in $LOGDIR/*.log; do
    basename="$(basename $filename .log)";
    destfile=$basename.json
    if [ ! -f "$SOLRDIR/$destfile" ]; then
        echo "Processing: $filename -> $destfile"
        $BINDIR/createSolrImport.php --file=$filename --level=PROD > $SOLRDIR/$destfile
    else
        echo "$filename allready parsed."
    fi
done
