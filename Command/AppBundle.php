<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Jhg\Sf4StructureMigrator\Parser\ComposerJson;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class AppBundle extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrate AppBundle');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            $io->text([
                'This will made some changes in your folder structure and in some code files: ',
                ' - move src/AppBundle/* to src/*',
                ' - replace namespace AppBundle\ to App\\ in all your PHP files at src/',
                ' - replace use AppBundle\\ to App\\ in your PHP files at src/',
                ' - replace php, yaml and twig @AppBundle to @App at src/, templates, and etc, and also in old app/config and app/Resources/views',
                ' - replace php, yaml and twig AppBundle: to App: at src/, templates, and etc, and also in old app/config and app/Resources/views',
                ' - update composer autoload',
            ]);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();
        $environments = $input->getOption('environments');

        $fs = new Filesystem();
        $finder = new Finder();

        if ($finder->directories()->depth(1)->in("$projectRoot/src")->count() != 1) {
            $io->error("AppBundle migrator can only work with single AppBundle directory in $projectRoot/src");

            return 1;
        }

        // MOVE src/AppBundle/* to src/

        /** @var SplFileInfo $file */
        foreach ($finder->depth(1)->in("$projectRoot/src/AppBundle") as $file) {
            $origin = $file->getRealPath();
            $dest = str_ireplace('AppBundle/', '', $file->getRealPath());
            $fs->rename($origin, $dest);
            $io->text("Moved '$origin' to '$dest'");
        }

        // replace namespaces and use namespace AppBundle\ to App\
        foreach ($finder->files()->in(["$projectRoot/src"])->name('*.php') as $file) {
            $fileContent = file_get_contents($file->getRealPath());
            $fileContent = str_ireplace('namespace AppBundle\\', 'namespace App\\', $fileContent);
            $fileContent = str_ireplace('use AppBundle\\', 'use App\\', $fileContent);
            file_put_contents($file->getRealPath(), $fileContent);
        }

        $dirs = [];
        // src/, templates, and etc, and also in old app/config and app/Resources/views
        if ($fs->exists("$projectRoot/src")) {
            $dirs[] = "$projectRoot/src";
        }
        if ($fs->exists("$projectRoot/templates")) {
            $dirs[] = "$projectRoot/templates";
        }
        if ($fs->exists("$projectRoot/config")) {
            $dirs[] = "$projectRoot/config";
        }
        if ($fs->exists("$projectRoot/app")) {
            $dirs[] = "$projectRoot/app";
        }

        foreach ($finder->files()->in($dirs)->name('*.yml')->name('*.yaml')->name('*.twig')->name('*.php') as $file) {
            // $io->text($file->getRealPath());
            $fileContent = file_get_contents($file->getRealPath());
            $fileContent = str_ireplace('@AppBundle', '@App', $fileContent);
            $fileContent = str_ireplace('AppBundle:', 'App:', $fileContent);
            $fileContent = str_ireplace('AppBundle\\', 'App\\', $fileContent);
            file_put_contents($file->getRealPath(), $fileContent);
        }

        // update composer autoload
        $composerJson = ComposerJson::read($projectRoot);
        if (isset($composerJson['autoload']['psr-4']['AppBundle\\'])) {
            unset($composerJson['autoload']['psr-4']['AppBundle\\']);
        }

        $composerJson['autoload']['psr-4']['App\\'] = 'src/';
        ComposerJson::write($projectRoot, $composerJson);

        $fs->remove("$projectRoot/src/AppBundle");

        $io->success('Migrated AppBundle to App');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }

    protected function showPostHelp(SymfonyStyle $io)
    {
        // $io->text([]);

        $this->showUndoHelp($io);
    }
}