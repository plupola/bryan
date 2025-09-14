<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/myhuddle'; // Changed from '/home' to '/myhuddle'

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        // Optional: You can also define a pattern for route model binding
        // Route::pattern('id', '[0-9]+');
        
        parent::boot();
    }

    // ... rest of the class
}