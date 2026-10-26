<?php
// Copiar a config.local.php y completar. config.local.php NO va a git.
return [
    'tk' => [
        'host' => 'localhost',
        'db'   => 'tk_imperio',
        'user' => 'root',
        'pass' => '',
    ],
    // Conexión de SOLO LECTURA a SAS Imperio (GRANT SELECT en ic_usuarios e ic_clientes).
    'sas' => [
        'host' => 'localhost',
        'db'   => 'c2881399_credit',
        'user' => 'tk_sas_ro',
        'pass' => '',
    ],
    'app' => [
        'debug' => true,
        'base_url' => '',          // '' si vive en la raíz del host; '/tk/public' en WAMP por carpeta
        'cookie_secure' => false,  // true en producción (HTTPS)
    ],
];
