#!/bin/bash

dir=`dirname "$0"`
source $dir/../config/config

$SOLRURL=$solrCore

curl $SOLRURL/update --data '<delete><query>*:*</query></delete>' -H 'Content-type:text/xml; charset=utf-8'

curl $SOLRURL/update --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'
