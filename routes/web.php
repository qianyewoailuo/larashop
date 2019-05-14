<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/','PagesController@root')->name('root');

Auth::routes();

Route::group(['middleware'=>'auth'],function(){
    // 邮箱验证提示路由
    Route::get('/email_verify_notice','PagesController@emailVerifyNotice')->name('email_verify_notice');
    // 邮箱验证
    Route::get('/email_verification/verify', 'EmailVerificationController@verify')->name('email_verification.verify');
    // 邮件发送
    Route::get('/email_verification/send', 'EmailVerificationController@send')->name('email_verification.send');

    // CheckEmailVerified 中间件测试开始
    Route::group(['middleware'=>'email_verified'],function(){
        Route::get('/test',function(){
            return '邮箱已验证';
        });
    });
    // 结束

});