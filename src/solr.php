<?php
function querySolr ($url, $query) {
    
    $useragent = 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)';
    $result = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_URL,$url); //set the url we want to use
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_USERAGENT, $useragent); //set our user agent
    $result = curl_exec ($ch); //execute and get the results
    $result = json_decode($result,true);
    return $result;
} 
