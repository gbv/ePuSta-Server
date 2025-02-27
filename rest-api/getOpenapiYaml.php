<?php 

include (__DIR__."/../config/config.php");

$yaml = file_get_contents('Epusta-1.0.0.openapi.yaml.template');

$yaml = str_replace('{{ restApiBasePath }}',$app->setBasePath($config['restApiBasePath']),$yaml);



?>