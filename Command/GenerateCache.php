<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Jhg\Sf4StructureMigrator\Parser\ComposerJson;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class GenerateCache extends AbstractCommand
{
    protected function configure()
    {
        parent::configure();

        $this->addOption('no-remove', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input,$output);
        $io->title('Generate new kernel cache file');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();

        $fs = new Filesystem();

        if ($fs->exists("$projectRoot/src/Cache.php")) {
            $io->error("Cache already exists in src/Cache.php");
            return 1;
        }

        $fs->mkdir("$projectRoot/src", 0755);
        file_put_contents("$projectRoot/src/Cache.php", file_get_contents(__DIR__.'/../Resources/skeleton/src/Cache.php.txt'));
        $io->text("Generated src/Cache.php");

        // remove old AppKernel class
        if (!$input->getOption('no-remove')) {
            if ($fs->exists("$projectRoot/app/AppCache.php")) {
                $fs->remove("$projectRoot/app/AppCache.php");
                $io->text("Removed app/AppCache.php");
            }
        }

        // update composer autoload
        $composerJson = ComposerJson::read($projectRoot);
        if (isset($composerJson['autoload']['classmap'])) {
            if(($key = array_search('app/AppCache.php', $composerJson['autoload']['classmap'])) !== false) {
                $io->text('Updated composer.json removing app/AppCache.php from autoload classmap');
                unset($composerJson['autoload']['classmap'][$key]);
            }

            if (empty($composerJson['autoload']['classmap'])) {
                $io->text('Updated composer.json removing empty classmap array');
                unset($composerJson['autoload']['classmap']);
            } else {
                $composerJson['autoload']['classmap'] = array_values($composerJson['autoload']['classmap']);
            }
        }
        ComposerJson::write($projectRoot, $composerJson);

        $this->runComposerDumpAutoload($io, $output);

        $io->success('Cache generated in src/Cache.php');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }
}