<?php
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\OpenAPIValidation\PSR15\Exception\InvalidServerRequestMessage;
use League\OpenAPIValidation\PSR15\Exception\InvalidResponseMessage;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/solr.php';
require __DIR__ . '/../src/documents.php';
require __DIR__ . '/../src/statistics.php';
require __DIR__ . '/../src/tags.php';
require __DIR__ . '/../src/swagger-ui.php';
require __DIR__ . '/../src/loglines.php';

include (__DIR__."/../config/config.php");

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

//$openapiFile = 'Epusta-1.0.0.openapi.yaml';

$yaml = file_get_contents('Epusta-1.0.0.openapi.yaml.template');

$openapiFile = str_replace('{{ restApiBasePath }}','https://'.$config['restApiDomain'].$config['restApiBasePath'],$yaml);

function isStartDateFirstDayOfGranularityPeriod ($start_date, $granularity){
    switch ($granularity) {
        case "total":
            return true;
        case "day":
            return true;
        case "month":
        case "year":
            $startDate = new DateTime($start_date);
            $firstDayOfPeriod = new DateTime($start_date);
            $firstDayOfPeriod->modify('first day of this '.$granularity);
            //echo $startDate->format('Y-m-d H:i:s').'<->'.$firstDayOfPeriod->format('Y-m-d H:i:s').'('.($startDate == $firstDayOfPeriod).')';
	    return ($startDate == $firstDayOfPeriod);
	case "week":
            $startDate = new DateTime($start_date);
            $firstDayOfPeriod = new DateTime($start_date);
            $firstDayOfPeriod->modify('monday this week');
            //echo $startDate->format('Y-m-d H:i:s').'<->'.$firstDayOfPeriod->format('Y-m-d H:i:s').'('.($startDate == $firstDayOfPeriod).')';
            return ($startDate == $firstDayOfPeriod);
    }
}

function isEndDateLastDayOfGranularityPeriod($end_date, $granularity){
    switch ($granularity) {
        case "total":
            return true;
        case "day":
            return true;
        case "month":
        case "year":
            $endDate = new DateTime($end_date);
            $lastDayOfPeriod = new DateTime($end_date);
            $lastDayOfPeriod->modify('last day of this '.$granularity);
            //echo $endDate->format('Y-m-d H:i:s').'<->'.$lastDayOfPeriod->format('Y-m-d H:i:s').'('.($endDate == $lastDayOfPeriod).')';
            return ($endDate == $lastDayOfPeriod);
        case "week":
            $endDate = new DateTime($end_date);
            $lastDayOfPeriod = new DateTime($end_date);
            $lastDayOfPeriod->modify('sunday this week');
            //echo $startDate->format('Y-m-d H:i:s').'<->'.$firstDayOfPeriod->format('Y-m-d H:i:s').'('.($startDate == $firstDayOfPeriod).')';
            return ($endDate == $lastDayOfPeriod);
    }
}

$app = AppFactory::create();

$app->addRoutingMiddleware();

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET');
});

$psr15Middleware = (new \League\OpenAPIValidation\PSR15\ValidationMiddlewareBuilder)->fromYaml($openapiFile)->getValidationMiddleware();
$app->add($psr15Middleware);

$logger = new Logger('error');
$formatter = new LineFormatter(
    null, // Format of message in log, default [%datetime%] %channel%.%level_name%: %message% %context% %extra%\n
    null, // Datetime format
    true, // allowInlineLineBreaks option, default false
    true,  // discard empty Square brackets in the end, default false
    true
);
$streamHandler = new StreamHandler(__DIR__ .'/'. $config['logdir'] . 'error.log' , 100);
$streamHandler->setFormatter($formatter); 
$logger->pushHandler($streamHandler);

$errorMiddleware = $app->addErrorMiddleware(true, true, true, $logger);

$errorMiddleware->setErrorHandler(
    InvalidServerRequestMessage::class,
    function (Slim\Psr7\Request $request, Throwable $exception, bool $displayErrorDetails) {
        global $accesslogger;
        $response = new Slim\Psr7\Response();
        $payload = json_encode(['error' => ['message' => $exception->getPrevious()->getMessage()] ]);
        $response->getBody()->write($payload);
        $accesslogger->info($request->getRequestTarget().' 400');
        return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET');
    }
);

