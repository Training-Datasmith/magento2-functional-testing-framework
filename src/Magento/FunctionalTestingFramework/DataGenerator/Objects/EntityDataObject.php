<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataGenerator\Objects;

use Magento\FunctionalTestingFramework\Config\MftfApplicationConfig;
use Magento\FunctionalTestingFramework\DataGenerator\Util\GenerationDataReferenceResolver;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Exceptions\TestReferenceException;
use Magento\FunctionalTestingFramework\Util\Logger\LoggingUtil;

/**
 * Class EntityDataObject
 */
class EntityDataObject
{
    public const NO_UNIQUE_PROCESS = 0;
    public const SUITE_UNIQUE_VALUE = 1;
    public const CEST_UNIQUE_VALUE = 2;
    public const SUITE_UNIQUE_NOTATION = 3;
    public const CEST_UNIQUE_NOTATION = 4;
    public const SUITE_UNIQUE_FUNCTION = 'msqs';
    public const CEST_UNIQUE_FUNCTION = 'msq';

    /**
     * Array of data name and its uniqueness attribute value.
     *
     * @var string[]
     */
    private $uniquenessData = [];

    /**
     * Constructor
     *
     * @param string      $name
     * @param string      $type
     * @param string[]    $data
     * @param string[]    $linkedEntities
     * @param string[]    $uniquenessData
     * @param string[]    $vars
     * @param string      $parentEntity
     * @param string      $filename
     * @param string|null $deprecated
     */
    public function __construct(
        /**
         * Name of the entity
         */
        private $name,
        /**
         * Type of the entity
         */
        private $type,
        /**
         * An array of Data Name to Data Value
         */
        private $data,
        /**
         * An array of required entity name to corresponding type
         */
        private $linkedEntities,
        $uniquenessData,
        /**
         * An array of variable mappings for static data
         */
        private $vars = [],
        /**
         * String of parent Entity
         */
        private $parentEntity = null,
        /**
         * String of filename
         */
        private $filename = null,
        /**
         * Deprecated message.
         */
        private $deprecated = null
    ) {
        if ($uniquenessData) {
            $this->uniquenessData = $uniquenessData;
        }
    }

    /**
     * Getter for the deprecated attr of the section.
     *
     * @return string
     */
    public function getDeprecated()
    {
        return $this->deprecated;
    }

    /**
     * Get the name of this entity data object
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Getter for the Entity Filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Get the type of this entity data object
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Getter for data array (field name to value)
     *
     * @return \string[]
     */
    public function getAllData()
    {
        return $this->data;
    }

    /**
     * Get a piece of data by name and the desired uniqueness format.
     *
     * @param string  $name
     * @param integer $uniquenessFormat
     * @return string|null
     * @throws TestFrameworkException
     */
    public function getDataByName($name, $uniquenessFormat)
    {
        if (MftfApplicationConfig::getConfig()->verboseEnabled()) {
            LoggingUtil::getInstance()->getLogger(EntityDataObject::class)
                ->debug('Fetching data field from entity', ['entity' => $this->getName(), 'field' => $name]);
        }

        if (!$this->isValidUniqueDataFormat($uniquenessFormat)) {
            $exceptionMessage = sprintf("Invalid unique data format value: %s \n", $uniquenessFormat);
            LoggingUtil::getInstance()->getLogger(EntityDataObject::class)
                ->error($exceptionMessage, ['entity' => $this->getName(), 'field' => $name]);
            throw new TestFrameworkException($exceptionMessage);
        }

        if ($this->data === null) {
            return null;
        }
        return $this->resolveDataReferences($name, $uniquenessFormat);
    }

    /**
     * Resolves data references in entities while generating static test files.
     *
     * @param string  $name
     * @param integer $uniquenessFormat
     * @return string|null
     * @throws TestFrameworkException
     * @throws TestReferenceException
     */
    private function resolveDataReferences($name, $uniquenessFormat)
    {
        $name_lower = strtolower($name);
        $dataReferenceResolver = new GenerationDataReferenceResolver();
        if (array_key_exists($name_lower, $this->data)) {
            if (is_array($this->data[$name_lower])) {
                return $this->data[$name_lower];
            }
            $uniquenessData = $this->getUniquenessDataByName($name_lower) ?? $dataReferenceResolver->getDataUniqueness(
                $this->data[$name_lower],
                $this->name . '.' . $name
            );
            if ($uniquenessData !== null) {
                $this->uniquenessData[$name] = $uniquenessData;
            }
            $this->data[$name_lower] = $dataReferenceResolver->getDataReference(
                $this->data[$name_lower],
                $this->name . '.' . $name
            );
            if (null === $uniquenessData || $uniquenessFormat === self::NO_UNIQUE_PROCESS) {
                return $this->data[$name_lower];
            }
            return $this->formatUniqueData($name_lower, $uniquenessData, $uniquenessFormat);
        }
        if (array_key_exists($name, $this->data)) {
            if (is_array($this->data[$name])) {
                return $this->data[$name];
            }
            $this->data[$name] = $dataReferenceResolver->getDataReference(
                $this->data[$name],
                $this->name . '.' . $name
            );
            // Data returned by the API may be camelCase so we need to check for the original $name also.
            return $this->data[$name];
        }
        return null;
    }

