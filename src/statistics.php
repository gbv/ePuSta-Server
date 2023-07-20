<?php

function getStatistics($start_date, $end_date,$identifier,$tags,$granularity) {
    
    global $config;
    $solrUrl=$config['solrURL'].$config['solrCore'].'/query';

    $dategap='';
    $datefacet=true;
    switch ($granularity) {
        case "day":
            $dategap='DAY';
            break;
        case "week":
            $dategap='7DAYS';
            break;
        case "month":
            $dategap='MONTH';
            break;
        case "year":
            $dategap='YEAR';
            break;
        case "total":
            $datefacet=false;
            break;
    }

    $query='q=dateTime:['.$start_date.'T00:00:00Z TO '.$end_date.'T23:59:59Z]';
    if ($identifier != '') $query.=' AND identifier:'.$identifier ;
    foreach ($tags as $tag) {
        if ( substr($tag,0,1) == '-') {
             $query .= ' AND NOT (subjects:'.addcslashes(substr($tag,1),':').')';
        } else {
             $query .= ' AND subjects:'.addcslashes($tag,':');
        }
    }
    $query.='&rows=0';

    if ($datefacet) {
        $query.='&json.facet={';
        $query.='  date:{';
        $query.='    type: range,';
        $query.='    field : dateTime,';
        $query.='    start : "'.$start_date.'T00:00:00Z",';
        $query.='    end : "'.$end_date.'T23:59:59Z",';
        $query.='    gap : "%2B1'.$dategap.'",';
        $query.='  }';
        $query.='}';
    }

    $result=querySolr($solrUrl,$query);

    $result2['total']=$result['response']['numFound'];
    $result2['day']=array();
    $result2['week']=array();
    $result2['month']=array();
    $result2['year']=array();
    switch ($granularity) {
        case "day":
            foreach ($result['facets']['date']['buckets'] as $bucket ) {
                $day=array();
                $day['date']=substr($bucket['val'],0,10);
                $day['count']=$bucket['count'];
                $result2['day'][]=$day;
            };
            break;
        case "week":
            foreach ($result['facets']['date']['buckets'] as $bucket ) {
                $week=array();
                // TO umrechnene in dateformat
                $date=new DateTime(substr($bucket['val'],0,10));
                $week['date']=$date->format("Y-\WW");;
                $week['count']=$bucket['count'];
                $result2['week'][]=$week;
            };
            break;
        case "month":
            foreach ($result['facets']['date']['buckets'] as $bucket ) {
                $month=array();
                $month['date']=substr($bucket['val'],0,7);
                $month['count']=$bucket['count'];
                $result2['month'][]=$month;
            };
            break;
        case "year":
            foreach ($result['facets']['date']['buckets'] as $bucket ) {
                $year=array();
                $year['date']=substr($bucket['val'],0,4);
                $year['count']=$bucket['count'];
                $result2['year'][]=$year;
            };
            break;
    }
    return $result2;
}

?>
