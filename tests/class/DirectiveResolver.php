<?php

use GraphQL\Error\Error;

class DirectiveResolver
{
    public function hasRole($next, $source, $args, $context)
    {
        if (!in_array($context->payload["role"], $args["role"])) {
            throw new Error("access deny");
        }
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
