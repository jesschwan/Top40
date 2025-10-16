<?php
    // currently use root user, ToDo: change later 

    $sqlHost = "localhost";
    $sqlUser = "root";
    $sqlPassword = "";
    $sqlDB = "top40db";

    // returns an open SQL Connection (do not forget to close if not needed any longer)
    function getSqlConnection() {
        global $sqlHost, $sqlUser, $sqlPassword, $sqlDB; // makes externally defined variables available 

        // connect with localhost
        $dbConn = new mysqli($sqlHost, $sqlUser, $sqlPassword, $sqlDB, 3306);

        if ($dbConn->connect_error) {
            die('Could not connect to database: ' . $dbConn->connect_error);
        }

        return $dbConn;
    }
?>