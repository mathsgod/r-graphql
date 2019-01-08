<?
$path = realpath(__DIR__ . "/../../../composer/vendor/autoload.php");
$loader = require_once($path);
$loader->addPsr4("Type\\", __DIR__ . "\Type");

$app = new App\App(realpath(__DIR__ . "/../../../cms"), $loader);

require_once __DIR__ . "/../vendor/autoload.php";
use R\GraphQL\Schema;
use GraphQL\Error\Error;

class DirectiveResolver
{
    public function hasRole($next, $source, $args, $context)
    {
        
        //if ($args["role"] != $context->getRole()) throw new Error("Error");
        return $next();
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