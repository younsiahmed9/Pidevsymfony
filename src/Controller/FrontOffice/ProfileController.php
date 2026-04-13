<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'front_profile_index', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $balanceTotal = (float) $entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(solde_total), 0) FROM portefeuille WHERE user_id = :uid',
            ['uid' => $user->getId()]
        );

        $form = $this->createFormBuilder($user)
            ->add('fullName', TextType::class)
            ->add('email', EmailType::class)
            ->add('profilePhotoFile', FileType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $uploadedPhoto */
            $uploadedPhoto = $form->get('profilePhotoFile')->getData();

            if ($uploadedPhoto instanceof UploadedFile) {
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                if (!in_array((string) $uploadedPhoto->getMimeType(), $allowedMimeTypes, true)) {
                    $this->addFlash('error', 'La photo doit etre une image JPG, PNG, WEBP ou GIF.');

                    return $this->redirectToRoute('front_profile_index');
                }

                if ($uploadedPhoto->getSize() > 5 * 1024 * 1024) {
                    $this->addFlash('error', 'La photo de profil doit faire 5 Mo maximum.');

                    return $this->redirectToRoute('front_profile_index');
                }

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $baseName = pathinfo((string) $uploadedPhoto->getClientOriginalName(), PATHINFO_FILENAME);
                $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $baseName) ?: 'profile';
                $extension = $uploadedPhoto->guessExtension() ?: 'jpg';
                $newFilename = sprintf('user-%d-%s-%s.%s', (int) $user->getId(), $safeBaseName, substr(bin2hex(random_bytes(4)), 0, 8), $extension);

                $uploadedPhoto->move($uploadDir, $newFilename);
                $user->setProfilePhoto('uploads/profiles/' . $newFilename);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('front_profile_index');
        }

        return $this->render('frontoffice/profile/index.html.twig', [
            'profileForm' => $form->createView(),
            'balanceTotal' => $balanceTotal,
        ]);
    }
}
