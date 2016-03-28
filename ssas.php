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

    function comment($iid, $comment)
    {
	$count;

	if($query = self::$mysqli -> prepare('SELECT COUNT FROM shared_image WHERE user_id = ? AND image_id = ?')){
            $query -> bind_param('ii', $uid, $iid);
            $query -> execute();
            $query -> store_result();
            $query -> bind_result($count);
            $query -> fetch();
	}

	//TODO: Better error handling
	if($count > 0)
	{
	    if($query = self::$mysqli -> prepare('INSERT INTO post(text, user_id, image_id) VALUES (?,?,?)')){
		$query -> bind_param('sii', $comment, $uid, $iid);
		$query -> execute();
	    }
	}
	 
    }


}
?>
