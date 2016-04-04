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

    // This function will authenticate a user based on the token cookie.
    // returns true if the user is authenticated, otherwise return false
    // if the token is invalid or has expired the method will call exit() and not return anything
    function authenticate(){
        if(isset($_COOKIE['token'])){
            try{
                //Retrives the JWT token from the cookie
                $token = $_COOKIE['token'];

                //Decrypts the token. This call will throw an exception if the token is invalid
                $token = (array) JWT::decode($token,self::$key,array('HS512'));

                //Extracts the user data from the token
                self::$data = (array) $token['data'];

                //Check that the user acutally exists (could have been removed)
                if($query = self::$mysqli -> prepare('SELECT id FROM user WHERE id = ? AND username = ?')){
                    $query -> bind_param('is', self::getUid(), self::getUsername());
                    $query -> execute();
                    $query -> store_result();
                    if($query -> num_rows == 1) return true;
                }
                
                //If the query did not succeed, then there is something wrong!
                throw new Exception('Authentication failed!');
                                
            } catch (Exception $e){ 

                //This will happend if 
                //  1) The token has expired 
                //  2) The token is not valid
                //  3) No user matching the user data exists

                self::logout();
                header("Location: index.php");
                exit(); //Just to be sure
                
            }
        }
       return false; //Could not authenticate
    }

    // This function will destroy the token cookie if it exists
    function logout(){
        if(isset($_COOKIE['token'])){
            unset($_COOKIE['token']);
            setcookie('token', '', time() - 3600);
        }
    }

    // This function will check if the user is logged in
    // If the user is not authenticated, the the method will try to authenticate.
    // returns true if the user is logged in otherwise false
    function isUserLoggedIn(){
        if(!isset(self::$data) && isset($_COOKIE['token'])) self::authenticate();
        return isset(self::$data);
    }

    // This function will return to logged in users id (if authenticated)
    function &getUid(){
    if(self::isUserLoggedIn()) return self::$data['uid'];
    }

    // This function will return to logged in users username (if authenticated)
    function &getUsername(){
    if(self::isUserLoggedIn()) return self::$data['username'];
    }

    // This function will create a new user with the given username password combo
    // returns true if the user was created, otherwise error message
    function createUser($username, $password){
        

        if($username == "") return "username can't be empty";
        if($password == "") return "password can't be empty";
        if(preg_match('/^[a-zA-Z0-9-]+$/', $username) !== 1) return "username must contain letters, numbers and dash without space";

        //Sanitizing username variable (just to be safe)
        $username = self::xssafe($username);

        //Generates salt
        $salt = mcrypt_create_iv(22,MCRYPT_DEV_URANDOM);

        //Hashes password. The returned string contains both password hash and salt
        $hash = password_hash($password, PASSWORD_BCRYPT, ['salt' => $salt ]);

        //Inserts username and password hash into the database
        if ($query = self::$mysqli -> prepare('INSERT INTO user(username,password) VALUES (?,?)')){
            $query -> bind_param('ss', $username,$hash);
            $query -> execute();

            //If exactly one row was affacted then we know that the user was inserted.
            if($query -> affected_rows == 1) return true;
            return "username already exists";
        }
        return "user could not be created";
    }

    // This function will login with the given username password combo
    // returns true if the login was successful, otherwise error message 
    function login($username, $password){

        //Sanitizing username variable
        $username = self::xssafe($username);

        //Query to get the user salt
        if($query = self::$mysqli -> prepare('SELECT id,password FROM user WHERE username = ?')){
            $query -> bind_param('s', $username);
            $query -> execute();
            $query -> store_result();

            //If there is a result then there is a salt
            if($query -> num_rows > 0){
                $query -> bind_result($uid, $hash);
                $query -> fetch();
            } else {
                return "username and password does not match";
            }
        }

        //If password and hash matches then we continue
        if(isset($hash) && password_verify($password,$hash)){

            //Generates random tokenid
            //TODO Maybe store this in the database so tokens can be revoked? (if we do so, a user can only be loggedin at one pc at a time)
            $tokenId = base64_encode(mcrypt_create_iv(32,MCRYPT_DEV_URANDOM));

            $issuedAt = time(); //time of issue
            $notBefore = $issuedAt; //can be used to say that a token is not valid before a given time (not used)
            $expire = $notBefore + 3600 * 24 * 90; //token expires in 90 days
            $data = [
                'iat' => $issuedAt,
                'jti' => $tokenId,
                'nbf' => $notBefore,
                'exp' => $expire,
                'data' => [
                    'uid' => $uid,
                    'username' => $username
                ]
            ];

            //Computes the encrypted token
            $jwt = JWT::encode($data,self::$key,'HS512');

            //Sets to cookie to never expire as the token itself contains the expiration date (Mimimum exposure)
            setcookie("token", $jwt, -1);
            return true;
        } else return "username and password does not match";

        return "could not login";
    }

    // This function uploads the given image
    // returns true if the image was successfully uploaded, otherwise false
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

    // This function will lookup a users id given the username
    // returns the user id if exists, otherwise false
    private function getUserId($username){
        
        //Sanitizing username variable
        $username = self::xssafe($username);

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

    // This function will remove sharing with the given user for the given image
    // returns true if the operation was successful, otherwise false
    function removeShare($iid, $username){

        //Sanitizing username variable
        $username = self::xssafe($username);

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

    // This function will share the given image with the given user
    // returns true if the image was shared, otherwise false
    function shareImage($iid, $username)
    {

        //Sanitizing username variable
        $username = self::xssafe($username);

        //The user must be owner of the image to share it
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

    // This function returns a list of users whom the given image can be shared with
    // returns a list of users if successful, otherwise false
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

    // This function returns a list of users whom the given image is shared with.
    // returns a list of users if successful, otherwise false
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

    // This function returns a list of all images shared with the loggedin user
    // returns a list of images if successful, otherwise false
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

    // This function returns the given image iff the loggedin user have access to it
    // returns the image if successful, otherwise false
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

    // This function will post given comment to given image iff the loggedin user has access to post
    // returns true if successful, otherwise false
    function comment($iid, $comment)
    {
        $comment = self::xssafe($comment);
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

    // This function gets all comments for the given image
    // returns a list of comments if successful, otherwise false
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

    // This function checks if the loggedin user is owner of the given image
    // returns true if the loggedin user is owner, otherwise false
    function isOwner($iid){
        if($query = self::$mysqli -> prepare('SELECT id FROM image WHERE owner_id = ? AND id = ?')){
            $query -> bind_param('ii', self::getUid(), $iid);
            $query -> execute();
            $query -> store_result();
            return $query -> num_rows > 0;
        }
        return false;
    }

    // This function checks if the loggedin user is either owner or has access to the given image
    // returns true if the loggedin user has access, otherwise false
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

    // This function sanitized the input from JS code and others (prevents XSS)
    private function xssafe($data,$encoding='UTF-8')
    {
       return htmlspecialchars($data,ENT_QUOTES | ENT_HTML401,$encoding);
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
