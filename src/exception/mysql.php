<?php 


const MYSQL_DUPLICATE_KEY_ENTRY = 1022;

class MySQLException extends Exception {}
class MySQLDuplicateKeyException extends MySQLException {}

function my_mysql_query($query, $conn=false) {
    $res = mysql_query($query, $conn);
    if (!$res) {
        $errno = mysql_errno($conn);
        $error = mysql_error($conn);
        switch ($errno) {
        case MYSQL_DUPLICATE_KEY_ENTRY:
            throw new MySQLDuplicateKeyException($error, $errno);
            break;
        default:
            throw MySQLException($error, $errno);
            break;
        }
    }
    // ...
    // doing something
    // ...
    if ($something_is_wrong) {
        throw new Exception("Logic exception while performing query result processing");
    }

}
?>