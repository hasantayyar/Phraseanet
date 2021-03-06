<?php

namespace Alchemy\Tests\Phrasea\Command;

use Alchemy\Phrasea\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandTest extends \PhraseanetTestCase
{
    /**
     * @var Command
     */
    protected $object;

    public function setUp()
    {
        parent::setUp();
        $this->object = new AbstractCommandTester('name');
    }

    /**
     * @covers Alchemy\Phrasea\Command\Command::getFormattedDuration
     */
    public function testGetFormattedDuration()
    {
        $this->assertRegExp('/50 \w+/', $this->object->getFormattedDuration(50));
        $this->assertRegExp('/1(\.|,)2 \w+/', $this->object->getFormattedDuration(70));
    }
}

class AbstractCommandTester extends Command
{
    protected function doExecute(InputInterface $input, OutputInterface $output)
    {

    }
}
