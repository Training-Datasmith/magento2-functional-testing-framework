<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataGenerator\Persist;

use Magento\FunctionalTestingFramework\Allure\AllureHelper;
use Magento\FunctionalTestingFramework\DataTransport\AdminFormExecutor;
use Magento\FunctionalTestingFramework\DataTransport\FrontendFormExecutor;
use Magento\FunctionalTestingFramework\DataTransport\WebApiExecutor;
use Magento\FunctionalTestingFramework\DataTransport\WebApiNoAuthExecutor;
use Magento\FunctionalTestingFramework\DataGenerator\Handlers\OperationDefinitionObjectHandler;
use Magento\FunctionalTestingFramework\DataGenerator\Objects\EntityDataObject;
use Magento\FunctionalTestingFramework\DataGenerator\Objects\OperationDefinitionObject;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\DataTransport\Protocol\CurlInterface;

/**
 * Class CurlHandler
 */
class CurlHandler
{
    /**
     * The data definitions used to map the operation.
     *
     * @var OperationDefinitionObject $operationDefinition
     */
    private $operationDefinition;

    /**
     * The request data.
     *
     * @var array
     */
    private $requestData;

    /**
     * If the content type is Json.
     */
    private bool $isJson;

    /**
     * Operation to Curl method mapping.
     */
    private static array $curlMethodMapping = [
            'create' => CurlInterface::POST,
            'delete' => CurlInterface::DELETE,
            'update' => CurlInterface::PUT,
            'get' => CurlInterface::GET,
        ];

    /**
     * ApiSubObject constructor.
     *
     * @param string           $operation
     * @param EntityDataObject $entityObject
     * @param string           $storeCode
     */
    public function __construct(/**
     * Describes the operation for the executor ('create','update','delete')
     */
    private $operation, /**
     * The entity object data being created, updated, or deleted.
     */
    private $entityObject, /**
     * Store code in web api rest url.
     */
    private $storeCode = null)
    {
        $this->operationDefinition = OperationDefinitionObjectHandler::getInstance()->getOperationDefinition(
            $this->operation,
            $this->entityObject->getType()
        );
        $this->isJson = false;
    }

    /**
     * Executes an api request based on parameters given by constructor.
     *
     * @param array $dependentEntities
     * @return array | null
     * @throws TestFrameworkException
     * @throws \Exception
     */
    public function executeRequest($dependentEntities)
    {
        $executor = null;
        $successRegex = null;
        $returnRegex = null;
        $returnIndex = null;

        if ((null !== $dependentEntities) && is_array($dependentEntities)) {
            $entities = array_merge([$this->entityObject], $dependentEntities);
        } else {
            $entities = [$this->entityObject];
        }
        $apiUrl = $this->resolveUrlReference($this->operationDefinition->getApiUrl(), $entities);
        $headers = $this->operationDefinition->getHeaders();
        $authorization = $this->operationDefinition->getAuth();
        $contentType = $this->operationDefinition->getContentType();
        $successRegex = $this->operationDefinition->getSuccessRegex();
        $returnRegex = $this->operationDefinition->getReturnRegex();
        $returnIndex = $this->operationDefinition->getReturnIndex();
        $method = $this->operationDefinition->getApiMethod();
        $this->operationDefinition->logDeprecated();
        AllureHelper::addAttachmentToCurrentStep($apiUrl, 'API Endpoint');
        AllureHelper::addAttachmentToCurrentStep(json_encode($headers, JSON_PRETTY_PRINT), 'Request Headers');

        $operationDataResolver = new OperationDataArrayResolver($dependentEntities);
        $this->requestData = $operationDataResolver->resolveOperationDataArray(
            $this->entityObject,
            $this->operationDefinition->getOperationMetadata(),
            $this->operationDefinition->getOperation(),
            false
        );

        AllureHelper::addAttachmentToCurrentStep(json_encode($this->requestData, JSON_PRETTY_PRINT), 'Request Body');

        if (($contentType === 'application/json') && ($authorization === 'adminOauth')) {
            $this->isJson = true;
            $executor = new WebApiExecutor($this->storeCode);
        } elseif ($authorization === 'adminFormKey') {
            $executor = new AdminFormExecutor($this->operationDefinition->removeUrlBackend());
        } elseif ($authorization === 'customerFormKey') {
            $executor = new FrontendFormExecutor(
                $this->requestData['customer_email'],
                $this->requestData['customer_password']
            );
        } elseif ($authorization === 'anonymous') {
            $this->isJson = true;
            $executor = new WebApiNoAuthExecutor($this->storeCode);
        }

        if (!$executor) {
            throw new TestFrameworkException(
                sprintf(
                    "Invalid content type and/or auth type. content type = %s, auth type = %s\n",
                    $contentType,
                    $authorization
                )
            );
        }

        $executor->write(
            $apiUrl,
            $this->requestData,
            $method ?? self::$curlMethodMapping[$this->operation],
            $headers
        );

        $response = $executor->read($successRegex, $returnRegex, $returnIndex);
        $executor->close();

        AllureHelper::addAttachmentToCurrentStep(
            json_encode(json_decode($response, true), JSON_PRETTY_PRINT+JSON_UNESCAPED_UNICODE+JSON_UNESCAPED_SLASHES),
            'Response Data'
        );

        return $response;
    }

    /**
     * Getter for request data in array.
     *
     * @return array
     */
    public function getRequestDataArray()
    {
        return $this->requestData;
    }

    /**
     * If content type of a request is Json.
     *
     * @return boolean
     */
    public function isContentTypeJson()
    {
        return $this->isJson;
    }

    /**
     * Resolve rul reference from entity objects.
     *
     * @param string $urlIn
     * @return string
     */
    private function resolveUrlReference($urlIn, array $entityObjects)
    {
        $urlOut = $urlIn;
        $matchedParams = [];
        // Find all the params ({}) references
        preg_match_all("/[{](.+?)[}]/", $urlIn, $matchedParams);

        foreach ($matchedParams[0] as $paramKey => $paramValue) {
            $paramEntityParent = "";
            $matchedParent = [];
            $dataItem = $matchedParams[1][$paramKey];
            // Find all the parent property (Type.key) references, assuming there will be only one
            // parent property reference within one param
            preg_match_all("/(.+?)\./", $dataItem, $matchedParent);

            if (!empty($matchedParent[0])) {
                $paramEntityParent = $matchedParent[1][0];
                $dataItem = preg_replace('/^'.$matchedParent[0][0].'/', '', $dataItem);
            }

            foreach ($entityObjects as $entityObject) {
                $param = null;

                if ($paramEntityParent === "" || $entityObject->getType() === $paramEntityParent) {
                    $param = $entityObject->getDataByName(
                        $dataItem,
                        EntityDataObject::CEST_UNIQUE_VALUE
                    );
                }

                if (null !== $param) {
                    $urlOut = str_replace($paramValue, $param, $urlOut);
                    continue;
                }
            }
        }
        return $urlOut;
    }
}
