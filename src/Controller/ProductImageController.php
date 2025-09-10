<?php

namespace App\Controller;

use App\Entity\ProductImage;
use App\Entity\Produit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/product-images', name: 'app_api_product_images_')]
class ProductImageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger
    ) {}

    #[Route('/upload/{produitId}', name: 'upload', methods: ['POST'])]
    public function upload(int $produitId, Request $request): JsonResponse
    {
        $produit = $this->entityManager->getRepository(Produit::class)->find($produitId);
        if (!$produit) {
            return $this->json(['success' => false, 'message' => 'Produit introuvable'], 404);
        }

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('image');
        if (!$uploadedFile) {
            return $this->json(['success' => false, 'message' => 'Aucune image fournie'], 400);
        }

        // Vérifications
        if (!$this->isValidImageFile($uploadedFile)) {
            return $this->json(['success' => false, 'message' => 'Format d\'image non supporté. Utilisez JPG, PNG ou GIF.'], 400);
        }

        if ($uploadedFile->getSize() > 5 * 1024 * 1024) { // 5MB max
            return $this->json(['success' => false, 'message' => 'L\'image ne peut pas dépasser 5MB'], 400);
        }

        try {
            // Générer nom de fichier unique
            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

            // Créer le dossier s'il n'existe pas
            $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/products';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Déplacer le fichier
            $uploadedFile->move($uploadDir, $newFilename);

            // Récupérer les dimensions de l'image
            $imagePath = $uploadDir.'/'.$newFilename;
            $imageSize = getimagesize($imagePath);
            $width = $imageSize ? $imageSize[0] : null;
            $height = $imageSize ? $imageSize[1] : null;

            // Créer l'entité ProductImage
            $productImage = new ProductImage();
            $productImage->setProduit($produit);
            $productImage->setFilename($newFilename);
            $productImage->setOriginalName($uploadedFile->getClientOriginalName());
            $productImage->setMimeType($uploadedFile->getMimeType());
            $productImage->setFileSize($uploadedFile->getSize());
            $productImage->setWidth($width);
            $productImage->setHeight($height);
            
            // Si c'est la première image, la rendre par défaut
            if ($produit->getImages()->isEmpty()) {
                $productImage->setIsDefault(true);
            }

            $this->entityManager->persist($productImage);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Image uploadée avec succès',
                'image' => [
                    'id' => $productImage->getId(),
                    'filename' => $productImage->getFilename(),
                    'originalName' => $productImage->getOriginalName(),
                    'path' => $productImage->getImagePath(),
                    'width' => $productImage->getWidth(),
                    'height' => $productImage->getHeight(),
                    'isDefault' => $productImage->getIsDefault()
                ]
            ]);

        } catch (FileException $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/produit/{produitId}', name: 'list', methods: ['GET'])]
    public function listByProduct(int $produitId): JsonResponse
    {
        $produit = $this->entityManager->getRepository(Produit::class)->find($produitId);
        if (!$produit) {
            return $this->json(['success' => false, 'message' => 'Produit introuvable'], 404);
        }

        $images = [];
        foreach ($produit->getImages() as $image) {
            $images[] = [
                'id' => $image->getId(),
                'filename' => $image->getFilename(),
                'originalName' => $image->getOriginalName(),
                'path' => $image->getImagePath(),
                'thumbnailPath' => $image->getThumbnailPath(),
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
                'isDefault' => $image->getIsDefault(),
                'alt' => $image->getAlt(),
                'createdAt' => $image->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }

        return $this->json([
            'success' => true,
            'images' => $images,
            'count' => count($images)
        ]);
    }

    #[Route('/{imageId}/default', name: 'set_default', methods: ['PUT'])]
    public function setDefault(int $imageId): JsonResponse
    {
        $image = $this->entityManager->getRepository(ProductImage::class)->find($imageId);
        if (!$image) {
            return $this->json(['success' => false, 'message' => 'Image introuvable'], 404);
        }

        // Définir cette image comme par défaut
        $image->getProduit()->setDefaultImage($image);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Image définie comme image par défaut'
        ]);
    }

    #[Route('/{imageId}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $imageId): JsonResponse
    {
        $image = $this->entityManager->getRepository(ProductImage::class)->find($imageId);
        if (!$image) {
            return $this->json(['success' => false, 'message' => 'Image introuvable'], 404);
        }

        $filePath = $this->getParameter('kernel.project_dir').'/public/uploads/products/'.$image->getFilename();
        
        // Supprimer le fichier physique
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Supprimer de la base
        $this->entityManager->remove($image);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Image supprimée avec succès'
        ]);
    }

    private function isValidImageFile(UploadedFile $file): bool
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($file->getMimeType(), $allowedMimes);
    }
}