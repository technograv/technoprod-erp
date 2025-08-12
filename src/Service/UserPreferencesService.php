<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserPreferences;
use App\Repository\UserPreferencesRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserPreferencesService
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

    /**
     * Récupère ou crée les préférences utilisateur
     */
    public function getUserPreferences(User $user): UserPreferences
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
     * Met à jour les préférences email
     */
    public function updateEmailPreferences(
        User $user,
        string $signatureType,
        ?string $customSignature = null,
        bool $emailNotifications = true
    ): UserPreferences {
        $preferences = $this->getUserPreferences($user);
        
        $preferences->setEmailSignatureType($signatureType);
        if ($signatureType === 'personal' && $customSignature) {
            $preferences->setCustomEmailSignature($customSignature);
        }
        $preferences->setEmailNotifications($emailNotifications);
        
        $this->entityManager->flush();
        
        return $preferences;
    }

    /**
     * Met à jour les préférences générales
     */
    public function updateGeneralPreferences(
        User $user,
        string $language = 'fr',
        string $timezone = 'Europe/Paris',
        bool $smsNotifications = false
    ): UserPreferences {
        $preferences = $this->getUserPreferences($user);
        
        $preferences->setLanguage($language);
        $preferences->setTimezone($timezone);
        $preferences->setSmsNotifications($smsNotifications);
        
        $this->entityManager->flush();
        
        return $preferences;
    }

    /**
     * Met à jour les notes personnelles
     */
    public function updateNotes(User $user, ?string $notes = null): UserPreferences
    {
        $preferences = $this->getUserPreferences($user);
        $preferences->setNotes($notes);
        
        $this->entityManager->flush();
        
        return $preferences;
    }

    /**
     * Retourne la signature effective à utiliser pour un utilisateur
     */
    public function getEffectiveEmailSignature(User $user, string $companySignature = ''): ?string
    {
        $preferences = $this->getUserPreferences($user);
        
        // Si l'utilisateur veut utiliser sa signature personnelle
        if ($preferences->usePersonalSignature()) {
            // Si une signature personnalisée est définie, l'utiliser
            if ($preferences->getCustomEmailSignature()) {
                return $preferences->getCustomEmailSignature();
            }
            // Sinon utiliser la signature Gmail de l'utilisateur si disponible
            elseif ($user->getGmailSignature()) {
                return $user->getGmailSignature();
            }
        }

        // Par défaut, utiliser la signature d'entreprise
        return $companySignature;
    }

    /**
     * Retourne les statistiques des préférences utilisateur
     */
    public function getUserPreferencesStats(): array
    {
        $totalUsers = $this->preferencesRepository->count([]);
        $personalSignatureUsers = $this->preferencesRepository->count(['emailSignatureType' => 'personal']);
        $notificationsEnabledUsers = $this->preferencesRepository->count(['emailNotifications' => true]);
        $smsEnabledUsers = $this->preferencesRepository->count(['smsNotifications' => true]);

        return [
            'total_users' => $totalUsers,
            'personal_signature_users' => $personalSignatureUsers,
            'personal_signature_percentage' => $totalUsers > 0 ? round(($personalSignatureUsers / $totalUsers) * 100, 1) : 0,
            'email_notifications_enabled' => $notificationsEnabledUsers,
            'sms_notifications_enabled' => $smsEnabledUsers,
        ];
    }

    /**
     * Applique les préférences d'affichage d'un utilisateur
     */
    public function applyUserDisplayPreferences(User $user, array $defaultSettings = []): array
    {
        $preferences = $this->getUserPreferences($user);
        
        $settings = $defaultSettings;
        $settings['language'] = $preferences->getLanguage();
        $settings['timezone'] = $preferences->getTimezone();
        $settings['email_notifications'] = $preferences->isEmailNotifications();
        $settings['sms_notifications'] = $preferences->isSmsNotifications();
        
        return $settings;
    }

    /**
     * Import/Export des préférences (pour futures fonctionnalités)
     */
    public function exportUserPreferences(User $user): array
    {
        $preferences = $this->getUserPreferences($user);
        
        return [
            'email_signature_type' => $preferences->getEmailSignatureType(),
            'custom_email_signature' => $preferences->getCustomEmailSignature(),
            'language' => $preferences->getLanguage(),
            'timezone' => $preferences->getTimezone(),
            'email_notifications' => $preferences->isEmailNotifications(),
            'sms_notifications' => $preferences->isSmsNotifications(),
            'dashboard_widgets' => $preferences->getDashboardWidgets(),
            'table_preferences' => $preferences->getTablePreferences(),
            'notes' => $preferences->getNotes(),
            'exported_at' => new \DateTime(),
        ];
    }
}