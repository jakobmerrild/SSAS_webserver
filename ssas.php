<?php

use \Firebase\JWT\JWT;

class ssas {

    private static $mysqlServer = 'localhost';
    private static $mysqlUser = 'root';
    private static $mysqlPass = '';
    private static $mysqlDb = 'ssas';
    private static $mysqli;
    private static $uid;
    private static $key = "secret";

    function ssas(){
        self::$mysqli = new mysqli(self::$mysqlServer,self::$mysqlUser,self::$mysqlPass,self::$mysqlDb);
    }

    function authenticate(){
        if(isset($_COOKIE['token'])){
            $token = $_COOKIE['token'];
            $decoded = JWT::decode($token,$key,array('HS256')); //will throw exception!
            self::$uid = $uid;
            return true;
        }
    }

    function isUserLoggedIn(){
        return isset(self::$uid);
    }

    function getUid(){
        if(isset(self::$uid)) return self::$uid;
    }

    function createUser($username, $password){

        //Generates salt in base64 and password hash
        $salt = mcrypt_create_iv(22,MCRYPT_DEV_URANDOM);
        $ench_password = password_hash($password, PASSWORD_BCRYPT, ['salt' => $salt ]);

        //Insert user into database
        if ($query = self::$mysqli -> prepare('INSERT INTO user(username,password,salt) VALUES (?,?,?)')){
            $query -> bind_param('sss', $username,$ench_password,"");
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
            $jwt = JWT::encode($uid, self::$key);
            setcookie("token", $jwt, time() + 3600);

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
