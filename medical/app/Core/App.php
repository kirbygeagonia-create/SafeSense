<?php

class App
{
    private $router;

    public function __construct()
    {
        $this->router = new Router();
        $this->initRoutes();
    }

    private function initRoutes()
    {
        $this->router->get('/', 'AuthController@dashboard');
        $this->router->get('/patients', 'PatientController@index');
        $this->router->post('/patients/store', 'PatientController@store');
        $this->router->get('/patients/edit', 'PatientController@edit');
        $this->router->post('/patients/update', 'PatientController@update');
        $this->router->post('/patients/delete', 'PatientController@delete');

        $this->router->get('/doctors', 'DoctorController@index');
        $this->router->post('/doctors/store', 'DoctorController@store');
        $this->router->get('/doctors/edit', 'DoctorController@edit');
        $this->router->post('/doctors/update', 'DoctorController@update');
        $this->router->post('/doctors/delete', 'DoctorController@delete');

        $this->router->get('/appointments', 'AppointmentController@index');
        $this->router->post('/appointments/store', 'AppointmentController@store');
        $this->router->get('/appointments/edit', 'AppointmentController@edit');
        $this->router->post('/appointments/update', 'AppointmentController@update');
        $this->router->post('/appointments/delete', 'AppointmentController@delete');

        $this->router->get('/login', 'AuthController@login');
        $this->router->post('/login/authenticate', 'AuthController@authenticate');
        $this->router->post('/logout', 'AuthController@logout');
        $this->router->get('/dashboard', 'AuthController@dashboard');

        // SafeSense IoT Routes
        $this->router->post('/api/alert', 'AlertController@receive');
        $this->router->get('/alerts', 'AlertController@index');
        $this->router->get('/api/alerts/poll', 'AlertController@poll');
        $this->router->post('/api/alerts/read', 'AlertController@markRead');
        $this->router->post('/api/alerts/dismiss', 'AlertController@dismiss');

        // Dashboard analytics API
        $this->router->get('/api/dashboard/stats', 'DashboardController@stats');

        $this->router->get('/{any:.*}', 'ErrorController@notFound');
    }

    public function run()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'];

        $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if ($basePath === '/') {
            $basePath = '';
        }
        
        if (str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        if ($uri === '') {
            $uri = '/';
        }

        $route = $this->router->match($uri, $requestMethod);

        if ($route !== null) {
            $target = $route['target'];
            $params = $route['params'];

            if (strpos($target, '@') !== false) {
                list($controllerName, $methodName) = explode('@', $target, 2);
            } else {
                $controllerName = $target . 'Controller';
                $methodName = 'index';
            }

            // Pre-load models for AlertController
            if ($controllerName === 'AlertController') {
                $alertModelFile = APP_PATH . '/Models/Alert.php';
                if (file_exists($alertModelFile))
                    require_once $alertModelFile;
            }

            $controllerFile = APP_PATH . '/Controllers/' . $controllerName . '.php';

            if (file_exists($controllerFile)) {
                require_once $controllerFile;
                $controller = new $controllerName();

                if (method_exists($controller, $methodName)) {
                    call_user_func_array([$controller, $methodName], $params);
                } else {
                    $controller->index();
                }
            } else {
                http_response_code(404);
                echo '<h1>404 - Controller Not Found: ' . htmlspecialchars($controllerName) . '</h1>';
            }
        } else {
            http_response_code(404);
            echo '<h1>404 - Page Not Found</h1>';
        }
    }
}
?>