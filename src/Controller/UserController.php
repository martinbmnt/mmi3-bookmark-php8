<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

class UserController extends AbstractController
{
    #[Route('/api/users', name: 'api_user_collection', methods: ['GET'], priority: 2)]
    #[Route('/api/users/pages/{page}', name: 'api_user_collection_page', methods: ['GET'], requirements: ['page' => '\d+'])]
    #[Route('/api/users/pages/{page}/{step}', name: 'api_user_collection_page_step', methods: ['GET'], requirements: ['page' => '\d+', 'step' => '\d+'], defaults: ['page' => 1])]
    #[Route('/api/users/last', name: 'api_user_collection_page_last', methods: ['GET'])]
    #[Route('/api/users/last/{step}', name: 'api_user_collection_page_last_step', methods: ['GET'], requirements: ['step' => '\d+'])]
    public function index(
        Request $request,
        ManagerRegistry $managerRegistry,
        UrlHelper $urlHelper,
        int $page = 1,
        int $step = 10
    ): JsonResponse {
        /** @var UserRepository $userRepository */
        $userRepository = $managerRegistry->getRepository(User::class);

        $userCount = $userRepository->countAll();

        if (in_array($request->attributes->get('_route'), ['api_user_collection_page_last', 'api_user_collection_page_last_step'])) {
            $current = max(1, $userCount - $step);
        } else {
            $current = ($page - 1) * $step + 1;
        }

        $response = new JsonResponse();
        $response->headers->set('Server', 'ExoAPICRUDREST');

        $baseUrl = $urlHelper->getAbsoluteUrl($this->generateUrl('api_user_collection'));

        $users = $userRepository->findNextX($current - 1, $step);

        $responseData = [
            'Locations' => [],
            'meta' => [
                'total_count' => $userCount,
            ],
        ];

        foreach ($users as $user) {
            $responseData['Locations'][] = $baseUrl . '/' . $user->getId();
        }

        $response->headers->set('Link', "<{$baseUrl}/last>; rel=\"last\"");
        $response->headers->set('Link', "<{$baseUrl}>; rel=\"first\"", false);
        $response->headers->set("X-Total-Count", $userCount);
        $response->headers->set("X-Current-Page", intdiv($current, $step) + 1);
        $response->headers->set("X-Per-Page", $step);
        $response->headers->set("X-Page-Size", count($users));

        if ($current > $userCount) {
            $response->setStatusCode(Response::HTTP_NO_CONTENT, 'Max page number reached');

            return $response;
        }

        if (!in_array($request->attributes->get('_route'), ['api_user_collection_page_last', 'api_user_collection_page_last_step'])) {
            $nextPage = intdiv($current, $step) + 2;
            $response->headers->set("Link", "<{$baseUrl}/pages/{$nextPage}>; rel=\"next\"", false);
        }

        $response->setData($responseData);
        $response->setStatusCode(Response::HTTP_PARTIAL_CONTENT);

        return $response;
    }

    #[Route('/api/users', name: 'api_user_create', methods: ['POST'])]
    public function create(
        ManagerRegistry $managerRegistry,
        Request $request,
        UrlHelper $urlHelper,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $response = new JsonResponse();
        $response->headers->set('Server', 'ExoAPICRUDREST');

        $email = $request->get('email');
        $password = $request->get('password');
        $roles = $request->get('roles', []);

        if (empty($email) || empty($password)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST, 'Missing email or password');

            return $response;
        }

        $entityManager = $managerRegistry->getManager();

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRoles($roles);

        $entityManager->persist($user);
        $entityManager->flush();

        $id = $user->getId();

        $response->setStatusCode(Response::HTTP_CREATED);
        $response->headers->set('Location', $urlHelper->getAbsoluteUrl($this->generateUrl('api_user_item', ['id' => $id])));

        return $response;
    }

    #[Route('/api/users/{id}', name: 'api_user_item', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(
        ManagerRegistry $managerRegistry,
        UrlHelper $urlHelper,
        int $id
    ): JsonResponse {
        $response = new JsonResponse();
        $response->headers->set('Server', 'ExoAPICRUDREST');

        /** @var UserRepository $userRepository */
        $userRepository = $managerRegistry->getRepository(User::class);

        $user = $userRepository->find($id);

        if (null === $user) {
            throw $this->createNotFoundException('User not found');
        }

        $baseUrl = $urlHelper->getAbsoluteUrl($this->generateUrl('api_bookmark_collection'));

        $response->headers->set('Link', "<{$baseUrl}>; rel=\"collection\"", false);

        $response->setVary('Accept');

        $response->setData([
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);

        return $response;
    }

    #[Route('/api/users/{id}', name: 'api_user_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(
        Request $request,
        ManagerRegistry $managerRegistry,
        UserPasswordHasherInterface $passwordHasher,
        User $user
    ): JsonResponse {
        $response = new JsonResponse();
        $response->headers->set('Server', 'ExoAPICRUDREST');

        $email = $request->get('email');
        $password = $request->get('password');
        $roles = $request->get('roles', $user->getRoles());

        if (empty($email) || empty($password)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST, 'Missing email or password');

            return $response;
        }

        $entityManager = $managerRegistry->getManager();

        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRoles($roles);

        $entityManager->flush();

        $response->setStatusCode(Response::HTTP_OK, 'User updated');

        return $response;
    }
}