//$app->log->setLevel(\Slim\Log::WARN);
//$app->log->setEnabled(true);

$app->setBasePath($config['restApiBasePath']);

$accesslogger= new Logger('access');
$streamHandler = new StreamHandler(__DIR__ .'/'. $config['logdir'] . 'access.log' , 100);
$accesslogger->pushHandler($streamHandler);


/*
 * Define Pathes
 *
 */
$app->get('/', function (Request $request, Response $response, $args) {
    $html = getSwaggerUi();
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

$app->get('/documents', function (Request $request, Response $response, $args) {
//    var_dump($request) ;
    global $accesslogger;
    $resp=[];
    $resp['request']=$request->getQueryParams();
    $start_date=$resp['request']['start_date'];
    $end_date=$resp['request']['end_date'];
    $tags= (isset ($resp['request']['tagquery']) && $resp['request']['tagquery'] != '') ?  explode(' ',$resp['request']['tagquery']) : [];
    $start= (isset ($resp['request']['start'])) ? $resp['request']['start'] : 0 ;
    $limit= (isset ($resp['request']['limit'])) ? $resp['request']['limit'] : 10;
    $resp['documents']=getDocuments($start_date,$end_date,$tags,$start,$limit);
    $payload = json_encode($resp);
    $response->getBody()->write($payload);
    $accesslogger->info($request->getRequestTarget().' 200'); 
    return $response->withHeader('Content-Type', 'application/json');
});


$app->get('/loglines', function (Request $request, Response $response, $args) {
    global $accesslogger;
    $resp=[];
    $resp['request']=$request->getQueryParams();
    $start_date=$resp['request']['start_date'];
    $end_date=$resp['request']['end_date'];
    $identifier= (isset($resp['request']['identifier'])) ? $resp['request']['identifier'] : '';
    $tags= (isset ($resp['request']['tagquery']) && $resp['request']['tagquery'] != '') ?  explode(' ',$resp['request']['tagquery']) : [];
    $resp['loglines']=getLoglines($start_date,$end_date,$identifier,$tags);
    $payload = json_encode($resp);
    $response->getBody()->write($payload);
    $accesslogger->info($request->getRequestTarget().' 200');
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/statistics', function (Request $request, Response $response, $args) {
    global $accesslogger;
    $resp=[];
    $param=$request->getQueryParams();
    $start_date=$param['start_date'];
    $end_date=$param['end_date'];
    $identifier= (isset($param['identifier'])) ? $param['identifier'] : '';
    $tags= (isset ($param['tagquery']) && $param['tagquery'] != '') ?  array_filter(explode(' ',$param['tagquery'])) : [];
    $granularity=(isset($param['granularity'])) ? $param['granularity'] : 'total';

    if (! isStartDateFirstDayOfGranularityPeriod($start_date,$granularity) ) {
        $msg='Parameter start_date should be the first day of '.$granularity;
        $payload = json_encode(['error' => ['message' => $msg ] ]);
        $response->getBody()->write($payload);
        $accesslogger->info($request->getRequestTarget().' 400');
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    if (! isEndDateLastDayOfGranularityPeriod($end_date,$granularity) ) {
        $msg='Parameter end_date should be the last day of '.$granularity;
        $payload = json_encode(['error' => ['message' => $msg ] ]);
        $response->getBody()->write($payload);
        $accesslogger->info($request->getRequestTarget().' 400');
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $resp=[];
    $resp['request']=$request->getQueryParams();
    $resp['statistics']=getStatistics($start_date,$end_date,$identifier,$tags,$granularity);
    $payload = json_encode($resp);
    $response->getBody()->write($payload);
    $accesslogger->info($request->getRequestTarget().' 200');
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/tags', function (Request $request, Response $response, $args) {
    global $accesslogger;
    $resp=getTags();
    $payload = json_encode($resp);
    $response->getBody()->write($payload);
    $accesslogger->info($request->getRequestTarget().' 200');
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
?>
