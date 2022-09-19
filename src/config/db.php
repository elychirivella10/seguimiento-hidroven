<?php 

	class DB {
		private $dbHost = 'localhost';
		private $dbName = 'seguimiento';
		private $dbUser = 'root';
		private $dbName2 = 'seguimiento';
		private $dbUser2 = 'root';
		private $dbPass = '';
		
		public function connection ($dbName) {
			$conec = new mysqli ($this->dbHost , $this->dbUser , $this->dbPass , $this->dbName);
			return $conec;
		}
		public function consultaSinParametros($dbName, $sql){
			$conec = new mysqli ($this->dbHost , $this->dbUser , $this->dbPass , $dbName);
			$resultado = $conec->prepare($sql);
    		$resultado->execute();
    		$resultado = $resultado->get_result();
			$array = $resultado->fetch_all(MYSQLI_ASSOC);
			return [$array, $resultado];
		}

		public function consultaAll($tipo ,$sql, $array = null, $out = null){
			/*funcion para consultas dinamicas
				$tipo es la base de datos que se va a consultar, si es de tipo usuario o de tipo mapa genereal
				$sql es la consulta escrita en leguaje sql
				$array es un array con los datos o id's que se van a agregar, si es que se va a agregar algo o se va a actualizar, es opcional este campo
				$out es como se quiere recibir los datos, por manera predeterminada se devuelven en array, pero se puede obtener como objeto mediante el valor objeto
			*/
			$conec = new mysqli ($this->dbHost , $tipo === "mapa"?$this->dbUser:$this->dbUser2 , $this->dbPass , $tipo === "mapa"?$this->dbName:$this->dbName2);
			$stmt = $conec->prepare($sql); 
			
			if ($array !== null) {
				$types = typesConsultas($array);
				$stmt->bind_param($types[0], ...$types[1]);
			}
			$stmt->execute();
			
			if ($stmt->affected_rows >= 0) {
				return $stmt;
			}else {
				$resultado = $stmt->get_result();
				
				if ($out === 'objeto') {
					return $resultado->fetch_object();
				} else {
					return $resultado->fetch_all(MYSQLI_ASSOC);
				}
			}
		}
	}

 ?>