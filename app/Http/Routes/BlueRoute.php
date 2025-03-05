<?php
namespace App\Http\Routes;

use Illuminate\Contracts\Routing\Registrar;

class BlueRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'blue'
        ], function ($router) {
            $router->post('/appalert', 'AppClient\\BlueController@appalert');
            $router->get('/appnotice', 'AppClient\\BlueController@appnotice');
            $router->get('/appknowledge', 'AppClient\\BlueController@appknowledge');
            $router->post('/applogin', 'AppClient\\BlueController@applogin');
            $router->post('/appsync', 'AppClient\\BlueController@appsync');
            $router->post('/appsendEmailVerify', 'AppClient\\BlueController@appsendEmailVerify');
            $router->post('/appforget', 'AppClient\\BlueController@appforget');
            $router->post('/appregister', 'AppClient\\BlueController@appregister');
            $router->post('/getTempToken', 'AppClient\\BlueController@getTempToken');
            $router->get ('/token2Login', 'AppClient\\BlueController@token2Login');
            $router->get ('/config', 'AppClient\\BlueController@config');
            $router->post('/appupdate', 'AppClient\\BlueController@appupdate');
            $router->post('/deleteAccount', 'AppClient\\BlueController@appDelete');
            $router->get('/homepage', 'AppClient\\BlueController@token2Login');
            $router->post('/orderdetail', 'AppClient\\BlueController@orderdetail');
            $router->post('/checktrade', 'AppClient\\BlueController@checktrade');
            $router->post('/ordercancel', 'AppClient\\BlueController@ordercancel');
            $router->post('/checkout', 'AppClient\\BlueController@checkout');
            $router->post('/ordersave', 'AppClient\\BlueController@ordersave');
            $router->post('/appinvite', 'AppClient\\BlueController@appinvite');
            $router->post('/couponCheck', 'AppClient\\BlueController@couponCheck');
            $router->get('/apppaymentmethod', 'AppClient\\BlueController@getPaymentMethod');
            $router->get('/appshop', 'AppClient\\BlueController@appshop');
            $router->post('/verifyuser', 'AppClient\\BlueController@verifyuser');
            $router->post('/verifycode', 'AppClient\\BlueController@verifycode');
            $router->post('/transfer', 'AppClient\\BlueController@transfer');
            $router->post('/withdraw', 'AppClient\\BlueController@withdraw');
            $router->get('/appfaq', 'AppClient\\BlueController@appfaq');
            $router->post('/withdrawStatus', 'AppClient\\BlueController@withdrawStatus');
            $router->get('/getTrafficLog', 'AppClient\\BlueController@getTrafficLog');
            $router->post('/iapCallBack', 'AppClient\\BlueController@iapCallBack');
        });
    }
}
