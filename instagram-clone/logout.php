<?php
/**
 * Logout Page
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

$user = new User();

if (isset($_SESSION['session_token'])) {
    $user->logout($_SESSION['session_token']);
}

header('Location: login.php');
exit();
