<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Jhg\Sf4StructureMigrator\Parser\ComposerJson;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateComposerJson extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input,$output);
        $io->title('Require PHP 7.0.8');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();

        $composerJson = ComposerJson::read($projectRoot);

        $io->text('Set require php ^7.0.8');
        $composerJson['require']['php'] = '^7.0.8';

        // this was removed from symfony/skeleton template
        // $io->text('Set config platform php to 7.0.8');
        // $composerJson['config']['platform']['php'] = '7.0.8';

        $io->text('Set config preferred-install * to dist');
        $composerJson['config']['preferred-install']['*'] = 'dist';

        $io->text('Set config sort-packates true');
        $composerJson['config']['sort-packages'] = true;

        ComposerJson::write($projectRoot, $composerJson);

        $io->success('Updated config in composer.json');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }

    protected function showPostHelp(SymfonyStyle $io)
    {
        $io->text([
            'Now you have configured php 7.0.8 in your composer.json, you should update:',
            '',
            '   $ composer update',
        ]);

        $this->showUndoHelp($io);
    }
}