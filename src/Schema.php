<?
namespace R\GraphQL;

use GraphQL\Utils\BuildSchema;
use GraphQL\GraphQL;

class Schema
{
    public function __construct($schema)
    {
        $this->schema = $schema;

    }

    public function executeQuery($query, $variableValues)
    {
        try {
            $result = GraphQL::executeQuery($this->schema, $query, $rootValue, null, $variableValues);

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

    public static function Build($gql, $context)
    {
        $schema = BuildSchema::build($gql);

        foreach ($schema->getTypeMap() as $type) {

            try {
                $class = new \ReflectionClass("\Type\\" . $type->name);
            } catch (\Exception $e) {
                continue;
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
                    }
    
                }
            }
        }

        $s = new Schema($schema);
        return $s;
    }

}
