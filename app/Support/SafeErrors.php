<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class SafeErrors
{
    public static function render(Throwable $exception, Request $request)
    {
        // Keep normal form validation, sign-in redirects and explicit controller responses.
        if ($exception instanceof ValidationException || $exception instanceof AuthenticationException ||
            $exception instanceof HttpResponseException) {
            return null;
        }
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        if (self::databaseUnavailable($exception)) {
            $status = 503;
        }
        [$title, $description] = match ($status) {
            400 => ['We could not process this request.', 'Please check the link and try again.'],
            401 => ['Please sign in to continue.', 'Your session may have ended. Sign in again to access your account.'],
            403 => ['This page is not available to you.', 'You may need to sign in with an account that has access.'],
            404 => ['This page slipped away.', 'The link may have changed, or this page may no longer be available.'],
            405 => ['This action is not available.', 'Return to the store and try the action from its page.'],
            410 => ['This page is no longer available.', 'Explore the store to find something new.'],
            419 => ['Your session has expired.', 'Refresh the page and try again. You may need to sign in again.'],
            422 => ['Please check your details.', 'Some information could not be accepted. Return to the form and try again.'],
            429 => ['A little pause, please.', 'Too many requests were made. Wait a moment before trying again.'],
            503 => ['We will be back shortly.', 'Avelle is temporarily unavailable. Please try again in a little while.'],
            default => ['Something went wrong.', 'We could not complete your request. Please try again later.'],
        };
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $headers['Cache-Control'] = 'no-store, private';
        $headers['X-Robots-Tag'] = 'noindex, nofollow';
        if ($status === 503) {
            $headers['Retry-After'] = '60';
        }
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $description, 'status' => $status], $status, $headers);
        }
        // This view deliberately uses no shared layouts, database, session or auth calls.
        return response()->view('errors.store', compact('status', 'title', 'description'), $status, $headers);
    }

    private static function databaseUnavailable(Throwable $exception): bool
    {
        do {
            if ($exception instanceof \PDOException) {
                $driverCode = (int) ($exception->errorInfo[1] ?? 0);
                $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
                if (in_array($driverCode, [1040, 1042, 2002, 2003, 2006, 2013], true) || str_starts_with($sqlState, '08')) {
                    return true;
                }
            }
            $exception = $exception->getPrevious();
        } while ($exception);
        return false;
    }
}
