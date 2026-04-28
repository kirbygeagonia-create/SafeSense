<?php

class UserController extends BaseController {

    public function index() {
        $this->requireLogin();
        $this->requireRole('admin');

        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('users/index', [
            'users'       => $users,
            'title'       => 'User Management',
            'currentRole' => $this->currentRole()
        ]);
    }

    public function store() {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/users');
            return;
        }
        $this->requireLogin();
        $this->requireRole('admin');
        $this->validateCsrf();

        $name     = trim($this->getPostData('name') ?? '');
        $email    = trim($this->getPostData('email') ?? '');
        $password = $this->getPostData('password') ?? '';
        $role     = $this->getPostData('role') ?? 'staff';

        if (empty($name) || empty($email) || empty($password)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Name, email, and password are required.'], 422);
            $_SESSION['flash_error'] = 'Name, email, and password are required.';
            $this->redirect('/users');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid email address.'], 422);
            $_SESSION['flash_error'] = 'Invalid email address.';
            $this->redirect('/users');
            return;
        }

        if (!in_array($role, ['admin','doctor','nurse','staff'], true)) {
            $role = 'staff';
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $database = new Database();
        $db = $database->getConnection();
        try {
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
            $stmt->execute([
                ':name'     => htmlspecialchars(strip_tags($name)),
                ':email'    => htmlspecialchars(strip_tags($email)),
                ':password' => $hash,
                ':role'     => $role
            ]);
            $id = $db->lastInsertId();

            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User created successfully',
                    'data'    => [
                        'id'         => $id,
                        'name'       => $name,
                        'email'      => $email,
                        'role'       => $role,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'User created successfully';
            $this->redirect('/users');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                if ($this->isAjax())
                    $this->jsonResponse(['success' => false, 'message' => 'A user with this email already exists.'], 422);
                $_SESSION['flash_error'] = 'A user with this email already exists.';
                $this->redirect('/users');
                return;
            }
            throw $e;
        }
    }

    public function edit() {
        $this->requireLogin();
        $this->requireRole('admin');

        $id = (int)($this->getGetData('id') ?? 0);
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid user ID'], 400);
            $this->redirect('/users');
            return;
        }

        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'User not found'], 404);
            $_SESSION['flash_error'] = 'User not found';
            $this->redirect('/users');
            return;
        }

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'data'    => $user
            ]);
        }
        $this->redirect('/users');
    }

    public function update() {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/users');
            return;
        }
        $this->requireLogin();
        $this->requireRole('admin');
        $this->validateCsrf();

        $id       = (int)($this->getPostData('id') ?? 0);
        $name     = trim($this->getPostData('name') ?? '');
        $email    = trim($this->getPostData('email') ?? '');
        $role     = $this->getPostData('role') ?? 'staff';
        $password = $this->getPostData('password') ?? '';

        if (!$id || empty($name) || empty($email)) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Name and email are required.'], 422);
            $_SESSION['flash_error'] = 'Name and email are required.';
            $this->redirect('/users');
            return;
        }

        if (!in_array($role, ['admin','doctor','nurse','staff'], true)) {
            $role = 'staff';
        }

        $database = new Database();
        $db = $database->getConnection();

        // Prevent self-role downgrade lockout
        $currentUserEmail = $_SESSION['user']['email'] ?? '';
        $stmt = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($targetUser && $targetUser['email'] === $currentUserEmail && $role !== 'admin') {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'You cannot remove your own admin role.'], 403);
            $_SESSION['flash_error'] = 'You cannot remove your own admin role.';
            $this->redirect('/users');
            return;
        }

        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET name = :name, email = :email, role = :role, password = :password WHERE id = :id");
                $stmt->execute([
                    ':name'     => htmlspecialchars(strip_tags($name)),
                    ':email'    => htmlspecialchars(strip_tags($email)),
                    ':role'     => $role,
                    ':password' => $hash,
                    ':id'       => $id
                ]);
            } else {
                $stmt = $db->prepare("UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id");
                $stmt->execute([
                    ':name'  => htmlspecialchars(strip_tags($name)),
                    ':email' => htmlspecialchars(strip_tags($email)),
                    ':role'  => $role,
                    ':id'    => $id
                ]);
            }

            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User updated successfully',
                    'data'    => [
                        'id'    => $id,
                        'name'  => $name,
                        'email' => $email,
                        'role'  => $role
                    ]
                ]);
            }
            $_SESSION['flash_success'] = 'User updated successfully';
            $this->redirect('/users');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                if ($this->isAjax())
                    $this->jsonResponse(['success' => false, 'message' => 'A user with this email already exists.'], 422);
                $_SESSION['flash_error'] = 'A user with this email already exists.';
                $this->redirect('/users');
                return;
            }
            throw $e;
        }
    }

    public function delete() {
        if (!$this->isPostRequest()) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $this->redirect('/users');
            return;
        }
        $this->requireLogin();
        $this->requireRole('admin');
        $this->validateCsrf();

        $id = (int)($this->getPostData('id') ?? 0);
        if (!$id) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'Invalid user ID'], 400);
            $_SESSION['flash_error'] = 'Invalid user ID';
            $this->redirect('/users');
            return;
        }

        // Prevent self-deletion
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['email'] === ($_SESSION['user']['email'] ?? '')) {
            if ($this->isAjax())
                $this->jsonResponse(['success' => false, 'message' => 'You cannot delete your own account.'], 403);
            $_SESSION['flash_error'] = 'You cannot delete your own account.';
            $this->redirect('/users');
            return;
        }

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'message' => 'User deleted successfully']);
        }
        $_SESSION['flash_success'] = 'User deleted successfully';
        $this->redirect('/users');
    }
}
