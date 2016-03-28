<?php
require_once('vendor/autoload.php');
use \Firebase\JWT\JWT;
class ssas {

    private static $mysqlServer = 'localhost';
    private static $mysqlUser = 'root';
    private static $mysqlPass = '';
    private static $mysqlDb = 'ssas';
    private static $mysqli;
    private static $key = "secret";
    private static $data;

    function ssas(){
        self::$mysqli = new mysqli(self::$mysqlServer,self::$mysqlUser,self::$mysqlPass,self::$mysqlDb);
    }

    function authenticate(){
        if(isset($_COOKIE['token'])){
            try{
                $token = $_COOKIE['token'];
                $data = (array) JWT::decode($token,self::$key,array('HS512')); //will throw exception!
		self::$data = (array) $data['data'];
                return true; 
            } catch (Exception $e){
                self::logout();
            }
	    return false;
        }
    }

    function logout(){
        if(isset($_COOKIE['token'])){
	    unset($_COOKIE['token']);
            setcookie('token', '', time() - 3600);
        }
    }

    function isUserLoggedIn(){
	if(!isset(self::$data) && isset($_COOKIE['token'])) self::authenticate();
	return isset(self::$data);
    }

    function getUid(){
       if(isset(self::$data)) return $data['uid'];
    }

    function createUser($username, $password){

        //Generates salt in base64 and password hash
        $salt = mcrypt_create_iv(22,MCRYPT_DEV_URANDOM);
        $ench_password = password_hash($password, PASSWORD_BCRYPT, ['salt' => $salt ]);

        //Insert user into database
        if ($query = self::$mysqli -> prepare('INSERT INTO user(username,password) VALUES (?,?)')){
            $query -> bind_param('ss', $username,$ench_password);
            $query -> execute();

            //If exactly one row was affacted then we know that the user was inserted.
            return $query -> affected_rows == 1;
        }
        return false;
    }

    function login($username, $password){
        //Query to get the user salt
        if($query = self::$mysqli -> prepare('SELECT id,password FROM user WHERE username = ?')){
            $query -> bind_param('s', $username);
            $query -> execute();
            $query -> store_result();

            //If there is a result then there is a salt
            if($query -> num_rows > 0){
                $query -> bind_result($uid, $hash);
                $query -> fetch();
            }
        }

        //If a salt is set then we continue
        if(isset($hash) && password_verify($password,$hash)){
            $tokenId = base64_encode(mcrypt_create_iv(32,MCRYPT_DEV_URANDOM));
            $issuedAt = time();
            $notBefore = $issuedAt;
            $expire = $notBefore + 3600;
            $serverName = $SERVER['SERVER_NAME'];
            $data = [
                'iat' => $issuedAt,
                'jti' => $tokenId,
                'iss' => $serverName,
                'nbf' => $notBefore,
                'exp' => $expire,
                'data' => [
                    'uid' => $uid,
                    'username' => $username
                ]
            ];

            $jwt = JWT::encode($data,self::$key,'HS512');
            setcookie("token", $jwt, -1);
            return true;
        }
        return false; 
    }


    function uploadImage($img){
        if(self::userIsLoggedIn()){
            if($query = self::$mysqli -> prepare('INSERT INTO image(owner_id, image) VALUES(?,?)')){
                $query -> bind_param('is', self::getUid(), $img);
                $query -> exercute();
                return $query -> affected_rows == 1;
            }
        }
        return false;
    }

    function shareImage($iid, $sid)
    {
        if(self::isUserLoggedIn()){

            //Owner check
            if($query = self::$mysqli -> prepare('SELECT COUNT(*) FROM image WHERE id = ? AND owner_id = ?')){
                $query -> bind_param('ii', $iid, self::getUid());
                $query -> execute();
                if($query -> num_rows <= 0) return false;
            }

            //Inserting sharing of image into database
            if($query = self::$mysqli -> prepare('INSERT INTO shared_image VALUES (?,?)')){
                $query -> bind_param('ii', $iid, $sid);
                $query -> execute();
                return $query -> affected_rows == 1;
            }
            return false;
        }
    }


    function getImage($iid)
    {
        if(self::isUserLoggedIn() && self::verifyShare(self::getUid(), $iid))
        {
            if($query = self::$mysqli -> prepare('SELECT image WHERE id = ?')){
                $query -> bind_param('i', $iid);
                $query -> execute();
                $query -> store_result();
                $query -> bind_result($img);
                $query -> fetch();
                return $img;
            }
        }

        return false;
    }

    function comment($iid, $comment)
    {
        if(self::isUserLoggedIn() && self::verifyShare(self::getUid(), $iid))
        {
            if($query = self::$mysqli -> prepare('INSERT INTO post(text, user_id, image_id) VALUES (?,?,?)')){
                $query -> bind_param('sii', $comment, self::getUid(), $iid);
                $query -> execute();
                return $query -> affected_rows == 1;
            }
        }
        return false;
    }

    function getComments($iid)
    {
        //For improvements see http://php.net/manual/en/mysqli-stmt.get-result.php
        //TODO get usernames also
        if(self::isUserLoggedIn() && self::verifyShare(self::getUid(), $iid))
        {
            $comments = array();
            if($query = self::$mysqli -> prepare('SELECT text FROM post WHERE image_id = ?')){
                $query -> bind_param('i', $iid);
                $query -> execute();
                $query -> store_result();

                $query -> bind_result($text);
                while($query -> fetch()){
                    $comments[] = $text;
                }

                return $comments;
            }
        }
        return false;
    }

    function verifyShare($uid, $iid)
    {
        if($query = self::$mysqli -> prepare('SELECT COUNT(*) FROM shared_image WHERE user_id = ? AND image_id = ?')){
            $query -> bind_param('ii', $uid, $iid);
            $query -> execute();
            return $query -> num_rows > 0;
        }
        return false;
    }
}
?>
