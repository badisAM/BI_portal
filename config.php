<?php

$conn = new mysqli(
"localhost",
"root",
"",
"bi_portal"
);

session_start();

if($conn->connect_error)
{
    die("Erreur connexion");
}