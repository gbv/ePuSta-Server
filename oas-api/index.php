<?php
// DEBIG only
ini_set('display_errors', 1);

// Config
$config=array(
    'solrURL' => 'http://esx-141.gbv.de:8983/solr/repper'
  );

$do = (isset($_GET['do']) ) ?  $_GET['do']: 'basic';
$format = (isset($_GET['format'])) ?  $_GET['format']: 'json';
$from = (isset ($_GET['from']) ) ?  $_GET['from'] : date('Y-m-d', strtotime('-3 days'));
$until = (isset ($_GET['until']) ) ?  $_GET['until'] : date('Y-m-d', strtotime('today'));
$granularity = $_GET['granularity'];
if (! in_array($granularity, array('day','week','month','year','total'))) die ("Error: Granularity ".$granularity." not in 'day','week','month','year','total'");
$content = (isset ($_GET['content'])) ? $_GET['content']: 'counter' ;
$content = explode (',',$content);
foreach ($content as $cont) {
    if (! in_array($cont, array('counter','counter_abstract','robots','robots_abstract'))) {
        die ("Error: Content ".$content." should only contains 'counter','counter_abstract','robots','robots_abstract'");
    }
}
$addemptyrecords=(isset($_GET['addemptyrecords']) &&  $_GET['addemptyrecords']  == 'true') ? true : false;
$summarized = (isset($_GET['summarized']) &&  $_GET['summarized']  == 'true') ? true : false;
$jsonheader = (isset($_GET['jsonheader']) && $_GET['jsonheader'] == 'true') ? true : false;
$informational = (isset($_GET['informational']) && $_GET['informational']  == 'true') ? true : false;
$identifier = (isset($_GET['identifier']) && $_GET['identifier']) ? $_GET['identifier'] : '*';

switch ($granularity) {
    case "week":
        $from=date('Y-m-d', strtotime('monday this week '. $from));
        $until=date('Y-m-d', strtotime('sunday this week '. $until));
        break;
    case "month":
        $from=date('Y-m-d', strtotime('first day of '. $from));
        $until=date('Y-m-d', strtotime('last day of '. $until));
        break;
    case "year":
        $from=date('Y', strtotime($from)).'-01-01';
        $until=date('Y', strtotime($until)).'-12-31';
        break;
}

//https://oase.gbv.de:443/api/v1/reports/basic.json?identifier=%25&from=2019-01-01&until=2019-07-01&granularity=day&addemptyrecords=true&summarized=true&informational=true&jsonheader=true
function getJSON($identifier,$from,$until,$granularity,$summarized) {
    global $config;
    $url=$config['solrURL'].'/query';
    //$proxy=file ('proxy.txt');
    $useragent='Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)';

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
    if ($identifier == '*') {
        $identifierQuery = 'identifier:["" TO *]';
    } else {
        $identifierQuery = 'identifier:'.$identifier;
    }
    $query='q='.$identifierQuery.'  AND  NOT (subjects:filter*)';
    //$query.=' AND dateTime:['.$from.'T00:00:00Z TO '.$until.'T23:59:59Z]';
    $query.='&rows=1';
    $query.='&json.facet={';
    if ($datefacet) {
        $query.='  date:{';
        $query.='    type: range,';
        $query.='    field : dateTime,';
        $query.='    start : "'.$from.'T00:00:00Z",';
        $query.='    end : "'.$until.'T23:59:59Z",';
        $query.='    gap : "%2B1'.$dategap.'",';
        $query.='    facet: { ';
    }
    // if $datefacet=true; identifer is a subfacet
    if (! $summarized) {
        $query.='      identifier: {';
        $query.='        type:terms, ';
        $query.='        field:identifier,';
        $query.='        limit:1000,';
        $query.='        facet: { ';
    }
    $query.='          oascontent:{';
    $query.='            type:terms, ';
    $query.='            field:subjects, ';
    $query.='            prefix:"oas:content"';
    $query.='          }';
    if (! $summarized) {
        $query.='        }';
        $query.='      }';
    }
    if ($datefacet) {
        $query.='    }';
    }
    $query.='  }';
    $query.='}';

    $ch = curl_init();
    //you might need to set some cookie details up (depending on the site)
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_URL,$url); //set the url we want to use
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_USERAGENT, $useragent); //set our user agent
    $result= curl_exec ($ch); //execute and get the results

    return $result;
}

function getEntrysForIdentifierBucket($bucket,$identifier,$addemptyrecords,$format,$content){
    $entries=array();
    //$entry=array();
    $bucketname=$bucket['val'];
    $identifierReg='/'.str_replace('*','.*',$identifier).'/';
    if (! preg_match($identifierReg,$bucketname)) return false;
    
    if ($format == 'xml') {
        foreach ($content as $cont) {
            $found=false;
            $entry=array();
            foreach ($bucket['oascontent']['buckets'] as $bucket2) {
                $oasContentType=substr($bucket2['val'],12);
                if ($oasContentType == $cont) {
                    $found=true;
                    if (! in_array($oasContentType,$content)) continue;
                    $entry['access']=array();
                    $entry['access']['type']=$oasContentType;
                    $entry['access']['count']=$bucket2['count'];
                    $entry['identifier']=$bucketname;
                    $entries[]=$entry;
                } 
            }
            if (! $found && $addemptyrecords) {
                $entry['access']=array();
                $entry['access']['type']=$cont;
                $entry['access']['count']=0;
                $entry['identifier']=$bucketname;
                $entries[]=$entry;
            } 
        }
    } else {
        $entry=array();
        foreach ($content as $cont) {
            $found=false;
            foreach ($bucket['oascontent']['buckets'] as $bucket2) {
                $oasContentType=substr($bucket2['val'],12);
                if ($oasContentType == $cont) {
                    $found=true;
                    if (! in_array($oasContentType,$content)) continue;
                    $entry[$oasContentType]=$bucket2['count'];
                }
            }
            if (! $found && $addemptyrecords) {
                $entry[$cont]=0;
            }
        }
        $entry['identifier']=$bucketname;
        $entries[]=$entry;    
    }
    return $entries;
}

