<?php 

include (__DIR__."/../config/config.php");

$yaml = file_get_contents('Epusta-1.0.x.openapi.yaml.template');

$yaml = str_replace('{{ restApiBasePath }}','https://'.$config['restApiDomain'].$config['restApiBasePath'],$yaml);
$yaml = str_replace('{{ openApiVersion }}','1.0.1',$yaml);

echo $yaml;

?>
