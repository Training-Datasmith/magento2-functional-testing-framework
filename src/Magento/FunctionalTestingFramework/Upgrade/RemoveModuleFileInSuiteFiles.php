<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Upgrade;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Util\Script\ScriptUtil;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Class RemoveModuleFileInSuiteFiles
 * @package Magento\FunctionalTestingFramework\Upgrade
 */
class RemoveModuleFileInSuiteFiles implements UpgradeInterface
{
    /**
     * OutputInterface
     */
    private ?\Symfony\Component\Console\Output\OutputInterface $output = null;

    /**
     * Console output style
     */
    private ?\Symfony\Component\Console\Style\SymfonyStyle $ioStyle = null;

    /**
     * Indicate if notice is print
     */
    private bool $printNotice = false;

    /**
     * Number of test being updated
     */
    private int $testsUpdated = 0;

    /**
     * Indicate if a match and replace has happened
     */
    private bool $replaced = false;

    /**
     * Scan all suite xml files, remove <module file="".../> node, and print update message
     *
     * @throws TestFrameworkException
     */
    public function execute(InputInterface $input, OutputInterface $output): string
    {
        $scriptUtil = new ScriptUtil();
        $this->setOutputStyle($input, $output);
        $this->output = $output;
        $testPaths[] = $input->getArgument('path');
        if (empty($testPaths[0])) {
            $testPaths = $scriptUtil->getAllModulePaths();
        }

        // Get module suite xml files
        $xmlFiles = $scriptUtil->getModuleXmlFilesByScope($testPaths, 'Suite');
        $this->processXmlFiles($xmlFiles);

        return ("Removed module file reference in {$this->testsUpdated} suite file(s).");
    }

    /**
     * Process on list of xml files
     *
     * @param Finder $xmlFiles
     */
    private function processXmlFiles($xmlFiles): void
    {
        foreach ($xmlFiles as $file) {
            $contents = $file->getContents();
            $filePath = $file->getRealPath();
            $this->replaced = false;
            $contents = $this->removeModuleFileAttributeInSuite($contents, $filePath);
            if ($this->replaced) {
                file_put_contents($filePath, $contents);
                $this->testsUpdated++;
            }
        }
    }

    /**
     * Remove module file attribute in Suite xml file
     *
     * @param string $contents
     * @param string $file
     * @return string|string[]|null
     */
    private function removeModuleFileAttributeInSuite($contents, $file): ?string
    {
        $pattern = '/<module[^\<\>]+file[\s]*=[\s]*"(?<file>[^"\<\>]*)"[^\>\<]*>/';
        return preg_replace_callback(
            $pattern,
            function ($matches) use ($file): string|array {
                if (!$this->printNotice) {
                    $this->ioStyle->note(
                        '`file` is not a valid attribute for <module> in Suite XML schema.' . PHP_EOL
                        . 'The `file`references in the following xml files are commented out. '
                        . 'Consider using <test> instead.'
                    );
                    $this->printNotice = true;
                }
                $this->output->writeln(
                    PHP_EOL
                    . '"' . trim((string) $matches[0]) . '"' . PHP_EOL
                    . 'is commented out from file: ' . $file . PHP_EOL
                );
                $result = str_replace('<module', '<!--module', $matches[0]);
                $result = str_replace('>', '--> <!-- Please replace with <test name="" -->', $result);
                $this->replaced = true;
                return $result;
            },
            $contents
        );
    }

    /**
     * Set Symfony Style for output
     */
    private function setOutputStyle(InputInterface $input, OutputInterface $output): void
    {
        // For output style
        if (null === $this->ioStyle) {
            $this->ioStyle = new SymfonyStyle($input, $output);
        }
    }
}
