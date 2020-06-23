<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// Create App
$app = AppFactory::create();

// Create Twig
$twig = Twig::create('../templates');

// Add Twig-View Middleware
$app->add(TwigMiddleware::create($app, $twig));

// Define named route
$app->get('/', function ($request, $response, $args) {
    $view = Twig::fromRequest($request);
    $data["result"] = 0;
    return $view->render($response, 'index.html.twig', $data);
})->setName('root');

$app->get('/api', function ($request, $response, $args) {

    $data = array('data' => '', 'result' => 'success', 'message' => 'successful api call');
    $payload = json_encode($data);

    $response->getBody()->write($payload);
    return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(200);

})->setName('api');

$app->get('/calculate', function ($request, $response, $args) {
    $view = Twig::fromRequest($request);
    $data["result"] = 0;
    return $view->render($response, 'index.html.twig', $data);
})->setName('calculate');


$app->post('/calculate', function ($request, $response, $args) {

    $params = $request->getParsedBody();
    //print_R($params);

    $coord_a_lat = filter_var($params["coord_a_lat"], FILTER_VALIDATE_FLOAT);
    $coord_a_long = filter_var($params["coord_a_long"], FILTER_VALIDATE_FLOAT);
    $coord_b_lat = filter_var($params["coord_b_lat"], FILTER_VALIDATE_FLOAT);
    $coord_b_long = filter_var($params["coord_b_long"], FILTER_VALIDATE_FLOAT);

    $data = [];

    if(!$coord_a_lat || !$coord_a_long || !$coord_b_lat || !$coord_b_long || 
        $coord_a_lat <= -91 || $coord_a_lat >= 91 || 
        $coord_b_lat <= -91 || $coord_b_lat >= 91 || 
        $coord_a_long <= -181 || $coord_a_long >= 181 || 
        $coord_b_long <= -181 || $coord_b_long >= 181) {

        $data["error"] = 1;
    } else {

        /*
        A    D
        C    B

        area = width * height
        perimeter = 2*width + 2*height
        
        */


        $coord_c_lat = $coord_a_lat;
        $coord_c_long = $coord_b_long;
        $coord_d_lat = $coord_b_lat;
        $coord_d_long = $coord_a_long;

        $width = $coord_d_lat - $coord_a_lat;
        $height = $coord_b_long - $coord_d_long;

        $width = measure($coord_a_lat, $coord_a_long, $coord_d_lat, $coord_d_long);
        $height = measure($coord_b_lat, $coord_b_long, $coord_d_lat, $coord_d_long);

        //echo "width: " . $width . " / height: " . $height . "<br><br>";

        $area = $width * $height;    
        $perimeter = (2 * $width) + (2 * $height);


        $data["coord_a_lat"] = $coord_a_lat;
        $data["coord_a_long"] = $coord_a_long;
        $data["coord_b_lat"] = $coord_b_lat;
        $data["coord_b_long"] = $coord_b_long;

        $data["coord_c_lat"] = $coord_c_lat;
        $data["coord_c_long"] = $coord_c_long;
        $data["coord_d_lat"] = $coord_d_lat;
        $data["coord_d_long"] = $coord_d_long;

        $data["area"] = $area;
        $data["perimeter"] = $perimeter;
        $data["result"] = 1;

        require_once "../config/info.php";
        
        $wire_w = ($width) - CORNER_SIZE_M - PILLAR_SIZE_M - GATE_SIZE_M - PILLAR_SIZE_M - PILLAR_SIZE_M - CORNER_SIZE_M;
        $wire_h = ($height) - CORNER_SIZE_M - PILLAR_SIZE_M - GATE_SIZE_M - PILLAR_SIZE_M - PILLAR_SIZE_M - CORNER_SIZE_M;

        //echo "wire_w: " . $wire_w;
        $wire_only_w = $wire_w / WIRE_SIZE_M;
        $wire_w = $wire_w / (WIRE_SIZE_M + PILLAR_SIZE_M);

        $pillar_length_w = $wire_only_w - $wire_w;
        $pillar_length_w = ceil($pillar_length_w);
        if(fmod($pillar_length_w, PILLAR_SIZE_M) != 0) {
            $pillar_length_w = $pillar_length_w + fmod($pillar_length_w, PILLAR_SIZE_M);
        }


        $wire_w = ceil($wire_w);
        if($wire_w % WIRE_SIZE_M != 0) {
            $wire_w = $wire_w + ($wire_w % WIRE_SIZE_M);
        }
        //echo "<br>wire_w rounded: " . $wire_w;
        

        //echo "<br>wire_h: " . $wire_h;
        $wire_only_h = $wire_h / WIRE_SIZE_M;
        $wire_h = $wire_h / (WIRE_SIZE_M + PILLAR_SIZE_M);

        $pillar_length_h = $wire_only_h - $wire_h;
        $pillar_length_h = ceil($pillar_length_h);
        if(fmod($pillar_length_h, PILLAR_SIZE_M) != 0) {
            $pillar_length_h = $pillar_length_h + fmod($pillar_length_h, PILLAR_SIZE_M);
        }

        $wire_h = ceil($wire_h);
        if($wire_h % WIRE_SIZE_M != 0) {
            $wire_h = $wire_h + ($wire_h % WIRE_SIZE_M);
        }
        //echo "<br>wire_h rounded: " . $wire_h;

        $wire_w_cost = $wire_w * WIRE_COST_EUR;
        $wire_h_cost = $wire_h * WIRE_COST_EUR;
        $corner_cost = 4 * CORNER_COST_EUR;
        $gate_cost = 4 * GATE_COST_EUR;
        $pillar_cost = 3 * 2 * 2 * PILLAR_COST_EUR + ($pillar_length_h / PILLAR_SIZE_M * 2 * PILLAR_COST_EUR) + ($pillar_length_w / PILLAR_SIZE_M * 2 * PILLAR_COST_EUR);
        $total_cost = ($wire_w_cost * 2) + ($wire_h_cost * 2) + $corner_cost + $gate_cost + $pillar_cost;

        $data["cost"] = $total_cost;
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'index.html.twig', $data);
})->setName('calculate');


/*
https://en.wikipedia.org/wiki/Haversine_formula
https://stackoverflow.com/questions/639695/how-to-convert-latitude-or-longitude-to-meters
*/
function measure($lat1, $lon1, $lat2, $lon2) {
    $R = 6378.137; // Radius of earth in KM
    $dLat = $lat2 * pi() / 180 - $lat1 * pi() / 180;
    $dLon = $lon2 * pi() / 180 - $lon1 * pi() / 180;
    $a = sin($dLat/2) * sin($dLat/2) +
    cos($lat1 * pi() / 180) * cos($lat2 * pi() / 180) *
    sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $d = $R * $c;
    return $d * 1000; // meters
}

// Run app
$app->run();

