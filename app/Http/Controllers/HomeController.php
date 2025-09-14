<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index()
    {
        return view('home');
    }

    /**
     * Show the application dashboard (alias for index).
     */
    public function home()
    {
        return $this->index();
    }
}