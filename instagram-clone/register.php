<?php
/**
 * Registration Page
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

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($full_name) || empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = $user->register($username, $email, $password, $full_name);
        if ($result['success']) {
            // Auto login after registration
            $login_result = $user->login($username, $password);
            if ($login_result['success']) {
                header('Location: index.php');
                exit();
            } else {
                $success = 'Registration successful! Please log in.';
            }
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
    <title>Sign up - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1 class="logo"><?php echo APP_NAME; ?></h1>
            <p class="tagline">Sign up to see photos and videos from your friends.</p>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <input type="email"
                       name="email"
                       placeholder="Email"
                       required
                       autocomplete="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                <input type="text"
                       name="full_name"
                       placeholder="Full Name"
                       required
                       autocomplete="name"
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">

                <input type="text"
                       name="username"
                       placeholder="Username"
                       required
                       autocomplete="username"
                       pattern="[a-zA-Z0-9_]{3,30}"
                       title="Username must be 3-30 characters and contain only letters, numbers, and underscores"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">

                <input type="password"
                       name="password"
                       placeholder="Password"
                       required
                       autocomplete="new-password"
                       minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">

                <button type="submit" name="register" class="btn-primary">Sign Up</button>
            </form>

            <p class="terms">
                By signing up, you agree to our Terms, Data Policy and Cookies Policy.
            </p>
        </div>

        <div class="auth-box">
            <p>Have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>
</body>
</html>
