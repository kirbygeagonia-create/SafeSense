<?php

class App {
    private $router;
    
    public function __construct() {
        $this->router = new Router();
        $this->initRoutes();
    }
    
    private function initRoutes() {
        // Patient routes
        $this->router->get('/', 'PatientController@index');
        $this->router->get('/patients', 'PatientController@index');
        $this->router->get('/patients/create', 'PatientController@create');
        $this->router->post('/patients/store', 'PatientController@store');
        $this->router->get('/patients/edit', 'PatientController@edit');
        $this->router->post('/patients/update', 'PatientController@update');
        $this->router->post('/patients/delete', 'PatientController@delete');
        
        // Doctor routes
        $this->router->get('/doctors', 'DoctorController@index');
        $this->router->get('/doctors/create', 'DoctorController@create');
        $this->router->post('/doctors/store', 'DoctorController@store');
        $this->router->get('/doctors/edit', 'DoctorController@edit');
        $this->router->post('/doctors/update', 'DoctorController@update');
        $this->router->post('/doctors/delete', 'DoctorController@delete');
        
        // Appointment routes
        $this->router->get('/appointments', 'AppointmentController@index');
        $this->router->get('/appointments/create', 'AppointmentController@create');
        $this->router->post('/appointments/store', 'AppointmentController@store');
        $this->router->get('/appointments/edit', 'AppointmentController@edit');
        $this->router->post('/appointments/update', 'AppointmentController@update');
        $this->router->post('/appointments/delete', 'AppointmentController@delete');
        
        // Authentication routes
        $this->router->get('/login', 'AuthController@login');
        $this->router->post('/login/authenticate', 'AuthController@authenticate');
        $this->router->post('/logout', 'AuthController@logout');
        
        // Dashboard
        $this->router->get('/dashboard', 'AuthController@dashboard');
        
        // Default route (404)
        $this->router->get('/{any:.*}', 'ErrorController@notFound');
    }
    
    public function run() {
        // Get current URL path
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        
        // Remove base path if exists
        $basePath = '';
        if (!empty($_SERVER['BASE_PATH'])) {
            $basePath = rtrim($_SERVER['BASE_PATH'], '/\\');
            if (str_starts_with($uri, $basePath)) {
                $uri = substr($uri, strlen($basePath));
            }
        }
        
        // Match route
        $route = $this->router->match($uri, $requestMethod);
        
        if ($route !== null) {
            // Extract controller and method
            $target = $route['target'];
            $params = $route['params'];
            
            // Parse controller@method
            if (strpos($target, '@') !== false) {
                list($controllerName, $methodName) = explode('@', $target, 2);
            } else {
                $controllerName = $target . 'Controller';
                $methodName = 'index';
            }
            
            // Load controller
            $controllerFile = APP_PATH . '/Controllers/' . $controllerName . '.php';
            
            if (file_exists($controllerFile)) {
                require_once $controllerFile;
                
                // Create controller instance
                $controller = new $controllerName();
                
                // Call method with parameters if it exists
                if (method_exists($controller, $methodName)) {
                    call_user_func_array([$controller, $methodName], $params);
                } else {
                    // Default to index method
                    $controller->index();
                }
            } else {
                // Handle 404
                http_response_code(404);
                echo '<h1>404 - Controller Not Found: ' . htmlspecialchars($controllerName) . '</h1>';
            }
        } else {
            // Handle 404
            http_response_code(404);
            echo '<h1>404 - Page Not Found</h1>';
        }
    }
}