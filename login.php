<?php
	//TODO Remove error reporting in final version!
	ini_set('display_errors', 1);
	error_reporting(~0);
	
	//Getting ssas class
	require_once("ssas.php");
	$ssas = new ssas();

	//If a POST occured, try to authenticate
	if(isset($_POST['username']) && isset($_POST['password'])){
		$result = $ssas -> login($_POST['username'],$_POST['password']);
	}

	//If the user is already logged in, redirect to index.php
	if($ssas -> isUserLoggedIn()){
		header("Location: index.php");
		exit();
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
		<div class="container">
			<div class="row">
			<div class="col-sm-4 col-sm-offset-4">
			<form action="login.php" method="post">
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
  <strong>Oh snap!</strong> Wrong username or password!
</div>
<?php } ?>
</div>
</div>
		</div>
	</body>
</html>
