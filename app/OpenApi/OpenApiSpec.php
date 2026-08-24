<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     title="JobAzmoon API",
 *     version="1.0.0",
 *     description="REST API for JobAzmoon"
 * )
 *
 * @OA\Server(url="/api/v1", description="API v1")
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Sanctum"
 * )
 */
class OpenApiSpec {}
