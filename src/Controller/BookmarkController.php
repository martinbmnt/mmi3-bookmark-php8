<?php

namespace App\Controller;

use App\Entity\Bookmark;
use App\Repository\BookmarkRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Annotation\Route;

class BookmarkController extends AbstractController
{
    #[Route('/api/bookmarks', name: 'api_bookmark_create', methods: ['POST'])]
    public function create(
        ManagerRegistry $doctrine,
        Request $request,
        UrlHelper $urlHelper
    ): JsonResponse {
        $response = new JsonResponse();
        $response->headers->set('Server', 'ExoAPICRUDREST');

        $name = $request->get('name');
        $url = $request->get('url');
        $description = $request->get('description');

        if (empty($name) || empty($url)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST, 'url and name must not be empty');

            return $response;
        }

        $entityManager = $doctrine->getManager();

        $bookmark = new Bookmark();
        $bookmark->setName($name);
        $bookmark->setUrl($url);
        $bookmark->setDescription($description);

        $entityManager->persist($bookmark);
        $entityManager->flush();

        $id = $bookmark->getId();

        $response->setStatusCode(Response::HTTP_CREATED);
        $response->headers->set('Location', $urlHelper->getAbsoluteUrl($this->generateUrl('api_bookmark_read', ['id' => $id])));

        return $response;
    }

    #[Route('/api/bookmarks/{id}', name: 'api_bookmark_read', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[Route('/api/bookmarks/latest', name: 'api_bookmark_latest', methods: ['GET'], priority: 1)]
    public function read(
        Request $request,
        ManagerRegistry $doctrine,
        UrlHelper $urlHelper,
        int $id = 0
    ): JsonResponse {
        $response = new JsonResponse();
        $response->headers->set('Server', 'ExoAPICRUDREST');

        /** @var BookmarkRepository $bookmarkRepository */
        $bookmarkRepository = $doctrine->getRepository(Bookmark::class);

        if ($request->attributes->get('_route') === 'api_bookmark_latest') {
            $bookmark = $bookmarkRepository->findLastEntry();
        } else {
            $bookmark = $bookmarkRepository->find($id);
        }

        if (null === $bookmark) {
            throw $this->createNotFoundException('Bookmark not found for id ' . $id);
        }

        $baseUrl = $urlHelper->getAbsoluteUrl($this->generateUrl('api_bookmark_collection'));

        $response->headers->set('Link', "<{$baseUrl}/{$bookmark->getId()}/qrcode>; title=\"QR Code\"; type=\"image/png\"");
        $response->headers->set('Link', "<{$bookmark->getUrl()}>; rel=\"related\"; title=\"Bookmarked link\"", false);
        $response->headers->set('Link', "<{$baseUrl}>; rel=\"collection\"", false);

        $response->setVary('Accept');

        $response->setData([
            'name' => $bookmark->getName(),
            'url' => $bookmark->getUrl(),
            'description' => $bookmark->getDescription(),
        ]);

        return $response;
    }

    #[Route('/api/bookmarks/{id}', name: 'api_bookmark_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(
        Request $request,
        ManagerRegistry $doctrine,
        Bookmark $bookmark
    ): JsonResponse {
        $response = new JsonResponse();
        $response->headers->set('Server', 'ExoAPICRUDREST');

        $name = $request->get('name');
        $url = $request->get('url');
        $description = $request->get('description');

        if (empty($name) || empty($url)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST, 'url and name must not be empty');

            return $response;
        }

        $entityManager = $doctrine->getManager();

        $bookmark->setName($name);
        $bookmark->setUrl($url);
        $bookmark->setDescription($description);

        $entityManager->flush();

        $response->setStatusCode(Response::HTTP_OK, 'Bookmark updated');

        return $response;
    }

    #[Route('/api/bookmarks/{id}', name: 'api_bookmark_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(
        ManagerRegistry $doctrine,
        Bookmark $bookmark
    ): JsonResponse {
        $response = new JsonResponse();
        $response->headers->set('Server', 'ExoAPICRUDREST');

        $entityManager = $doctrine->getManager();

        $entityManager->remove($bookmark);
        $entityManager->flush();

        $response->setStatusCode(Response::HTTP_NO_CONTENT);

        return $response;
    }

    // Collections

    #[Route('/api/bookmarks', name: 'api_bookmark_collection', methods: ['GET'], priority: 2)]
    #[Route('/api/bookmarks/pages/{page}', name: 'api_bookmark_collection_page', methods: ['GET'], requirements: ['page' => '\d+'])]
    #[Route('/api/bookmarks/pages/{page}/{step}', name: 'api_bookmark_collection_page_step', methods: ['GET'], requirements: ['page' => '\d+', 'step' => '\d+'], defaults: ['page' => 1])]
    #[Route('/api/bookmarks/last', name: 'api_bookmark_collection_page_last', methods: ['GET'])]
    #[Route('/api/bookmarks/last/{step}', name: 'api_bookmark_collection_page_last_step', methods: ['GET'], requirements: ['step' => '\d+'])]
    public function collection(
        Request $request,
        ManagerRegistry $doctrine,
        UrlHelper $urlHelper,
        int $page = 1,
        int $step = 10
    ): JsonResponse {
        /** @var BookmarkRepository $bookmarkRepository */
        $bookmarkRepository = $doctrine->getRepository(Bookmark::class);

        $bookmarkCount = $bookmarkRepository->countAll();

        if (in_array($request->attributes->get('_route'), ['api_bookmark_collection_page_last', 'api_bookmark_collection_page_last_step'])) {
            $current = max(1, $bookmarkCount - $step);
        } else {
            $current = ($page - 1) * $step + 1;
        }

        $response = new JsonResponse();
        $response->headers->set('Server', 'ExoAPICRUDREST');

        $baseUrl = $urlHelper->getAbsoluteUrl($this->generateUrl('api_bookmark_collection'));

        $bookmarks = $bookmarkRepository->findNextX($current - 1, $step);

        $responseData = [
            'Locations' => [],
            'meta' => [
                'total_count' => $bookmarkCount,
            ],
        ];

        foreach ($bookmarks as $bookmark) {
            $responseData['Locations'][] = $baseUrl . '/' . $bookmark->getId();
        }

        $response->headers->set('Link', "<{$baseUrl}/last>; rel=\"last\"");
        $response->headers->set('Link', "<{$baseUrl}>; rel=\"first\"", false);
        $response->headers->set("X-Total-Count", $bookmarkCount);
        $response->headers->set("X-Current-Page", intdiv($current, $step) + 1);
        $response->headers->set("X-Per-Page", $step);
        $response->headers->set("X-Page-Size", count($bookmarks));

        if ($current > $bookmarkCount) {
            $response->setStatusCode(Response::HTTP_NO_CONTENT, 'Max page number reached');

            return $response;
        }

        if (!in_array($request->attributes->get('_route'), ['api_bookmark_collection_page_last', 'api_bookmark_collection_page_last_step'])) {
            $nextPage = intdiv($current, $step) + 2;
            $response->headers->set("Link", "<{$baseUrl}/pages/{$nextPage}>; rel=\"next\"", false);
        }

        $response->setData($responseData);
        $response->setStatusCode(Response::HTTP_PARTIAL_CONTENT);

        return $response;
    }
}
