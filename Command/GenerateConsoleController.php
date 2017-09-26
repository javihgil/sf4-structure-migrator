<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class GenerateConsoleController extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrate console');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            $io->text([
                'This updates the bin/console script',
            ]);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();

        $fs = new Filesystem();

        $fs->mkdir("$projectRoot/bin", 0755);
        file_put_contents("$projectRoot/bin/console", file_get_contents(__DIR__.'/../Resources/skeleton/bin/console.txt'));
        $io->text("Updated bin/console");

        $io->success('Updated console script at bin/console');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }

    protected function showPostHelp(SymfonyStyle $io)
    {
        $this->showUndoHelp($io);
    }
}