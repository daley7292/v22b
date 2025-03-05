<?php
namespace App\Http\Routes;

use Illuminate\Contracts\Routing\Registrar;

class TomatoRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'tomato'
        ], function ($router) {
            //$router->post('/appalert', 'AppClient\\TomatoController@appalert');
            $router->get('/news', 'AppClient\\TomatoController@appnotice');
            $router->get('/alert', 'AppClient\\TomatoController@alert');
            //$router->get('/appknowledge', 'AppClient\\TomatoController@appknowledge');
            $router->post('/sign', 'AppClient\\TomatoController@applogin');
            $router->post('/sync', 'AppClient\\TomatoController@appsync');
            $router->post('/verification', 'AppClient\\TomatoController@appsendEmailVerify');
            // $router->post('/appforget', 'AppClient\\TomatoController@appforget');
            $router->post('/signup', 'AppClient\\TomatoController@appregister');
            // $router->post('/getTempToken', 'AppClient\\TomatoController@getTempToken');
            // $router->get ('/token2Login', 'AppClient\\TomatoController@token2Login');
            $router->get ('/getconfig', 'AppClient\\TomatoController@config');
            $router->post('/version', 'AppClient\\TomatoController@appupdate');
            $router->post('/remove', 'AppClient\\TomatoController@appDelete');
            // $router->get('/homepage', 'AppClient\\TomatoController@token2Login');
            // $router->post('/orderdetail', 'AppClient\\TomatoController@orderdetail');
            // $router->post('/checktrade', 'AppClient\\TomatoController@checktrade');
            // $router->post('/ordercancel', 'AppClient\\TomatoController@ordercancel');
            // $router->post('/checkout', 'AppClient\\TomatoController@checkout');
            // $router->post('/ordersave', 'AppClient\\TomatoController@ordersave');
            // $router->post('/appinvite', 'AppClient\\TomatoController@appinvite');
            // $router->post('/couponCheck', 'AppClient\\TomatoController@couponCheck');
            // $router->get('/apppaymentmethod', 'AppClient\\TomatoController@getPaymentMethod');
            // $router->get('/appshop', 'AppClient\\TomatoController@appshop');
            // $router->post('/verifyuser', 'AppClient\\TomatoController@verifyuser');
            // $router->post('/verifycode', 'AppClient\\TomatoController@verifycode');
            // $router->post('/transfer', 'AppClient\\TomatoController@transfer');
            // $router->post('/withdraw', 'AppClient\\TomatoController@withdraw');
            // $router->get('/appfaq', 'AppClient\\TomatoController@appfaq');
            // $router->post('/withdrawStatus', 'AppClient\\TomatoController@withdrawStatus');
            // $router->get('/getTrafficLog', 'AppClient\\TomatoController@getTrafficLog');
            // $router->post('/iapCallBack', 'AppClient\\TomatoController@iapCallBack');
        });
    }
}
