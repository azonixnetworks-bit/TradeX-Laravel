<?php
namespace App\Http\Middleware;
use App\Models\StaffMember;use Closure;use Illuminate\Http\Request;use Illuminate\Support\Facades\Auth;use Symfony\Component\HttpFoundation\Response;
class StaffAuthenticate { public function handle(Request $request,Closure $next):Response{$staff=Auth::guard('staff')->user();if(!$staff instanceof StaffMember||$staff->employment_status==='TERMINATED'){Auth::guard('staff')->logout();return redirect()->route('staff.login')->with('error','Please sign in to continue.');}if(!$staff->employment_agreement_accepted_at&&!$request->routeIs('staff.agreement.*'))return redirect()->route('staff.agreement.show');return $next($request);} }