    /**
     * Getter for data parent
     *
     * @return \string
     */
    public function getParentName()
    {
        return $this->parentEntity;
    }

    /**
     * Formats and returns data based on given uniqueDataFormat and prefix/suffix.
     *
     * @param string $name
     * @param string $uniqueData
     * @param string $uniqueDataFormat
     * @throws TestFrameworkException
     */
    private function formatUniqueData($name, $uniqueData, $uniqueDataFormat): ?string
    {
        switch ($uniqueDataFormat) {
            case self::SUITE_UNIQUE_VALUE:
                $this->checkUniquenessFunctionExists(self::SUITE_UNIQUE_FUNCTION, $uniqueDataFormat);
                if ($uniqueData === 'prefix') {
                    return msqs($this->getName()) . $this->data[$name];
                }
                // $uniData == 'suffix'
                return $this->data[$name] . msqs($this->getName());
            case self::CEST_UNIQUE_VALUE:
                $this->checkUniquenessFunctionExists(self::CEST_UNIQUE_FUNCTION, $uniqueDataFormat);
                if ($uniqueData === 'prefix') {
                    return msq($this->getName()) . $this->data[$name];
                }
                // $uniqueData == 'suffix'
                return $this->data[$name] . msq($this->getName());
            case self::SUITE_UNIQUE_NOTATION:
                if ($uniqueData === 'prefix') {
                    return self::SUITE_UNIQUE_FUNCTION . '("' . $this->getName() . '")' . $this->data[$name];
                }
                // $uniqueData == 'suffix'
                return $this->data[$name] . self::SUITE_UNIQUE_FUNCTION . '("' . $this->getName() . '")';
            case self::CEST_UNIQUE_NOTATION:
                if ($uniqueData === 'prefix') {
                    return self::CEST_UNIQUE_FUNCTION . '("' . $this->getName() . '")' . $this->data[$name];
                }
                // $uniqueData == 'suffix'
                return $this->data[$name] . self::CEST_UNIQUE_FUNCTION . '("' . $this->getName() . '")';
            default:
                break;
        }
        return null;
    }

    /**
     * Performs a check that the given uniqueness function exists, throws an exception if it doesn't.
     *
     * @throws TestFrameworkException
     */
    private function checkUniquenessFunctionExists(string $function, string $uniqueDataFormat): void
    {
        if (!function_exists($function)) {
            $exceptionMessage = sprintf(
                'Unique data format value: %s can only be used when running cests.\n',
                $uniqueDataFormat
            );

            throw new TestFrameworkException($exceptionMessage);
        }
    }

    /**
     * Function which returns a reference to another entity (e.g. a var with entity="category" field="id" returns as
     * category->id)
     *
     * @param string $key
     */
    public function getVarReference($key): ?string
    {
        if (array_key_exists($key, $this->vars)) {
            return $this->vars[$key];
        }

        return null;
    }

    /**
     * This function takes an array of entityTypes indexed by name and a string that represents the type of interest.
     * The function returns an array of entityNames relevant to the specified type.
     *
     * @param string $type
     */
    public function getLinkedEntitiesOfType($type): array
    {
        $groupedArray = [];

        foreach ($this->linkedEntities as $entityName => $entityType) {
            if ($entityType === $type) {
                $groupedArray[] = $entityName;
            }
        }

        return $groupedArray;
    }

    /**
     * Get array of entity names specified as associated to this entity.
     *
     * @return \string[]
     */
    public function getLinkedEntities()
    {
        return $this->linkedEntities;
    }

    /**
     * Get array of var based fields defined in this entity.
     *
     * @return \string[]
     */
    public function getVarReferences()
    {
        return $this->vars;
    }

    /**
     * This function retrieves uniqueness data by its name.
     *
     * @param string $dataName
     */
    public function getUniquenessDataByName($dataName): ?string
    {
        $name = strtolower($dataName);

        if (array_key_exists($name, $this->uniquenessData)) {
            return $this->uniquenessData[$name];
        }

        return null;
    }

    /**
     * This function retrieves uniqueness data.
     *
     * @return array|null
     */
    public function getUniquenessData()
    {
        return $this->uniquenessData;
    }

    /**
     * Validate if input value is a valid unique data format.
     *
     * @param integer $uniDataFormat
     */
    private function isValidUniqueDataFormat($uniDataFormat): bool
    {
        return in_array(
            $uniDataFormat,
            [
                self::NO_UNIQUE_PROCESS,
                self::SUITE_UNIQUE_VALUE,
                self::CEST_UNIQUE_VALUE,
                self::SUITE_UNIQUE_NOTATION,
                self::CEST_UNIQUE_NOTATION,
            ],
            true
        );
    }
}
