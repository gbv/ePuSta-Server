<?php

function getDocuments($start_date, $end_date,$tags, $start, $limit) {
    global $config;
    $solrUrl=$config['solrUrl'].$config['solrCore'].'/query';

    $query='q=dateTime:['.$start_date.'T00:00:00Z TO '.$end_date.'T23:59:59Z]';
    foreach ($tags as $tag) {
        if ( substr($tag,0,1) == '-') {
             $query .= ' AND NOT (subjects:'.addcslashes(substr($tag,1),':').')';
        } else {
             $query .= ' AND subjects:'.addcslashes($tag,':');
        }

    }
    $query.='&rows=10';

    $query.='&json.facet={';
    $query.='  identifier:{';
    $query.='    type:terms,';
    $query.='    field : identifier,';
    $query.='    limit:'.$limit.',';
    $query.='    offset:'.$start.'';
    $query.='  }';
    $query.='}';

    $result=querySolr($solrUrl,$query);
    $docs=[];
    foreach ($result['facets']['identifier']['buckets'] as $bucket ) {
        $doc=array();
        $doc['identifier']=$bucket['val'];
        $doc['count']=$bucket['count'];
        $docs[]=$doc;
    };
    return $docs;
}

?>
