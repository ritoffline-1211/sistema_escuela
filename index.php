<?php

require_once __DIR__ . '/auth.php';

requiereSesion();

header('Location: lista_alumnos.php');
exit;
