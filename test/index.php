<?
$path = realpath(__DIR__ . "/../../../composer/vendor/autoload.php");
$loader = require_once($path);
$loader->addPsr4("Type\\", __DIR__ . "\Type");

$app = new App\App(realpath(__DIR__ . "/../../../cms"), $loader);

require_once __DIR__ . "/../vendor/autoload.php";
use R\GraphQL\Schema;

class DirectiveResolver
{
    public function hasRole($next, $source, $args, $context)
    {
        return $next()->then(function ($s) use ($args) {
            return $s . "a" . $args["role"] . $args["a"];
        });
    }

    public function upper($next)
    {
        return $next()->then(function ($s) {
            return strtoupper($s);
        });
    }
    public function lower($next)
    {
        return $next()->then(function ($s) {
            return strtolower($s);
        });
    }

}

$schema = Schema::build(file_get_contents(__DIR__ . '/schema.gql'), $context, new DirectiveResolver());

$q = "query{
    User{
        first_name
    }
}
";

print_r($schema->executeQuery($q));