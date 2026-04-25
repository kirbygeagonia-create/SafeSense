<?php

class ErrorController extends BaseController {
    public function notFound() {
        http_response_code(404);
        $this->render('errors/404', [
            'title' => 'Page Not Found'
        ]);
    }
    
    public function error() {
        http_response_code(500);
        $this->render('errors/500', [
            'title' => 'Internal Server Error'
        ]);
    }
}