function solr2entries ($identifier,$granularity,$addemptyrecords,$solrjson,$format,$content) {
    $solrResult=json_decode($solrjson,true);
    // TO solr Errors
    // print_r($solrResult);
    $result=array();
    if (isset($solrResult['facets']['date'])) {
        foreach ($solrResult['facets']['date']['buckets'] as $bucket) {
            $entry=array();
            $date=$bucket['val'];
            $dateStr="";
            switch ($granularity) {
	        case "day":
	            $dateStr=date('Y-m-d', strtotime($date));;
                    break;
	        case "week":
	            // TO DO Date format (w=Weak of Year?)
	            $dateStr=date('Y-Ww', strtotime($date));
                    break;
		case "month":
	            $dateStr=date('Y-m', strtotime($date));
	            break;
		case "year":
		    $dateStr=date('Y', strtotime($date));
	            break;
	        default :
		    $dateStr=$date;
            }

            // TO DO Test for identifier
            if (isset ($bucket['identifier'])){
                foreach ($bucket['identifier']['buckets'] as $bucket2) {
                    $entries=getEntrysForIdentifierBucket($bucket2,$identifier,$addemptyrecords,$format,$content);
                    if ($entries===false) continue;
                    foreach ($entries as $key => $entry) {
                        $entries[$key]['date']=$dateStr; 
                    }
                    $result=array_merge($result,$entries);
                }
            } else if (isset ($bucket['oascontent'])) {
                $fakeBucket=array(
                    'val' => $identifier,
                    'count' => $bucket['count'],
                    'oascontent' =>  $bucket['oascontent']
                  );
                $entries=getEntrysForIdentifierBucket($fakeBucket,$identifier,$addemptyrecords,$format,$content);
                if ($entries===false) continue;
                foreach ($entries as $key => $entry) {
                    $entries[$key]['date']=$dateStr;
                }
                $result=array_merge($result,$entries);
            } else if (!$addemptyrecords && $bucket['count'] == 0) {
            } else {
                if ($format == 'xml') {
                    foreach ($content as $cont){
                        $entry['date']=$dateStr;
                        $entry['identifier']=$identifier;
                        $entry['access']=array();
                        $entry['access']['type']=$cont;
                        $entry['access']['count']=$bucket['count'];
                        $result[]=$entry;
                    }
                } else {
                    $entry['date']=$dateStr;
                    $entry['identifier']=$identifier;
                    foreach ($content as $cont){
                        $entry[$cont]=$bucket['count'];
                    }
                    $entry[$cont];
                    $result[]=$entry;
                }
            }

        }

    } else if (isset($solrResult['facets']['identifier'])) {
        // case granularity total
        foreach ($solrResult['facets']['identifier']['buckets'] as $bucket) {
            $entries=getEntrysForIdentifierBucket($bucket,$identifier,$addemptyrecords,$format,$content);
            if ($entries===false) continue;
            foreach ($entries as $key => $entry) {
                $entries[$key]['date']='2000-01-01'; // TO DO : first day of
            }
            $result=array_merge($entries,$result);
        }
    } else if ($solrResult['response']['numFound'] == 0) {
        http_response_code(204);
        echo "no content";
        //print_r( $solrResult);
        return false; 
        //die ("no content");
    } else {
       // case total and summarized TO DO Test the reaction of solr
    }
    return $result;
}

function to_xml(SimpleXMLElement $object, array $data)
{   
    foreach ($data as $key => $value) {
        if (is_int($key)) {
            $tagName=$object->getName();
            if ($tagName == 'entries') {
                $key = 'entry';
            } else if (substr($tagName,-1) == 's') {
                $key = substr($tagName,0,-1);
            } else { 
                $key = "key_$key";
            }
        }
        if (is_array($value)) {
            $new_object = $object->addChild($key);
            to_xml($new_object, $value);
        } else {
            $object->addChild($key, $value);
        }   
    }   
} 

$result=array();

if ($jsonheader == 'true') {
    $result['from']=$from;
    $result['until']=$until;
    $result['granularity']=$granularity;
    $result['addemptyrecords']=$addemptyrecords;
    $result['summarized']=$summarized;
}

$json=getJSON($identifier,$from,$until,$granularity,$summarized);

$result['solr_response']=json_decode($json,true);

$result['entries']=solr2entries($identifier,$granularity,$addemptyrecords,$json,$format,$content);

if ($result['entries'] != false) {
    if ($format=="json") {
        header('Content-Type: application/json');
        echo json_encode($result,JSON_PRETTY_PRINT);
    } else if ($format=="xml") {
        unset($result['solr_response']);
        header("Content-type: text/xml");
        $xml = new SimpleXMLElement('<report/>');
        to_xml($xml, $result);
        print $xml->asXML();
    } else {
        die ("No format defined.");
    }
}


?>
