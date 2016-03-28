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

    function login($username, $password){

        if($query = self::$mysqli -> prepare('SELECT salt FROM user WHERE username = ?')){
            $query -> bind_param('s', $username);
            $query -> execute();
            $query -> store_result();
            $query -> bind_result($salt);
            $query -> fetch();
        }

        if(isset($salt)){

            $options = [
                'salt' => $salt
            ];

            $hash = password_has($password, PASSWORD_BCRYPT, $options);

            if($query = self::$mysqli -> prepare('SELECT id FROM user WHERE username = ? AND password = ?')){
                $query -> bind_param('ss', $username, $hash);
                $query -> execute();
                $query -> store_result();
                $query -> bind_result($uid);
                $query -> fetch();
            }

            if(isset($uid)){
                self::$uid = $uid;
                return true;
            }
        }
        return false;
    }
}
?>
