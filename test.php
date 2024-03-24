<?php

$data = [
    "a"=>"a",
    "b"=>"b",
];

$c = &$data["c"];
var_dump($c);
$c="c";
var_dump($data);
