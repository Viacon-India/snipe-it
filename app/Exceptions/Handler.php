<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Helpers\Helper;
use Illuminate\Validation\ValidationException;
use Log;
use JsonException;


class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
        \Intervention\Image\Exception\NotSupportedException::class,
        \League\OAuth2\Server\Exception\OAuthServerException::class,
        JsonException::class
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if ($this->shouldReport($exception)) {
            \Log::error($exception);
            return parent::report($exception);
        }
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {


        // CSRF token mismatch error
        if ($e instanceof \Illuminate\Session\TokenMismatchException) {
            return redirect()->back()->with('error', trans('general.token_expired'));
        }

        // Invalid JSON exception
        // TODO: don't understand why we have to do this when we have the invalidJson() method, below, but, well, whatever
        if ($e instanceof JsonException) {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'invalid JSON'), 422);
        }


        // Handle Ajax requests that fail because the model doesn't exist
        if ($request->ajax() || $request->wantsJson()) {

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $className = last(explode('\\', $e->getModel()));
                return response()->json(Helper::formatStandardApiResponse('error', null, $className . ' not found'), 200);
            }

            if ($this->isHttpException($e)) {

                $statusCode = $e->getStatusCode();

                switch ($e->getStatusCode()) {
                    case '404':
                       return response()->json(Helper::formatStandardApiResponse('error', null, $statusCode . ' endpoint not found'), 404);
                    case '405':
                        return response()->json(Helper::formatStandardApiResponse('error', null, 'Method not allowed'), 405);
                    default:
                        return response()->json(Helper::formatStandardApiResponse('error', null, $statusCode), 405);

                }
            }
        }


        if ($this->isHttpException($e) && (isset($statusCode)) && ($statusCode == '404' )) {
            return response()->view('layouts/basic', [
                'content' => view('errors/404')
            ],$statusCode);
        }

        return parent::render($request, $e);

    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized or unauthenticated.'], 401);
        }

        return redirect()->guest('login');
    }

    /**
     * Convert a validation exception into a JSON response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Validation\ValidationException  $exception
     * @return \Illuminate\Http\JsonResponse
     */
    protected function invalidJson($request, ValidationException $exception)
    {
        return response()->json(Helper::formatStandardApiResponse('error', null, $exception->errors(), 422));
    }
}
