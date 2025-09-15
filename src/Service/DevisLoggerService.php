<?php

namespace App\Service;

use App\Entity\Devis;
use App\Entity\DevisLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class DevisLoggerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Log une action sur un devis
     */
    public function log(Devis $devis, string $action, ?string $details = null, ?User $user = null): DevisLog
    {
        $log = new DevisLog();
        $log->setDevis($devis);
        $log->setAction($action);
        $log->setDetails($details);
        
        // Utiliser l'utilisateur fourni ou l'utilisateur actuel
        $logUser = $user ?? $this->security->getUser();
        if ($logUser instanceof User) {
            $log->setUser($logUser);
        }
        
        // Capturer l'adresse IP si disponible
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $log->setIpAddress($request->getClientIp());
        }
        
        $this->entityManager->persist($log);
        $this->entityManager->flush();
        
        return $log;
    }

    /**
     * Log la création d'un devis
     */
    public function logCreated(Devis $devis, ?User $user = null): DevisLog
    {
        $details = sprintf(
            'Devis %s créé pour le client %s - Montant TTC: %s€',
            $devis->getNumeroDevis(),
            $devis->getClient()?->getNom() ?? 'Non défini',
            $devis->getTotalTtc()
        );
        
        return $this->log($devis, 'created', $details, $user);
    }

    /**
     * Log la modification d'un devis
     */
    public function logUpdated(Devis $devis, ?array $changes = null, ?User $user = null): DevisLog
    {
        $details = null;
        
        if ($changes) {
            $changeDetails = [];
            foreach ($changes as $field => $change) {
                if (isset($change['old']) && isset($change['new'])) {
                    $changeDetails[] = sprintf(
                        '%s: %s → %s',
                        $field,
                        $change['old'],
                        $change['new']
                    );
                }
            }
            if (!empty($changeDetails)) {
                $details = implode(', ', $changeDetails);
            }
        }
        
        return $this->log($devis, 'updated', $details, $user);
    }

    /**
     * Log l'envoi d'un devis
     */
    public function logSent(Devis $devis, string $email, ?User $user = null): DevisLog
    {
        $details = sprintf(
            'Devis envoyé à %s - %s',
            $email,
            $devis->getDateEnvoi()?->format('d/m/Y H:i')
        );
        
        return $this->log($devis, 'sent', $details, $user);
    }

    /**
     * Log le renvoi d'un devis
     */
    public function logResent(Devis $devis, string $email, ?User $user = null): DevisLog
    {
        $details = sprintf(
            'Devis renvoyé à %s - %s',
            $email,
            $devis->getDateEnvoi()?->format('d/m/Y H:i')
        );
        
        return $this->log($devis, 'resent', $details, $user);
    }

    /**
     * Log la signature d'un devis
     */
    public function logSigned(Devis $devis, string $signerName, string $signerEmail): DevisLog
    {
        $details = sprintf(
            'Devis signé par %s (%s) le %s',
            $signerName,
            $signerEmail,
            $devis->getDateSignature()?->format('d/m/Y H:i')
        );
        
        return $this->log($devis, 'signed', $details);
    }

    /**
     * Log la création d'une version
     */
    public function logVersionCreated(Devis $devis, int $versionNumber, ?string $versionName = null, ?User $user = null): DevisLog
    {
        $details = sprintf(
            'Version %d créée%s',
            $versionNumber,
            $versionName ? ' - ' . $versionName : ''
        );
        
        return $this->log($devis, 'version_created', $details, $user);
    }

    /**
     * Log le changement de statut
     */
    public function logStatusChanged(Devis $devis, string $oldStatus, string $newStatus, ?User $user = null): DevisLog
    {
        $statusLabels = [
            'brouillon' => 'Brouillon',
            'envoye' => 'Envoyé',
            'relance' => 'Relancé',
            'signe' => 'Signé',
            'acompte_regle' => 'Acompte réglé',
            'accepte' => 'Accepté',
            'refuse' => 'Refusé',
            'expire' => 'Expiré'
        ];
        
        $details = sprintf(
            'Statut modifié: %s → %s',
            $statusLabels[$oldStatus] ?? $oldStatus,
            $statusLabels[$newStatus] ?? $newStatus
        );
        
        return $this->log($devis, 'status_changed', $details, $user);
    }

    /**
     * Log la conversion en commande
     */
    public function logConvertedToOrder(Devis $devis, string $orderNumber, ?User $user = null): DevisLog
    {
        $details = sprintf(
            'Devis converti en commande %s',
            $orderNumber
        );
        
        return $this->log($devis, 'converted_to_order', $details, $user);
    }

    /**
     * Log la conversion en facture
     */
    public function logConvertedToInvoice(Devis $devis, string $invoiceNumber, ?User $user = null): DevisLog
    {
        $details = sprintf(
            'Devis converti en facture %s',
            $invoiceNumber
        );
        
        return $this->log($devis, 'converted_to_invoice', $details, $user);
    }

    /**
     * Log la réception d'un paiement
     */
    public function logPaymentReceived(Devis $devis, string $amount, string $method, ?User $user = null): DevisLog
    {
        $details = sprintf(
            'Paiement reçu: %s€ par %s le %s',
            $amount,
            $method,
            $devis->getDatePaiementAcompte()?->format('d/m/Y H:i')
        );
        
        return $this->log($devis, 'payment_received', $details, $user);
    }

    /**
     * Log l'annulation d'un devis
     */
    public function logCancelled(Devis $devis, ?string $reason = null, ?User $user = null): DevisLog
    {
        $details = 'Devis annulé';
        if ($reason) {
            $details .= ' - Raison: ' . $reason;
        }
        
        return $this->log($devis, 'cancelled', $details, $user);
    }

    /**
     * Récupère tous les logs d'un devis
     */
    public function getDevisLogs(Devis $devis): array
    {
        return $this->entityManager->getRepository(DevisLog::class)
            ->createQueryBuilder('dl')
            ->where('dl.devis = :devis')
            ->setParameter('devis', $devis)
            ->orderBy('dl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les logs d'un devis avec pagination
     */
    public function getDevisLogsPaginated(Devis $devis, int $limit = 20, int $offset = 0): array
    {
        return $this->entityManager->getRepository(DevisLog::class)
            ->createQueryBuilder('dl')
            ->where('dl.devis = :devis')
            ->setParameter('devis', $devis)
            ->orderBy('dl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de logs pour un devis
     */
    public function countDevisLogs(Devis $devis): int
    {
        return $this->entityManager->getRepository(DevisLog::class)
            ->createQueryBuilder('dl')
            ->select('COUNT(dl.id)')
            ->where('dl.devis = :devis')
            ->setParameter('devis', $devis)
            ->getQuery()
            ->getSingleScalarResult();
    }
}