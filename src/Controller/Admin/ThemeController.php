<?php

namespace App\Controller\Admin;

use App\Service\ThemeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ThemeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ThemeService $themeService
    ) {}

    // ================================
    // ENVIRONMENT & THEMES
    // ================================

    #[Route('/environment', name: 'app_admin_environment', methods: ['GET'])]
    public function environment(): Response
    {
        // Récupérer les paramètres d'environnement actuels
        $currentTheme = [
            'couleur_primaire' => '#007bff',
            'couleur_secondaire' => '#6c757d',
            'logo_url' => null,
            'nom_entreprise' => 'TechnoProd'
        ];
        
        return $this->render('admin/theme/environment.html.twig', [
            'current_theme' => $currentTheme
        ]);
    }

    #[Route('/environment/colors', name: 'app_admin_environment_colors', methods: ['POST'])]
    public function updateColors(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validation basique des couleurs hexadécimales
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['couleur_primaire'] ?? '')) {
                return $this->json(['error' => 'Couleur primaire invalide'], 400);
            }
            
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['couleur_secondaire'] ?? '')) {
                return $this->json(['error' => 'Couleur secondaire invalide'], 400);
            }
            
            // Mise à jour des couleurs via le service
            $this->themeService->updateColors(
                $data['couleur_primaire'],
                $data['couleur_secondaire']
            );
            
            return $this->json([
                'success' => true,
                'message' => 'Couleurs mises à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/environment/logo', name: 'app_admin_environment_logo', methods: ['POST'])]
    public function uploadLogo(Request $request): JsonResponse
    {
        try {
            /** @var UploadedFile $logoFile */
            $logoFile = $request->files->get('logo');
            
            if (!$logoFile) {
                return $this->json(['error' => 'Aucun fichier fourni'], 400);
            }
            
            // Vérifier le type MIME
            $allowedMimes = ['image/jpeg', 'image/png', 'image/svg+xml'];
            if (!in_array($logoFile->getMimeType(), $allowedMimes)) {
                return $this->json(['error' => 'Type de fichier non autorisé'], 400);
            }
            
            // Upload via le service
            $logoPath = $this->themeService->uploadLogo($logoFile);
            
            return $this->json([
                'success' => true,
                'message' => 'Logo téléchargé avec succès',
                'logo_url' => $logoPath
            ]);
        } catch (FileException $e) {
            return $this->json(['error' => 'Erreur lors du téléchargement: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/environment/reset', name: 'app_admin_environment_reset', methods: ['POST'])]
    public function resetTheme(): JsonResponse
    {
        try {
            $this->themeService->resetToDefault();
            
            return $this->json([
                'success' => true,
                'message' => 'Thème réinitialisé aux valeurs par défaut'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la réinitialisation: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/environment/preview', name: 'app_admin_environment_preview', methods: ['POST'])]
    public function previewTheme(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Générer un aperçu HTML avec les couleurs fournies
            $previewHtml = $this->themeService->generatePreview(
                $data['couleur_primaire'] ?? '#007bff',
                $data['couleur_secondaire'] ?? '#6c757d',
                $data['logo_url'] ?? null
            );
            
            return $this->json([
                'success' => true,
                'preview_html' => $previewHtml
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la génération de l\'aperçu: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/theme/societe/{id}', name: 'app_admin_theme_societe', methods: ['GET'])]
    public function getSocieteTheme(int $id): JsonResponse
    {
        try {
            $theme = $this->themeService->getSocieteTheme($id);
            
            return $this->json([
                'theme' => $theme,
                'inherited' => $theme['inherited'] ?? false,
                'parent_societe' => $theme['parent_societe'] ?? null
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération du thème: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/theme/societe/{id}', name: 'app_admin_theme_societe_update', methods: ['POST'])]
    public function updateSocieteTheme(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $this->themeService->updateSocieteTheme($id, [
                'couleur_primaire' => $data['couleur_primaire'] ?? null,
                'couleur_secondaire' => $data['couleur_secondaire'] ?? null,
                'logo' => $data['logo'] ?? null
            ]);
            
            return $this->json([
                'success' => true,
                'message' => 'Thème de la société mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }
}
