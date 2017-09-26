<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

abstract class AbstractCommand extends Command
{
    protected function getProjectRoot()
    {
        return getcwd();
    }

    protected $confirmed = null;

    protected function configure()
    {
        $this->addOption('skip-help', null, InputOption::VALUE_NONE);
        $this->addOption('environments', null, InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Symfony environments', ['dev', 'test', 'prod']);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function confirm(InputInterface $input, OutputInterface $output)
    {
        if ($this->confirmed !== null) {
            return $this->confirmed;
        }

        $io = new SymfonyStyle($input, $output);

        $question = new ConfirmationQuestion('Continue with this action?',false);

        $this->confirmed = 'y' == $io->askQuestion($question);

        return $this->confirmed;
    }

    protected function checkRequirements(SymfonyStyle $io)
    {
        if (!class_exists('Symfony\Component\Dotenv\Dotenv')) {
            $io->error([
                'DotEnv Symfony component is required. You can either update your SF version to 3.3 or require individual component: ',
                '',
                ' $ composer require symfony/dotenv ^3.3',
            ]);
            throw new \Exception('Check requirements');
        }
    }

    protected function showUndoHelp(SymfonyStyle $io)
    {
        $io->text([
            '',
            'You can easy undo the changes making (warning: all uncommited changes will be lost):',
            '',
            '   $ git add . ; git reset --hard HEAD',
            '',
        ]);
    }

    protected function runComposerDumpAutoload(SymfonyStyle $io, OutputInterface $output)
    {
        $io->text('Running composer dump-autoload');

        $process = new Process('composer dump-autoload');
        $process->mustRun(function($type, $message) use ($io) {
//            if ($type == Process::ERR) {
//                $io->error($message);
//            } else {
//                $io->text($message);
//            }
        });
    }

    protected function showPostHelp(SymfonyStyle $io)
    {
        $this->showUndoHelp($io);
    }
}