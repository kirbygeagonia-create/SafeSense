<?php

class BaseController {
    protected function render($view, $data = []) {
        // Extract data to variables
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include the view file
        $viewPath = APP_PATH . '/Views/' . $view . '.php';
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo '<h1>View not found: ' . $view . '</h1>';
        }
        
        // Get the content and clean the buffer
        $content = ob_get_clean();
        
        // Use auth layout for login/auth pages, main layout for everything else
        $layoutName = (strpos($view, 'auth/') === 0) ? 'auth' : 'main';
        include APP_PATH . '/Views/layouts/' . $layoutName . '.php';
    }
    
    protected function redirect($url) {
        if (!str_starts_with($url, 'http')) {
            $url = url($url);
        }
        header('Location: ' . $url);
        exit();
    }
    
    protected function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    protected function isPostRequest() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    protected function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    protected function getPostData($key, $default = null) {
        return $_POST[$key] ?? $default;
    }
    
    protected function getGetData($key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    
    protected function validateRequiredFields($fields) {
        $errors = [];
        
        foreach ($fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = $field . ' is required';
            }
        }
        
        return $errors;
    }
}