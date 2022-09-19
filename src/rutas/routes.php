<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = new \Slim\App;

require __DIR__ . '/../dotenv/dotenvRun.php';
require __DIR__ . '/../jwtMiddleware/tuupola.php';
require __DIR__ . '/../class/auth.php';
require __DIR__ . '/../funciones/funciones.php';
require __DIR__ . '/../class/classRegistros.php';
require __DIR__ . '/../class/classPaginador.php';
require __DIR__ . '/../config/db.php';

$app->add(Tuupola());



//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//////////////////////////////* Usuario *////////////////////////////////////
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||

$app->get('/user/verification', function (Request $request, Response $response) {

    return "OK";
    
});


$app->group('/api/user/', function () use ($app) {
    
    $app->post('authenticate', function (Request $request, Response $response) {
        $body = json_decode($request->getBody());
    
        $sql = "SELECT `usuarios`.*
                FROM `usuarios`";
        $db = new DB();
        $resultado = $db->consultaAll('usuarios', $sql);
        
        
        $body=json_decode($body->body);
        
        foreach ($resultado as $key => $user) {
        if ($user['nick'] == $body->user && $user['pass'] == $body->pass) {
            $current_user = $user;
        }}
    
        if (!isset($current_user)) {
            echo json_encode("No user found");
        } else{
    
            $sql = "SELECT * FROM tokens
                 WHERE id_usuario_token  = ?";
    
            try {
                $db = new DB();
                $token_from_db = $db->consultaAll('usuarios', $sql, [$current_user['id_usuario']], 'objeto');
                
                $db = null;
                if ($token_from_db) {
                    return $response->withJson([
                    "Token" => $token_from_db->token,
                    "User_render" =>$current_user['id_rol'], 
                   // "Hidrologica" =>$current_user
                    ]);
                }    
                }catch (Exception $e) {
                $e->getMessage();
                }
    
            if (count($current_user) != 0 && !$token_from_db) {
    
    
                 $data = [
                    "user_login" => $current_user['nick'],
                    "user_id"    => $current_user['id_usuario'],
                    "user_estado"    => $current_user['id_estado'],
                    "user_municipio"=>$current_user['id_municipio'],
                    "user_parroquia"=>$current_user['id_parroquia'],
                    "user_rol"    => $current_user['id_rol']
                ];
    
                 try {
                    $token=Auth::SignIn($data);
                 } catch (Exception $e) {
                     echo json_encode($e);
                 }
    
                  $sql = "INSERT INTO tokens (id_usuario_token, token)
                      VALUES (?, ?)";
                  try {
                        $hoy = (date('Y-m-d', time()));
                        $db = new DB();
                        $db = $db->consultaAll('usuarios', $sql, [$current_user['id_usuario'], $token]);
                        
                        
                        return $response->withJson([
                        "Token" => $token,
                        "User_render" =>$current_user['id_rol']
                        ]);
     
                  } catch (PDOException $e) {
                      echo '{"error":{"text":' . $e->getMessage() . '}}';
                  }
             }
        }
    
    });

    $app->post('create', function (Request $request, Response $response) { 
        $scope=$request->getAttribute('jwt')["data"]->scope[0];
        if (userVerification($scope) !== false) {
            $datos = json_decode($request->getBody());
            $pass = generar_password_complejo(8);
                
            try {
                $sql = "INSERT INTO `usuarios`(`id_usuario`, `nick`, `email`, `pass`, `id_rol`, `id_acceso`, `id_estado`, id_municipio, id_parroquia) VALUES (null, 0, ?, ?, ?, ?, ?, ?, ?)";
                $db = new DB();
                $resultado = $db->consultaAll('usuarios', $sql, [$datos->email, $pass, $datos->id_rol, $datos->id_acceso, $datos->id_estado, $datos->id_municipio, $datos->id_parroquia]);
                if ($resultado) {
                    $sql = "UPDATE `usuarios` SET `nick`=? WHERE usuarios.id_usuario = ?";
                    $nick = $datos->nick."-".$resultado->insert_id;
                    $resultado2 = $db->consultaAll('usuarios', $sql, [$nick, $resultado->insert_id]);
                    $retorno = [
                        "id"=>$resultado->insert_id,
                        "nick"=>$nick,
                        "pass"=>$pass, 
                        "rol"=>$datos->id_rol
                    ];
                    return $response->withJson($retorno); 
                }
                //return $response->withJson($resultado);
                } 
            catch (MySQLDuplicateKeyException $e) {
                $e->getMessage();
            }
            catch (MySQLException $e) {
                $e->getMessage();
            }
            catch (Exception $e) {
                $e->getMessage();
            }
        } else {
            return $response->withStatus(401);
        }
    });

    $app->post('info/user', function (Request $request, Response $response) { 
        $body = json_decode($request->getBody());
        $nick = json_decode($body->body);
        
        try {
            $sql = "SELECT usuarios.id_usuario, usuarios.id_rol, usuarios.nick, usuarios.id_estado FROM usuarios WHERE usuarios.nick = ?";
            $db = new DB();
            $resultado = $db->consultaAll('usuarios', $sql, [$nick], 'objeto');
            $array = ["id" => $resultado->id_usuario, "scope" => $resultado->id_rol, "nick"=>$resultado->nick, "estado"=>$resultado->id_estado];
            return $response->withJson($array);          
            
            } 
        catch (MySQLDuplicateKeyException $e) {
            $e->getMessage();
        }
        catch (MySQLException $e) {
            $e->getMessage();
        }
        catch (Exception $e) {
            $e->getMessage();
        }
    });



    
});


//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//////////////////////////////* GET *///////////////////////////////////////||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||



