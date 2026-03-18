<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Upgrade;

use Magento\FunctionalTestingFramework\Util\Script\ScriptUtil;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class RenameMetadataFiles
 * @package Magento\FunctionalTestingFramework\Upgrade
 */
class RenameMetadataFiles implements UpgradeInterface
{
    /**
     * Upgrades all test xml files
     */
    public function execute(InputInterface $input, OutputInterface $output): string
    {
        $scriptUtil = new ScriptUtil();
        $testPaths[] = $input->getArgument('path');
        if (empty($testPaths[0])) {
            $testPaths = $scriptUtil->getAllModulePaths();
        }

        foreach ($testPaths as $testsPath) {
            $finder = new Finder();
            $finder->files()->in($testsPath)->name('*-meta.xml');

            foreach ($finder->files() as $file) {
                $oldFileName = $file->getFileName();
                $newFileName = $this->convertFileName($oldFileName);
                $oldPath = $file->getPathname();
                $newPath = $file->getPath() . '/' . $newFileName;
                print('Renaming ' . $oldPath . ' => ' . $newPath . "\n");
                rename($oldPath, $newPath);
            }
        }

        return 'Finished renaming -meta.xml files.';
    }

    /**
     * Convert filenames like:
     *     user_role-meta.xml => UserRoleMeta.xml
     *     store-meta.xml => StoreMeta.xml
     */
    private function convertFileName(string $oldFileName): string
    {
        $stripEnding = preg_replace('/-meta.xml/', '', $oldFileName);
        $hyphenToUnderscore = str_replace('-', '_', $stripEnding);
        $parts = explode('_', $hyphenToUnderscore);
        $ucParts = [];
        foreach ($parts as $part) {
            $ucParts[] = ucfirst($part);
        }
        $recombine = join('', $ucParts);
        return $recombine . 'Meta.xml';
    }
}
