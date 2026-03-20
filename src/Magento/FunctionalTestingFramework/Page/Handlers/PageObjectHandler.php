<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Page\Handlers;

use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Object_Manager\Object_Handler_Interface;
use Magento\Functional_Testing_Framework\Object_Manager_Factory;
use Magento\Functional_Testing_Framework\Page\Objects\Page_Object;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Validation\Name_Validation_Util;
use Magento\Functional_Testing_Framework\Xml_Parser\Page_Parser;
class Page_Object_Handler implements Object_Handler_Interface
{
    public const PAGE = 'page';
    public const SECTION = 'section';
    public const URL = 'url';
    public const MODULE = 'module';
    public const PARAMETERIZED = 'parameterized';
    public const AREA = 'area';
    public const FILENAME = 'filename';
    public const NAME_BLOCKLIST_ERROR_MSG = "Page names cannot contain non alphanumeric characters.\tPage='%s'";
    /**
     * The singleton instance of this class
     */
    private static ?\Magento\Functional_Testing_Framework\Page\Handlers\Page_Object_Handler $INSTANCE = null;
    /**
     * Array containing all page objects
     *
     * @var PageObject[]
     */
    private array $page_objects = [];
    /**
     * Private constructor
     *
     * @throws XmlException
     */
    private function __construct()
    {
        $object_manager = Object_Manager_Factory::get_object_manager();
        $parser = $object_manager->get(Page_Parser::class);
        $parser_output = $parser->get_data(self::PAGE);
        if (!$parser_output) {
            // No *Page.xml files found so give up
            return;
        }
        $page_name_validator = new Name_Validation_Util();
        foreach ($parser_output as $page_name => $page_data) {
            if (preg_match('/[^a-zA-Z0-9_]/', (string) $page_name)) {
                throw new Xml_Exception(sprintf(self::NAME_BLOCKLIST_ERROR_MSG, $page_name));
            }
            $filename = $page_data[self::FILENAME] ?? null;
            $page_name_validator->validate_affixes($page_name, Name_Validation_Util::PAGE, $filename);
            $area = $page_data[self::AREA] ?? null;
            $url = $page_data[self::URL] ?? null;
            if ($area === 'admin') {
                $url = ltrim((string) $url, '/');
            }
            $module = $page_data[self::MODULE] ?? null;
            $section_names = array_keys($page_data[self::SECTION] ?? []);
            $url_contains_mustaches = str_contains((string) $url, '{{') && str_contains((string) $url, '}}');
            $parameterized = $page_data[self::PARAMETERIZED] ?? $url_contains_mustaches ?? false;
            $filename = $page_data[self::FILENAME] ?? null;
            $deprecated = $page_data[self::OBJ_DEPRECATED] ?? null;
            if ($deprecated !== null) {
                Logging_Util::get_instance()->get_logger(self::class)->deprecation("The page '{$page_name}' is deprecated.", ['fileName' => $filename, 'deprecatedMessage' => $deprecated]);
            }
            $this->page_objects[$page_name] = new Page_Object($page_name, $url, $module, $section_names, $parameterized, $area, $filename, $deprecated);
        }
        $page_name_validator->summarize(Name_Validation_Util::PAGE . ' name');
    }
    /**
     * Singleton method to return PageObjectHandler.
     *
     * @return PageObjectHandler
     * @throws XmlException
     */
    public static function get_instance()
    {
        if (!self::$INSTANCE) {
            self::$INSTANCE = new Page_Object_Handler();
        }
        return self::$INSTANCE;
    }
    /**
     * Return a page object by name
     *
     * @param string $name
     * @return PageObject|null
     */
    public function get_object($name)
    {
        if (array_key_exists($name, $this->page_objects)) {
            return $this->get_all_objects()[$name];
        }
        return null;
    }
    /**
     * Return all page objects
     *
     * @return PageObject[]
     */
    public function get_all_objects()
    {
        return $this->page_objects;
    }
}