<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'admin/candidate-login',
        'admin/slots/candidate-login',
        'admin/book/execute',
        'admin/slots/book/execute',
        'admin/release-lock',
        'admin/slots/release-lock',
        'admin/release-all-locks',
        'admin/slots/release-all-locks',
        'admin/card-payment/*',
        'admin/slots/card-payment/*',
        'admin/check',
        'admin/slots/check',
    ];
}
