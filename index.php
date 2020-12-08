<!DOCTYPE html>
<head>
    <title>Games</title>
    <style type="text/css">
        div {
            display: inline-block;
        }
    </style>
</head>
<body>
<nav>
    <a href="/">index</a>
    <a href="/games/">games</a>
</nav>

<?php

// get url path
$path = explode('/', urldecode(parse_url($_SERVER['REQUEST_URI'])['path']));
if($path[0] == "") {
    array_shift($path);
}
if($path[count($path) - 1] == "") {
    array_pop($path);
}

// include components
require_once("config.php");
require_once("database.php");
require_once("games.php");

// route & run component
if(isset($path[0])) {
    if($path[0] == "games") {
        $games = new Games($path);
        echo $games->makePage($path);
    }
}