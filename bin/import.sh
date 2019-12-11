#!/bin/bash

dir=`dirname "$0"`
source $dir/../config/config

$SOLRCORE=$solrCore

/opt/solr/bin/post -c $SOLRCORE solrImport.json
