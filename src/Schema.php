<?
namespace R\GraphQL;


use GraphQL\Utils\BuildSchema;
use GraphQL\GraphQL;
use GraphQL\Utils\Utils;
use GraphQL\Language\AST\DirectiveNode;
use GuzzleHttp\Promise\Promise;
use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;

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

    public static function Build($gql, $context = null, $directiveDef = null)
    {
        $schema = BuildSchema::build($gql);

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
        }

        if ($directiveDef) {
            attachDirectiveResolvers($schema, $directiveDef);
        }


        $s = new Schema($schema);
        return $s;
    }


}
