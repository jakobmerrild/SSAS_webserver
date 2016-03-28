<?php
//TODO Remove error reporting in final version!
ini_set('display_errors', 1);
error_reporting(~0);

//Getting ssas class
require_once("ssas.php");
$ssas = new ssas();

//If a POST occured, try to authenticate
if(isset($_POST['username']) && isset($_POST['password'])){
    $result = $ssas -> createUser($_POST['username'],$_POST['password']);
    if($result) header("Location: login.php"); //Bugfix, otherwise the reditect to index is without cookies (for some reason!)
}

//If the user is already logged in, redirect to index.php
if($ssas -> isUserLoggedIn()){
    header("Location: index.php");
    exit();
}
?>

<?php include 'header.php'; ?>

<div class="row">
    <div class="col-sm-4 col-sm-offset-4">
        <form action="register.php" method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input 
                    id="username"
                    type="text" 
                    class="form-control"
                    name="username" 
                >
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input 
                    id="password"
                    type="password" 
                    class="form-control" 
                    name="password"
                >
            </div>
            <button type="submit" class="btn btn-default">Log in</button>
        </form>

<?php if(isset($result) && !$result){ ?>
        <div class="alert alert-danger" role="alert">
            <strong>Oh snap!</strong> Username was not available!
        </div>
<?php } ?>

    </div>
</div>

<?php include 'footer.php'; ?>
