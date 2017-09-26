<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class MoveConfiguration extends AbstractCommand
{
    protected function configure()
    {
        parent::configure();

        $this->addOption('no-remove', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input,$output);
        $io->title('Move configuration to config');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            $io->text([
                'This command moves configuration structure from app/config to config/packages.',
                '',
                ' - creates one config file in config/packages for each block in config.yml',
                ' - creates one config file in config/packages/<env> for each block in config_<env>.yml',
                ' - moves standard app/config/security.yml to config/packages/security.yaml',
                ' - moves standard app/config/services.yml to config/packages/container.yaml',
            ]);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();
        $environments = $input->getOption('environments');

        $fs = new Filesystem();

        if (!$fs->exists("$projectRoot/app/config/config.yml")) {
            $io->error("Can not find app/config/config.yml in $projectRoot");
            return 1;
        }

        $configYmlData = Yaml::parse(file_get_contents("$projectRoot/app/config/config.yml"));

        $this->moveConfigEnvironment($io, $projectRoot, $input->getOption('no-remove'), null);

        if (isset($configYmlData['imports']) && array_filter($configYmlData['imports'], function ($value, $key) {
                return isset($value['resource']) && $value['resource'] == 'security.yml';
            }, ARRAY_FILTER_USE_BOTH) && file_exists("$projectRoot/app/config/security.yml")) {
            $fs->rename("$projectRoot/app/config/security.yml", "$projectRoot/config/package/security.yaml");
            $io->text(" - moving security.yml configuration to 'config/package/security.yaml'");
        }

        if (isset($configYmlData['imports']) && array_filter($configYmlData['imports'], function ($value, $key) {
                return isset($value['resource']) && $value['resource'] == 'services.yml';
            }, ARRAY_FILTER_USE_BOTH) && file_exists("$projectRoot/app/config/services.yml")) {
            $fs->rename("$projectRoot/app/config/services.yml", "$projectRoot/config/container.yaml");
            $io->text(" - moving services.yml configuration to 'config/package/container.yaml'");
        }

        // update container.yaml with parameters
        if (!empty($configYmlData['parameters'])) {
            $containerYaml = Yaml::parse(file_get_contents("$projectRoot/config/container.yaml"));

            if (isset($containerYaml['parameters'])) {
                $containerYaml['parameters'] = array_merge($containerYaml['parameters'], $configYmlData['parameters']);
            } else {
                $containerYaml['parameters'] = $configYmlData['parameters'];
            }

            file_put_contents("$projectRoot/config/container.yaml", Yaml::dump($containerYaml, 4));
        }

        foreach ($environments as $environment) {
            $io->text('');
            $this->moveConfigEnvironment($io, $projectRoot, $input->getOption('no-remove'), $environment);
        }


        $io->success('Moved configuration to config directory');

        if (!$input->getOption('skip-help')) {
            $this->showPostHelp($io);
        }
    }

    protected function showPostHelp(SymfonyStyle $io)
    {
        $io->text([
            'Now you have to update your Kernel to comply with new structure with options:',
            '',
            '1. running kernel migration command',
            '',
            '  $ bin/sf4-structure migrate:kernel',
            '',
            '2. updating manually your kernel',
            '',
            '   use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;',
            '   use Symfony\Component\DependencyInjection\ContainerBuilder;',
            '   use Symfony\Component\Config\Loader\LoaderInterface;',
            '',
            '   class Kernel extends BaseKernel',
            '   {',
            '      use MicroKernelTrait;',
            '',
            '      /**',
            '      /**',
            '       * To be updated with SF4',
            '       */',
            '      protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void',
            '      {',
            '          $confDir = dirname(__DIR__).\'/config\';',
            '           // Load Package files',
            '          /** @var SplFileInfo $file */',
            '          foreach ((new Finder())->in($confDir.\'/packages\')->depth(0)->name(\'/\'.self::CONFIG_EXTS_REGEX.\'/\')->files() as $file) {',
            '              $loader->load($file->getRealPath(), \'glob\');',
            '          }',
            '           // Load Environment Package files',
            '          if (is_dir($confDir.\'/packages/\'.$this->getEnvironment())) {',
            '              /** @var SplFileInfo $file */',
            '              foreach ((new Finder())->in($confDir.\'/packages/\'.$this->getEnvironment())->name(\'/\'.self::CONFIG_EXTS_REGEX.\'/\')->files() as $file) {',
            '                  $loader->load($file->getRealPath(), \'glob\');',
            '              }',
            '          }',
            '           // Load Container file',
            '          /** @var SplFileInfo $file */',
            '          foreach ((new Finder())->in($confDir)->depth(0)->name(\'/^container\'.self::CONFIG_EXTS_REGEX.\'/\')->files() as $file) {',
            '              $loader->load($file->getRealPath(), \'glob\');',
            '          }',
            '      }',
            '      ',
            '      ',
            '    }',
        ]);

        $this->showUndoHelp($io);
    }

    protected function moveConfigEnvironment(SymfonyStyle $io, $projectRoot, $noRemove, $environment = null)
    {
        $configFilePath = $environment ? "$projectRoot/app/config/config_$environment.yml" : "$projectRoot/app/config/config.yml";
        $configPackagesRelative = $environment ? "config/packages/$environment" : "config/packages";
        $configPackagesPath = "$projectRoot/$configPackagesRelative";

        $io->text($environment ? "Processing '$environment' configuration from 'app/config/config_$environment.yml'" : "Processing global configuration from 'app/config/config.yml'");

        $fs = new Filesystem();
        $fs->mkdir("$configPackagesPath", 0755);

        $configYmlData = Yaml::parse(file_get_contents($configFilePath));

        foreach ($configYmlData as $block => $config) {
            if (in_array($block, ['imports', 'parameters'])) {
                $io->text(" - skiping $block");
                continue;
            }

            $io->text(" - moving $block configuration to '$configPackagesRelative/$block.yaml'");

            file_put_contents("$configPackagesPath/$block.yaml", Yaml::dump([$block=>$config], 4));
        }

        if (!$noRemove) {
            $fs->remove($configFilePath);
        }
    }
}