$app->get('/api/reportes/paginador[/{params:.*}]', function (Request $request, Response $response, $args) {
    /*
VALORES NECESARIOS 
0 - ID_ESTADO
1 - TIPO DE TABLA A VISUALIZAR
2 - PAGINA

*/

$TablaConsultar = [
    'produccion',//0 
    'rehabilitacion_pozo',//1 
    'fugas',//2 
    'tomas_ilegales',//3 
    'reparaciones_brippas',//4
    'afectaciones',//5 
    'operatividad_abastecimiento',//6
    'pozo',//7
    'brippas',//8
    'sistemas'//9
];

$db = new DB();

if (!empty($args['params'])) {
    $params = EliminarBarrasURL($args['params']);
    
}
   $valorSQL = null;

   if (isset($params[0]) AND isset($params[1])) {
        if ($params[0] > 0 AND $params[0] < 25) {
            $sql1 = "SELECT COUNT(*) as Paginas
            FROM reporte 
            LEFT JOIN `tablas` ON `reporte`.`id_tabla` = `tablas`.`id` 
            LEFT JOIN `estados` ON `reporte`.`id_estado` = `estados`.`id_estado` 
            WHERE reporte.id_estado = ? AND reporte.id_tabla = ?";

            $datos = $db->consultaAll('mapa',$sql1, [$params[0], $params[1]]);

       }else {
            $sql1 = "SELECT COUNT(*) as Paginas
            FROM reporte 
            LEFT JOIN `tablas` ON `reporte`.`id_tabla` = `tablas`.`id` 
            LEFT JOIN `estados` ON `reporte`.`id_estado` = `estados`.`id_estado` 
            WHERE reporte.id_tabla = ?";

            $datos = $db->consultaAll('mapa',$sql1, [$params[1]]);
       }
   
}else {
    $sql1 = "SELECT COUNT(*) as Paginas
    FROM reporte 
    LEFT JOIN `tablas` ON `reporte`.`id_tabla` = `tablas`.`id` 
    LEFT JOIN `estados` ON `reporte`.`id_estado` = `estados`.`id_estado` ";

    $datos = $db->consultaAll('mapa',$sql1);
}
   
    $pagina = isset($params[2]) ?(int)$params[2] : 1;


    $regPagina = 12;
    $inicio = ($pagina > 1) ? (($pagina * $regPagina) - $regPagina) : 0 ;


    if (isset($params[0]) AND isset($params[1])){

        if ($params[0] > 0 AND $params[0] < 25) {
            $sql2 = "SELECT SQL_CALC_FOUND_ROWS  reporte.* , estados.estado, tablas.tipo_reporte 
            FROM reporte 
            LEFT JOIN `tablas` ON `reporte`.`id_tabla` = `tablas`.`id` 
            LEFT JOIN `estados` ON `reporte`.`id_estado` = `estados`.`id_estado` 
            WHERE reporte.id_estado = ? AND reporte.id_tabla = ?
            LIMIT $inicio , $regPagina";
            $resultado = $db->consultaAll('mapa',$sql2, [$params[0], $params[1]]);
        }else{
            $sql2 = "SELECT SQL_CALC_FOUND_ROWS  reporte.* , estados.estado, tablas.tipo_reporte 
            FROM reporte 
            LEFT JOIN `tablas` ON `reporte`.`id_tabla` = `tablas`.`id` 
            LEFT JOIN `estados` ON `reporte`.`id_estado` = `estados`.`id_estado` 
            WHERE reporte.id_tabla = ?
            LIMIT $inicio , $regPagina";
            $resultado = $db->consultaAll('mapa',$sql2, [$params[1]]);
        }
        


    }else {
        $sql2 = "SELECT SQL_CALC_FOUND_ROWS  reporte.* , estados.estado, tablas.tipo_reporte 
        FROM reporte 
        LEFT JOIN `tablas` ON `reporte`.`id_tabla` = `tablas`.`id` 
        LEFT JOIN `estados` ON `reporte`.`id_estado` = `estados`.`id_estado` 
        LIMIT $inicio , $regPagina";
        $resultado = $db->consultaAll('mapa',$sql2);
    }
 
    return   json_encode([
        "paginas"=>$pagina,
        "datos"=>$resultado
    ]);

  
   
});


         


//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||PAGINADOR|||||||||||||||||||||||||||||||||||||||||
//||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||

$app->get('reportes[/{params:.*}]', function (Request $request, Response $response, $args) {
$valorSQL = null;
    /*
        ruta para obtener los reportes de el sistema
        1) existen tres parametros opcionales en la ruta, params[1] es el numero de paginacion, params[2] es el tipo de busqueda que vas a hacer si es el caso, params[3] es el id que se utiliza para formular la busqueda
        
    */

    $idHidrologica=$request->getAttribute('jwt')["data"]->user_hidrologica;
    /*
    Obteniendo user_hidrologica del usuario para el primer render de la paginacion, se obtiene por el Token
    1) se optiene el token para asi poder verificar o dar la informacion en base a lo que presenta el usuario en su informacion personal
    2) en esta opcion se optione el user_hidrologica que es el id de la hidrologica del usuario
    3) es totalmente necesario para el funcionamiento de la ruta
    */

    if (!empty($args['params'])) { //validamos si la ruta tiene algun valor opcional en el url

        ;
        $params = EliminarBarrasURL($args['params']);
        
        $tipoConsulta = null;
        if (count($params)>3) {
            $params[4] = trim(rawurldecode($params[4]), ' ');
            //comprobamos que en el params[1] venga el valor de busqueda
            if ($params[3] === "busqueda") {
                $array = [];
                if ($params[4][0]==="#") {
                    //si es un id, que se identifica por tener como primer valor el #, entonces extraemos los parametros para la consulta mysql, se le pasa como segundo valor a la funcion, el tipo, que en este caso es id
                    $tipoConsulta = ExtraerConsultaParametro($params[3], 'id');         
                        $paramsSaneado = explode('-', $params[4]);
                        $params[4] = [$paramsSaneado[1]];
                }else {
                    // en caso contrario se pasa nada mas el primer parametro, y se crea un array con los valores duplicados para enviarlos a la consulta
                    $tipoConsulta = ExtraerConsultaParametro($params[3]);
                    for ($i=0; $i < count($tipoConsulta); $i++) {
                        $param = urldecode($params[4]);
                        $param = '%'.$params[4].'%';               
                        array_push($array, $param);
                    }
                    $params[4]=$array;
                    
                }
            }else {
                $tipoConsulta = ExtraerConsultaParametro($params[3]);
                $params[3]=[ucfirst($params[4])];
                
            }
        }
        if ($tipoConsulta !== null) {
            $where = CondicionalMYSQL($idHidrologica, $tipoConsulta, $params[4]);
        }else {
            $where = CondicionalMYSQL($idHidrologica);
        }

        

        $sql = "SELECT COUNT(*) as Paginas
                FROM $valorSQL
                LEFT JOIN reporte ON $valorSQL.id_reporte = reporte.id_reporte
                WHERE $valorSQL.id_orientacion_proyecto = ? AND $valorSQL.id_validacion = ?
                {$where}";


        
               

        if ($where !== "") {
            if ($tipoConsulta!==null) {
                if ($idHidrologica===20) {
                    $db = new DB(); 
                    $datos = array($db->consultaAll('mapa', $sql, [$params[0], $params[1] , ...$params[4]])[0]['Paginas'],$params[2]);
                    
                    
                }else{
                    $db = new DB();
                    $param= [$params[0],$params[1], $idHidrologica, ...$params[4]];
                    $datos = array($db->consultaAll('mapa', $sql,  $param)[0]['Paginas'], $params[2]);
                    
                }
            }else {
                $db = new DB();
                $datos = array($db->consultaAll('mapa', $sql, [$params[0], $params[1] , $idHidrologica])[0]['Paginas'],$params[2]);
                
            }
        }else {
            $db = new DB();
            $datos = array($db->consultaAll('mapa',$sql,[$params[0], $params[1]])[0]['Paginas'], $params[2]);
            
            
        }

        if ($params[2] < 1 || $params[2] > ceil($datos[0] / 20)){
            return "La pagina solicitada no existe";
        }else{
            $paginador = New paginadorIncidencia($tipoConsulta!==null?$params[4]:null, $idHidrologica, $tipoConsulta);
             return json_encode($paginador->paginadorIncidencias($datos, $params[0]));             
        }
    }else {
        $sql = "SELECT COUNT(*) as Paginas
        FROM reporte
        LEFT JOIN reporte ON reporte.id_tabla = tablas.id
        WHERE id_tabla = $valorSQL
        ";
         try {
            $db = new DB();
            $resultado = $db->consultaAll('mapa',$sql);  
            return $response->withJson($resultado);                  
         } 
        catch (MySQLDuplicateKeyException $e) {
            $e->getMessage();
        }
        catch (MySQLException $e) {
            $e->getMessage();
        }
        catch (Exception $e) {
            $e->getMessage();
        }
    }
        
});

