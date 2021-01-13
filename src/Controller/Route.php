<?php
namespace PhpRest\Controller;

use Symfony\Component\HttpFoundation\Request;
use PhpRest\Render\IResponseRender;
use PhpRest\Meta\HookMeta;

class Route
{
    /**
     * httpMethod
     * @var string
     */
    public $method;

    /**
     * @var string
     */
    public $uri;

    /**
     * @var string
     */
    public $summary = '';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var RequestHandler
     */
    public $requestHandler;

    /**
     * @var HookMeta[]
     */
    public $hooks = [];

    /**
     * @param Application $app
     * @param Request $request
     * @param string $classPath
     * @param string $actionName
     */
    public function invoke($app, $request, $classPath, $actionName) 
    {
        $next = function($request) use ($app, $classPath, $actionName) {
            $params = $this->requestHandler->makeParams($app, $request);
            $ctlClass = $app->get($classPath);
            $res = call_user_func_array([$ctlClass, $actionName], $params);
            $responseRender = $app->get(IResponseRender::class);
            return $responseRender->render($res);
        };

        foreach (array_reverse($this->hooks) as $hookMeta) {
            $next = function($request)use($app, $hookMeta, $next) {
                $params['method'] = $this->method;
                $params['uri']    = $this->uri;
                $params['params'] = $hookMeta->params;
                $hook = $app->getDIContainer()->make($hookMeta->classPath, $params);
                return $hook->handle($request, $next);
            };
        }

        return $next($request);
    }
}