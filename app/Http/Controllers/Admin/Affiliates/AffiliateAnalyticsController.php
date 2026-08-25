<?php

namespace App\Http\Controllers\Admin\Affiliates;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AffiliateAnalyticsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.affiliates.index');
    }
}