//////////////////////////////// DESPLEGABLES ///////////////////////////////////////////

//////////////////////////////////////ESTADOS
$app->get('/api/desplegables/estados[/{id}]', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $db = New DB();
    if ($id) {
        $sql = "SELECT estados.id_estado, estados.estado
        FROM estados where estados.id_estado = ?";
        $estado= $db->consultaAll('mapa',$sql, [$id]);
        unset($estado[24]);
        return json_encode($estado);
    } else{
        $sql = "SELECT estados.id_estado, estados.estado
        FROM estados";
        $esta= $db->consultaAll('mapa',$sql);
        unset($esta[24]);
        return json_encode($esta);
    }
    
        
});
 
               
//////////////////////////////////////MUNICIPIOS
        $app->get('/api/desplegables/municipios/{id_estado}', function (Request $request, Response $response) {
            $id = $request->getAttribute('id_estado');
            
            
            $sql = "SELECT municipios.id_municipio, municipios.municipio, estados.id_estado
                FROM municipios 
                LEFT JOIN estados ON municipios.id_estado = estados.id_estadO
                WHERE municipios.id_estado = ?";
                $db = New DB();
            
            return json_encode($db->consultaAll('mapa',$sql,[$id]));
        });
        
        
        
/////////////////////////////////////PARROQUIAS
    $app->get('/api/desplegables/parroquias/{id_municipio}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id_municipio');
        
        
        $sql = "SELECT parroquias.id_parroquia, parroquias.parroquia, municipios.id_municipio 
                FROM parroquias
                LEFT JOIN municipios ON parroquias.id_municipio = municipios.id_municipio 
                WHERE municipios.id_municipio = ?";
                    $db = New DB();
        
                    return json_encode($db->consultaAll('mapa',$sql,[$id]));
                    
                    
    });



    $app->get('/api/desplegables/pozos[/{id_estado}]', function (Request $request, Response $response) {
        
        if ($request->getAttribute('id_estado')) {
           
            $id = $request->getAttribute('id_estado');
        }
        
        $db = New DB();

        if (isset($id)) {
            $sql = "SELECT `pozo`.*, `estados`.`estado`
                    FROM `pozo` 
                    LEFT JOIN `estados` ON `pozo`.`id_estado` = `estados`.`id_estado`
            WHERE estados.id_estado = ?";
            $resultado = $db->consultaAll('mapa',$sql,[$id]);
        }else {
            $sql = "SELECT `pozo`.*, `estados`.`estado`
                    FROM `pozo` 
                    LEFT JOIN `estados` ON `pozo`.`id_estado` = `estados`.`id_estado`";
                $resultado = $db->consultaAll('mapa',$sql);

        }


        if (empty($resultado)) {
            return "LA CONSULTA NO TIENE RESULTADOS";
        }else {            
            return json_encode($resultado);
        }
        
                    
                    
    });


    $app->get('/api/desplegables/brippas[/{id_estado}]', function (Request $request, Response $response) {
        
        if ($request->getAttribute('id_estado')) {
           
            $id = $request->getAttribute('id_estado');
        }
        
        $db = New DB();

        if (isset($id)) {
            $sql = "SELECT `brippas`.*, `estados`.`estado`,municipios.municipio, `parroquias`.`parroquia`
                    FROM `brippas` 
                    LEFT JOIN `estados` ON `brippas`.`id_estado` = `estados`.`id_estado`
                    LEFT JOIN `municipios` ON brippas.id_municipio = `municipios`.`id_municipio`
                    LEFT JOIN parroquias ON brippas.id_parroquia = `parroquias`.`id_parroquia`
                    WHERE `estados`.`id_estado` = ?";
            $resultado = $db->consultaAll('mapa',$sql,[$id]);
        }else {
            $sql = "SELECT `brippas`.*, `estados`.`estado`, `municipios`.`municipio`, `parroquias`.`parroquia`
                    FROM `brippas` 
                    LEFT JOIN `estados` ON `brippas`.`id_estado` = `estados`.`id_estado` 
                    LEFT JOIN `municipios` ON `brippas`.`id_municipio` = `municipios`.`id_municipio` 
                    LEFT JOIN `parroquias` ON `brippas`.`id_parroquia` = `parroquias`.`id_parroquia`;";
            $resultado = $db->consultaAll('mapa',$sql);

        }

        if (empty($resultado)) {
            return "LA CONSULTA NO TIENE RESULTADOS";
        }else {            
            return json_encode($resultado);
        }
        
        
                    
                    
    });


    $app->get('/api/desplegables/sistemas', function (Request $request, Response $response) {
                
        $sql = "SELECT * FROM `sistemas`";
                    $db = New DB();

            $resultado = $db->consultaAll('mapa',$sql);
                    

        if (empty($resultado)) {
            return "LA CONSULTA NO TIENE RESULTADOS";
        }else {            
            return json_encode($resultado);
        }
        
    });



    $app->get('/api/reportes/dia', function (Request $request, Response $response) {

        $TablaConsultar = [
            'produccion',//0 
            'rehabilitacion_pozo',//1 
            'fugas',//2 
            'tomas_ilegales',//3 
            'reparaciones_brippas',//4
            'afectaciones',//5 
            'operatividad_abastecimiento',//6
            'pozo',//7
            'brippas',//8
            'sistemas'//9
        ];


        $db = New DB();
        $sql = "SELECT reporte.* FROM `reporte` WHERE TO_DAYS(reporte.fecha) = TO_DAYS(NOW())";
        $resultado= $db->consultaAll('mapa',$sql);

        $array=[
            ["AMAZONAS"],
            ["ANZOATEGUI"],
            ["APURE"],
            ["ARAGUA"],
            ["BARINAS"],
            ["BOLIVAR"],
            ["CARABOBO"],
            ["COJEDES"],
            ["DELTA AMACURO"],
            ["FALCON"],
            ["GUARICO"],
            ["LARA"],
            ["MERIDA"],
            ["MIRANDA"],
            ["MONAGAS"],
            ["NUEVA ESPARTA"],
            ["PORTUGUESA"],
            ["SUCRE"],
            ["TACHIRA"],
            ["TRUJILLO"],
            ["VARGAS"], 
            ["YARACUY"],
            ["ZULIA"],
            ["DISTRITO CAPITAL"]
        ];


        for ($i=0; $i < count($resultado); $i++) { 
           array_push($array[$resultado[$i]["id_estado"] - 1], $TablaConsultar[$resultado[$i]["id_tabla"] +0]);
        }


       return json_encode($array);
        
        
            
    });
     
          
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////      
$app->get('/api/pozo/{id_pozo}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id_pozo');

    $sql = "SELECT * FROM pozo WHERE pozo.id = ?";
    $db = New DB();
    $resultado = $db->consultaAll('mapa',$sql,[$id]);  

    if (empty($resultado)) {
        return "LA CONSULTA NO TIENE RESULTADOS";
    }else {            
        return json_encode($resultado);
    }
    
                   
});


