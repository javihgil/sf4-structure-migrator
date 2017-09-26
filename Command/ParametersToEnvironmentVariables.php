<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

class ParametersToEnvironmentVariables extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input,$output);
        $io->title('Parameters to environment variables');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();

        $fs = new Filesystem();

        if (!$fs->exists("$projectRoot/app/config/parameters.yml.dist")) {
            $io->error("Can not find app/config/parameters.yml.dist in $projectRoot");
            return 1;
        }

        $environmentVariablesNames = [];

        foreach (['parameters.yml' => '.env', 'parameters.yml.dist' => '.env.dist'] as $paramFile => $envFile) {
            if ($fs->exists("$projectRoot/app/config/$paramFile")) {
                $parametersYml = Yaml::parse(file_get_contents("$projectRoot/app/config/$paramFile"));

                $environmentVariables = '';

                if ($envFile = '.env.dist') {
                    $environmentVariables .= '# This file is a "template" of which env vars needs to be defined in your configuration or in an .env file'. "\n";
                    $environmentVariables .= '# Set variables here that may be different on each deployment target of the app, e.g. development, staging, production.'. "\n";
                    $environmentVariables .= '# https://symfony.com/doc/current/best_practices/configuration.html#infrastructure-related-configuration'. "\n";
                    $environmentVariables .= "\n";
                }

                $environmentVariables .= '###> symfony/framework-bundle ###'. "\n";
                $environmentVariables .= 'APP_ENV=dev'. "\n";
                $environmentVariables .= 'APP_DEBUG=1'. "\n";
                $environmentVariables .= 'APP_SECRET='.(isset($parametersYml['parameters']['secret']) ? $parametersYml['parameters']['secret'] : 'ThisTokenIsNotSoSecretChangeIt'). "\n";
                $environmentVariables .= '###< symfony/framework-bundle ###'. "\n";

                $environmentVariablesNames["%secret%"] = '%env(APP_SECRET)%';

                foreach ($parametersYml['parameters'] as $param => $value) {
                    if (in_array($param, ['secret'])) {
                        continue;
                    }

                    $environmentVariablesNames["%$param%"] = '%env('.strtoupper($param).')%';
                    $environmentVariables .= "\n".strtoupper($param).'='.$value;
                }

                $io->text("Dumping $paramFile to $envFile file");
                file_put_contents("$projectRoot/$envFile", $environmentVariables);

                $fs->remove("$projectRoot/app/config/$paramFile");
            }
        }

        if (!$fs->exists("$projectRoot/.env") && $fs->exists("$projectRoot/.env.dist")) {
            $fs->copy("$projectRoot/.env.dist", "$projectRoot/.env");
        }

        $finder = new Finder();
        /** @var SplFileInfo $file */
        $dirs = [];

        if (is_dir("$projectRoot/app/config")) {
            $dirs[] = "$projectRoot/app/config";
        }

        if (is_dir("$projectRoot/config")) {
            $dirs[] = "$projectRoot/config";
        }

        foreach ($finder->files()->in($dirs)->name('*.y*ml') as $file) {
            $io->text("Replacing old '%param_name%' with new '%env(PARAM_NAME)%' syntax in $file");
            $fileContent = file_get_contents($file->getRealPath());
            $fileContent = str_ireplace(array_keys($environmentVariablesNames), array_values($environmentVariablesNames), $fileContent);
            file_put_contents($file->getRealPath(), $fileContent);
        }

        $gitignore = file_get_contents("$projectRoot/.gitignore");
        if (!substr($gitignore, -1) == "\n") {
            $gitignore .= "\n";
        }
        $gitignore .= '.env';
        file_put_contents("$projectRoot/.gitignore", $gitignore);

        $io->success('Dumped parameters to environment variables');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }

    protected function showPostHelp(SymfonyStyle $io)
    {
        $io->text([
            'Now you have to update your main controller to use .env file',
            '',
            '   // The check is to ensure we don\'t use .env in production',
            '   if (!getenv(\'APP_ENV\')) {',
            '       (new Dotenv())->load(__DIR__.\'/../.env\');',
            '   }',
            '',
            '   $kernel = new Kernel(getenv(\'APP_ENV\'), getenv(\'APP_DEBUG\'));',
            '',
            'It\'s recommended to add .env file to .gitignore to prevent upload to repository',
        ]);

        $this->showUndoHelp($io);
    }
}