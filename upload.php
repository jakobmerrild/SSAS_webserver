<?php
//Remove error reporting in final version!
ini_set('display_errors', 1);
error_reporting(~0);
require_once("ssas.php");
$ssas = new Ssas();

if(!$ssas -> isUserLoggedIn()){
    header("Location: index.php");
    echo "redicrect!";
    exit();
}

//If a POST occured, try to authenticate
if(isset($_FILES['image'])){
    $file_tmp= $_FILES['image']['tmp_name'];
    $type = pathinfo($file_tmp, PATHINFO_EXTENSION);
    $data = file_get_contents($file_tmp);
    $image = 'data:image/' . $type . ';base64,' . base64_encode($data);
    $result = $ssas -> uploadImage($image);
    if($result){
        header("Location: index.php");
        echo "redicrect!";
        exit();
    }
}

?>

<?php include 'header.php'; ?>
<?php if($ssas -> isUserLoggedIn()){ ?>
<div class="row">
    <div class="col-sm-8 col-sm-offset-2">
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="password">Image:</label>
                <input
                    id="image"
                    type="file"
                    class="form-control"
                    name="image"
                >
            </div>
            <button class="btn btn-success" type="submit">Upload</button>
        </form>
    </div>
</div>
<?php } ?>
<?php if(isset($result) && !$result){ ?>
        </br>
        <div class="alert alert-danger" role="alert">
            <strong>Ups!</strong> Image could not be uploaded.
        </div>
<?php } ?>
<?php if(isset($result) && $result){ ?>
        </br>
        <div class="alert alert-success" role="alert">
            <strong>Success!</strong> Image was uploaded!
        </div>
<?php } ?>
<?php include 'footer.php'; ?>