$app->get('/api/reportes/unico[/{params:.*}]', function (Request $request, Response $response, $args) {
    $params = EliminarBarrasURL($args['params']);  
    /*
    0 - Tipo de consulta ($Tabla a consultar)
    1 - id del reporte
    2 - 
    3 -      */ 
    $db = New DB();
  
    $TablaConsultar = [
        'produccion',//0 
        'rehabilitacion_pozo',//1 
        'fugas',//2 
        'tomas_ilegales',//3 
        'reparaciones_brippas',//4
        'afectaciones',//5 
        'operatividad_abastecimiento',//6
        'pozo',//7
        'brippas',//8
        'sistemas'//9
    ];

   
    
    if (!empty($params[0]) && is_numeric($params[0])) {
        if (($params[0] >= 0) && ($params[0] <= 6)) {
            $params[0] = $params[0] + 0;
            $valorSQL = $TablaConsultar[$params[0]];
        }else {
            return 'TABLA A CONSULTAR NO VALIDA';
        }
    }else {
        return 'PARAMETROS DE BUSQUEDA NO VALIDOS';
    }
    

    if (count($params) === 2) {
        
        if (($params[0] === 1) || ($params[0] === 5) || ($params[0] === 4)) {
            switch ($params[0]) {
                case 1:
                    $valorSQL2 = $TablaConsultar[7];
                    
                    break;

                case 5:
                    $valorSQL2 = $TablaConsultar[9];
                    break;

                case 4:
                    $valorSQL2 = $TablaConsultar[8];
                    break;
                
                default:
                $valorSQL2 = null;
                    break;
            }
            $sql = "SELECT $valorSQL.*, `estados`.`estado`, $valorSQL2.*,reporte.fecha
                    FROM $valorSQL 
                        LEFT JOIN $valorSQL2 ON $valorSQL.id_$valorSQL2 = $valorSQL2.id
                        LEFT JOIN `estados` ON $valorSQL2.id_estado = `estados`.`id_estado`
	                    LEFT JOIN `reporte` ON $valorSQL.`id_reporte` = `reporte`.`id`

                        WHERE $valorSQL2.id = ?";
            
        }else {

            $sql = "SELECT $valorSQL.*, `estados`.`estado`,`reporte`.`fecha`
                    FROM $valorSQL 
                        LEFT JOIN `estados` ON $valorSQL.`id_estado` = `estados`.`id_estado`
	                    LEFT JOIN `reporte` ON $valorSQL.`id_reporte` = `reporte`.`id`

                        WHERE $valorSQL.id = ?";

        
        }
            $reporte= $db->consultaAll('mapa',$sql, [$params[1]]);
     
            return json_encode($reporte);
        
    }elseif (count($params) === 3) {

        
    }elseif (count($params) === 4) {
        
    }else {
        return 'FALTAN O SOBRAN INSERTAR PARAMETROS PARA SOLICITAR EL REPORTE';
    }
    
            
});

