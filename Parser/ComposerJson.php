<?php

namespace Jhg\Sf4StructureMigrator\Parser;

class ComposerJson
{
    public static function read($basePath)
    {
        return json_decode(file_get_contents("$basePath/composer.json"), true);
    }

    public static function write($basePath, array $data)
    {
        file_put_contents("$basePath/composer.json", json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }
}