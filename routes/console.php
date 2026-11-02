<?php
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
Artisan::command('store:admin',function(){
 $email=strtolower(trim($this->ask('Admin email','owner@avelle.local')));
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->error('Enter a valid email.');return 1;}
 $existing=User::where('email',$email)->first();
 if($existing&&$existing->role!=='admin'){$this->error('This email belongs to a customer. Choose a separate admin email.');return 1;}
 $name=$this->ask('Admin name',$existing?->name??'Store owner');
 $password=$this->secret('Choose an admin password (at least 12 characters)');
 if(strlen($password??'')<12){$this->error('Use at least 12 characters.');return 1;}
 if($password!==$this->secret('Confirm password')){$this->error('Passwords do not match.');return 1;}
 $user=$existing??new User;$user->fill(['name'=>$name,'email'=>$email,'password'=>$password]);$user->role='admin';$user->is_active=true;$user->auth_version=($user->auth_version??1)+1;$user->save();
 $this->info('Database admin account ready. Open '.url('/admin'));
});

Artisan::command('store:mail-test {email}',function(){ $email=$this->argument('email');if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->error('Enter a valid recipient email.');return 1;}try{\Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\StoreMessage('Email setup test','Your Avelle email template and mail transport are working.',url('/'),'Open store'));$this->info(config('mail.default')==='log'?'Local preview written to storage/logs/mail.log. No email was sent.':'SMTP server accepted the test email. Check the inbox and spam folder.');return 0;}catch(\Throwable $e){$this->error('Email test failed. Check SMTP host, port, sender and credentials. Error type: '.get_class($e));return 1;}});
