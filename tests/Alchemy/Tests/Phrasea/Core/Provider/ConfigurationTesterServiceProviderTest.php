<?php

namespace Alchemy\Tests\Phrasea\Core\Provider;

/**
 * @covers Alchemy\Phrasea\Core\Provider\ConfigurationTesterServiceProvider
 */
class ConfigurationTesterServiceProviderTest extends ServiceProviderTestCase
{
    public function provideServiceDescription()
    {
        return [
            ['Alchemy\Phrasea\Core\Provider\ConfigurationTesterServiceProvider', 'phraseanet.configuration-tester', 'Alchemy\\Phrasea\\Setup\\ConfigurationTester'],
        ];
    }
}
