<?php

function getTags () {
    global $config;
    $solrUrl=$config['solrURL'].$config['solrCore'].'/select';

    $query='facet.field=subjects&facet.limit=-1&facet=true&q=*%3A*&rows=0';

    $result=querySolr($solrUrl,$query);

    $result=$result['facet_counts']['facet_fields']['subjects'];
    $result2=[];
    for ($i = 0; $i < count($result); $i += 2) {
        $result2[] = $result[$i];
    }
    $result=$result2;
    return $result;
}

?>
