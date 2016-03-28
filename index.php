<?php
//Remove error reporting in final version!
ini_set('display_errors', 1);
error_reporting(~0);
require_once("ssas.php");
$ssas = new ssas();
?>

<?php include 'header.php'; ?>
<?php if($ssas -> isUserLoggedIn()){ ?>

    <h1>Hello, world!</h1>
    <p>You are logged in!</p>
    <a href="logout.php">Logout</a>

<?php } else { ?>

    <h1>Hello, world!</h1>
    <a href="login.php">Login</a> or <a href="register.php">Register</a>

<?php } ?>
<?php include 'footer.php'; ?>