$app->get('/api/reportes/estado[/{params:.*}]', function (Request $request, Response $response, $args) {
    $params = EliminarBarrasURL($args['params']);   
    /*
    0 - Tipo de formulario ($Tabla a consultar)
    1 - Estado
    2 - Municipio
    3 - Parroquia     */    
    $db = New DB();
    /**/
    $TablaConsultar = [
        'produccion',//0 
        'rehabilitacion_pozo',//1 
        'fugas',//2 
        'tomas_ilegales',//3 
        'reparaciones_brippas',//4
        'afectaciones',//5 
        'operatividad_abastecimiento',//6
        'pozo',//7
        'brippas',//8
        'sistemas'//9
    ];

   
    
    if (!empty($params[0]) && is_numeric($params[0])) {
        if (($params[0] >= 0) && ($params[0] <= 6)) {
            $params[0] = $params[0] + 0;
            $valorSQL = $TablaConsultar[$params[0]];
        }else {
            return 'TABLA A CONSULTAR NO VALIDA';
        }
    }else {
        return 'PARAMETROS DE BUSQUEDA NO VALIDOS';
    }
    

    if (count($params) === 2) {
        
        if (($params[0] === 1) || ($params[0] === 5) || ($params[0] === 4)) {
            switch ($params[0]) {
                case 1:
                    $valorSQL2 = $TablaConsultar[7];
                    
                    break;

                case 5:
                    $valorSQL2 = $TablaConsultar[9];
                    break;

                case 4:
                    $valorSQL2 = $TablaConsultar[8];
                    break;
                
                default:
                $valorSQL2 = null;
                    break;
            }
            $sql = "SELECT $valorSQL.*, `estados`.`estado`, $valorSQL2.*
                    FROM $valorSQL 
                        LEFT JOIN $valorSQL2 ON $valorSQL.id_$valorSQL2 = $valorSQL2.id
                        LEFT JOIN `estados` ON $valorSQL2.id_estado = `estados`.`id_estado`
                        WHERE $valorSQL2.id_estado = ?";


            


        }else {

            $sql = "SELECT $valorSQL.*, `estados`.`estado`
                    FROM $valorSQL 
                        LEFT JOIN `estados` ON $valorSQL.`id_estado` = `estados`.`id_estado`
                        WHERE $valorSQL.id_estado = ?";

           
        
        }
            $reporte= $db->consultaAll('mapa',$sql, [$params[1]]);
     
            return json_encode($reporte);
        
    }elseif (count($params) === 3) {

        
    }elseif (count($params) === 4) {
        
    }else {
        return 'FALTAN O SOBRAN INSERTAR PARAMETROS PARA SOLICITAR EL REPORTE';
    }
    
        
});

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$app->get('/api/reportes/fecha[/{params:.*}]', function (Request $request, Response $response, $args) {
    $params = EliminarBarrasURL($args['params']);
    /*params = 
    0 - Tipo de formulario
    1 - Fecha de Inicio
    2 - Fecha Final
        ///////////////
    3 - Si el reporte es por estados aca va el id del estado solicitado
    */
    $TablaConsultar = [
    'produccion',//0 
    'rehabilitacion_pozo',//1 
    'fugas',//2 
    'tomas_ilegales',//3 
    'reparaciones_brippas',//4
    'afectaciones',//5 
    'operatividad_abastecimiento',//6
    'pozo',//7
    'brippas',//8
    'sistemas'//9
    ];
    
    $db = New DB();    

    if (isset($params[0]) && is_numeric($params[0])) {
        if (($params[0] >= 0) && ($params[0] <= 6)) {
            $params[0] = $params[0] + 0;
        }else {
            return 'TABLA A CONSULTAR NO VALIDA';
        }
    }else {
        return 'PARAMETROS DE BUSQUEDA NO VALIDOS';
    }

    if (count($params) === 3) {
        
        if ( ($params[0] === 1) || ($params[0] === 5) || ($params[0] === 4)) {
            $valorSQL = $TablaConsultar[$params[0]];
        
            switch ($params[0]) {
                case 1:
                    $valorSQL2 = $TablaConsultar[7];
                    break;

                case 5:
                    $valorSQL2 = $TablaConsultar[9];
                    break;

                case 4:
                    $valorSQL2 = $TablaConsultar[8];
                    break;
                
                default:
                $valorSQL2 = null;
                    break;
            }

            $sql = "SELECT $valorSQL.*, `reporte`.fecha , $valorSQL2.nombre, estados.estado
            FROM $valorSQL
                LEFT JOIN `reporte` ON $valorSQL.id_reporte = `reporte`.`id`   
                LEFT JOIN $valorSQL2 ON $valorSQL.id_$valorSQL2 = $valorSQL2.id
                LEFT JOIN estados ON $valorSQL2.id_estado = estados.id_estado
                WHERE reporte.fecha 
                BETWEEN ? AND ?";
                $reporte= $db->consultaAll('mapa',$sql, [$params[1], 
                                                         $params[2]]);
                return json_encode($reporte);
                            
        }else {

            $valorSQL = $TablaConsultar[$params[0]];

            $sql = "SELECT $valorSQL.*, `reporte`.fecha, estados.estado
            FROM $valorSQL
                LEFT JOIN `reporte` ON $valorSQL.id_reporte = `reporte`.`id`             
                LEFT JOIN `estados` ON $valorSQL.id_estado = estados.id_estado 
                WHERE reporte.fecha 
                BETWEEN ? AND ?";

                $reporte= $db->consultaAll('mapa',$sql, [$params[1], 
                                                         $params[2]]);
                return json_encode($reporte);
        }
      

    }elseif (count($params) === 4) {
        
        if ( ($params[0] === 1) || ($params[0] === 5) || ($params[0] === 4)) {
            $valorSQL = $TablaConsultar[$params[0]];

            switch ($params[0]) {
                case 1:
                    $valorSQL2 = $TablaConsultar[7];
                    break;

                case 5:
                    $valorSQL2 = $TablaConsultar[9];
                    break;

                case 4:
                    $valorSQL2 = $TablaConsultar[8];
                    break;
                
                default:
                $valorSQL2 = null;
                    break;
            }

            $sql = "SELECT $valorSQL.*, `reporte`.fecha , $valorSQL2.nombre, estados.estado
            FROM $valorSQL
                LEFT JOIN `reporte` ON $valorSQL.id_reporte = `reporte`.`id`    
                LEFT JOIN $valorSQL2 ON $valorSQL.id_$valorSQL2 = $valorSQL2.id
                LEFT JOIN estados ON $valorSQL2.id_estado = estados.id_estado
                WHERE $valorSQL2.id_estado = ? AND reporte.fecha BETWEEN ? AND ?";

            $reporte= $db->consultaAll('mapa',$sql, [$params[3],
                                                     $params[1], 
                                                     $params[2]]);
            return json_encode($reporte);

        }else{

        $valorSQL = $TablaConsultar[$params[0]];

        $sql = "SELECT $valorSQL.*, `reporte`.fecha, estados.estado
        FROM $valorSQL
            LEFT JOIN `reporte` ON $valorSQL.id_reporte = `reporte`.`id`                  
            LEFT JOIN `estados` ON $valorSQL.id_estado = estados.id_estado 
            WHERE $valorSQL.id_estado = ? AND reporte.fecha BETWEEN ? AND ?";

            $reporte= $db->consultaAll('mapa',$sql, [$params[3],
                                                     $params[1], 
                                                     $params[2]]);
            return json_encode($reporte);
        }

    }else{
        return 'FALTAN INSERTAR PARAMETROS PARA SOLICITAR EL REPORTE';
}     
});
////////////////////////////////////////////////////FIN/////////////////////////////////////////////////////// 

