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

            foreach ($type->getFields() as $field) {
                try {

                    $method = $class->getMethod($field->name);

                    $field->resolveFn = function ($root, $args) use ($class, $method, $field, $context) {
                        $className = $class->getName();
                        $t = new $className();


                        return call_user_func_array([$t, $field->name], [$root, $args, $context]);

                    };
                } catch (\Exception $e) {


                }

            }
        }

        $s = new Schema($schema);
        return $s;
    }

}
