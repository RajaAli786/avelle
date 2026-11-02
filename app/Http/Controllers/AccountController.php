<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
class AccountController {
 public function form(Request $r,string $mode='login'){if(Auth::check())return redirect(url('/account'));return view('auth',['mode'=>$mode,'checkout'=>$r->boolean('checkout')]);}
 public function registerForm(Request $r){return $this->form($r,'register');}
 public function register(Request $r){
 $r->merge(['email'=>strtolower(trim($r->email??''))]);
 $d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:200|unique:users,email','password'=>['required','confirmed',Password::min(10)],'checkout'=>'nullable|boolean']);
 $user=User::create(collect($d)->only(['name','email','password'])->all());DB::afterCommit(fn()=>\App\Support\StoreMail::welcome($user));Auth::login($user);$r->session()->regenerate();$r->session()->put('auth_version',Auth::user()->auth_version);
 return redirect($r->boolean('checkout')?url('/checkout'):url('/account'));
 }
 public function login(Request $r){
 $r->merge(['email'=>strtolower(trim($r->email??''))]);$d=$r->validate(['email'=>'required|email','password'=>'required|string']);
 if(!Auth::attempt($d+['is_active'=>true])){return back()->withErrors(['email'=>'Email or password is incorrect.'])->withInput($r->only('email','checkout'));}
 $r->session()->regenerate();$r->session()->put('auth_version',Auth::user()->auth_version);return redirect($r->boolean('checkout')?url('/checkout'):url('/account'));
 }
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect(url('/'));}
 public function index(Request $r){if(!Auth::check())return redirect(url('/account/login'));$section=last(explode('/',$r->path()));if(!in_array($section,['orders','addresses','profile','security']))$section='overview';return view('account',['section'=>$section,'user'=>Auth::user(),'orders'=>DB::table('orders')->where('user_id',Auth::id())->latest()->paginate(10),'addresses'=>DB::table('addresses')->where('user_id',Auth::id())->orderByDesc('is_default')->get()]);}
 public function update(Request $r){
 abort_unless(Auth::check(),403);$d=$r->validate(['name'=>'required|string|max:100','phone'=>['nullable','regex:/^[6-9][0-9]{9}$/'],'address'=>'nullable|string|max:500','city'=>'nullable|string|max:100','pincode'=>['nullable','regex:/^[1-9][0-9]{5}$/']]);
 Auth::user()->update($d);return back()->with('success','Your profile has been updated.');
 }
 public function password(Request $r){
 abort_unless(Auth::check(),403);$d=$r->validate(['current_password'=>'required|current_password:web','password'=>['required','confirmed',Password::min(10)]]);
 $user=Auth::user();$user->password=$d['password'];$user->auth_version++;$user->remember_token=\Illuminate\Support\Str::random(60);$user->save();$r->session()->regenerate();$r->session()->put('auth_version',Auth::user()->auth_version);return back()->with('success','Your password has been updated.');
 }
}