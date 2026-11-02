<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnsureAccountIsActive {
 public function handle(Request $request,Closure $next){
 $user=Auth::user();
 if($user&&(!$user->is_active||(int)$request->session()->get('auth_version',0)!==(int)$user->auth_version)){
 Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
 if($request->expectsJson())return response()->json(['message'=>'Your session has ended. Please sign in again.'],401);
 return redirect(url($request->is('admin*')?'/admin':'/account/login'))->withErrors(['email'=>'Your session has ended. Please sign in again.']);
 }
 return $next($request);
 }
}