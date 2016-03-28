<?php
//Remove error reporting in final version!
ini_set('display_errors', 1);
error_reporting(~0);
require_once("ssas.php");
?>

<?php include 'header.php'; ?>
<?php if(!$ssas -> isUserLoggedIn()){ ?>

    <h1>Hello, world!</h1>
    <p>You are logged in!</p>

<?php } else { ?>

    <h1>Hello, world!</h1>
    <a href="login.php>Login</a>

<?php } ?>
<?php include 'footer.php'; ?>
