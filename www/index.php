<?php
include __DIR__ . "/../vendor/autoload.php";

$app = new Silex\Application();
$app->register(new Silex\Provider\SessionServiceProvider());
list($consumerKey, $consumerSecret) = include __DIR__ . '/credentials.conf.php';

$loggin = new SilexFacebookLogin($app, 'facebook');
$loggin->setConsumerKey($consumerKey);
$loggin->setConsumerSecret($consumerSecret);

$loggin->setRoutesWithoutLogin(array('/about'));

$loggin->registerOnLoggin(function () use ($app, $loggin) {
        $userProfile = $loggin->getFacebookProfile();
        $app['session']->set($loggin->getSessionId(), [
                'id'           => $userProfile['id'],
                'name'         => $userProfile['name'],
                'first_name'   => $userProfile['first_name'],
                'last_name'    => $userProfile['last_name'],
                'link'         => $userProfile['link'],
                'username'     => $userProfile['username'],
                'gender'       => $userProfile['gender'],
                'timezone'     => $userProfile['timezone'],
                'verified'     => $userProfile['verified'],
                'updated_time' => $userProfile['updated_time']
            ]);
    });
$loggin->mountOn('/login', function () {
    return '<a href="/login/requestToken">login</a>';
});


$app->get('/', function () use ($app){
    return 'Hello ' . $app['session']->get('facebook')['first_name'];
});
$app->get('/about', function () use ($app){
    return 'about';
});

$app['debug'] = true;
$app->run();