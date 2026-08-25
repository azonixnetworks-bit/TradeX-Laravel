<?php

namespace App\Http\Controllers\Admin\Affiliates;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;

class AffiliateAnalyticsController extends Controller
{
    public function index(): RedirectResponse
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser
            && $admin->hasPermission('affiliates.view'),
            403
        );

        return redirect()->route('admin.affiliates.index');
    }
}
