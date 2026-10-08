<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\SearchEngineIndexing;
use Illuminate\Http\Response;

/**
 * Dynamic robots.txt: everything is disallowed unless indexing is explicitly
 * enabled in production. (There is deliberately no static public/robots.txt.)
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $body = SearchEngineIndexing::allowed()
            ? "User-agent: *\nDisallow:\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
