<?php
ini_set('display_errors', 1);
error_reporting(~0);
require_once("ssas.php");
if(!empty($_POST)){
	$ssas = new ssas;
	$ssas -> createUser($_POST["username"],$_POST["password"]);
}
?>
<html>
	<head>
	<!-- Required meta tags always come first -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta http-equiv="x-ua-compatible" content="ie=edge">

	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.2/css/bootstrap.min.css" integrity="sha384-y3tfxAZXuh4HwSYylfB+J125MxIs6mR5FOHamPBG064zB+AFeWH94NdvaCBm8qnd" crossorigin="anonymous">
	</head>
	<body>
		<form role="form">
		  <div class="form-group">
			<label for="username">Username:</label>
			<input type="username" class="form-control" id="username">
		  </div>
		  <div class="form-group">
			<label for="password">Password:</label>
			<input type="password" class="form-control" id="password">
		  </div>
		  <button type="submit" class="btn btn-default">Register</button>
		</form>
	</body>
</html>