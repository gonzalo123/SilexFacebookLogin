<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SilexFacebookLogin
{
    private $app;
    private $controllersFactory;
    private $consumerKey;
    private $consumerSecret;
    private $routesWithoutLogin;
    private $onLoggin;
    private $prefix;
    private $facebookProfile;

    private $redirectOnSuccess = self::DEFAULT_REDIRECT_ON_SUCCESS;
    private $sessionId;
    private $requestTokenRoute = self::DEFAULT_REQUESTTOKEN;
    private $callbackUrlRoute = self::DEFAULT_CALLBACKURL;

    const DEFAULT_SESSION_ID   = 'facebook';
    const DEFAULT_REQUESTTOKEN = 'requestToken';
    const DEFAULT_CALLBACKURL  = 'callbackUrl';
    const DEFAULT_REDIRECT_ON_SUCCESS = '/';

    public function __construct(Application $app, $sessionId = self::DEFAULT_SESSION_ID)
    {
        $this->sessionId          = $sessionId;
        $this->app                = $app;
        $this->controllersFactory = $app['controllers_factory'];
    }

    public function mountOn($prefix, $loginCallaback)
    {
        $this->prefix = $prefix;
        $this->app->get($prefix, $loginCallaback);
        $this->defineApp();
        $this->app->mount($prefix, $this->getControllersFactory());
    }

    private function defineApp()
    {
        $this->setUpRedirectMiddleware();

        // ugly things due to php5.3 compatibility
        $app               = $this->app;
        $consumerKey       = $this->consumerKey;
        $consumerSecret    = $this->consumerSecret;
        $redirectOnSuccess = $this->redirectOnSuccess;
        $that              = $this;
        ////

        $facebook = new Facebook(array(
            'appId'  => $consumerKey,
            'secret' => $consumerSecret,
        ));

        $this->controllersFactory->get("/{$this->requestTokenRoute}", function (Request $request) use ($app, $facebook) {

            $loginUrl = $facebook->getLoginUrl(array(
                'redirect_uri' => $request->getSchemeAndHttpHost() . $this->prefix . '/' . $this->callbackUrlRoute,
                'cancel_url'   => $request->getSchemeAndHttpHost() . $this->prefix,
            ));

            /*
             $loginUrl = $facebook->getLoginUrl(array(
                 'redirect_uri' => $request->getSchemeAndHttpHost() . $this->redirectOnSuccess, $callbackUrlRoute
                 'cancel_url'   => $request->getSchemeAndHttpHost() . $this->prefix,
             ));
             */
            return $app->redirect($loginUrl);
        });

        $this->controllersFactory->get("/{$this->callbackUrlRoute}", function () use ($app, $facebook, $that, $redirectOnSuccess) {
            $userProfile = $facebook->api('/me');
            $that->setFacebookProfile($userProfile);
            $that->triggerOnLoggin();

            return $app->redirect($redirectOnSuccess);
        });
    }

    public function triggerOnLoggin()
    {
        if (is_callable($this->onLoggin)) {
            call_user_func($this->onLoggin);
        }
    }

    private function setUpRedirectMiddleware()
    {
        // ugly things due to php5.3 compatibility
        $app                = $this->app;
        $sessionId          = $this->sessionId;
        $prefix             = $this->prefix;
        $requestTokenRoute  = $this->requestTokenRoute;
        $callbackUrlRoute   = $this->callbackUrlRoute;
        $routesWithoutLogin = $this->routesWithoutLogin;
        ////

        $this->app->before(function (Request $request) use ($app, $sessionId, $prefix, $requestTokenRoute, $callbackUrlRoute, $routesWithoutLogin) {
            $path = $request->getPathInfo();
            if (!$app['session']->has($sessionId)) {
                $withoutLogin = array($prefix, "{$prefix}/{$requestTokenRoute}", "{$prefix}/{$callbackUrlRoute}");
                foreach ($routesWithoutLogin as $route) {
                    $withoutLogin[] = $route;
                }

                if (!in_array($path, $withoutLogin)) {

                    return new RedirectResponse($prefix);
                }
            }
        });
    }

    public function registerOnLoggin($onLoggin)
    {
        $this->onLoggin = $onLoggin;
    }

    public function setConsumerKey($consumerKey)
    {
        $this->consumerKey = $consumerKey;
    }

    public function setConsumerSecret($consumerSecret)
    {
        $this->consumerSecret = $consumerSecret;
    }

    public function setRoutesWithoutLogin($routesWithoutLogin)
    {
        $this->routesWithoutLogin = $routesWithoutLogin;
    }

    public function setRedirectOnSuccess($redirectOnSuccess)
    {
        $this->redirectOnSuccess = $redirectOnSuccess;
    }

    private function getControllersFactory()
    {
        return $this->controllersFactory;
    }

    public function setFacebookProfile($facebookProfile)
    {
        $this->facebookProfile = $facebookProfile;
    }

    public function getFacebookProfile()
    {
        return $this->facebookProfile;
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }
}