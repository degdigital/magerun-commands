<?php

namespace DEG\Magento\Command\Database;

use N98\Magento\Command\Database\DumpCommand;
use N98\Util\OperatingSystem;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends DumpCommand
{
    protected function configure()
    {

        $this
            ->setName('db:export')
            ->addArgument('filename', InputArgument::OPTIONAL, 'Dump filename')
            ->addOption('add-time', 't', InputOption::VALUE_OPTIONAL, 'Adds time to filename (only if filename was not provided)')
            ->addOption('compression', 'c', InputOption::VALUE_REQUIRED, 'Compress the dump file using one of the supported algorithms')
            ->addOption('tables', 'l', InputOption::VALUE_REQUIRED, 'Tables to include in the export')
            ->addOption('where-limit', 'w', InputOption::VALUE_OPTIONAL, 'Limit the number of rows dumped')
            ->addOption('only-command', null, InputOption::VALUE_NONE, 'Print only mysqldump command. Do not execute')
            ->addOption('print-only-filename', null, InputOption::VALUE_NONE, 'Execute and prints no output except the dump filename')
            ->addOption('no-single-transaction', null, InputOption::VALUE_NONE, 'Do not use single-transaction (not recommended, this is blocking)')
            ->addOption('stdout', null, InputOption::VALUE_NONE, 'Dump to stdout')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not prompt if all options are defined')
            ->addOption('human-readable', null, InputOption::VALUE_NONE, 'Use a single insert with column names per row. Useful to track database differences. Use db:import --optimize for speeding up the import.')
            ->addOption('add-routines', null, InputOption::VALUE_NONE, 'Include stored routines in dump (procedures & functions)')
            ->addOption('data-only', null, InputOption::VALUE_NONE, 'Dump only the data. Do not dump the table information.')
            ->addOption('skip-add-locks', null, InputOption::VALUE_NONE, 'Do not add table locks to the dump.')
            ->setDescription('Dumps a partial database with mysqldump cli client according to informations from local.xml');

        $help = <<<HELP
Exports a specified set of tables from a magento database with `mysqldump`.
You must have installed the MySQL client tools.

On debian systems run `apt-get install mysql-client` to do that.

The command reads app/etc/local.xml to find the correct settings.
If you like to skip data of some tables you can use the --strip option.
The strip option creates only the structure of the defined tables and
forces `mysqldump` to skip the data.

Exports table(s) from your database with the option to limit the number of rows. This is useful creating SQL files used for automated tests.

Separate each table to strip by a space.
You can use wildcards like * and ? in the table names to strip multiple tables.
In addition you can specify pre-defined table groups, that start with an @
Example: "dataflow_batch_export unimportant_module_* @log

   $ n98-magerun.phar db:export --tables="@customers"

Available Table Groups:

* Includes all the table groups used by the db:export command.
* @catalog The catalog tables

- If you like to prepend a timestamp to the dump name the --add-time option can be used.

HELP;
        $this->setHelp($help);
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectDbSettings($output);

        if (!$input->getOption('stdout') && !$input->getOption('only-command')
            && !$input->getOption('print-only-filename')
        ) {
            $this->writeSection($output, 'Dump MySQL Database');
        }
        $compressor = $this->getCompressor(null);
        $fileName   = $this->getFileName($input, $output, $compressor);

        $dumpOptions = '';
        if (!$input->getOption('no-single-transaction')) {
            $dumpOptions = '--single-transaction --quick ';
        }

        if ($input->getOption('human-readable')) {
            $dumpOptions .= '--complete-insert --skip-extended-insert ';
        }

        if ($input->getOption('add-routines')) {
            $dumpOptions .= '--routines ';
        }

        if ($input->getOption('data-only')) {
            $dumpOptions .= '--no-create-info ';
        }

        if ($input->getOption('skip-add-locks')) {
            $dumpOptions .= '--skip-add-locks ';
        }

        if ($input->getOption('where-limit')) {
            $dumpOptions .= '--where="true LIMIT '. $input->getOption('where-limit') .'" ';
        }

        $includeTables = false;
        if ($input->getOption('tables')) {
            $definitions = $this->getTableDefinitions();
            $includeTables = $this->getHelper('database')->resolveTables(explode(' ', $input->getOption('tables')), $this->getTableDefinitions());
            if (!$input->getOption('stdout') && !$input->getOption('only-command')
                && !$input->getOption('print-only-filename')
            ) {
                $output->writeln('<comment>Exporting data and structure for: <info>' . implode(' ', $includeTables)
                    . '</info></comment>'
                );
            }
        }

        if ($includeTables) {
            $execs = array();

            $include = '';
            foreach ($includeTables as $includeTable) {
                $include .= $includeTable . ' ';
            }

            $exec = 'mysqldump ' . $dumpOptions . $this->getHelper('database')->getMysqlClientToolConnectionString() . ' ' . $include;
            $exec .= $this->postDumpPipeCommands();
            $exec = $compressor->getCompressingCommand($exec);
            if (!$input->getOption('stdout')) {
                $exec .= ' >> ' . escapeshellarg($fileName);
            }
            $execs[] = $exec;

            $this->runExecs($execs, $fileName, $input, $output);
        } else {
            $output->writeln('<comment>No tables for export. <info>'
                . '</info></comment>'
            );
        }
    }

    /**
     * @param array $execs
     * @param string $fileName
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function runExecs(array $execs, $fileName, InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('only-command') && !$input->getOption('print-only-filename')) {
            foreach ($execs as $exec) {
                $output->writeln($exec);
            }
        } else {
            if (!$input->getOption('stdout') && !$input->getOption('only-command')
                && !$input->getOption('print-only-filename')
            ) {
                $output->writeln('<comment>Start dumping database <info>' . $this->dbSettings['dbname']
                    . '</info> to file <info>' . $fileName . '</info>'
                );
            }

            foreach ($execs as $exec) {
                $commandOutput = '';
                if ($input->getOption('stdout')) {
                    passthru($exec, $returnValue);
                } else {
                    exec($exec, $commandOutput, $returnValue);
                }
                if ($returnValue > 0) {
                    $output->writeln('<error>' . implode(PHP_EOL, $commandOutput) . '</error>');
                    $output->writeln('<error>Return Code: ' . $returnValue . '. ABORTED.</error>');

                    return;
                }
            }

            if (!$input->getOption('stdout') && !$input->getOption('print-only-filename')) {
                $output->writeln('<info>Finished</info>');
            }
        }

        if ($input->getOption('print-only-filename')) {
            $output->writeln($fileName);
        }
    }

    /**
     * Generate help for table definitions
     *
     * @return string
     * @throws \Exception
     */
    public function getTableDefinitionHelp()
    {
        $messages = array();
        $this->commandConfig = $this->getCommandConfig();
        $messages[] = '';
        $messages[] = '<comment>Tables option</comment>';
        $messages[] = ' Separate each table to tables by a space.';
        $messages[] = ' You can use wildcards like * and ? in the table names to export multiple tables.';
        $messages[] = ' In addition you can specify pre-defined table groups, that start with an @';
        $messages[] = ' Example: "dataflow_batch_export unimportant_module_* @log';
        $messages[] = '';
        $messages[] = '<comment>Available Table Groups</comment>';

        $definitions = $this->getTableDefinitions();
        foreach ($definitions as $id => $definition) {
            $description = isset($definition['description']) ? $definition['description'] : '';
            /** @TODO:
             * Column-Wise formatting of the options, see InputDefinition::asText for code to pad by the max length,
             * but I do not like to copy and paste ..
             */
            $messages[] = ' <info>@' . $id . '</info> ' . $description;
        }

        return implode(PHP_EOL, $messages);
    }

}
