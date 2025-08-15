<?php
require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/datalib.php');
require_once($CFG->libdir . '/accesslib.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://capacita.prodominicana.gob.do');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!isloggedin() || isguestuser()) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autorizado']);
    exit;
}

try {
    global $USER, $DB;
    
    $accion = $_POST['accion'] ?? '';
    $evento_titulo = $_POST['evento_titulo'] ?? '';
    $evento_fecha = $_POST['evento_fecha'] ?? '';
    
    if (empty($accion) || empty($evento_titulo)) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit;
    }
    
    $acciones_validas = ['google_calendar', 'download_ics'];
    if (!in_array($accion, $acciones_validas)) {
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        exit;
    }
    
    // Obtener contexto del sistema
    $system_context = context_system::instance();
    
    // Estructura completa y correcta para Moodle 4.0 + MariaDB 10.6.22
    $log = new stdClass();
    
    // Campos obligatorios principales
    $log->eventname = '\\core\\event\\user_loggedin';  // Evento válido y conocido
    $log->component = 'core';                          // Componente seguro
    $log->action = 'viewed';                           // Acción estándar
    $log->target = 'user';                             // Target válido
    $log->objecttable = 'user';                        // Tabla existente
    $log->objectid = $USER->id;                        // ID del usuario
    $log->crud = 'r';                                  // Read operation
    $log->edulevel = 0;                                // Nivel educacional (0 = other)
    
    // Contexto
    $log->contextid = $system_context->id;
    $log->contextlevel = CONTEXT_SYSTEM;
    $log->contextinstanceid = 0;
    
    // Usuario y curso
    $log->userid = $USER->id;
    $log->courseid = 0;                                // 0 para eventos del sistema
    $log->relateduserid = null;
    $log->anonymous = 0;
    
    // Datos personalizados - AQUÍ VA LA INFORMACIÓN REAL
    $other_data = [
        'agenda_component' => 'agenda_capacitaciones',   // Nuestro componente real
        'agenda_action' => $accion,                      // Acción real (google_calendar/download_ics)
        'evento_titulo' => substr($evento_titulo, 0, 255), // Título limitado
        'evento_fecha' => $evento_fecha,                 // Fecha del evento
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'tracking_version' => '1.0'
    ];
    $log->other = json_encode($other_data);
    
    // Timestamps y metadata
    $log->timecreated = time();
    $log->origin = 'web';
    $log->ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $log->realuserid = null;
    
    // Insertar en la base de datos
    $log_id = $DB->insert_record('logstore_standard_log', $log);
    
    if ($log_id) {
        echo json_encode([
            'success' => true, 
            'message' => 'Acción registrada correctamente',
            'data' => [
                'log_id' => $log_id,
                'user_id' => $USER->id,
                'username' => $USER->username,
                'action' => $accion,
                'event_title' => $evento_titulo,
                'timestamp' => date('Y-m-d H:i:s', time()),
                'system' => 'Moodle 4.0 + MariaDB 10.6.22'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'No se pudo insertar el registro'
        ]);
    }
    
} catch (moodle_exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Error de Moodle',
        'details' => $e->getMessage()
    ]);
} catch (dml_exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Error de base de datos',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Error interno del servidor',
        'details' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>