<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;
use Throwable;

/**
 * Application kernel: boots services, dispatches the request through the
 * middleware pipeline and sends the response.
 */
final class App
{
    private static ?App $instance = null;

    private Router $router;

    private Request $request;

    private function __construct()
    {
        $this->request = Request::capture();
        $this->router = new Router();
    }

    public static function boot(): App
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $app = new self();
        self::$instance = $app;

        $app->enforceHttps();
        Session::start();

        if (is_installed()) {
            Database::instance();
            Settings::load();
            $app->applyTenant();
        }

        $app->registerRoutes();
        $app->shareViewData();

        return $app;
    }

    public static function instance(): ?App
    {
        return self::$instance;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): Request
    {
        return $this->request;
    }

    private function enforceHttps(): void
    {
        if (PHP_SAPI === 'cli' || !(bool) Env::get('APP_FORCE_HTTPS', false)) {
            return;
        }
        if ($this->request->isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

            return;
        }
        if (in_array($this->request->host(), ['localhost', '127.0.0.1'], true)) {
            return;
        }
        $target = 'https://' . $this->request->host() . ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $target, true, 301);
        exit;
    }

    /**
     * Resolve the reseller white-label tenant for the current hostname so the
     * public site can be served under a reseller's own domain.
     */
    private function applyTenant(): void
    {
        try {
            Tenant::resolve($this->request->host());
        } catch (Throwable $e) {
            Logger::warning('Tenant resolution failed: ' . $e->getMessage());
        }
    }

    private function registerRoutes(): void
    {
        $routes = CONFIG_PATH . '/routes.php';
        if (is_file($routes)) {
            (static function (Router $router): void {
                require CONFIG_PATH . '/routes.php';
            })($this->router);
        }
    }

    private function shareViewData(): void
    {
        View::share('app', $this);
        View::share('router', $this->router);
        View::share('request', $this->request);
        View::share('auth', Auth::instance());
        View::share('flashes', Session::flashes());
        View::share('errors', Session::errors());
        View::share('branding', Tenant::branding());
        Session::ageFlashData();
    }

    public function run(): void
    {
        $response = $this->dispatch();
        $this->applySecurityHeaders($response);
        $response->send();
    }

    private function dispatch(): Response
    {
        try {
            $path = $this->request->path();

            // Installation gate -------------------------------------------
            if (!is_installed() && !str_starts_with($path, '/install')) {
                return Response::redirect(Url::to('install'));
            }
            if (is_installed() && str_starts_with($path, '/install') && !str_starts_with($path, '/install/assets')) {
                return Response::redirect(Url::to('/'));
            }

            $match = $this->router->match($this->request->method(), $path);

            if ($match === null) {
                $allowed = $this->router->allowedMethods($path);
                if ($allowed !== []) {
                    throw new HttpException(405, 'This action does not accept ' . $this->request->method() . ' requests.');
                }
                throw HttpException::notFound();
            }

            $this->request->setRouteParams($match['params']);

            return $this->runPipeline($match['middleware'], function () use ($match): Response {
                return $this->callHandler($match['handler'], $match['params']);
            });
        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            $reference = Logger::exception($e);

            if ((bool) Config::get('app.debug', false)) {
                throw $e;
            }
            if ($this->request->wantsJson()) {
                return Response::json([
                    'success'   => false,
                    'message'   => 'An unexpected error occurred.',
                    'reference' => $reference,
                ], 500);
            }

            return $this->errorPage(500, ErrorHandler::defaultMessage(500), $reference);
        }
    }

    /**
     * @param array<int,string> $middleware
     */
    private function runPipeline(array $middleware, callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            static function (callable $next, string $name): callable {
                return static function (Request $request) use ($next, $name): Response {
                    $shortName = $name;
                    $parameter = null;
                    if (str_contains($name, ':')) {
                        [$shortName, $parameter] = explode(':', $name, 2);
                    }
                    $shortName = ltrim($shortName, '\\');
                    $class = str_starts_with($shortName, 'App\\') ? $shortName : 'App\\Middleware\\' . $shortName;
                    if (!class_exists($class)) {
                        throw new \RuntimeException('Middleware not found: ' . $class);
                    }
                    /** @var MiddlewareInterface $instance */
                    $instance = new $class();

                    return $instance->handle($request, $next, $parameter);
                };
            },
            static fn (Request $request): Response => $destination($request)
        );

        return $pipeline($this->request);
    }

    /** @param array<string,string> $params */
    private function callHandler(mixed $handler, array $params): Response
    {
        if (is_callable($handler)) {
            $result = $handler(...array_values($params));

            return $result instanceof Response ? $result : Response::make((string) $result);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $class = self::resolveControllerClass($class);

            if (!class_exists($class)) {
                throw new \RuntimeException('Controller not found: ' . $class);
            }
            $controller = new $class();
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException('Controller action not found: ' . $class . '::' . $method);
            }

            $result = $controller->{$method}(...array_values($params));

            return $result instanceof Response ? $result : Response::make((string) $result);
        }

        throw new \RuntimeException('Invalid route handler.');
    }

    /**
     * Route handlers are written relative to App\Controllers (for example
     * "Site\SiteController@home"); a fully-qualified name is used as-is.
     */
    private static function resolveControllerClass(string $class): string
    {
        $class = ltrim($class, '\\');

        return str_starts_with($class, 'App\\') ? $class : 'App\\Controllers\\' . $class;
    }

    private function handleValidationException(ValidationException $e): Response
    {
        if ($this->request->wantsJson()) {
            return Response::json([
                'success' => false,
                'message' => $e->firstMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }

        Session::flashErrors($e->errors());
        Session::flashInput($e->input());
        Session::flash('error', $e->firstMessage());

        $referer = $this->request->referer();
        $target = $referer !== '' && str_starts_with($referer, Url::root()) ? $referer : Url::to('/');

        return Response::redirect($target, 303);
    }

    private function handleHttpException(HttpException $e): Response
    {
        $status = $e->getStatusCode();
        $message = $e->getMessage() !== '' ? $e->getMessage() : ErrorHandler::defaultMessage($status);

        if ($status === 401 && !$this->request->wantsJson()) {
            Session::flash('error', $message);
            Session::put('_intended', $this->request->path());

            return Response::redirect(Url::to('login'));
        }

        if ($this->request->wantsJson()) {
            return Response::json(['success' => false, 'message' => $message], $status);
        }

        return $this->errorPage($status, $message, 'N/A');
    }

    private function errorPage(int $status, string $message, string $reference): Response
    {
        try {
            $view = new View();

            return $view->render('errors.error', [
                'status'    => $status,
                'message'   => $message,
                'reference' => $reference,
                'debug'     => false,
                'e'         => null,
            ], $status);
        } catch (Throwable) {
            return Response::make('<h1>' . $status . '</h1><p>' . e($message) . '</p>', $status, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }
    }

    private function applySecurityHeaders(Response $response): void
    {
        $headers = $response->getHeaders();

        if (!isset($headers['Content-Type'])) {
            $response->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('X-Frame-Options', $this->frameOption());
        $response->header('Permissions-Policy', 'geolocation=(self), microphone=(), camera=(), payment=(self)');

        if ($this->request->isSecure()) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->header('Content-Security-Policy', $this->contentSecurityPolicy());
        $response->header('X-Powered-By', 'DigitalVisitingCard');
    }

    private function frameOption(): string
    {
        return $this->isEmbeddable() ? 'SAMEORIGIN' : 'DENY';
    }

    /**
     * Template previews and public cards are intentionally embeddable inside
     * the design gallery and the live editor, which render them in iframes:
     * /templates/preview/{code}, /cards/{id}/preview and /editor/{id}/preview
     * as well as the public card itself. Every other page refuses framing
     * outright rather than relying on a same-origin exception.
     */
    private function isEmbeddable(): bool
    {
        $path = $this->request->path();

        return str_starts_with($path, '/card/')
            || str_starts_with($path, '/templates/preview')
            || str_ends_with($path, '/preview');
    }

    private function contentSecurityPolicy(): string
    {
        // Assets and form actions are generated from APP_URL. When visitors
        // reach the site on a host that is not byte-identical to APP_URL
        // (www vs non-www, an alias domain, a reseller's white-label domain)
        // those URLs are cross-origin, and a bare 'self' policy would block
        // the site's own stylesheets, scripts and form submissions. The
        // configured origin is therefore always allowed alongside 'self'.
        $own = trim("'self' " . $this->configuredOrigin());

        $directives = [
            "default-src {$own}",
            "base-uri 'self'",
            "form-action {$own} https://api.razorpay.com",
            // The gallery and editor frame these pages using APP_URL-derived
            // src attributes, so the configured origin has to be an allowed
            // ancestor as well -- otherwise every preview breaks the moment
            // the site is browsed on a host APP_URL does not name verbatim.
            'frame-ancestors ' . ($this->isEmbeddable() ? $own : "'none'"),
            "object-src 'none'",
            "img-src {$own} data: blob: https:",
            "font-src {$own} data: https://fonts.gstatic.com",
            "style-src {$own} 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "script-src {$own} 'unsafe-inline' https://checkout.razorpay.com https://cdn.jsdelivr.net",
            "connect-src {$own} https://lumberjack.razorpay.com https://api.razorpay.com",
            "frame-src {$own} https://api.razorpay.com https://checkout.razorpay.com https://www.youtube.com https://www.youtube-nocookie.com https://www.google.com",
            "media-src {$own} https:",
        ];

        if ($this->request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    /**
     * Scheme + host of APP_URL, when it differs from the host being browsed.
     * Returns an empty string when they match, so the policy stays minimal.
     */
    private function configuredOrigin(): string
    {
        $configured = (string) Config::get('app.url', '');
        if ($configured === '') {
            return '';
        }

        $parts = parse_url($configured);
        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            return '';
        }

        $scheme = (string) ($parts['scheme'] ?? ($this->request->isSecure() ? 'https' : 'http'));
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $origin = $scheme . '://' . $host . $port;

        $current = ($this->request->isSecure() ? 'https' : 'http') . '://' . $this->request->host();

        return $origin === $current ? '' : $origin;
    }
}
