<?php return [
    "default" => "single",
    "channels" => [
        "mail" => ["driver" => "single", "path" => storage_path("logs/mail.log"), "level" => "debug"],
        "single" => ["driver" => "single", "path" => storage_path("logs/laravel.log"), "level" => "debug"],
    ],
];
