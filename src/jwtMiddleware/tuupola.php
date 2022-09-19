<?php

function Tuupola()
{
    return(new Tuupola\Middleware\JwtAuthentication([
        "attribute" => "jwt",
        "secret" => $_ENV['JWT_SECRET'],
        "ignore" => ["/api/user/authenticate", "/api/user/info"]
    ]));
}