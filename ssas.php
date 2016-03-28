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





}
?>
