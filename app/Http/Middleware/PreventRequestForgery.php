<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

/**
 * CSRF protection (token + Sec-Fetch-Site origin check) for the "web" group.
 *
 * The XSRF-TOKEN cookie is disabled: it only exists for JavaScript HTTP
 * clients, which this server-rendered application does not use. Forms carry
 * the token in a hidden field (@csrf).
 */
class PreventRequestForgery extends Middleware
{
    /**
     * @var bool
     */
    protected $addHttpCookie = false;
}
