<?php

use \Firebase\JWT\JWT;

class Auth {
    private static $secret_key = '814751571e197af005fa3505cdbb5d50';
    private static $encrypt = ['HS256'];
    private static $aud = null;

    public static function SignIn($data) {
        $roles= "";
        switch ($data["user_rol"]) {
            case 1:
                $roles = ["leer", "eliminar", "actualizar", "agregar", "ADpermisos"];
                break;
            case 2:
                $roles = ["leer", "eliminar", "actualizar", "agregar"];
                break;
            case 3:
                $roles = ["leer"];
                break;
            
            default:
                $roles = ["leer", "eliminar", "actualizar", "agregar"];
                break;
        }

        $token = array(
            "iss" => "https://min-aguas.000webhostapp.com/",
            'data' => [
                "user_login" => $data["user_login"],
                "user_id" => $data["user_id"],
                "user_estado" => $data["user_estado"],
                "user_municipio" => $data["user_municipio"],
                "user_parroquia" => $data["user_parroquia"],
                "scope"=> [$roles]
            ]
        );

        return JWT::encode($token, self::$secret_key);

    }

    public static function Check($token)
    {
        if(empty($token))
        {
            throw new Exception("Invalid token supplied.");
        }

        $decode = JWT::decode(
            $token,
            self::$secret_key,
            self::$encrypt
        );

        if($decode->aud !== self::Aud())
        {
            throw new Exception("Invalid user logged in.");
        }
    }

    public static function GetData($token)
    {
        return JWT::decode(
            $token,
            self::$secret_key,
            self::$encrypt
        );
    }

    private static function Aud()
    {
        $aud = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $aud = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $aud = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $aud = $_SERVER['REMOTE_ADDR'];
        }

        $aud .= @$_SERVER['HTTP_USER_AGENT'];
        $aud .= gethostname();

        return sha1($aud);
    }



   
}