<?php
require_once('vendor/autoload.php');
use \Firebase\JWT\JWT;
class Ssas {

    private static $mysqlServer = 'localhost';
    private static $mysqlUser = 'root';
    private static $mysqlPass = '';
    private static $mysqlDb = 'ssas';
    private static $mysqli;
    private static $key = "secret";
    private static $data;

    function __construct(){
        self::$mysqli = new mysqli(self::$mysqlServer,self::$mysqlUser,self::$mysqlPass,self::$mysqlDb);
    }

    function authenticate(){
        if(isset($_COOKIE['token'])){
            try{
                //Retrives the JWT token from the cookie
                $token = $_COOKIE['token'];

                //Decrypts the token. This call will throw an exception if the token is invalid
                $token = (array) JWT::decode($token,self::$key,array('HS512'));

                //Extracts the user data from the token
                self::$data = (array) $token['data'];
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

    function &getUid(){
    if(self::isUserLoggedIn()) return self::$data['uid'];
    }

    function &getUsername(){
    if(self::isUserLoggedIn()) return $data['username'];
    }

    function createUser($username, $password){

        //Generates salt
        $salt = mcrypt_create_iv(22,MCRYPT_DEV_URANDOM);

        //Hashes password. The returned string contains both password hash and salt
        $hash = password_hash($password, PASSWORD_BCRYPT, ['salt' => $salt ]);

        //Inserts username and password hash into the database
        if ($query = self::$mysqli -> prepare('INSERT INTO user(username,password) VALUES (?,?)')){
            $query -> bind_param('ss', $username,$hash);
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

        //If password and hash matches then we continue
        if(isset($hash) && password_verify($password,$hash)){
            //Generates random tokenid
            $tokenId = base64_encode(mcrypt_create_iv(32,MCRYPT_DEV_URANDOM));
            $issuedAt = time(); //time of issue
            $notBefore = $issuedAt; //can be used to say that a token is not valid before a given time (not used)
            $expire = $notBefore + 3600; //token expire data
            //$serverName = $SERVER['SERVER_NAME'];
            $data = [
                'iat' => $issuedAt,
                'jti' => $tokenId,
                //'iss' => $serverName,
                'nbf' => $notBefore,
                'exp' => $expire,
                'data' => [
                    'uid' => $uid,
                    'username' => $username
                ]
            ];

            //Computes the encrypted token (TODO, maybe change mechanism to RSA?)
            $jwt = JWT::encode($data,self::$key,'HS512');

            //Sets to cookie to never expire as the token itself contains the expiration date (Mimimum exposure)
            setcookie("token", $jwt, -1);

            return true;
        }
        return false;
    }


    function uploadImage($img){
        if(self::isUserLoggedIn()){
            if($query = self::$mysqli -> prepare('INSERT INTO image(owner_id, image) VALUES(?,?)')){
                $query -> bind_param('is', self::getUid(), $img);
                $query -> execute();
                return $query -> affected_rows == 1;
            }
        }
        return false;
    }

    function getUserId($username){
        if($query = self::$mysqli -> prepare('SELECT id FROM user WHERE username = ?')){
            $query -> bind_param('s', $username);
            $query -> execute();
            $query -> store_result();
            if($query -> num_rows > 0){
                $query -> bind_result($uid);
                $query -> fetch();
                return $uid;
            }
        }
        return false;
    }

    function removeShare($iid, $username){
        if(self::isUserLoggedIn() && self::isOwner($iid)){
            $uid = self::getUserId($username);
            if($uid == false) return false;

            //Inserting sharing of image into database
            if($query = self::$mysqli -> prepare('DELETE FROM shared_image WHERE image_id = ? AND user_id = ?')){
                $query -> bind_param('ii', $iid, $uid);
                $query -> execute();
                return $query -> affected_rows == 1;
            }
        }
        return false;
    }

    function shareImage($iid, $username)
    {
        if(self::isUserLoggedIn() && self::isOwner($iid)){

            //Getting uid from username
            $uid = self::getUserId($username);
            if($uid == false) return false;

            //Inserting sharing of image into database
            if($query = self::$mysqli -> prepare('INSERT INTO shared_image VALUES (?,?)')){
                $query -> bind_param('ii', $uid, $iid);
                $query -> execute();
                return $query -> affected_rows == 1;
            }
            return false;
        }
    }

    function getUsersToShareWith($iid){
        if(self::isUserLoggedIn() && self::isOwner($iid)){
            $users = array();
            if($query = self::$mysqli -> prepare('SELECT id,username FROM user WHERE id <> ? AND id NOT IN (SELECT user_id FROM shared_image WHERE image_id = ?)')){
                $query -> bind_param('ii', self::getuid(),$iid);
                $query -> execute();
                $query -> store_result();
                $query -> bind_result($id, $username);
                while($query -> fetch()){
                    $users[] = new user($id,$username);
                }
            }
            return $users;
        }
        return false;
    }

    function sharedWith($iid){
        if(self::isUserLoggedIn() && self::isOwner($iid)){
            $users = array();
            if($query = self::$mysqli -> prepare('SELECT id,username FROM user INNER JOIN shared_image ON id = user_id WHERE image_id = ?')){
                $query -> bind_param('i', $iid);
                $query -> execute();
                $query -> store_result();
                $query -> bind_result($id, $username);
                while($query -> fetch()){
                    $users[] = new User($id,$username);
                }
            }
            return $users;
        }
        return false;
    }

    function getImages(){
        if(self::isUserLoggedIn()){
            $images = array();
            if($query = self::$mysqli -> prepare('SELECT DISTINCT image.id,image,owner_id,username,createdDate FROM image INNER JOIN user on user.id = owner_id LEFT JOIN shared_image ON image_id = image.id WHERE user_id = ? OR owner_id = ? ORDER BY createdDate DESC')){
                $query -> bind_param('ii', self::getUid(), self::getUid());
                $query -> execute();
                $query -> store_result();
                $query -> bind_result($id, $image, $owner_id, $username, $datetime);
                while($query -> fetch()){
                    $images[] = new Image($id,$owner_id,$username,$image,$datetime);
                }
            }
            return $images;
        }
        return false;
    }

    function getImage($iid)
    {
        if(self::isUserLoggedIn())
        {
            if($query = self::$mysqli -> prepare('SELECT image.id,image,owner_id,username,createdDate FROM image INNER JOIN user ON user.id = owner_id LEFT JOIN shared_image ON image_id = image.id WHERE (user_id = ? OR owner_id = ?) AND image.id = ?')){
                $query -> bind_param('iii', self::getUid(), self::getUid(), $iid);
                $query -> execute();
                $query -> store_result();
                $query -> bind_result($id, $image, $owner_id, $username, $datetime);
                if($query -> num_rows > 0){
                    $query -> fetch();
                    return new Image($id,$owner_id,$username,$image,$datetime);
                }
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
        if(self::isUserLoggedIn() && self::verifyShare(self::getUid(), $iid))
        {
            $comments = array();
            if($query = self::$mysqli -> prepare('SELECT post.id,username,text,createdDate FROM post INNER JOIN user ON user_id = user.id WHERE image_id = ? ORDER BY createdDate ASC')){
                $query -> bind_param('i', $iid);
                $query -> execute();
                $query -> store_result();

                $query -> bind_result($id, $user, $text, $datetime);
                while($query -> fetch()){
                    $comments[] = new Comment($id,$user,$text,$datetime);
                }

                return $comments;
            }
        }
        return false;
    }

    function isOwner($iid){
        if($query = self::$mysqli -> prepare('SELECT id FROM image WHERE owner_id = ? AND id = ?')){
            $query -> bind_param('ii', self::getUid(), $iid);
            $query -> execute();
            $query -> store_result();
            return $query -> num_rows > 0;
        }
        return false;
    }

    function verifyShare($uid, $iid)
    {
        if($query = self::$mysqli -> prepare('SELECT id FROM image LEFT JOIN shared_image ON image_id = id WHERE (user_id = ? OR owner_id = ?) AND id = ?')){
            $query -> bind_param('iii', $uid, $uid, $iid);
            $query -> execute();
            $query -> store_result();
            return $query -> num_rows > 0;
        }
        return false;
    }
}

class User{
    private $_id;
    private $_name;

    public function __construct($id, $name){
        $this -> _id = $id;
        $this -> _name = $name;
    }

    public function getName(){ return $this -> _name; }
    public function getId(){ return $this -> _id; }
}

class Image{

    private $_id;
    private $_ownerId;
    private $_image;
    private $_username;
    private $_datetime;

    public function __construct($id, $ownerId, $username, $image, $datetime){
        $this -> _id = $id;
        $this -> _ownerId = $ownerId;
        $this -> _image = $image;
        $this -> _username = $username;
        $this -> _datetime = new DateTime($datetime);
    }

    public function getId() { return $this -> _id; }
    public function getOwnerId() { return $this -> _ownerId; }
    public function getUser() { return $this -> _username; }
    public function getImage() { return $this -> _image; }
    public function getAge() {
        $date = $this -> _datetime;
        $currentDate = new DateTime();
        $dateDiff = $date -> diff($currentDate);
        $years = $dateDiff -> y;
        $months = $dateDiff -> m;
        $days = $dateDiff -> d;
        $hours = $dateDiff -> h;
        $minutes = $dateDiff -> i;
        $seconds = $dateDiff -> s;


        if($years > 1) return $years .' years';
        if($years > 0) return $years .' year';
        if($months > 1) return $months .' months';
        if($months > 0) return $months .' month';
        if($days > 1) return $days .' days';
        if($days > 0) return $days .' day';
        if($hours > 1) return $hours .' hours';
        if($hours > 0) return $hours .' hour';
        if($minutes > 1) return $minutes .' minutes';
        if($minutes > 0) return $minutes .' minute';
        if($seconds > 1) return $seconds .' seconds';
        if($seconds >= 0) return $seconds .' second';
        return "Error!";
    }
}

class Comment{
    private $_id;
    private $_userName;
    private $_text;
    private $_datetime;

    public function __construct($id, $userName, $text, $datetime){
        $this -> _id = $id;
        $this -> _userName = $userName;
        $this -> _text = $text;
        $this -> _datetime = new DateTime($datetime);
    }

    public function getId() { return $this -> _id; }
    public function getUser() { return $this -> _userName; }
    public function getText() { return $this -> _text; }
    public function getAge() {
        $date = $this -> _datetime;
        $currentDate = new DateTime();
        $dateDiff = $date -> diff($currentDate);
        $years = $dateDiff -> y;
        $months = $dateDiff -> m;
        $days = $dateDiff -> d;
        $hours = $dateDiff -> h;
        $minutes = $dateDiff -> i;
        $seconds = $dateDiff -> s;


        if($years > 1) return $years .' years';
        if($years > 0) return $years .' year';
        if($months > 1) return $months .' months';
        if($months > 0) return $months .' month';
        if($days > 1) return $days .' days';
        if($days > 0) return $days .' day';
        if($hours > 1) return $hours .' hours';
        if($hours > 0) return $hours .' hour';
        if($minutes > 1) return $minutes .' minutes';
        if($minutes > 0) return $minutes .' minute';
        if($seconds > 1) return $seconds .' seconds';
        if($seconds >= 0) return $seconds .' second';
        return "Error!";
    }

}
?>
