<?php

namespace Spatie\LaravelIgnition\Tests\Context;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelIgnition\ContextProviders\LaravelRequestContextProvider;
use Spatie\LaravelIgnition\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class LaravelRequestContextProviderTest extends TestCase
{
    /** @test */
    public function it_returns_route_name_in_context_data()
    {
        $route = Route::get('/route/', fn () => null)->name('routeName');

        $request = $this->createRequest('GET', '/route');

        $route->bind($request);

        $request->setRouteResolver(fn () => $route);

        $context = new LaravelRequestContextProvider($request);

        $contextData = $context->toArray();

        $this->assertSame('routeName', $contextData['route']['route']);
    }

    /** @test */
    public function it_returns_route_parameters_in_context_data()
    {
        $route = Route::get('/route/{parameter}/{otherParameter}', fn () => null);

        $request = $this->createRequest('GET', '/route/value/second');

        $route->bind($request);

        $request->setRouteResolver(function () use ($route) {
            return $route;
        });

        $context = new LaravelRequestContextProvider($request);

        $contextData = $context->toArray();

        $this->assertSame([
            'parameter' => 'value',
            'otherParameter' => 'second',
        ], $contextData['route']['routeParameters']);
    }

    /** @test */
    public function it_returns_the_url()
    {
        $request = $this->createRequest('GET', '/route', []);

        $context = new LaravelRequestContextProvider($request);

        $request = $context->getRequest();

        $this->assertSame('http://localhost/route', $request['url']);
    }

    /** @test */
    public function it_returns_the_cookies()
    {
        $request = $this->createRequest('GET', '/route', [], ['cookie' => 'noms']);

        $context = new LaravelRequestContextProvider($request);

        $this->assertSame(['cookie' => 'noms'], $context->getCookies());
    }

    /** @test */
    public function it_returns_the_authenticated_user()
    {
        $user = new User();
        $user->forceFill([
            'id' => 1,
            'email' => 'john@example.com',
        ]);

        $request = $this->createRequest('GET', '/route', [], ['cookie' => 'noms']);
        $request->setUserResolver(fn () => $user);

        $context = new LaravelRequestContextProvider($request);
        $contextData = $context->toArray();

        $this->assertSame($user->toArray(), $contextData['user']);
    }

    /** @test */
    public function it_the_authenticated_user_model_has_a_toFlare_method_it_will_be_used_to_collect_user_data()
    {
        $user = new class extends User {
            public function toFlare()
            {
                return ['id' => $this->id];
            }
        };

        $user->forceFill([
            'id' => 1,
            'email' => 'john@example.com',
        ]);

        $request = $this->createRequest('GET', '/route', [], ['cookie' => 'noms']);
        $request->setUserResolver(fn () => $user);

        $context = new LaravelRequestContextProvider($request);
        $contextData = $context->toArray();

        $this->assertSame(['id' => $user->id], $contextData['user']);
    }

    /** @test */
    public function it_the_authenticated_user_model_has_no_matching_method_it_will_return_no_user_data()
    {
        $user = new class {
        };

        $request = $this->createRequest('GET', '/route', [], ['cookie' => 'noms']);
        $request->setUserResolver(fn () => $user);

        $context = new LaravelRequestContextProvider($request);
        $contextData = $context->toArray();

        $this->assertSame([], $contextData['user']);
    }

    /** @test */
    public function it_the_authenticated_user_model_is_broken_it_will_return_no_user_data()
    {
        $user = new class extends User {
            protected $appends = ['invalid'];
        };

        $request = $this->createRequest('GET', '/route', [], ['cookie' => 'noms']);
        $request->setUserResolver(fn () => $user);

        $context = new LaravelRequestContextProvider($request);
        $contextData = $context->toArray();

        $this->assertSame([], $contextData['user']);
    }

    protected function createRequest($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): Request
    {
        $files = array_merge($files, $this->extractFilesFromDataArray($parameters));

        $symfonyRequest = SymfonyRequest::create(
            $this->prepareUrlForRequest($uri),
            $method,
            $parameters,
            $cookies,
            $files,
            array_replace($this->serverVariables, $server),
            $content
        );

        return Request::createFromBase($symfonyRequest);
    }
}