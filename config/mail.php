<?php
return [
 'default'=>env('MAIL_MAILER','log'),
 'mailers'=>[
 'smtp'=>['transport'=>'smtp','scheme'=>env('MAIL_SCHEME','smtp'),'host'=>env('MAIL_HOST','smtp.example.com'),'port'=>env('MAIL_PORT',587),'username'=>env('MAIL_USERNAME'),'password'=>env('MAIL_PASSWORD'),'timeout'=>15,'local_domain'=>env('MAIL_EHLO_DOMAIN',parse_url(env('APP_URL','http://localhost'),PHP_URL_HOST))],
 'log'=>['transport'=>'log','channel'=>'mail'],
 'array'=>['transport'=>'array'],
 ],
 'from'=>['address'=>env('MAIL_FROM_ADDRESS','hello@example.com'),'name'=>env('MAIL_FROM_NAME','Avelle')],
 'admin_address'=>env('MAIL_ADMIN_ADDRESS'),
];
