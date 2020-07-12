<?php

require_once './vendor/autoload.php';

use Kreait\Firebase\Factory;

$factory = (new Factory)->withServiceAccount('./firebase_credentials.json');

$database = $factory->createDatabase();

// $reference = $database->getReference('users');
/*

$reference->getChild(12348764531)->set([
    "status"=> "awating_name"
]);
*/


print_r($last_message);
