<?php

namespace Jhg\Sf4StructureMigrator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class GenerateBundlesFile extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generate bundles file');

        if ($input->isInteractive()) {
            $this->checkRequirements($io);

            $io->text([
                'This generates the config/bundles.php file with every bundles configured in AppKernel',
                '',
            ]);

            if (!$this->confirm($input, $output)) {
                return 0;
            }
        }

        $projectRoot = $this->getProjectRoot();
        $environments = $input->getOption('environments');

        $fs = new Filesystem();

        if (!$fs->exists("$projectRoot/app/AppKernel.php")) {
            $io->error("Can not find app/AppKernel.php in $projectRoot");
            return 1;
        }

        if ($fs->exists("$projectRoot/config/bundles.php")) {
            $io->error("Bundles file already exists in config/bundles.php");
            return 1;
        }

        $this->runComposerDumpAutoload($io, $output);

        $bundles = [];

        foreach ($environments as $environment) {
            $kernel = new \AppKernel($environment, false);
            $registeredBundlesForEnvironment = $kernel->registerBundles();

            foreach ($registeredBundlesForEnvironment as $registeredBundle) {
                $bundles[get_class($registeredBundle)][$environment] = true;
            }
        }

        $bundlesPhpCode = "<?php\n";
        $bundlesPhpCode .= "\n";
        $bundlesPhpCode .= "return [\n";

        foreach ($bundles as $bundle => $bundleEnvironments) {
            if (sizeof($environments) == sizeof($bundleEnvironments)) {
                $bundlesPhpCode .= "    '$bundle' => ['all' => true],\n";
            } else {

                $bundleEnvironmentsCode = [];
                foreach (array_keys($bundleEnvironments) as $bundleEnvironment) {
                    $bundleEnvironmentsCode[] = "'$bundleEnvironment' => true";
                }

                $bundlesPhpCode .= "    '$bundle' => [".implode(', ', $bundleEnvironmentsCode)."],\n";
            }
        }

        $bundlesPhpCode .= "];\n";

        $io->text(explode("\n", $bundlesPhpCode));

        $fs->mkdir("$projectRoot/config", 0755);
        file_put_contents("$projectRoot/config/bundles.php", $bundlesPhpCode);

        $io->success('Generated bundle.php file');

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
            '',
            '   class Kernel extends BaseKernel',
            '   {',
            '       use MicroKernelTrait;',
            '       ',
            '       public function registerBundles(): iterable',
            '       {',
            '           $contents = require dirname(__DIR__).\'/config/bundles.php\';',
            '           foreach ($contents as $class => $envs) {',
            '               if (isset($envs[\'all\']) || isset($envs[$this->getEnvironment()])) {',
            '                   yield new $class();',
            '               }',
            '           }',
            '       }',
            '   }',
        ]);

        $this->showUndoHelp($io);
    }
}