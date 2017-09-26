<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class MoveRouting extends AbstractCommand
{
    protected function configure()
    {
        parent::configure();

        $this->addOption('no-remove', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Move routing to etc');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();
        $environments = $input->getOption('environments');

        $fs = new Filesystem();

        if (!$fs->exists("$projectRoot/app/config/routing.yml")) {
            $io->error("Can not find app/config/routing.yml in $projectRoot");
            return 1;
        }

        $fs->mkdir("$projectRoot/config/routing", 0755);

        $otherRouteFiles = [];

        if ($fs->exists("$projectRoot/app/config/routing.yml")) {
            // search for included resources
            $routingYaml = Yaml::parse(file_get_contents("$projectRoot/app/config/routing.yml"));
            foreach ($routingYaml as $route => $routeConfig) {
                if (isset($routeConfig['resource']) && $fs->exists("$projectRoot/app/config/{$routeConfig['resource']}")) {
                    $otherRouteFiles[] = $routeConfig['resource'];

                    $routingYaml[$route]['resource'] = "routing/{$routeConfig['resource']}";
                }
            }
            file_put_contents("$projectRoot/app/config/routing.yml", Yaml::dump($routingYaml, 4));

            if (!$input->getOption('no-remove')) {
                $fs->rename("$projectRoot/app/config/routing.yml", "$projectRoot/config/routing.yaml");
                $io->text(" - moving routing.yml configuration to 'config/routing.yaml'");
            } else {
                // do not copy to prevent memory changes
                file_put_contents("$projectRoot/app/config/routing.yml", Yaml::dump($routingYaml, 4));
                $io->text(" - coping routing.yml configuration to 'config/routing.yaml'");
            }
        }

        foreach ($environments as $env) {
            if ($fs->exists("$projectRoot/app/config/routing_$env.yml")) {
                // search for included resources
                $routingYaml = Yaml::parse(file_get_contents("$projectRoot/app/config/routing_$env.yml"));
                foreach ($routingYaml as $route => $routeConfig) {
                    if (isset($routeConfig['resource']) && $fs->exists("$projectRoot/app/config/{$routeConfig['resource']}")) {
                        if ($routeConfig['resource'] == 'routing.yml') {
                            unset($routingYaml[$route]);
                        } else {
                            $otherRouteFiles[] = "$projectRoot/app/config/{$routeConfig['resource']}";
                        }
                    }
                }
                file_put_contents("$projectRoot/app/config/routing_$env.yml", Yaml::dump($routingYaml, 4));
                clearstatcache();

                $fs->mkdir("$projectRoot/config/routing/$env");
                if (!$input->getOption('no-remove')) {
                    $fs->rename("$projectRoot/app/config/routing_$env.yml", "$projectRoot/config/routing/$env/routing.yaml");
                    $io->text(" - moving routing_$env.yml configuration to 'config/routing/$env/routing.yaml'");
                } else {
                    $fs->copy("$projectRoot/app/config/routing_$env.yml", "$projectRoot/config/routing/$env/routing.yaml");
                    $io->text(" - coping routing_$env.yml configuration to 'config/routing/$env/routing.yaml'");
                }
            }
        }

        foreach ($otherRouteFiles as $otherRouteFile) {
            $fs->mkdir("$projectRoot/config/routing");
            if (!$input->getOption('no-remove')) {
                $fs->rename("$projectRoot/app/config/$otherRouteFile", "$projectRoot/config/routing/$otherRouteFile");
                $io->text(" - moving $otherRouteFile configuration to 'config/routing/$otherRouteFile'");
            } else {
                $fs->copy("$projectRoot/app/config/$otherRouteFile", "$projectRoot/config/routing/$otherRouteFile");
                $io->text(" - coping $otherRouteFile configuration to 'config/routing/$otherRouteFile'");
            }
        }


        // resource: '%kernel.root_dir%/config/routing_dev.yml'
        if ($fs->exists("$projectRoot/app/config/config_dev.yml")) {
            $configDevYaml = Yaml::parse(file_get_contents("$projectRoot/app/config/config_dev.yml"));

            if (isset($configDevYaml['framework']['router']['resource']) && $configDevYaml['framework']['router']['resource'] = '%kernel.root_dir%/config/routing_dev.yml') {
                unset($configDevYaml['framework']['router']['resource']);
                file_put_contents("$projectRoot/app/config/config_dev.yml", Yaml::dump($configDevYaml, 4));

                $io->text('Remove framework.router.resource in app/config/config_dev.yml');
            }
        }
        if ($fs->exists("$projectRoot/app/config/config.yml")) {
            $configDevYaml = Yaml::parse(file_get_contents("$projectRoot/app/config/config.yml"));

            if (isset($configDevYaml['framework']['router']['resource']) && $configDevYaml['framework']['router']['resource'] = '%kernel.root_dir%/config/routing.yml') {
                unset($configDevYaml['framework']['router']['resource']);
                file_put_contents("$projectRoot/app/config/config.yml", Yaml::dump($configDevYaml, 4));

                $io->text('Remove framework.router.resource in app/config/config.yml');
            }
        }

        $io->success('Moved configuration to etc directory');

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
            '  $ run bin/sf4-structure migrate:kernel',
            '',
            '2. updating manually your kernel',
            '',
            '   use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;',
            '   use Symfony\Component\Finder\Finder;',
            '   use Symfony\Component\Routing\RouteCollectionBuilder;',
            '',
            '   class Kernel extends BaseKernel',
            '   {',
            '      use MicroKernelTrait;',
            '',
            '      /**',
            '       * To be updated with SF4',
            '       */',
            '      protected function configureRoutes(RouteCollectionBuilder $routes): void',
            '      {',
            '          $confDir = dirname(__DIR__).\'/config\';',
            '                   ',
            '          // Load Package files',
            '          /** @var SplFileInfo $file */',
            '          foreach ((new Finder())->in($confDir.\'/routing\')->depth(0)->name(\'/\'.self::CONFIG_EXTS_REGEX.\'/\')->files() as $file) {',
            '              $routes->import($file->getRealPath());',
            '          }',
            '                     ',
            '          // Load Environment Package files',
            '          if (is_dir($confDir.\'/routing/\'.$this->getEnvironment())) {',
            '              /** @var SplFileInfo $file */',
            '              foreach ((new Finder())->in($confDir.\'/routing/\'.$this->getEnvironment())->name(\'/\'.self::CONFIG_EXTS_REGEX.\'/\')->files() as $file) {',
            '                  $routes->import($file->getRealPath());',
            '              }',
            '          }',
            '                    ',
            '         // Load Container file',
            '         /** @var SplFileInfo $file */',
            '         foreach ((new Finder())->in($confDir)->depth(0)->name(\'/^routing\'.self::CONFIG_EXTS_REGEX.\'/\')->files() as $file) {',
            '             $routes->import($file->getRealPath());',
            '         }',
            '      }',
            '    }',
        ]);

        $this->showUndoHelp($io);
    }
}