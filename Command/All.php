<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class All extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Migrate all');

        $commands = [
            'migrate:php71',
            'migrate:app-bundle',
            'migrate:parameters',
            'migrate:routing',
            'migrate:templates',
            'migrate:translations',
            'migrate:config',
            'migrate:bundles',
            'migrate:kernel',
            'migrate:kernel-cache',
            'migrate:controller',
            'migrate:console',
            'migrate:makefile',
            'migrate:cleanup',
        ];

        if ($input->isInteractive()) {
            $io->text([
                'This command runs all SF4 structure migration commands (in this order):',
                '',
            ]);

            $io->text($commands);

            $io->text([
                '',
                'After run, you can easy undo the changes making (warning: all uncommited changes will be lost):',
                '',
                '   $ git add . ; git reset --hard HEAD',
            ]);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        foreach ($commands as $command) {
            $returnCode = $this->runCommand($command, $input, $output);

            if ($returnCode) {
                return $returnCode;
            }
        }

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }

    protected function showPostHelp(SymfonyStyle $io)
    {
        $io->text([
            'Now all your application has been updated to SF4 project structure.',
            '',
            'You should update your composer.lock:',
            '',
            '    $ composer update',
            '',
            'Check that it works, and if so commit your changes.',
        ]);

        $this->showUndoHelp($io);
    }

    protected function runCommand($commandName, InputInterface $input, OutputInterface $output)
    {
        $environmentsArgs = '';
        $process = new Process(__DIR__."/../sf4-structure $commandName --skip-help $environmentsArgs");
        $process->mustRun(function ($type, $message) use ($output) {
            $output->writeln($message);
        });

//        $command = $this->getApplication()->find($commandName);
//
//        $args = [
//            'command' => $commandName,
//            '--skip-help' => true,
//            '--environments' => $input->getOption('environments'),
//        ];
//
//        $subInput = new ArrayInput($args);
//        $subInput->setInteractive(false);
//
//        return $command->run($subInput, $output);
    }
}