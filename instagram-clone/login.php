<?php
/**
 * Login Page
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

$user = new User();
$error = '';
$success = '';

// Check if already logged in
if ($user->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = $user->login($username_or_email, $password);
        if ($result['success']) {
            header('Location: index.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1 class="logo"><?php echo APP_NAME; ?></h1>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <input type="text"
                       name="username_or_email"
                       placeholder="Username or Email"
                       required
                       autocomplete="username"
                       value="<?php echo htmlspecialchars($_POST['username_or_email'] ?? ''); ?>">

                <input type="password"
                       name="password"
                       placeholder="Password"
                       required
                       autocomplete="current-password">

                <button type="submit" name="login" class="btn-primary">Log In</button>
            </form>

            <div class="divider">
                <span>OR</span>
            </div>

            <div class="forgot-password">
                <a href="forgot-password.php">Forgot password?</a>
            </div>
        </div>

        <div class="auth-box">
            <p>Don't have an account? <a href="register.php">Sign up</a></p>
        </div>
    </div>
</body>
</html>