//////////////////////////////////////////////////////DASH/////////////////////////////////////////

    
    $app->get('/api/dashboard/ultimos_reportes', function (Request $request, Response $response) {
                
        $sql = "SELECT `reporte`.*, tablas.tipo_reporte
        FROM `reporte`
        LEFT JOIN tablas ON reporte.id_tabla = tablas.id
        ";
        $db = New DB();

        $ultimos_reportes = $db->consultaAll('mapa',$sql);
        $values = array_slice($ultimos_reportes,-5);


        if (empty($values)) {
            return "LA CONSULTA NO TIENE RESULTADOS";
        }else {            
            return json_encode($values);
        }
       
    });


    $app->get('/api/dashboard/lps_recuperados[/{id_estado}]', function (Request $request, Response $response) {

        if ($request->getAttribute('id_estado')) {
            $id = $request->getAttribute('id_estado');
        }


        if (isset($id)) {
            $sql = "SELECT `reporte`.`fecha`, SUM(`rehabilitacion_pozo`.`lps`) AS total, `pozo`.`id_estado`, `estados`.`estado`
                FROM `reporte` 
                    LEFT JOIN `rehabilitacion_pozo` ON `rehabilitacion_pozo`.`id_reporte` = `reporte`.`id` 
                    LEFT JOIN `pozo` ON `rehabilitacion_pozo`.`id_pozo` = `pozo`.`id` 
                    LEFT JOIN `estados` ON `pozo`.`id_estado` = `estados`.`id_estado`
                    WHERE pozo.id_estado = ?
                    GROUP BY (reporte.fecha)";
                $db = New DB();
                $resultado = $db->consultaAll('mapa',$sql, [$id]);
        }else {
         $sql = "SELECT `reporte`.`fecha`, SUM(`rehabilitacion_pozo`.`lps`) AS total, `pozo`.`id_estado`, `estados`.`estado`
            FROM `reporte` 
            LEFT JOIN `rehabilitacion_pozo` ON `rehabilitacion_pozo`.`id_reporte` = `reporte`.`id` 
            LEFT JOIN `pozo` ON `rehabilitacion_pozo`.`id_pozo` = `pozo`.`id` 
            LEFT JOIN `estados` ON `pozo`.`id_estado` = `estados`.`id_estado`
            GROUP BY (reporte.fecha)";
        $db = New DB();
        $resultado = $db->consultaAll('mapa',$sql);
        }
        


        $array=[
            ["ENERO",0],
            ["FEBRERO",0],
            ["MARZO",0],
            ["ABRIL",0],
            ["MAYO",0],
            ["JUNIO",0],
            ["JULIO",0],
            ["AGOSTO",0],
            ["SEPTIEMBRE",0],
            ["OCTUBRE",0],
            ["NOVIEMBRE",0],
            ["DICIEMBRE",0]
        ];
 


       for ($i=0; $i < count($resultado) ; $i++) { 
            $fecha = strtotime($resultado[$i]["fecha"]);
            $mes = date("m", $fecha);
            $array[substr($mes,-1)][1] = $array[substr($mes,-1)][1] + $resultado[$i]["total"];

        }

       

        if (empty($array)) {
            return "LA CONSULTA NO TIENE RESULTADOS";
        }else {            
            return json_encode($array);
        }
             
    });




    $app->get('/api/dashboard/tomas_ilegales[/{id_estado}]', function (Request $request, Response $response) {

            if ($request->getAttribute('id_estado')) {
                $id = $request->getAttribute('id_estado');
            }
    
    
            if (isset($id)) {
                $sql = "SELECT `reporte`.`fecha`, SUM(`tomas_ilegales`.`cantidad_tomas_eliminadas`)AS total, `estados`.`estado`
                FROM `reporte` 
                    LEFT JOIN `tomas_ilegales` ON `tomas_ilegales`.`id_reporte` = `reporte`.`id` 
                    LEFT JOIN `estados` ON `tomas_ilegales`.`id_estado` = `estados`.`id_estado`
                    WHERE tomas_ilegales.id_estado = ?
                    GROUP BY (reporte.fecha)";
                    $db = New DB();
                    $resultado = $db->consultaAll('mapa',$sql, [$id]);
            }else {
                $sql = "SELECT `reporte`.`fecha`, SUM(`tomas_ilegales`.`cantidad_tomas_eliminadas`)AS total, `estados`.`estado`
                FROM `reporte` 
                    LEFT JOIN `tomas_ilegales` ON `tomas_ilegales`.`id_reporte` = `reporte`.`id` 
                    LEFT JOIN `estados` ON `tomas_ilegales`.`id_estado` = `estados`.`id_estado`
                    GROUP BY (reporte.fecha)";
                     $db = New DB();
                     $resultado = $db->consultaAll('mapa',$sql);
            }
            
              $array=[
                ["ENERO",0],
                ["FEBRERO",0],
                ["MARZO",0],
                ["ABRIL",0],
                ["MAYO",0],
                ["JUNIO",0],
                ["JULIO",0],
                ["AGOSTO",0],
                ["SEPTIEMBRE",0],
                ["OCTUBRE",0],
                ["NOVIEMBRE",0],
                ["DICIEMBRE",0]
            ];


            
       for ($i=0; $i < count($resultado) ; $i++) { 
        $fecha = strtotime($resultado[$i]["fecha"]);
        $mes = date("m", $fecha);
        $array[substr($mes,-1)][1] = $array[substr($mes,-1)][1] + $resultado[$i]["total"];

    }

     

    if (empty($array)) {
        return "LA CONSULTA NO TIENE RESULTADOS";
    }else {            
        return json_encode($array);
    }
});
     



    $app->get('/api/dashboard/fugas[/{id_estado}]', function (Request $request, Response $response) {

        if ($request->getAttribute('id_estado')) {
            $id = $request->getAttribute('id_estado');
        }

        if (isset($id)) {
            $sql = "SELECT `reporte`.`fecha`, SUM(`fugas`.`cantidad_fugas_reparadas`)AS total, `estados`.`estado`
            FROM `reporte` 
            LEFT JOIN `fugas` ON `fugas`.`id_reporte` = `reporte`.`id` 
            LEFT JOIN `estados` ON `fugas`.`id_estado` = `estados`.`id_estado`
            WHERE fugas.id_estado = ?
            GROUP BY (reporte.fecha)";
                $db = New DB();
                $resultado = $db->consultaAll('mapa',$sql, [$id]);
        }else {
            $sql = "SELECT `reporte`.`fecha`, SUM(`fugas`.`cantidad_fugas_reparadas`) AS total, `estados`.`estado`
            FROM `reporte` 
            LEFT JOIN `fugas` ON `fugas`.`id_reporte` = `reporte`.`id` 
            LEFT JOIN `estados` ON `fugas`.`id_estado` = `estados`.`id_estado`
            GROUP BY (reporte.fecha)";
                 $db = New DB();
                 $resultado = $db->consultaAll('mapa',$sql);

        }
        
          $array=[
            ["ENERO",0],
            ["FEBRERO",0],
            ["MARZO",0],
            ["ABRIL",0],
            ["MAYO",0],
            ["JUNIO",0],
            ["JULIO",0],
            ["AGOSTO",0],
            ["SEPTIEMBRE",0],
            ["OCTUBRE",0],
            ["NOVIEMBRE",0],
            ["DICIEMBRE",0]
        ];


        for ($i=0; $i < count($resultado) ; $i++) { 
            $fecha = strtotime($resultado[$i]["fecha"]);
            $mes = date("m", $fecha);
            $array[substr($mes,-1)][1] = $array[substr($mes,-1)][1] + $resultado[$i]["total"];
    
        }  
 

        if (empty($array)) {
            return "LA CONSULTA NO TIENE RESULTADOS";
        }else {            
            return json_encode($array);
        }

    });



    $app->get('/api/dashboard/pozos_operativos[/{id_estado}]', function (Request $request, Response $response) {
              $id = $request->getAttribute('id_estado'); 

        $sql = "SELECT COUNT(`pozo`.`id`) as total,pozo.operatividad, `estados`.`estado`
        FROM `pozo` 
            LEFT JOIN `estados` ON `pozo`.`id_estado` = `estados`.`id_estado`
                WHERE pozo.id_estado = ? AND pozo.operatividad = ?";
        $db = New DB();

        $array = [
            "operativos" => $db->consultaAll('mapa',$sql, [$id,1]),
            "inoperativos" => $db->consultaAll('mapa',$sql, [$id,0])
        ];
             return json_encode($array);

                
    });



