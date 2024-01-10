<?php
/**
 * Trait for controllers.
 */

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;

trait ControllerTrait
{
    /**
     * Get request data.
     *
     * @param Request $request
     *
     * @return array
     */
    protected function getRequestData(Request $request): array
    {
        $contentType = $request->getContentTypeFormat();

        return match ($contentType) {
            'json' => json_decode($request->getContent(), true),
            'form' => $request->request->all(),
            default => [],
        };
    }
}
