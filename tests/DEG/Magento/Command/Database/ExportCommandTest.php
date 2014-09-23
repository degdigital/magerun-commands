<?php
namespace DEG\Magento\Command\Database;

use Symfony\Component\Console\Tester\CommandTester;
use DEG\Magento\Command\PHPUnit\TestCase;

class ExportCommandTest extends TestCase
{
    public function testExecute()
    {
        $command = $this->getCommand();

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array(
                'command'        => $command->getName(),
                '--add-time'     => true,
                '--only-command' => true,
                '--force'        => true,
                '--tables'  => 'catalog_product_entity'
            )
        );

        $this->assertRegExp('/mysqldump/', $commandTester->getDisplay());
        $this->assertRegExp('/\.sql/', $commandTester->getDisplay());
        $this->assertContains("catalog_product_entity", $commandTester->getDisplay());
    }

    /**
     * @return \Symfony\Component\Console\Command\Command
     */
    protected function getCommand()
    {
        $application = $this->getApplication();
        $application->add(new ExportCommand());
        $command = $this->getApplication()->find('db:export');

        return $command;
    }

}