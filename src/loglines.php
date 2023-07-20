<?php

function getLoglines($start_date,$end_date,$identifier,$tags) {
    
    $query='q=dateTime:['.$start_date.'T00:00:00Z TO '.$end_date.'T23:59:59Z]';
    if ($identifier != '') $query.=' AND identifier:'.$identifier ;
    foreach ($tags as $tag) {
        if ( substr($tag,0,1) == '-') {
             $query .= ' AND NOT (subjects:'.addcslashes(substr($tag,1),':').')';
        } else {
             $query .= ' AND subjects:'.addcslashes($tag,':');
        }

    }
    $query.='&rows=10';
    $result=querySolr($solrUrl,$query);
    $result=$result['response']['docs'];
    return $result;
}

?>
