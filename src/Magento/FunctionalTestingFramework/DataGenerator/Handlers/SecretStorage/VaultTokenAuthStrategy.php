<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Handlers\Secret_Storage;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Vault\Authentication_Strategies\Abstract_Authentication_Strategy;
use Vault\Response_Models\Auth;
/**
 * Class VaultTokenAuthStrategy
 */
class Vault_Token_Auth_Strategy extends Abstract_Authentication_Strategy
{
    /**
     * VaultTokenAuthStrategy constructor
     *
     * @param string $token
     */
    public function __construct(protected $token)
    {
    }
    /**
     * Returns auth for further interactions with Vault
     *
     * @return Auth
     * @throws TestFrameworkException
     */
    public function authenticate(): ?Auth
    {
        try {
            return new Auth(['clientToken' => $this->token]);
        } catch (\Exception) {
            throw new Test_Framework_Exception('Cannot authenticate Vault token.');
        }
    }
}