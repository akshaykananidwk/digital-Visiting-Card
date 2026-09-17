<?php

declare(strict_types=1);

namespace App\Core;

/** Base controller with view rendering, redirects and validation helpers. */
abstract class Controller
{
    protected Request $request;

    protected View $view;

    public function __construct()
    {
        $this->request = Request::current() ?? Request::capture();
        $this->view = new View();
    }

    /** @param array<string,mixed> $data */
    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        return $this->view->render($view, $data, $status);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(Url::to($path), $status);
    }

    protected function redirectAway(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    protected function back(string $fallback = '/'): Response
    {
        $referer = $this->request->referer();
        if ($referer !== '' && str_starts_with($referer, Url::root())) {
            return Response::redirect($referer);
        }

        return $this->redirect($fallback);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** @param array<string,mixed> $extra */
    protected function ok(string $message = 'OK', array $extra = []): Response
    {
        return Response::json(['success' => true, 'message' => $message] + $extra);
    }

    /** @param array<string,mixed> $extra */
    protected function fail(string $message = 'Request failed', int $status = 422, array $extra = []): Response
    {
        return Response::json(['success' => false, 'message' => $message] + $extra, $status);
    }

    protected function success(string $message): void
    {
        Session::flash('success', $message);
    }

    protected function error(string $message): void
    {
        Session::flash('error', $message);
    }

    protected function info(string $message): void
    {
        Session::flash('info', $message);
    }

    /**
     * Validate the incoming request; on failure flashes errors + old input
     * and throws a redirect-style response via ValidationException.
     *
     * @param array<string,string> $rules
     * @param array<string,string> $messages
     * @return array<string,mixed>
     */
    protected function validate(array $rules, array $messages = []): array
    {
        $validator = new Validator($this->request->all(), $rules, $messages);
        if (!$validator->passes()) {
            throw new ValidationException($validator->errors(), $this->request->all());
        }

        return $validator->validated();
    }

    protected function db(): Database
    {
        return Database::instance();
    }

    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }

    protected function abortIf(bool $condition, int $status, string $message = ''): void
    {
        if ($condition) {
            $this->abort($status, $message);
        }
    }
}