/////////////////////////////////////////////////////////FIN DASHBOARD///////////////////////////////////////////////////////////////







//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
///////////////////////////////* POST *//////////////////////////////////////
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
//|||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||

$app->post('/api/formularios/reportes', function (Request $request, Response $response) {
    $body = json_decode($request->getBody());
    $TablaConsultar = ['produccion','rehabilitacion_pozo','fugas','tomas_ilegales','reparaciones_brippas','afectaciones','operatividad_abastecimiento','pozo','brippas','sistemas'];
    
    $tablasInsertar=[
    ['`metros_cubicos`', '`id_estado`', '`id_reporte`'],
    ['`lps`', '`id_pozo`', '`id_reporte`'],
    ['`nombre_aduccion`', '`id_estado`', '`id_municipio`', '`id_parroquia`', '`sector`' , '`cantidad_fugas_reparadas`','`id_reporte`', '`lps_recuperados`'],
    ['`nombre_aduccion`', '`id_estado`', '`id_municipio`', '`id_parroquia`', '`sector`' , '`cantidad_tomas_eliminadas`', '`lps`', '`id_reporte`', '`lps_recuperados`'],
    ['`averias_levantadas_ap`', '`averias_levantadas_ap`', '`averias_levantadas_as`', '`averias_corregidas_as`', '`id_brippas`' , '`id_reporte`'],
    ['`id_estado`', '`cantidad`', '`horas_sin_servicio`', '`equipos_danados`', '`id_infraestructura`' , '`id_sistema`', '`id_reporte`'],
    ['`id_estado`', '`porcentaje_operatividad`', '`porcentaje_abastecimiento`', '`observacion`', '`id_reporte`'],
    ['`nombre`', '`operatividad`', '`lps`', '`id_estado`', '`id_municipio`', '`id_parroquia`', '`sector`', '`poblacion`'],
    ['`nombre`', '`id_estado`', '`id_municipio`', '`id_parroquia`', '`sector`', '`cantidad_integrantes`', '`dotacion`', '`formacion`'],
    ['`nombre`', '`cantidad_pp`', '`cantidad_eb`', '`cantidad_pozo`', '`id_estado`']

    ];

    $tipoDatos = [//Tipo de datos a ingresar en cada formulario
        ["integer", "integer"],                                                                 //produccion
        ["integer", "integer"],                                                                 //rehabilitacion_pozo
        ["string", "integer", "integer", "integer", "string" , "integer", "integer"],           //fugas
        ["string", "integer", "integer", "integer", "string" , "integer", "integer", "integer"],//tomas_ilegales
        ["integer", "integer", "integer", "integer", "integer"],                                //reparaciones_brippas
        ["integer", "integer", "integer", "integer", "integer" , "integer"],                    //afectaciones
        ["integer", "integer", "integer", "string"],                                            //operatividad_abastecimiento
        ["string", "integer", "integer", "integer", "integer", "integer", "string", "integer"], //pozo
        ["string", "integer", "integer", "integer", "string", "integer", "integer", "integer"], //brippas
        ["string", "integer", "integer", "integer", "integer"]                                             //sistemas
    ];

    
    if (!empty($body->{'valores_insertar'})) {
        if (end($body->{'valores_insertar'}) === "reporte") {
            if (count($tablasInsertar[$body->{'tipo_formulario'}]) !== (count($body->{'valores_insertar'}) - 2 )) {
                return 'LOS VALORES NO COINCIDEN CON EL TIPO DE FORMULARIO1';                
            }
            
        }else{
            if (count($tablasInsertar[$body->{'tipo_formulario'}]) !== (count($body->{'valores_insertar'}))) {
                return 'LOS VALORES NO COINCIDEN CON EL TIPO DE FORMULARIO';
            }
        }        
    }else{
        return 'NO HAY VALORES PARA INSERTAR';
    }

    
    if (isset($body->{'tipo_formulario'})) {
        
        
        if (($body->{'tipo_formulario'} >= 0) AND ($body->{'tipo_formulario'} <=6)) {
            $sqlreporte = "INSERT INTO `reporte` (`id`, `ubicacion_reporte`, `fecha`, id_tabla, id_estado, id_revision) VALUES (NULL, ?, ?,?,?,?)";

            $sqlFormulario = generarSqlRegistro($tablasInsertar[$body->{'tipo_formulario'}], $body->{'tipo_formulario'}, $TablaConsultar[$body->{'tipo_formulario'}],);

            $values = array_slice($body->{'valores_insertar'},2,-1);
            $validacion = validarDatosFormulario($values,$body->{'tipo_formulario'},$tipoDatos[$body->{'tipo_formulario'}]);


            if ($validacion !== 'OK') {
                return  $validacion;
            }
    
            $db = new DB();

            $stmt = $db->consultaAll('mapa', $sqlreporte, [$body->{'valores_insertar'}[0], $body->{'valores_insertar'}[1],$body->{'tipo_formulario'}, $body->{'id_estado'},1]);
            
            if ($stmt) {
                array_push($values,$stmt->{'insert_id'});
                $stmt2 = $db->consultaAll('mapa', $sqlFormulario, $values);
                if ($stmt2) {
                $db = null;
                return 'REGISTRO EXITOSO, REPORTE NUMERO: '.$stmt->{'insert_id'};
            } else {
                return 'ERROR EN EL REGISTRO DEL REPORTE';
            }
            }

        }elseif(($body->{'tipo_formulario'} >= 7) AND ($body->{'tipo_formulario'} <=9)){
            $sqlFormulario = generarSqlRegistro($tablasInsertar[$body->{'tipo_formulario'}], $body->{'tipo_formulario'}, $TablaConsultar[$body->{'tipo_formulario'}]);
            
            
            $validacion = validarDatosFormulario($body->{'valores_insertar'},$body->{'tipo_formulario'},$tipoDatos[$body->{'tipo_formulario'}]);


            if ($validacion !== 'OK') {
                return  $validacion;
            }
            
            $db = new DB();
            $stmt = $db->consultaAll('mapa', $sqlFormulario, $body->{'valores_insertar'});
            if ($stmt) {
                return 'REGISTRO EXITOSO, '.strtoupper($TablaConsultar[$body->{'tipo_formulario'}]).' NUMERO: '.$stmt->{'insert_id'};
            }else {
                return 'ERROR EN EL REGISTRO DEL REPORTE';
            }

            
            
        }else {
            return 'EL VALOR DEL TIPO DE FORMULARIO NO ES VALIDO';
        }
    }else {

        return 'TIPO DE FORMULARIO NO ENVIADO';
    }
   

});


