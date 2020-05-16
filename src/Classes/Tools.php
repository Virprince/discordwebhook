<?php

namespace Bolt\Extension\Virprince\DiscordWebHook\Classes;


class Tools 
{

    public static function getterName(string $name)
    {
        return 'get'.implode('', array_map('ucfirst', explode('_', $name)));
    }
    
    
}
