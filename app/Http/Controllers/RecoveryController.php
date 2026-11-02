<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
class RecoveryController {
 public function forgot(){return view('market.recovery',['mode'=>'forgot']);}
 public function form(Request $r,string $token){return view('market.recovery',['mode'=>'reset','token'=>$token,'email'=>$r->query('email','')]);}
 public function send(Request $r){$r->merge(['email'=>strtolower(trim($r->email??''))]);$d=$r->validate(['email'=>'required|email|max:200']);try{Password::sendResetLink($d+['is_active'=>true]);}catch(\Throwable $e){\Illuminate\Support\Facades\Log::warning('Password reset email failed',['type'=>get_class($e)]);return back()->withErrors(['email'=>'Email service is temporarily unavailable. Please try again later or contact support.']);}$message=config('mail.default')==='log'?'Local email preview mode: if an active account matches, its reset email is saved in the local mail log.':'If an active account matches, a password-reset link will be emailed. Please allow a few minutes.';return back()->with('success',$message);}
 public function reset(Request $r){$r->merge(['email'=>strtolower(trim($r->email??''))]);$d=$r->validate(['token'=>'required|string|max:200','email'=>'required|email|max:200','password'=>['required','confirmed',PasswordRule::min(12)]]);$status=DB::transaction(fn()=>Password::reset($d+['is_active'=>true],function($user,$password){$user->password=$password;$user->auth_version++;$user->remember_token=Str::random(60);$user->save();}));if($status!==Password::PASSWORD_RESET)return back()->withErrors(['email'=>'This reset link is invalid or expired. Request a new link.'])->withInput($r->only('email'));return redirect(url('/account/login'))->with('success','Your password has been reset. Sign in with your new password.');}
}
