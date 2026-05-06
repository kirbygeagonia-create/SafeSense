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

    // ── Task 2: CSRF Protection ────────────────────────────────────────────
    protected function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function validateCsrf(): void {
        // Accept token from AJAX header or fallback hidden form field
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
               ?? $_POST['_csrf_token']
               ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'CSRF token validation failed.'], 403);
            }
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            $this->redirect('/dashboard');
            exit;  // ← missing exit added
        }
    }

    protected function requireLogin(): void {
        if (empty($_SESSION['user'])) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Unauthorized. Please log in.'], 401);
            }
            $_SESSION['flash_error'] = 'Please log in to access that page.';
            $this->redirect('/login');
            exit;
        }
    }

    protected function requireRole($allowedRoles): void {
        $role = $_SESSION['user']['role'] ?? '';
        $allowed = (array) $allowedRoles;
        if (!in_array($role, $allowed, true)) {
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'You do not have permission to perform this action.'
                ], 403);
            }
            $_SESSION['flash_error'] = 'Access denied. You do not have permission for that action.';
            $this->redirect('/dashboard');
            exit;
        }
    }

    protected function currentRole(): string {
        return $_SESSION['user']['role'] ?? 'staff';
    }

    /**
     * Write an entry to the audit_logs table.
     * Call this inside any store(), update(), or delete() method after a successful DB operation.
     *
     * @param string   $action     e.g. 'create', 'update', 'delete'
     * @param string   $resource   e.g. 'patient', 'appointment', 'billing'
     * @param int|null $resourceId The ID of the affected record
     * @param string   $detail     Optional human-readable description
     */
    protected function logAction(string $action, string $resource, ?int $resourceId = null, string $detail = ''): void
    {
        try {
            $database = new Database();
            $db       = $database->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO audit_logs (user_email, user_role, action, resource, resource_id, detail, ip_address)
                 VALUES (:email, :role, :action, :resource, :resource_id, :detail, :ip)"
            );
            $stmt->execute([
                ':email'       => $_SESSION['user']['email']  ?? 'unknown',
                ':role'        => $_SESSION['user']['role']   ?? 'unknown',
                ':action'      => $action,
                ':resource'    => $resource,
                ':resource_id' => $resourceId,
                ':detail'      => $detail,
                ':ip'          => $_SERVER['REMOTE_ADDR']     ?? null,
            ]);
        } catch (Exception $e) {
            // Audit log failure must never crash the app — log silently
            error_log('Audit log error: ' . $e->getMessage());
        }
    }
}