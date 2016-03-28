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

    function isUserLoggedIn(){
        return isset(self::$uid);
    }

    function createUser($username, $password){

        //Generates salt in base64 and password hash
        $salt = base64_encode(mcrypt_create_iv(22,MCRYPT_DEV_URANDOM));
        $ench_password = password_hash($password, PASSWORD_BCRYPT, ['salt' => $salt ]);

        //Insert user into database
        if ($query = self::$mysqli -> prepare('INSERT INTO user(username,password,salt) VALUES (?,?,?)')){
            $query -> bind_param('sss', $username,$ench_password,$salt);
            $query -> execute();

            //If exactly one row was affacted then we know that the user was inserted.
            return $query -> affected_rows == 1;
        }

        return false;
    }

    function login($username, $password){

        //Query to get the user salt
        if($query = self::$mysqli -> prepare('SELECT salt FROM user WHERE username = ?')){
            $query -> bind_param('s', $username);
            $query -> execute();
            $query -> store_result();

            //If there is a result then there is a salt
            if($query -> num_rows > 0){
                $query -> bind_result($salt);
                $query -> fetch();
            }
        }

        //If a salt is set then we continue
        if(isset($salt)){
            //Generates password hash using the retrived salt
            $hash = password_hash($password, PASSWORD_BCRYPT, ['salt' => $salt]);

            //Querying the username and password combo
            if($query = self::$mysqli -> prepare('SELECT id FROM user WHERE username = ? AND password = ?')){
                $query -> bind_param('ss', $username, $hash);
                $query -> execute();
                $query -> store_result();

                //If there is a result then the login was successful
                if($query -> num_rows > 0){
                    $query -> bind_result($uid);
                    $query -> fetch();
                }
            }

            //Setting the global userid - TODO SHOULD BE DONE WITH SESSION COOKIES INSTEAD!
            if(isset($uid)){
                self::$uid = $uid;
                return true;
            }
        }
        return false; 
    }
}
?>
