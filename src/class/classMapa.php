<?php

class Mapa {
    private $municipio;

    function __construct($municipio){
        $this->municipio=$municipio;
    }

    function mapaParroquia() {
        
        $json2 = file_get_contents(__DIR__.'/parroquiasCcs.json');
        $json2=json_decode($json2);
        $jsonNum = count($json2->features);
        $array=[];
        for ($i=0; $i < $jsonNum; $i++) { 
            if ($json2->features[$i]->properties->municipio === $this->municipio) {
                array_push($array, $json2->features[$i]);
            }
        }
        return $array;
    }
}
?>