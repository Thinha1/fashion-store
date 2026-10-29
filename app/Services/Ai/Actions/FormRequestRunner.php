<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;

/**
 * Runs an admin form's own FormRequest (authorization, preparation, rules
 * and after-hooks) on data the assistant put together, so an action can
 * never save something the form itself would refuse. Same approach as the
 * Excel import (AdminExcel::saveUsingForm).
 */
class FormRequestRunner
{
    public function __construct(
        private readonly Container $container,
        private readonly Redirector $redirector,
    ) {}

    /**
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $data
     * @return T
     *
     * @throws InvalidActionException with the form's first error message
     */
    public function validate(string $class, array $data, User $actor): FormRequest
    {
        $request = $class::createFrom(Request::create('/', 'POST', $data), new $class);
        $request->setContainer($this->container)->setRedirector($this->redirector);
        $request->setUserResolver(fn () => $actor);
        // A create form: no record bound to the route.
        $request->setRouteResolver(fn () => null);

        try {
            $request->validateResolved();
        } catch (ValidationException $exception) {
            throw new InvalidActionException(collect($exception->errors())->flatten()->first() ?? 'Dữ liệu không hợp lệ.');
        }

        return $request;
    }
}
