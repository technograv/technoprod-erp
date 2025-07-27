<?php

namespace App\Controller;

use App\Entity\UserPreferences;
use App\Repository\UserPreferencesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/preferences')]
#[IsGranted('ROLE_USER')]
class UserPreferencesController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserPreferencesRepository $preferencesRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPreferencesRepository $preferencesRepository
    ) {
        $this->entityManager = $entityManager;
        $this->preferencesRepository = $preferencesRepository;
    }

    #[Route('', name: 'app_user_preferences_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $preferences = $this->getUserPreferences($user);

        return $this->render('user_preferences/index.html.twig', [
            'preferences' => $preferences,
            'user' => $user,
        ]);
    }

    #[Route('/email', name: 'app_user_preferences_email')]
    public function email(Request $request): Response
    {
        $user = $this->getUser();
        $preferences = $this->getUserPreferences($user);

        if ($request->isMethod('POST')) {
            $signatureType = $request->request->get('email_signature_type', 'company');
            $customSignature = $request->request->get('custom_email_signature', '');
            $emailNotifications = $request->request->has('email_notifications');

            $preferences->setEmailSignatureType($signatureType);
            if ($signatureType === 'personal') {
                $preferences->setCustomEmailSignature($customSignature);
            }
            $preferences->setEmailNotifications($emailNotifications);

            $this->entityManager->flush();

            $this->addFlash('success', 'Vos préférences email ont été mises à jour avec succès.');
            return $this->redirectToRoute('app_user_preferences_email');
        }

        return $this->render('user_preferences/email.html.twig', [
            'preferences' => $preferences,
            'user' => $user,
        ]);
    }

    #[Route('/general', name: 'app_user_preferences_general')]
    public function general(Request $request): Response
    {
        $user = $this->getUser();
        $preferences = $this->getUserPreferences($user);

        if ($request->isMethod('POST')) {
            $language = $request->request->get('language', 'fr');
            $timezone = $request->request->get('timezone', 'Europe/Paris');
            $smsNotifications = $request->request->has('sms_notifications');

            $preferences->setLanguage($language);
            $preferences->setTimezone($timezone);
            $preferences->setSmsNotifications($smsNotifications);

            $this->entityManager->flush();

            $this->addFlash('success', 'Vos préférences générales ont été mises à jour avec succès.');
            return $this->redirectToRoute('app_user_preferences_general');
        }

        return $this->render('user_preferences/general.html.twig', [
            'preferences' => $preferences,
            'user' => $user,
            'timezones' => $this->getTimezones(),
        ]);
    }

    #[Route('/notes', name: 'app_user_preferences_notes')]
    public function notes(Request $request): Response
    {
        $user = $this->getUser();
        $preferences = $this->getUserPreferences($user);

        if ($request->isMethod('POST')) {
            $notes = $request->request->get('notes', '');
            $preferences->setNotes($notes);

            $this->entityManager->flush();

            $this->addFlash('success', 'Vos notes personnelles ont été sauvegardées.');
            return $this->redirectToRoute('app_user_preferences_notes');
        }

        return $this->render('user_preferences/notes.html.twig', [
            'preferences' => $preferences,
            'user' => $user,
        ]);
    }

    /**
     * Récupère ou crée les préférences utilisateur
     */
    private function getUserPreferences($user): UserPreferences
    {
        $preferences = $this->preferencesRepository->findOneBy(['user' => $user]);
        
        if (!$preferences) {
            $preferences = new UserPreferences();
            $preferences->setUser($user);
            $this->entityManager->persist($preferences);
            $this->entityManager->flush();
        }

        return $preferences;
    }

    /**
     * Liste des fuseaux horaires disponibles
     */
    private function getTimezones(): array
    {
        return [
            'Europe/Paris' => 'Paris (Europe/Paris)',
            'Europe/London' => 'Londres (Europe/London)',
            'Europe/Berlin' => 'Berlin (Europe/Berlin)',
            'Europe/Rome' => 'Rome (Europe/Rome)',
            'Europe/Madrid' => 'Madrid (Europe/Madrid)',
            'America/New_York' => 'New York (America/New_York)',
            'America/Los_Angeles' => 'Los Angeles (America/Los_Angeles)',
            'Asia/Tokyo' => 'Tokyo (Asia/Tokyo)',
            'Asia/Shanghai' => 'Shanghai (Asia/Shanghai)',
            'Australia/Sydney' => 'Sydney (Australia/Sydney)',
        ];
    }
}