<?php


class ssas {

    private static $mysqlServer = 'localhost';
    private static $mysqlUser = 'root';
    private static $mysqlPass = '';
    private static $mysqlDb = 'ssas';
    private static $mysqli;
    private static $uid;


    function ssas(){
        self::$mysqli = new mysqli(self::$mysqlServer,self::$mysqlUser,self::$mysqlPass,self::$mysqlDb);
    }

    function createUser($username, $password){


        $options = [
            'salt' => mcrypt_create_iv(22, MCRYPT_DEV_URANDOM)
        ];
        $ench_password = password_hash($password, PASSWORD_BCRYPT, $options);

        if ($query = self::$mysqli -> prepare('INSERT INTO user(username,password,salt) VALUES (?,?,?)')){
            $query -> bind_param('sss', $username,$ench_password,$options['salt']);
            $query -> execute();
        }
        

        echo "username: " . $username;
    }

    function uploadImage($img)
    {
	    if($query = self::$mysqli -> prepare('INSERT INTO image(owner_id, image) VALUES(?,?)')){
	    	$query -> bind_param('is', $uid, $img);
		$query -> exercute();
	    }
    }

    function shareImage($iid, $sid)
    {
	$owner_id;

	if($query = self::$mysqli -> prepare('SELECT owner_id FROM image WHERE id = ?')){
            $query -> bind_param('i', $iid);
            $query -> execute();
            $query -> store_result();
            $query -> bind_result($owner_id);
            $query -> fetch();
        }

	//TODO: Better error handling
	if($owner_id == $uid)
	{
      	    if($query = self::$mysqli -> prepare('INSERT INTO shared_image VALUES (?,?)')){
                $query -> bind_param('ii', $iid, $sid);
	        $query -> execute();
	    }
	}
    }


    function getImage($iid)
    {
	if(verifyShare($uid, $iid))
	{
	    if($query = self::$mysqli -> prepare('SELECT image WHERE id = ?')){
		$query -> bind_param('i', $iid);
		$query -> execute();
		$query -> store_result();
		$query -> bind_result($img);
		$query -> fetch();
	    }
	}

	//TODO: Return image
	return $img;
    }

    function getImages()
    {
	$imgs;

	//TODO: Shared images
	if($query = self::$mysqli -> prepare('SELECT * FROM image WHERE owner_id = ?')){
            $query -> bind_param('i', $uid);
            $query -> execute();
            $query -> store_result();
            $query -> bind_result($imgs);
	    $query -> fetch();
	}

	//Return images
	return $imgs;
    }

    function comment($iid, $comment)
    {
	if(verifyShare($uid, $iid))
	{
	    if($query = self::$mysqli -> prepare('INSERT INTO post(text, user_id, image_id) VALUES (?,?,?)')){
		$query -> bind_param('sii', $comment, $uid, $iid);
		$query -> execute();
	    }
	}
	 
    }
    
    function getComments($iid)
    {
	$comments;

	if(verifyShare($uid, $iid))
	{
	    if($query = self::$mysqli -> prepare('SELECT * FROM post WHERE image_id = ?')){
                $query -> bind_param('i', $iid);
		$query -> exercute();
		$query -> store_result();
		$query -> bind_result($comments);
		$query -> fetch();
	    }
	}

	return $comments;
    }

    function verifyShare($user, $image)
    {

	$count;

	if($query = self::$mysqli -> prepare('SELECT COUNT FROM shared_image WHERE user_id = ? AND image_id = ?')){
            $query -> bind_param('ii', $uid, $iid);
            $query -> execute();
            $query -> store_result();
            $query -> bind_result($count);
            $query -> fetch();
	}

	return count > 0;
    }



}
?>
