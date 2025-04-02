<?php
namespace App\Http\Routes;

use Illuminate\Contracts\Routing\Registrar;

class PassportRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'passport'
        ], function ($router) {
            // Auth
            $router->post('/auth/register', 'Passport\\AuthController@register');
            $router->post('/auth/login', 'Passport\\AuthController@login');
            $router->get ('/auth/token2Login', 'Passport\\AuthController@token2Login');
            $router->post('/auth/forget', 'Passport\\AuthController@forget');
            $router->post('/auth/getQuickLoginUrl', 'Passport\\AuthController@getQuickLoginUrl');
            $router->post('/auth/loginWithMailLink', 'Passport\\AuthController@loginWithMailLink');
            // Comm
            $router->post('/comm/sendEmailVerify', 'Passport\\CommController@sendEmailVerify');
            $router->post('/comm/pv', 'Passport\\CommController@pv');
            // Api
            $router->post('/api/register', 'Passport\\ApiController@register');
            $router->post('/api/unificationReg', 'Passport\\ApiController@unificationReg');
            $router->post('/api/order', 'Passport\\ApiController@validateCouponAndSaveOrder');
            $router->post('/api/checkEmail', 'Passport\\ApiController@checkEmail');
        });
    }
}