$app->post('/api/reportes/eliminar', function (Request $request, Response $response) { 
    $db = new DB();
    $body = json_decode($request->getBody());

// DATOS = TIPO DE INFORME/CONSULTA
//ID DEL INFORME A ELIMINAR
    $TablaConsultar = 
    ['produccion'
    ,'rehabilitacion_pozo'
    ,'fugas'
    ,'tomas_ilegales'
    ,'reparaciones_brippas'
    ,'afectaciones'
    ,'operatividad_abastecimiento'
    ,'pozo'
    ,'brippas'
    ,'sistemas'];

    

    
    if (isset($body->{'id_informe'}) && is_numeric($body->{'id_informe'})) {
        if (($body->{'id_informe'} >= 0) && ($body->{'id_informe'} <= 6)) {
            $valorSQL = $TablaConsultar[$body->{'id_formulario'}];
            $sql1 = "SELECT $valorSQL.* FROM $valorSQL";
            $consulta = $db->consultaAll('mapa', $sql1);
        }else {
            return 'TABLA A CONSULTAR NO VALIDA';
        }
    }else {
        return 'PARAMETROS DE BUSQUEDA NO VALIDOS';
    }

    if ($consulta) {
        

        try {
            $sql = "DELETE FROM $valorSQL WHERE $valorSQL.`id` = ?";
            $stmt = $db->consultaAll('mapa', $sql, [$body->{'id_informe'}]);


            if ($stmt) {

                $sql = "DELETE FROM reporte WHERE reporte.id = ?";
                $stmt = $db->consultaAll('mapa', $sql, [$consulta[0]["id_reporte"]]);
                if ($stmt) {
                    return 'INFORME ELIMINADO';
                }else {
                    return 'HUBO UN ERROR EN LA ELIMINACION DEL INFORME';
                }        
            }else {
                return 'HUBO UN ERROR EN LA ELIMINACION DEL INFORME';
            }
            
            } 
        catch (MySQLDuplicateKeyException $e) {
            $e->getMessage();
        }
        catch (MySQLException $e) {
            $e->getMessage();
        }
        catch (Exception $e) {
            $e->getMessage();
        }
    }
    
    
    
        
        
     
});



$app->put('/api/actualizacion/pozo', function (Request $request, Response $response) { 
    $body = json_decode($request->getBody());
    /*
    DATOS
    OPERATIVIDAD (1-0)
    ID DEL POZO A ACTUALIZAR

    */
    try {
        $sql = "UPDATE `pozo` SET `operatividad` = ? WHERE `pozo`.`id` = ?";
        $db = new DB();
        $stmt = $db->consultaAll('mapa', $sql, [$body->{'operatividad'}, $body->{'id_pozo'}]);
        if ($stmt) {
            return 'POZO ACTUALIZADO';          
        }else {
            return 'HUBO UN ERROR EN LA ACTUALIZACION';
        }
        
        } 
    catch (MySQLDuplicateKeyException $e) {
        $e->getMessage();
    }
    catch (MySQLException $e) {
        $e->getMessage();
    }
    catch (Exception $e) {
        $e->getMessage();
    }
});



$app->put('/api/actualizacion/revision', function (Request $request, Response $response) { 
    $body = json_decode($request->getBody());
    /*
    DATOS
    id_revision (1 - 2)
    id del reporte a actualizar

    */
    try {
        $sql = "UPDATE `reporte` SET `id_revision` = ? WHERE `reporte`.`id` = ?";
        $db = new DB();
        $stmt = $db->consultaAll('mapa', $sql, [$body->{'id_revision'}, $body->{'id_reporte'}]);
        if ($stmt) {
            return 'REPORTE REVISADO';          
        }else {
            return 'HUBO UN ERROR EN LA LECTURA DEL REPORTE';
        }
        
        } 
    catch (MySQLDuplicateKeyException $e) {
        $e->getMessage();
    }
    catch (MySQLException $e) {
        $e->getMessage();
    }
    catch (Exception $e) {
        $e->getMessage();
    }
});
