<?php

/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Util\Path;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
class Url_Formatter implements Formatter_Interface
{
    /**
     * Return formatted url path from input string.
     *
     *
     * @throws TestFrameworkException
     */
    public static function format(string $url, bool $with_trailing_separator = true): string
    {
        $sanitized_url = rtrim($url, '/');
        // Remove all characters except letters, digits and $-_.+!*'(),{}|\\^~[]`<>#%";/?:@&=
        $sanitized_url = filter_var($sanitized_url, FILTER_SANITIZE_URL);
        if (false === $sanitized_url) {
            throw new Test_Framework_Exception("Invalid url: {$url}\n");
        }
        // Validate URL according to http://www.faqs.org/rfcs/rfc2396
        $valid_url = filter_var($sanitized_url, FILTER_VALIDATE_URL);
        if (false !== $valid_url) {
            return $with_trailing_separator ? $valid_url . '/' : $valid_url;
        }
        // Validation might be failed due to missing URL scheme or host, attempt to build them and re-validate
        $valid_url = filter_var(self::build_url($sanitized_url), FILTER_VALIDATE_URL);
        if (false !== $valid_url) {
            return $with_trailing_separator ? $valid_url . '/' : $valid_url;
        }
        throw new Test_Framework_Exception("Invalid url: {$url}\n");
    }
    /**
     * Try to build missing url scheme and host.
     *
     *
     */
    private static function build_url(string $url): string
    {
        $url_parts = parse_url($url);
        if (!isset($url_parts['scheme'])) {
            $url_parts['scheme'] = 'http';
        }
        if (!isset($url_parts['host'])) {
            $url_parts['host'] = rtrim($url_parts['path'], '/');
            $url_parts['host'] = str_replace('//', '/', $url_parts['host']);
            unset($url_parts['path']);
        }
        if (isset($url_parts['path'])) {
            $url_parts['path'] = rtrim($url_parts['path'], '/');
        }
        return str_replace('///', '//', self::merge($url_parts));
    }
    /**
     * Returns url from $parts given, used with parse_url output for convenience.
     * This only exists because of deprecation of http_build_url, which does the exact same thing as the code below.
     *
     *
     */
    private static function merge(array $parts): string
    {
        $get = fn($key) => $parts[$key] ?? '';
        $pass = $get('pass');
        $user = $get('user');
        $userinfo = $pass !== '' ? "{$user}:{$pass}" : $user;
        $port = $get('port');
        $scheme = $get('scheme');
        $query = $get('query');
        $fragment = $get('fragment');
        $authority = ($userinfo !== '' ? "{$userinfo}@" : '') . $get('host') . ($port ? ":{$port}" : '');
        return str_replace(['%scheme', '%authority', '%path', '%query', '%fragment'], [strlen($scheme) ? "{$scheme}:" : '', strlen($authority) ? "//{$authority}" : '', $get('path'), strlen($query) ? "?{$query}" : '', strlen($fragment) ? "#{$fragment}" : ''], '%scheme%authority%path%query%fragment');
    }
}