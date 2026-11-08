<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminSdkAssetController
{
    private const ASSETS = [
        'companyware-admin-sdk.js' => 'application/javascript; charset=utf-8',
        'companyware-admin-tokens.css' => 'text/css; charset=utf-8',
    ];

    #[Route('/admin-sdk/{asset}', name: 'admin_sdk_asset', requirements: ['asset' => 'companyware-admin-(?:sdk\.js|tokens\.css)'], methods: ['GET'])]
    public function __invoke(string $asset): Response
    {
        if (!isset(self::ASSETS[$asset])) return new Response('Not found.', Response::HTTP_NOT_FOUND);
        $file = dirname(__DIR__, 2).'/admin-sdk/dist/'.$asset;
        if (!is_file($file)) return new Response('Not found.', Response::HTTP_NOT_FOUND);

        $response = new BinaryFileResponse($file);
        $response->headers->set('Content-Type', self::ASSETS[$asset]);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();

        return $response;
    }
}
