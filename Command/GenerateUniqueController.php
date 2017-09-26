<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class GenerateUniqueController extends AbstractCommand
{
    protected function configure()
    {
        parent::configure();

        $this->addOption('no-remove', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrate controller');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            $io->text([
                'This generates the public/index.php unique controller file',
            ]);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();

        $fs = new Filesystem();
        $fs->rename("$projectRoot/web", "$projectRoot/public");

        $environments = $input->getOption('environments');

        $fs = new Filesystem();

        if ($fs->exists("$projectRoot/public/index.php")) {
            $io->error("Unique controller file already exists in public/index.php");
            return 1;
        }

        $fs->mkdir("$projectRoot/public", 0755);
        file_put_contents("$projectRoot/public/index.php", file_get_contents(__DIR__.'/../Resources/skeleton/public/index.php.txt'));
        $io->text("Generated public/index.php");

        if (!$input->getOption('no-remove')) {
            if ($fs->exists("$projectRoot/public/app.php")) {
                $fs->remove("$projectRoot/public/app.php");
                $io->text("Removing old web/app.php at public/app.php");
            }

            foreach ($environments as $environment) {
                if ($fs->exists("$projectRoot/public/app_$environment.php")) {
                    $fs->remove("$projectRoot/public/app_$environment.php");
                    $io->text("Removing old web/app_$environment.php at public/app_$environment.php");
                }
            }
        }

        $io->success('Generated unique controller at public/index.php');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }

    protected function showPostHelp(SymfonyStyle $io)
    {
        $io->text([
            'Now you must update your server config to use public/index.php instead of web/app*.php as Symfony front controller.',
            '',
            'You should check your old web/app.php and other web/app*.php if you previously had any non default configuration to update in public/index.php',
        ]);

        $this->showUndoHelp($io);
    }
}