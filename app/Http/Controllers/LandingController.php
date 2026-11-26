<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('landing', [
            'content' => SiteContent::landing(),
        ]);
    }
}
