<?php
class User{
 private $pdo;
 function __construct($pdo){$this->pdo=$pdo;}
 function findByEmail($e){$s=$this->pdo->prepare("SELECT * FROM users WHERE email=?");$s->execute([$e]);return $s->fetch(PDO::FETCH_ASSOC);}
 function create($n,$e,$p){$s=$this->pdo->prepare("INSERT INTO users(name,email,password) VALUES(?,?,?)");return $s->execute([$n,$e,$p]);}
}
