<?php

declare(strict_types=1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

use Composer\Autoload\ClassLoader;
use Firebase\JWT\JWT;
use GraphQL\Error\Error;
use PHPUnit\Framework\TestCase;
use R\GraphQL\Schema;

final class SchemaTest extends TestCase
{
    public function testCreate()
    {
        $schema = Schema::Build(file_get_contents(__DIR__ . "/schema.gql"));
        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function testValidation()
    {
        $schema = Schema::Build(file_get_contents(__DIR__ . "/schema.gql"));
        $this->assertInstanceOf(Schema::class, $schema);


        $payload = [
            "sub" => "hello",
            "name" => "Raymond",
            "iat" => time()
        ];

        $jwt = JWT::encode($payload, "abc123");

        $schema->validation($jwt, "abc123", function ($p) use ($payload) {
            $this->assertEquals($p, $payload);
        });
    }

    public function test_executeQuery()
    {
        $loader = new ClassLoader();
        $loader->addPsr4("", __DIR__ . "/class");
        $loader->register(true);

        $schema = Schema::build(file_get_contents(__DIR__ . '/schema.gql'));

        $q = "query{ me }";
        $result = $schema->executeQuery($q);

        $this->assertEquals("Raymond", $result["data"]["me"]);
    }

    public function test_directive_resolver()
    {
        $loader = new ClassLoader();
        $loader->addPsr4("", __DIR__ . "/class");
        $loader->register(true);

        $schema = Schema::build(file_get_contents(__DIR__ . '/schema.gql'), null, new DirectiveResolver());

        $q = "query{ first_name }";
        $result = $schema->executeQuery($q);
        $this->assertEquals("RAYMOND", $result["data"]["first_name"]);

        $q = "query{ last_name }";
        $result = $schema->executeQuery($q);
        $this->assertEquals("chong", $result["data"]["last_name"]);
    }

    public function test_hasRole()
    {
        $loader = new ClassLoader();
        $loader->addPsr4("", __DIR__ . "/class");
        $loader->register(true);
        $directiveResolvers = [
            "hasRole" => function ($next, $source, $args, $app) {
                if (!in_array($app->payload["role"], $args["role"])) {
                    throw new Error("access deny");
                }
                return $next();
            }
        ];


        $context = new stdClass();
        $context->payload = [
            "sub" => "hello",
            "name" => "Raymond",
            "iat" => time(),
            "role" => "User"
        ];
        $schema = Schema::build(file_get_contents(__DIR__ . '/schema.gql'), $context, $directiveResolvers);

        $q = "query{ information }";
        $result = $schema->executeQuery($q);
        $this->assertEquals("hello", $result["data"]["information"]);


        $context = new stdClass();
        $context->payload = [
            "sub" => "hello",
            "name" => "Raymond",
            "iat" => time(),
            "role" => "Other"
        ];
        $schema = Schema::build(file_get_contents(__DIR__ . '/schema.gql'), $context, $directiveResolvers);

        $q = "query{ information }";

        try {
            $result = $schema->executeQuery($q);
        } catch (Exception $e) {
            $this->expectException($e);
        }
    }
}
