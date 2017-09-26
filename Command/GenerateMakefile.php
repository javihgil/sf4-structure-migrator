<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Jhg\Sf4StructureMigrator\Parser\ComposerJson;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class GenerateMakefile extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input,$output);
        $io->title('Generate new Makefile');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();

        $fs = new Filesystem();

        if ($fs->exists("$projectRoot/Makefile")) {
            $io->error("Makefile already exists in $projectRoot");
            return 1;
        }

        file_put_contents("$projectRoot/Makefile", file_get_contents(__DIR__.'/../Resources/skeleton/Makefile.txt'));
        $io->text("Generated $projectRoot/Makefile");

        $composerJson = ComposerJson::read($projectRoot);

        $composerJson['scripts']['auto-scripts'] = [
            'make cache-warmup',
            'bin/console assets:install --symlink --relative web',
        ];
        $composerJson['scripts']['post-install-cmd'] = ['@auto-scripts'];
        $composerJson['scripts']['post-update-cmd'] = ['@auto-scripts'];

        ComposerJson::write($projectRoot, $composerJson);
        $io->text("Updated scripts in composer.json");

        $io->success('Makefile generated in $projectRoot');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }
}