<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class MoveTemplates extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Move templates to root dir');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();

        $fs = new Filesystem();

        $fs->rename("$projectRoot/app/Resources/views", "$projectRoot/templates");

        if ($fs->exists("$projectRoot/app/config/config.yml")) {
            $configYaml = Yaml::parse(file_get_contents("$projectRoot/app/config/config.yml"));

            if (!isset($configYaml['twig']['paths'])) {
                $configYaml['twig']['paths'] = [];
            }

            $configYaml['twig']['paths'][] = "%kernel.root_dir%/../templates";

            file_put_contents("$projectRoot/app/config/config.yml", Yaml::dump($configYaml, 4));

            $io->text('Add templates directory to twig.paths in app/config/config.yml');
        }

        // twig:
        // paths: []

        $io->success('Moved templates to project root');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }
}