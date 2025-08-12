<?php

namespace App\Controller\Admin;

use App\Entity\DocumentTemplate;
use App\Service\ThemeService;
use App\Service\InheritanceService;
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
        private ThemeService $themeService,
        private InheritanceService $inheritanceService
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
            $couleurPrimaire = $request->request->get('couleur_primaire');
            $couleurSecondaire = $request->request->get('couleur_secondaire');
            
            if (!$couleurPrimaire || !$couleurSecondaire) {
                return $this->json(['error' => 'Couleurs manquantes'], 400);
            }
            
            // Validation format hexadécimal
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $couleurPrimaire) || 
                !preg_match('/^#[0-9A-Fa-f]{6}$/', $couleurSecondaire)) {
                return $this->json(['error' => 'Format de couleur invalide'], 400);
            }
            
            // Sauvegarder les couleurs via le service de thème
            $this->themeService->updateColors($couleurPrimaire, $couleurSecondaire);
            
            return $this->json([
                'success' => true,
                'message' => 'Couleurs mises à jour avec succès',
                'couleur_primaire' => $couleurPrimaire,
                'couleur_secondaire' => $couleurSecondaire
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
            
            // Validation du fichier
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
            if (!in_array($logoFile->getMimeType(), $allowedMimeTypes)) {
                return $this->json(['error' => 'Type de fichier non autorisé'], 400);
            }
            
            // Taille maximale : 2MB
            if ($logoFile->getSize() > 2 * 1024 * 1024) {
                return $this->json(['error' => 'Fichier trop volumineux (max 2MB)'], 400);
            }
            
            // Générer un nom unique
            $fileName = uniqid() . '.' . $logoFile->guessExtension();
            
            // Déplacer le fichier
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/logos';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $logoFile->move($uploadDir, $fileName);
            
            $logoUrl = '/uploads/logos/' . $fileName;
            
            // Sauvegarder via le service de thème
            $this->themeService->updateLogo($logoUrl);
            
            return $this->json([
                'success' => true,
                'message' => 'Logo mis à jour avec succès',
                'logo_url' => $logoUrl
            ]);
        } catch (FileException $e) {
            return $this->json(['error' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/environment/logo', name: 'app_admin_environment_logo_delete', methods: ['DELETE'])]
    public function deleteLogo(): JsonResponse
    {
        try {
            $this->themeService->deleteLogo();
            
            return $this->json([
                'success' => true,
                'message' => 'Logo supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/environment/theme', name: 'app_admin_environment_theme', methods: ['POST'])]
    public function updateTheme(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Données JSON invalides'], 400);
            }
            
            $updates = [];
            
            if (isset($data['nom_entreprise'])) {
                $updates['nom_entreprise'] = $data['nom_entreprise'];
            }
            
            if (isset($data['slogan'])) {
                $updates['slogan'] = $data['slogan'];
            }
            
            if (isset($data['couleur_primaire'])) {
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['couleur_primaire'])) {
                    return $this->json(['error' => 'Couleur primaire invalide'], 400);
                }
                $updates['couleur_primaire'] = $data['couleur_primaire'];
            }
            
            if (isset($data['couleur_secondaire'])) {
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['couleur_secondaire'])) {
                    return $this->json(['error' => 'Couleur secondaire invalide'], 400);
                }
                $updates['couleur_secondaire'] = $data['couleur_secondaire'];
            }
            
            // Appliquer les mises à jour via le service de thème
            $this->themeService->updateTheme($updates);
            
            return $this->json([
                'success' => true,
                'message' => 'Thème mis à jour avec succès',
                'updates' => $updates
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/environment/preview-css', name: 'app_admin_environment_preview_css', methods: ['GET'])]
    public function previewCSS(Request $request): Response
    {
        $couleurPrimaire = $request->query->get('primary', '#007bff');
        $couleurSecondaire = $request->query->get('secondary', '#6c757d');
        
        $css = $this->themeService->generateCSS($couleurPrimaire, $couleurSecondaire);
        
        return new Response($css, 200, [
            'Content-Type' => 'text/css'
        ]);
    }

    // ================================
    // TEMPLATES DOCUMENTS
    // ================================

    #[Route('/templates', name: 'app_admin_templates', methods: ['GET'])]
    public function templates(): Response
    {
        $templates = $this->entityManager
            ->getRepository(DocumentTemplate::class)
            ->findBy([], ['type' => 'ASC', 'nom' => 'ASC']);
        
        return $this->render('admin/theme/templates.html.twig', [
            'templates' => $templates
        ]);
    }

    #[Route('/templates/{id}', name: 'app_admin_template_get', methods: ['GET'])]
    public function getTemplate(DocumentTemplate $template): JsonResponse
    {
        return $this->json([
            'id' => $template->getId(),
            'nom' => $template->getNom(),
            'type' => $template->getType(),
            'description' => $template->getDescription(),
            'contenu' => $template->getContenu(),
            'variables' => $template->getVariables(),
            'actif' => $template->isActif(),
            'par_defaut' => $template->isParDefaut(),
            'created_at' => $template->getCreatedAt()?->format('d/m/Y H:i'),
            'updated_at' => $template->getUpdatedAt()?->format('d/m/Y H:i')
        ]);
    }

    #[Route('/templates', name: 'app_admin_template_create', methods: ['POST'])]
    public function createTemplate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom']) || !isset($data['type'])) {
                return $this->json(['error' => 'Nom et type obligatoires'], 400);
            }
            
            $template = new DocumentTemplate();
            $template->setNom($data['nom']);
            $template->setType($data['type']);
            $template->setDescription($data['description'] ?? '');
            $template->setContenu($data['contenu'] ?? '');
            $template->setVariables($data['variables'] ?? []);
            $template->setActif($data['actif'] ?? true);
            $template->setParDefaut($data['par_defaut'] ?? false);
            
            // Si défini comme par défaut, désactiver les autres templates du même type
            if ($template->isParDefaut()) {
                $this->entityManager->createQuery(
                    'UPDATE App\Entity\DocumentTemplate t SET t.parDefaut = false WHERE t.type = :type'
                )->setParameter('type', $template->getType())->execute();
            }
            
            $this->entityManager->persist($template);
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Template créé avec succès',
                'template' => [
                    'id' => $template->getId(),
                    'nom' => $template->getNom(),
                    'type' => $template->getType(),
                    'actif' => $template->isActif(),
                    'par_defaut' => $template->isParDefaut()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/templates/{id}', name: 'app_admin_template_update', methods: ['PUT'])]
    public function updateTemplate(Request $request, DocumentTemplate $template): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $template->setNom($data['nom']);
            }
            if (isset($data['description'])) {
                $template->setDescription($data['description']);
            }
            if (isset($data['contenu'])) {
                $template->setContenu($data['contenu']);
            }
            if (isset($data['variables'])) {
                $template->setVariables($data['variables']);
            }
            if (isset($data['actif'])) {
                $template->setActif($data['actif']);
            }
            
            // Gestion du par défaut
            if (isset($data['par_defaut']) && $data['par_defaut']) {
                // Désactiver les autres templates par défaut du même type
                $this->entityManager->createQuery(
                    'UPDATE App\Entity\DocumentTemplate t SET t.parDefaut = false WHERE t.type = :type AND t.id != :id'
                )->setParameters([
                    'type' => $template->getType(),
                    'id' => $template->getId()
                ])->execute();
                
                $template->setParDefaut(true);
            } elseif (isset($data['par_defaut'])) {
                $template->setParDefaut($data['par_defaut']);
            }
            
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Template mis à jour avec succès',
                'template' => [
                    'id' => $template->getId(),
                    'nom' => $template->getNom(),
                    'type' => $template->getType(),
                    'actif' => $template->isActif(),
                    'par_defaut' => $template->isParDefaut()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/templates/{id}', name: 'app_admin_template_delete', methods: ['DELETE'])]
    public function deleteTemplate(DocumentTemplate $template): JsonResponse
    {
        try {
            // Vérifier que le template n'est pas utilisé
            // TODO: Ajouter vérifications selon les relations métier
            
            $this->entityManager->remove($template);
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Template supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/templates/{id}/set-default', name: 'app_admin_template_set_default', methods: ['POST'])]
    public function setTemplateAsDefault(DocumentTemplate $template): JsonResponse
    {
        try {
            // Désactiver les autres templates par défaut du même type
            $this->entityManager->createQuery(
                'UPDATE App\Entity\DocumentTemplate t SET t.parDefaut = false WHERE t.type = :type'
            )->setParameter('type', $template->getType())->execute();
            
            // Activer celui-ci comme par défaut
            $template->setParDefaut(true);
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Template défini comme défaut avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // INHERITANCE INFO
    // ================================

    #[Route('/inheritance-info', name: 'app_admin_inheritance_info', methods: ['GET'])]
    public function inheritanceInfo(): JsonResponse
    {
        try {
            $inheritanceData = $this->inheritanceService->getInheritanceInfo();
            
            return $this->json([
                'inheritance_data' => $inheritanceData,
                'total_societes' => count($inheritanceData),
                'societes_mere' => count(array_filter($inheritanceData, fn($s) => !$s['parent_id'])),
                'societes_fille' => count(array_filter($inheritanceData, fn($s) => $s['parent_id']))
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()], 500);
        }
    }
}