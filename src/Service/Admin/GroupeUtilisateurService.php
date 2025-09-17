<?php

namespace App\Service\Admin;

use App\Entity\GroupeUtilisateur;
use App\Entity\Societe;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class GroupeUtilisateurService implements GroupeUtilisateurServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getGroupe(int $id): JsonResponse
    {
        $this->logger->info("Récupération du groupe ID: {$id}");
        
        $groupe = $this->entityManager->getRepository(GroupeUtilisateur::class)->find($id);
        
        if (!$groupe) {
            return new JsonResponse(['error' => 'Groupe non trouvé'], 404);
        }

        // Récupérer les sociétés associées
        $societes = [];
        foreach ($groupe->getSocietes() as $societe) {
            $societes[] = [
                'id' => $societe->getId(),
                'nom' => $societe->getNom()
            ];
        }

        return new JsonResponse([
            'id' => $groupe->getId(),
            'nom' => $groupe->getNom(),
            'description' => $groupe->getDescription(),
            'niveau' => $groupe->getNiveau(),
            'couleur' => $groupe->getCouleur(),
            'parent_id' => $groupe->getParent() ? $groupe->getParent()->getId() : null,
            'actif' => $groupe->isActif(),
            'ordre' => $groupe->getOrdre(),
            'permissions' => $groupe->getPermissions(),
            'societes' => $societes
        ]);
    }

    public function updateGroupe(int $id, Request $request): JsonResponse
    {
        $this->logger->info("Mise à jour du groupe ID: {$id}");
        
        $groupe = $this->entityManager->getRepository(GroupeUtilisateur::class)->find($id);
        
        if (!$groupe) {
            return new JsonResponse(['error' => 'Groupe non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs
        if (isset($data['nom'])) {
            $groupe->setNom($data['nom']);
        }
        if (isset($data['description'])) {
            $groupe->setDescription($data['description']);
        }
        if (isset($data['niveau'])) {
            $groupe->setNiveau($data['niveau']);
        }
        if (isset($data['couleur'])) {
            $groupe->setCouleur($data['couleur']);
        }
        if (isset($data['actif'])) {
            $groupe->setActif($data['actif']);
        }
        if (isset($data['parent_id'])) {
            if ($data['parent_id']) {
                $parent = $this->entityManager->getRepository(GroupeUtilisateur::class)->find($data['parent_id']);
                $groupe->setParent($parent);
            } else {
                $groupe->setParent(null);
            }
        }
        if (isset($data['permissions'])) {
            $groupe->setPermissions($data['permissions']);
        }
        if (isset($data['societes'])) {
            // Supprimer toutes les sociétés existantes
            $groupe->getSocietes()->clear();
            
            // Ajouter les nouvelles sociétés
            foreach ($data['societes'] as $societeId) {
                $societe = $this->entityManager->getRepository(Societe::class)->find($societeId);
                if ($societe) {
                    $groupe->addSociete($societe);
                }
            }
        }

        $this->entityManager->flush();
        $this->logger->info("Groupe {$groupe->getNom()} mis à jour avec succès");

        return new JsonResponse([
            'success' => true,
            'message' => 'Groupe mis à jour avec succès'
        ]);
    }

    public function createGroupe(Request $request): JsonResponse
    {
        $this->logger->info("Création d'un nouveau groupe");
        
        $data = json_decode($request->getContent(), true);

        $groupe = new GroupeUtilisateur();
        $groupe->setNom($data['nom']);
        $groupe->setDescription($data['description'] ?? '');
        $groupe->setNiveau($data['niveau'] ?? 1);
        $groupe->setCouleur($data['couleur'] ?? '#6c757d');
        $groupe->setActif($data['actif'] ?? true);
        $groupe->setOrdre($data['ordre'] ?? 0);
        $groupe->setPermissions($data['permissions'] ?? []);

        if (isset($data['parent_id']) && $data['parent_id']) {
            $parent = $this->entityManager->getRepository(GroupeUtilisateur::class)->find($data['parent_id']);
            $groupe->setParent($parent);
        }

        $this->entityManager->persist($groupe);
        $this->entityManager->flush();
        
        $this->logger->info("Groupe {$groupe->getNom()} créé avec succès, ID: {$groupe->getId()}");

        return new JsonResponse([
            'success' => true,
            'message' => 'Groupe créé avec succès',
            'id' => $groupe->getId()
        ]);
    }

    public function deleteGroupe(int $id): JsonResponse
    {
        $this->logger->info("Suppression du groupe ID: {$id}");
        
        $groupe = $this->entityManager->getRepository(GroupeUtilisateur::class)->find($id);
        
        if (!$groupe) {
            return new JsonResponse(['error' => 'Groupe non trouvé'], 404);
        }

        // Vérifier si le groupe est utilisé par des utilisateurs
        $usersCount = $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->join('u.groupes', 'g')
            ->where('g.id = :groupeId')
            ->setParameter('groupeId', $id)
            ->getQuery()
            ->getSingleScalarResult();

        if ($usersCount > 0) {
            $this->logger->warning("Tentative de suppression du groupe {$groupe->getNom()} utilisé par {$usersCount} utilisateur(s)");
            return new JsonResponse([
                'error' => "Impossible de supprimer ce groupe car il est utilisé par {$usersCount} utilisateur(s)"
            ], 400);
        }

        $groupeName = $groupe->getNom();
        $this->entityManager->remove($groupe);
        $this->entityManager->flush();
        
        $this->logger->info("Groupe {$groupeName} supprimé avec succès");

        return new JsonResponse([
            'success' => true,
            'message' => 'Groupe supprimé avec succès'
        ]);
    }

    public function toggleGroupe(int $id): JsonResponse
    {
        $this->logger->info("Basculement de l'état du groupe ID: {$id}");
        
        $groupe = $this->entityManager->getRepository(GroupeUtilisateur::class)->find($id);
        
        if (!$groupe) {
            return new JsonResponse(['error' => 'Groupe non trouvé'], 404);
        }

        $oldState = $groupe->isActif();
        $groupe->setActif(!$oldState);
        $this->entityManager->flush();
        
        $newState = $groupe->isActif() ? 'activé' : 'désactivé';
        $this->logger->info("Groupe {$groupe->getNom()} {$newState}");

        return new JsonResponse([
            'success' => true,
            'message' => $groupe->isActif() ? 'Groupe activé' : 'Groupe désactivé'
        ]);
    }
}