<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
(new Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables())->bootstrap($app);
(new Illuminate\Foundation\Bootstrap\LoadConfiguration())->bootstrap($app);
$r = new Illuminate\Foundation\Bootstrap\RegisterProviders();
(new ReflectionMethod($r, "mergeAdditionalProviders"))->invoke($r, $app);
$providers = $app->make("config")->get("app.providers");
file_put_contents($app->getCachedPackagesPath(), "<?php return [];");
file_put_contents(
    $app->getCachedServicesPath(),
    "<?php return " .
        var_export(["providers" => $providers, "eager" => $providers, "deferred" => [], "when" => []], true) .
        ";",
);
echo "Cache written";
