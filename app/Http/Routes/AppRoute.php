<?php
#move to app/Http/Routes/
#v2board.app
namespace App\Http\Routes;

use Illuminate\Contracts\Routing\Registrar;

class AppRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'app'
        ], function ($router) {
            $router->get('/appnotice', 'AppClient\\AppController@appnotice');
            $router->get('/appknowledge', 'AppClient\\AppController@appknowledge');
            $router->post('/applogin', 'AppClient\\AppController@applogin');
            $router->post('/appsendEmailVerify', 'AppClient\\AppController@appsendEmailVerify');
            $router->post('/appforget', 'AppClient\\AppController@appforget');
            $router->post('/appregister', 'AppClient\\AppController@appregister');
            $router->post('/appsync','AppClient\\AppController@appsync');
            $router->post('/appalert', 'AppClient\\AppController@appalert');
            $router->post('/accountdelete','AppClient\\AppController@appDelete');
            $router->post('/getTempToken', 'AppClient\\AppController@getTempToken');
            $router->get ('/config', 'AppClient\\AppController@appconfig');
            $router->post('/appupdate', 'AppClient\\AppController@appupdate');
            $router->get('/homepage', 'AppClient\\AppController@token2Login');
            $router->post('/appalert', 'AppClient\\AppController@appalert');
        });
    }
}
