<?php

namespace Bolt\Extension\Virprince\DiscordWebHook\Tests;

use Bolt\Tests\BoltUnitTest;
use Bolt\Extension\Virprince\DiscordWebHook\Extension;

/**
 * Ensure that the DiscordWebHook extension loads correctly.
 *
 */
class ExtensionTest extends BoltUnitTest
{
    public function testExtensionRegister()
    {
        $app = $this->getApp();
        $extension = new Extension($app);
        $app['extensions']->register( $extension );
        $name = $extension->getName();
        $this->assertSame($name, 'DiscordWebHook');
        $this->assertSame($extension, $app["extensions.$name"]);
    }
}
