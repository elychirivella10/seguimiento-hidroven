<?php


class Registro {



    function registroVoceros($body , $voceros){
        $db=new DB();


        $sql = "SELECT id_datos_mta from mta WHERE mta.id_mta = ?";
        $db = New DB();
        $id_datos_mta = $db->consultaAll('mapa',$sql,[$body->{'id_mta'}])[0]["id_datos_mta"];


        if ($id_datos_mta) {
            $sql = "INSERT INTO voceros (id_voceros, nombre, cedula, telefono, correo, id_datos_mta, id_unidad_vocero, id_consejo_comunal) 
            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)";

            for ($i=0; $i < count($voceros) ; $i++) { 
                $db = new DB();               
                $stmt = $db->consultaAll
                
                ('mapa', $sql,
                    [$voceros[$i]->nombre, 
                    $voceros[$i]->cedula, 
                    $voceros[$i]->telefono,
                    $voceros[$i]->correo,
                    $id_datos_mta, 
                    $voceros[$i]->id_unidad_vocero+0 ,
                    $voceros[$i]->id_consejo_comunal+0]);   
            }    

            if ($stmt) {
                return["total"=> count($voceros)." Voceros guardados"];
            }  
           
        }    
        
    }
    
}

?>