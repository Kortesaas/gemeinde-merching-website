<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Temporary start page proving the stack works. Replaced by the real homepage
 * in a later phase.
 */
class PlaceholderController extends Controller
{
    public function __invoke(): View
    {
        return view('public.placeholder');
    }
}
