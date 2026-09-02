<?php

declare(strict_types=1);

namespace App\Actions\Health;

use App\Health\ReadinessRunner;
use App\Response\ApiResponse;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class ReadinessAction
{
    public function __construct(
        private ReadinessRunner $readinessRunner,
        private ApiResponse $response
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $result     = $this->readinessRunner->run();
        $isReady    = $result['isReady'];
        $httpStatus = $isReady ? 200 : 503;

        return $this->response->json([
            'status'    => $isReady ? 'ready' : 'unhealthy',
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'services'  => $result['services'],
        ], $httpStatus);
    }
}
