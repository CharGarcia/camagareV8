<?php
include_once("../ajax/autocomplete/clientes.php");
//$clientes =  clientes();
 $clientes = array ("ActionScript", "AppleScript", "Asp", "BASIC", "C", "C++",
    "Clojure", "COBOL", "ColdFusion", "Erlang", "Fortran",
    "Groovy", "Haskell", "Java", "JavaScript", "Lisp", "Perl",
    "PHP", "Python", "Ruby", "Scala", "Scheme" );


echo json_encode($clientes);
?>