<?
namespace R\GraphQL;


use GraphQL\Utils\BuildSchema;
use GraphQL\GraphQL;
use GraphQL\Utils\Utils;
use GraphQL\Language\AST\DirectiveNode;
use GuzzleHttp\Promise\Promise;
use GraphQL\Error\Error;

class Util
{

    /**
     * @param ResolveInfo $info
     * @param string $name
     * @return DirectiveNode|null
     */
    public static function getDirectiveByName(\GraphQL\Type\Definition\ResolveInfo $info, string $name)
    {
        $fieldNode = $info->fieldNodes[0];
        /** @var NodeList $directives */
        $directives = $fieldNode->directives;
        if ($directives) {
            /** @var DirectiveNode[] $directives */
            foreach ($directives as $directive) {
                if ($directive->name->value === $name) {
                    return $directive;
                }
            }
        }
        return null;
    }

    /**
     * @param DirectiveNode $directive
     * @return ValueNode[]
     */
    public static function getDirectiveArguments(DirectiveNode $directive)
    {
        $args = [];
        foreach ($directive->arguments as $arg) {
            $args[$arg->name->value] = $arg->value;
        }
        return $args;
    }
}

function fieldResolver($source, $args, $context, \GraphQL\Type\Definition\ResolveInfo $info)
{
    $fieldName = $info->fieldName;
    $property = null;

    if (is_array($source) || $source instanceof \ArrayAccess) {
        if (isset($source[$fieldName])) {
            $property = $source[$fieldName];
        }
    } else if (is_object($source)) {
        if (isset($source->{$fieldName})) {
            $property = $source->{$fieldName};
        }
    }

    return $property instanceof Closure ? $property($source, $args, $context, $info) : $property;
}

class Schema
{
    public function __construct($schema)
    {
        $this->schema = $schema;

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
                }
            }

            return $property instanceof Closure ? $property($source, $args, $context, $info) : $property;
        };
    }

    public function executeQuery($query, $variableValues)
    {
        try {
            $result = GraphQL::executeQuery($this->schema, $query, $rootValue, null, $variableValues, $operationName);
            //$result = GraphQL::executeQuery($this->schema, $query, $rootValue, null, $variableValues);
            $result = $result->toArray();

            if ($result["errors"]) {
                $result["error"]["message"] = $result["errors"][0]["message"];
                $result["error"]["errors"] = $result["errors"];
                unset($result["errors"]);

            }
        } catch (\Exception $e) {
            $result = [
                'error' => [
                    'message' => $e->getMessage()
                ]
            ];
        }
        return $result;
    }

    public static function Build($gql, $context, $directiveDef)
    {
        $schema = BuildSchema::build($gql);

        if ($directiveDef) {
            foreach ($schema->getTypeMap() as $type) {
                if (!$type instanceof \GraphQL\Type\Definition\ObjectType) {
                    continue;
                }
                foreach ($type->getFields() as $field) {
                    $directives = [];
                    foreach ($field->astNode->directives as $node) {
                        $name = $node->name->value;

                        $args = [];
                        foreach (Util::getDirectiveArguments($node) as $k => $v) {
                            $args[$k] = $v->value;
                        }

                        if (is_array($directiveDef)) {
                            $resolveFn = $directiveDef[$name];
                        } else {
                            $resolveFn = function ($next, $source, $args, $context, $info) use ($directiveDef, $name) {
                                if (method_exists($directiveDef, $name)) {
                                    return $directiveDef->$name($next, $source, $args, $context, $info);
                                } else {
                                    return $next();
                                }

                            };
                        }

                        $directives[$node->name->value] = [
                            "name" => $node->name->value,
                            "args" => $args,
                            "resolveFn" => $resolveFn ? $resolveFn : function ($next) {
                                return $next();
                            }
                        ];
                    }
                    $field->directives = $directives;
                }
            }
        }


        foreach ($schema->getTypeMap() as $type) {
            try {
                $class = new \ReflectionClass("\Type\\" . $type->name);
            } catch (\Exception $e) {
                continue;
            }

            $className = $class->getName();
            $o = new $className();
            foreach ($type->getFields() as $field) {
                if (is_callable([$className, $field->name]) || method_exists($o, "__call")) {
                    $field->resolveFn = function ($root, $args) use ($field, $context, $o) {
                        return call_user_func_array([$o, $field->name], [$root, $args, $context]);
                    };
                } else {
                    $field->resolveFn = self::FieldResolver();
                }
            }

            foreach ($type->getFields() as $field) {
                $orginalResolveFn = $field->resolveFn;

                $field->resolveFn = function ($root, $args, $context, $info) use ($field, $orginalResolveFn) {
                    $parent = new Promise();
                    $p = $parent;
                    foreach ($field->directives as $name => $directive) {
                        $p = function () use ($p) {
                            return $p;
                        };
                        $resolver = $directive["resolveFn"];

                        $p = $resolver($p, $root, $directive["args"], $context, $info);
                    }

                    $value = $orginalResolveFn($root, $args, $context, $info);
                    $parent->resolve($value);

                    return $p->wait();

                };
            }

        }

        $s = new Schema($schema);
        return $s;
    }


}
