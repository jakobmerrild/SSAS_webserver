<?php
ini_set('display_errors', 1);
error_reporting(~0);
require_once("ssas.php");
if(!empty($_POST)){
	$ssas = new ssas;
	$ssas -> createUser($_POST["username"],$_POST["password"]);
}
?>
<div class="row">
	<form method="post" action="/register.php">
	<div class="col-xs-6 col-sm-2">
		Username: <input type="string" name="username">
	</div>
	<div class="col-xs-6 col-sm-2">
		Password: <input type="password" name="password">
	</div>
	<div class="col-xs-12 col-sm-2">
		<input type="submit" name="submit" value="Submit">
	</div>
	
	</form>
</div>