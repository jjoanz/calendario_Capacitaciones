<?php
require_once(__DIR__ . '/config.php');
require_once($CFG->libdir . '/externallib.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://capacita.prodominicana.gob.do'); //  dominio plataforma 
header('Access-Control-Allow-Credentials: true');

if (isloggedin() && !isguestuser()) {
    global $USER;
    echo json_encode([
        'logueado' => true,
        'id' => $USER->id,
        'nombre' => fullname($USER),
        'email' => $USER->email
    ]);
} else {
    echo json_encode([
        'logueado' => false
    ]);
}
