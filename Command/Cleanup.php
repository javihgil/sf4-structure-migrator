<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Jhg\Sf4StructureMigrator\Parser\ComposerJson;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class Cleanup extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input,$output);
        $io->title('Cleanup old files');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();
        $fs = new Filesystem();

        $composerJson = ComposerJson::read($projectRoot);
        // cleanup symfony-scripts
        unset($composerJson['scripts']['symfony-scripts']);
        $io->text(' - cleanup composer.json symfony-scripts script');

        // remove parameter builder
        unset($composerJson['require']['incenteev/composer-parameter-handler']);
        unset($composerJson['require-dev']['incenteev/composer-parameter-handler']);
        unset($composerJson['extra']['incenteev-parameters']);
        $io->text(' - cleanup composer.json incenteev/composer-parameter-handler dependency');

        // remove distribution bundle
        unset($composerJson['require']['sensio/distribution-bundle']);
        unset($composerJson['require-dev']['sensio/distribution-bundle']);
        $io->text(' - cleanup composer.json sensio/distribution-bundle dependency');

        ComposerJson::write($projectRoot, $composerJson);

        if ($fs->exists("$projectRoot/app/autoload.php")) {
            $fs->remove("$projectRoot/app/autoload.php");
            $io->text(" - remove $projectRoot/app/autoload.php");
        }

        if ($fs->exists("$projectRoot/web/config.php")) {
            $fs->remove("$projectRoot/web/config.php");
            $io->text(" - remove $projectRoot/web/config.php");
        }

        if ($fs->exists("$projectRoot/var/bootstrap.php.cache")) {
            $fs->remove("$projectRoot/var/bootstrap.php.cache");
            $io->text(" - remove $projectRoot/var/bootstrap.php.cache");
        }

        if ($fs->exists("$projectRoot/var/SymfonyRequirements.php")) {
            $fs->remove("$projectRoot/var/SymfonyRequirements.php");
            $io->text(" - remove $projectRoot/var/SymfonyRequirements.php");
        }

        if ($fs->exists("$projectRoot/app/config") && count(glob("$projectRoot/app/config/*")) === 0) {
            $fs->remove("$projectRoot/app/config");
            $io->text(" - remove $projectRoot/app/config");
        }

        if ($fs->exists("$projectRoot/app/Resources") && count(glob("$projectRoot/app/Resources/*")) === 0) {
            $fs->remove("$projectRoot/app/Resources");
            $io->text(" - remove $projectRoot/app/Resources");
        }

        if ($fs->exists("$projectRoot/app") && count(glob("$projectRoot/app/*")) === 0) {
            $fs->remove("$projectRoot/app");
            $io->text(" - remove $projectRoot/app");
        }

        $gitignore = file_get_contents("$projectRoot/.gitignore");
        $gitignore = str_ireplace(["/app/config/parameters.yml\n", "!var/SymfonyRequirements.php\n"] , '', $gitignore);
        file_put_contents("$projectRoot/.gitignore", $gitignore);


        $io->success('Your project is now clean');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }
}