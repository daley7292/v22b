<?php
namespace App\Http\Routes;

use Illuminate\Contracts\Routing\Registrar;

class ClientRoute
{
    public function map(Registrar $router)
    {
        // 1. 无需验证的路由组
        $router->group([
            'prefix' => 'client'
        ], function ($router) {
            // 新增无需验证的订阅接口
            $router->get('/getuuidSubscribe', 'Client\\ClientController@getuuidSubscribe');
        });

        // 2. 需要验证的路由组
        $router->group([
            'prefix' => 'client',
            'middleware' => 'client'
        ], function ($router) {
            // Client
            $router->get('/subscribe', 'Client\\ClientController@subscribe');
            // App
            $router->get('/app/getConfig', 'Client\\AppController@getConfig');
            $router->get('/app/getVersion', 'Client\\AppController@getVersion');
        });
    }
}
