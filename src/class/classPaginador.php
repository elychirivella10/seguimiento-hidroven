<?php
  
      
class paginadorMesa {
    private $id;
           
    function __construct($id=null, $idPrimerRender, $tipoConsulta){
        $this->id=$id;
        $this->idPrimerRender=$idPrimerRender;
        $this->tipoConsulta=$tipoConsulta;
    }

    function paginadorMesa($datos, $scope, $id){

        if(!isset($datos[1]))
        {
            $pagina = 1;
        }else{
            $pagina = intval($datos[1]);
        }
        
        $pagina = isset($datos[1]) ?(int)$datos[1]  : 1;

        $regPagina = 20;
        $inicio = ($pagina > 1) ? (($pagina * $regPagina) - $regPagina) : 0 ;
        $where = CondicionalMYSQL($this->idPrimerRender, $this->tipoConsulta,  $this->id, $scope);
        

        $sql = "SELECT SQL_CALC_FOUND_ROWS `mta`.`id_mta`, `datos_geograficos`.*, `datos_mta`.*, tipo_registro.tipo_registro, `municipios`.`municipio`, `estados`.`estado`, `parroquias`.`parroquia`, `estatus`.`estatus` 
                FROM `mta` 
                LEFT JOIN `datos_geograficos` ON `mta`.`id_datos_geograficos` = `datos_geograficos`.`id_datos_geograficos` 
                LEFT JOIN `datos_mta` ON `mta`.`id_datos_mta` = `datos_mta`.`id_datos_mta` 

                LEFT JOIN `tipo_registro` ON `mta`.`tipo_registro` = `tipo_registro`.`id_tipo_registro`
                LEFT JOIN `municipios` ON `datos_geograficos`.`id_municipio` = `municipios`.`id_municipio` 
                LEFT JOIN `estados` ON `datos_geograficos`.`id_estado` = `estados`.`id_estado` 
                LEFT JOIN `parroquias` ON `datos_geograficos`.`id_parroquia` = `parroquias`.`id_parroquia` 
                LEFT JOIN `estatus` ON `mta`.`id_estatus` = `estatus`.`id_estatus` {$where} LIMIT $inicio , $regPagina";

            //var_dump($sql);
            
        if ($where !== "") {
            if ($this->tipoConsulta!==null) {
                if ($this->idPrimerRender===25) {
                    $db = New DB();
                    $resultado = $db->consultaAll('mapa',$sql, $this->id);
                }else if($this->idPrimerRender!==25 && userVerification($scope) === false){
                    $db = New DB();
                    $param= [$id, ...$this->id];
                    $resultado = $db->consultaAll('mapa',$sql, $param);
                }
                else{
                    $db = New DB();
                    $param= [$this->idPrimerRender, ...$this->id];
                    $resultado = $db->consultaAll('mapa',$sql, $param);
                }
            }else if (userVerification($scope) === false) {
                $db = New DB();
                
                $resultado = $db->consultaAll('mapa',$sql, [$id]);
            }else {
                $db = New DB();
                $resultado = $db->consultaAll('mapa',$sql, [$this->idPrimerRender]);
            }
        }else {
            $db = New DB();
            $resultado = $db->consultaAll('mapa',$sql);
        }

        /*for ($i=0; $i < count($resultado); $i++) { 
             $resultado[$i]["id_search"] ="#".$resultado[$i]["nomenclatura"]."-".$resultado[$i]["id_proyecto"];        
        }*/

        $nroPaginas = ceil($datos[0] / $regPagina);
        
        $sql2 = "SELECT COUNT(consejo_comunal.id_consejo_comunal) AS Consejos,SUM(consejo_comunal.poblacion) AS Poblacion 
        FROM `mta` 
        LEFT JOIN `datos_mta` ON `mta`.`id_datos_mta` = `datos_mta`.`id_datos_mta` 
        LEFT JOIN `consejo_comunal` ON `consejo_comunal`.`id_datos_mta` = `datos_mta`.`id_datos_mta` 
        WHERE mta.id_mta = ?";
        $sql3 = "SELECT COUNT(voceros.id_voceros) AS Voceros
        FROM `mta`
        LEFT JOIN `datos_mta` ON `mta`.`id_datos_mta` = `datos_mta`.`id_datos_mta` 
        LEFT JOIN `voceros` ON `voceros`.`id_datos_mta` = `datos_mta`.`id_datos_mta` 
        WHERE mta.id_mta = ?";
        
        for ($i=0; $i < $datos[0]; $i++) { 
            $ar = $db->consultaAll('mapa',$sql2, [$resultado[$i]['id_mta']]);
            $er = $db->consultaAll('mapa',$sql3, [$resultado[$i]['id_mta']]);
            $resultado[$i]['poblacion']= $ar[0]['Poblacion'];
            $resultado[$i]['consejos']= $ar[0]['Consejos'];
            $resultado[$i]['voceros']= $er[0]['Voceros'];
            
        }
        
            return [
                
                "proyectos" => $resultado
            ];       
        
        

        

        
    }

}



?>