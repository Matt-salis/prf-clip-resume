crear el archivo run_task.php en la raiz de moodle.
escribir este codigo 

/////////////////////////////////
<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');

// Manually trigger the task.
$task = new \local_drive_video_transfer\task\transfer_videos();
$task->execute();
/////////////////////////////////


tirar la carpeta completa en ../moodle/local/

comando para correr la task desde el path de moodle
php run_task.php



aun no logra conectar con google drive, pero el json con las credenciales esta incluido y deberia servir 
