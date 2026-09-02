<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Actions\Health\LivenessAction;
use App\Actions\Health\ReadinessAction;
use App\Actions\Home\ApiInfoAction;
use App\Actions\Todo\CreateTodoAction;
use App\Actions\Todo\DeleteTodoAction;
use App\Actions\Todo\GetTodoAction;
use App\Actions\Todo\ListTodosAction;
use App\Actions\Todo\UpdateTodoAction;
use App\Middleware\ReadinessAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

final class Routes
{
    public static function register(App $app): void
    {
        // API Info / Root endpoint
        $app->get('/', ApiInfoAction::class)->setName('home');

        // Health check endpoints
        $app->get('/health', LivenessAction::class)->setName('health');
        $app->get('/health/live', LivenessAction::class)->setName('health.live');
        $app->get('/health/ready', ReadinessAction::class)
            ->setName('health.ready')
            ->add(ReadinessAuthMiddleware::class);

        // API Todo CRUD routes
        $app->group('/api/todos', function (RouteCollectorProxy $group) {
            $group->get('', ListTodosAction::class)->setName('todos.index');
            $group->get('/{id:[0-9]+}', GetTodoAction::class)->setName('todos.show');
            $group->post('', CreateTodoAction::class)->setName('todos.create');
            $group->put('/{id:[0-9]+}', UpdateTodoAction::class)->setName('todos.update');
            $group->patch('/{id:[0-9]+}', UpdateTodoAction::class)->setName('todos.patch');
            $group->delete('/{id:[0-9]+}', DeleteTodoAction::class)->setName('todos.delete');
        });
    }
}
