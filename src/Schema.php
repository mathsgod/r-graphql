<?php

namespace R\GraphQL;

use GraphQL\Utils\BuildSchema;
use GraphQL\GraphQL;
use Closure;
use Exception;
use Firebase\JWT\JWT;

class Schema
{
    public $context = null;
    public $debug = false;
    public static $Namespace = "\\";
    public $allowed_algs = ["HS256"];

    public function __construct($schema, $context)
    {
        $this->schema = $schema;
        $this->context = $context;
    }

    private function isValidJWT(string $jwt): bool
    {
        return count(explode(".", $jwt)) == 3;
    }

    public static function FieldResolver()
    {
        return function ($source, $args, $context, \GraphQL\Type\Definition\ResolveInfo $info) {
            $fieldName = $info->fieldName;
            $property = null;

            if (is_array($source) || $source instanceof \ArrayAccess) {
                if (isset($source[$fieldName])) {
                    $property = $source[$fieldName];
                }
            } else if (is_object($source)) {
                if (isset($source->{$fieldName})) {
                    $property = $source->{$fieldName};
                } elseif (method_exists($source, $fieldName)) {
                    return call_user_func([$source, $fieldName]);
                }
            }

            return $property instanceof Closure ? $property($source, $args, $context, $info) : $property;
        };
    }

    public function validation(string $jwt, string $secret, callable $callback)
    {
        if (!$this->isValidJWT($jwt)) {
            throw new Exception("Invalid jwt format");
        }

        $payload = JWT::decode($jwt, $secret, $this->allowed_algs);
        $callback((array) $payload);
    }

    public function executeQuery(string $query, $variableValues = null): array
    {
        $rootValue = null;
        $operationName = null;
        try {
            $result = GraphQL::executeQuery($this->schema, $query, $rootValue, $this->context, $variableValues, $operationName);
            $result = $result->toArray($this->debug);

            if ($result["errors"]) {
                $result["error"]["message"] = $result["errors"][0]["message"];
                $result["error"]["errors"] = $result["errors"];
                unset($result["errors"]);
            }
        } catch (Exception $e) {
            $result = [
                'error' => [
                    'message' => $e->getMessage()
                ]
            ];
        }
        return $result;
    }

    public static function Build(string $gql, $context = null, $directiveDef = null, callable $typeConfigDecorator = null, callable $fieldResolver = null): Schema
    {
        $schema = BuildSchema::build($gql, $typeConfigDecorator);

        foreach ($schema->getTypeMap() as $type) {

            if ($type instanceof \GraphQL\Type\Definition\CustomScalarType) {
                $class = self::$Namespace . "Scalar\\" . $type->name;

                if (class_exists($class)) {
                    $scalar = new $class;
                    $type->description = $scalar->description;

                    if (method_exists($scalar, "serialize")) {
                        $type->config["serialize"] = [$scalar, "serialize"];
                    }

                    if (method_exists($scalar, "parseLiteral")) {
                        $type->config["parseLiteral"] = [$scalar, "parseLiteral"];
                    }

                    if (method_exists($scalar, "parseValue")) {
                        $type->config["parseValue"] = [$scalar, "parseValue"];
                    }
                }
            }

            if ($type instanceof \GraphQL\Type\Definition\ObjectType) {
                $class = self::$Namespace . "Type\\" . $type->name;

                if (class_exists($class)) {
                    $o = new $class;
                    foreach ($type->getFields() as $field) {
                        if (is_callable([$o, $field->name]) || method_exists($o, "__call")) {
                            $field->resolveFn = function ($root, $args, $context) use ($field, $o) {
                                return call_user_func_array([$o, $field->name], [$root, $args, $context]);
                            };
                        } elseif ($fieldResolver) {
                            $field->resolveFn = $fieldResolver;
                        } else {
                            $field->resolveFn = self::FieldResolver();
                        }
                    }
                }
            }
        }

        if ($directiveDef) {
            attachDirectiveResolvers($schema, $directiveDef);
        }


        $s = new Schema($schema, $context);
        return $s;
    }
}
