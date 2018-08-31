<?php

class M_PDO{

    const DB_SERVER = "localhost";
    const DB_USER = "root";
    const DB_PASS = "";
    const DB_NAME = "printing_doc";


	private static $instance;
	
	public static function Instance(){
		if(self::$instance == null){
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	private function __construct(){
		setlocale(LC_ALL, 'UTF8');
		$this->db = new mysqli(self::DB_SERVER, self::DB_USER, self::DB_PASS, self::DB_NAME);
		$this->db->set_charset("utf8");

	}
	
	public function Select($query){

        $res = $this->db->prepare($query);
        $res->execute();
        $array = $res->get_result()->fetch_all(MYSQLI_ASSOC);
	    return $array;
	}

    public function connectDB(){

        try {
            $this->db = new PDO("mysql:host=".self::DB_SERVER.";dbname=".self::DB_NAME."", self::DB_USER, self::DB_PASS);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->exec("set names utf8");

        }
        catch(PDOException $e) {
            echo $e->getMessage();
            // echo "Нет соединения с базой данных";
        }
        return $this->db;
    }
}
?>