![PHP Composer](https://github.com/mathsgod/r-graphql/workflows/PHP%20Composer/badge.svg)

# r-graphql

```php

try {
    $schema = Schema::Build(file_get_contents(__DIR__ . "/schema.gql"), $this->app);
    
} catch (Exception $e) {
    return ["error" => [
        "message" => $e->getMessage()
    ]];
}

$this->request->getBody()->getContents();
$input = json_decode($input, true);
$query = $input['query'];
$variableValues = $input['variables'];

$schema->debug=true;//debug mode

$result = $schema->executeQuery($query, $variableValues);

$this->write(json_encode($result));

```

### Scalar function added


### JWT
```php
try {
    $schema = Schema::Build(file_get_contents(__DIR__ . "/schema.gql"), $this->app);
    $schema->validation($jwt, $key, function ($payload)  {
        print_r($payload);
    });
} catch (Exception $e) {
    return ["error" => [
        "message" => $e->getMessage()
    ]];
}
```

