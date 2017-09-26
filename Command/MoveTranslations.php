<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class MoveTranslations extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Move translations to root dir');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();

        $fs = new Filesystem();

        $fs->rename("$projectRoot/app/Resources/translations", "$projectRoot/translations");

        if ($fs->exists("$projectRoot/app/config/config.yml")) {
            $configYaml = Yaml::parse(file_get_contents("$projectRoot/app/config/config.yml"));

            if (!isset($configYaml['framework']['translator']['paths'])) {
                $configYaml['framework']['translator']['paths'] = [];
            }

            $configYaml['framework']['translator']['paths'][] = "%kernel.root_dir%/../translations";

            file_put_contents("$projectRoot/app/config/config.yml", Yaml::dump($configYaml, 4));

            $io->text('Add translations directory to twig.paths in app/config/config.yml');
        }

        $io->success('Moved translations to project root');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }
}