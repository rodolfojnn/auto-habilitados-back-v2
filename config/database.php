<?php

/*
CREATE USER IF NOT EXISTS nf_demonstracao@'%' IDENTIFIED BY '5e96dc9f-57d4-4326-8207-9e8daa0e5335';
GRANT ALL PRIVILEGES ON nf_demonstracao.* TO nf_demonstracao@'%' IDENTIFIED BY '5e96dc9f-57d4-4326-8207-9e8daa0e5335' WITH GRANT OPTION;
FLUSH PRIVILEGES;
*/

return [

  'default' => 'instrut_db',

  'connections' => [

    'instrut_db' => [
      'driver' => 'mysql',
      'host' => '35.208.228.251',
      'port' => '13360',
      'database' => 'instrut_db',
      'username' => 'instrut_db',
      'password' => env('DB_PASSWORD'),
      'charset' => 'utf8mb4',
      'collation' => 'utf8mb4_unicode_ci',
      'prefix' => '',
      'prefix_indexes' => true,
      'strict' => true,
      'engine' => null
    ],

  ],

  'migrations' => 'migrations',

];
