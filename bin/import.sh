#!/bin/bash
$SOLRCORE=

/opt/solr/bin/post -c $SOLRCORE solrImport.json
