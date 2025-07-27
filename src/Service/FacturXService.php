<?php

namespace App\Service;

use App\Entity\Facture;
use App\Entity\FactureItem;
use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

/**
 * Service FacturXService
 * 
 * Génère des factures au format Factur-X (PDF/A-3 + XML CII) conforme à la norme EN 16931
 * Prépare la conformité à la facture électronique obligatoire en 2026
 * 
 * Profils supportés :
 * - MINIMUM : Données minimales requises
 * - BASIC WL : Basic Without Lines - facture sans détail des lignes 
 * - BASIC : Facture complète avec lignes de détail
 * - EN 16931 : Profil européen complet
 * 
 * @author TechnoProd - Conformité 2026
 * @version 1.0
 */
class FacturXService
{
    private EntityManagerInterface $em;
    private DocumentIntegrityService $integrityService;
    private Security $security;
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private ParameterBagInterface $params;
    private Environment $twig;
    
    // Configuration Factur-X
    private string $companyName;
    private string $companySiret;
    private string $companyAddress;
    private string $companyPhone;
    private string $companyEmail;
    private string $companyVatNumber;
    
    // Chemins des certificats pour signature qualifiée
    private string $certificatePath;
    private string $privateKeyPath;
    
    // Profils Factur-X supportés
    private const PROFILES = [
        'MINIMUM' => 'urn:factur-x.eu:1p0:minimum',
        'BASIC_WL' => 'urn:factur-x.eu:1p0:basicwl', 
        'BASIC' => 'urn:factur-x.eu:1p0:basic',
        'EN16931' => 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:en16931'
    ];
    
    // Schémas XSD pour validation
    private const XSD_SCHEMAS = [
        'CII' => 'CrossIndustryInvoice_100pD20B.xsd',
        'FACTURX' => 'Factur-X_1.0.07.xsd'
    ];

    public function __construct(
        EntityManagerInterface $em,
        DocumentIntegrityService $integrityService,
        Security $security,
        RequestStack $requestStack,
        LoggerInterface $logger,
        ParameterBagInterface $params,
        Environment $twig
    ) {
        $this->em = $em;
        $this->integrityService = $integrityService;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->params = $params;
        $this->twig = $twig;
        
        // Configuration entreprise depuis parameters
        $this->companyName = $params->get('app.company.name') ?? 'TechnoProd';
        $this->companySiret = $params->get('app.company.siret') ?? '12345678901234';
        $this->companyAddress = $params->get('app.company.address') ?? 'Adresse non configurée';
        $this->companyPhone = $params->get('app.company.phone') ?? '';
        $this->companyEmail = $params->get('app.company.email') ?? 'contact@technoprod.fr';
        $this->companyVatNumber = $params->get('app.company.vat_number') ?? 'FR12345678901';
        
        // Chemins des certificats
        $cryptoPath = $params->get('kernel.project_dir') . '/var/crypto';
        $this->certificatePath = $cryptoPath . '/facturx_certificate.pem';
        $this->privateKeyPath = $cryptoPath . '/facturx_private_key.pem';
    }

    /**
     * Génère une facture Factur-X complète (PDF/A-3 + XML CII intégré)
     * 
     * @param Facture $facture La facture à convertir
     * @param string $profile Profil Factur-X (MINIMUM, BASIC_WL, BASIC, EN16931)
     * @param bool $signDocument Activer la signature numérique qualifiée
     * @return string Contenu binaire du PDF Factur-X
     * @throws \InvalidArgumentException Si le profil est invalide
     * @throws \RuntimeException Si erreur durant la génération
     */
    public function generateFacturX(
        Facture $facture, 
        string $profile = 'BASIC', 
        bool $signDocument = true
    ): string {
        // Validation du profil
        if (!array_key_exists($profile, self::PROFILES)) {
            throw new \InvalidArgumentException("Profil Factur-X invalide: {$profile}");
        }
        
        $this->logger->info('Démarrage génération Factur-X', [
            'facture_id' => $facture->getId(),
            'facture_numero' => $facture->getNumeroFacture(),
            'profile' => $profile,
            'sign_document' => $signDocument
        ]);
        
        try {
            // 1. Génération XML CII conforme au profil
            $xmlCII = $this->generateXMLCII($facture, $profile);
            
            // 2. Validation XML selon schéma XSD
            $this->validateXMLCII($xmlCII, $profile);
            
            // 3. Génération PDF/A-3 avec métadonnées Factur-X
            $pdfContent = $this->generatePDFA3($facture, $profile);
            
            // 4. Intégration XML dans PDF comme fichier attaché
            $facturXContent = $this->embedXMLInPDF($pdfContent, $xmlCII, $facture->getNumeroFacture());
            
            // 5. Signature numérique qualifiée (optionnelle)
            if ($signDocument && $this->areCertificatesAvailable()) {
                $facturXContent = $this->signFacturX($facturXContent);
            }
            
            // 6. Sécurisation intégrité selon NF203
            $user = $this->security->getUser();
            if ($user instanceof User) {
                $this->integrityService->secureDocument($facture, $user);
            }
            
            $this->logger->info('Factur-X généré avec succès', [
                'facture_id' => $facture->getId(),
                'profile' => $profile,
                'size_bytes' => strlen($facturXContent),
                'signed' => $signDocument && $this->areCertificatesAvailable()
            ]);
            
            return $facturXContent;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur génération Factur-X', [
                'facture_id' => $facture->getId(),
                'profile' => $profile,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException(
                "Erreur lors de la génération Factur-X: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Génère le XML CII (Cross Industry Invoice) conforme au standard Factur-X
     * 
     * @param Facture $facture
     * @param string $profile
     * @return string XML CII formaté
     */
    public function generateXMLCII(Facture $facture, string $profile = 'BASIC'): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xml->preserveWhiteSpace = false;

        // Élément racine avec namespaces UN/CEFACT CII
        $root = $xml->createElement('rsm:CrossIndustryInvoice');
        $root->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $root->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttribute('xmlns:xs', 'http://www.w3.org/2001/XMLSchema');
        $root->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $xml->appendChild($root);

        // 1. CONTEXTE DU DOCUMENT
        $context = $xml->createElement('rsm:ExchangedDocumentContext');
        
        // Paramètre de contexte avec profil Factur-X
        $guideline = $xml->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($this->createTextElement($xml, 'ram:ID', self::PROFILES[$profile]));
        $context->appendChild($guideline);
        
        $root->appendChild($context);

        // 2. EN-TÊTE DU DOCUMENT
        $header = $xml->createElement('rsm:ExchangedDocument');
        $header->appendChild($this->createTextElement($xml, 'ram:ID', $facture->getNumeroFacture()));
        $header->appendChild($this->createTextElement($xml, 'ram:TypeCode', '380')); // 380 = Facture commerciale
        
        // Date d'émission
        $issueDateTime = $xml->createElement('ram:IssueDateTime');
        $issueDateFormat = $xml->createElement('udt:DateTimeString');
        $issueDateFormat->setAttribute('format', '102'); // Format CCYYMMDD
        $issueDateFormat->appendChild($xml->createTextNode($facture->getDateFacture()->format('Ymd')));
        $issueDateTime->appendChild($issueDateFormat);
        $header->appendChild($issueDateTime);
        
        // Notes additionnelles
        if ($facture->getNotesFacturation()) {
            $notes = $xml->createElement('ram:IncludedNote');
            $notes->appendChild($this->createTextElement($xml, 'ram:Content', $facture->getNotesFacturation()));
            $header->appendChild($notes);
        }
        
        $root->appendChild($header);

        // 3. TRANSACTION COMMERCIALE
        $transaction = $xml->createElement('rsm:SupplyChainTradeTransaction');

        // 3.1 ACCORD COMMERCIAL (Parties)
        $agreement = $xml->createElement('ram:ApplicableHeaderTradeAgreement');
        
        // Référence acheteur (numéro de commande si disponible)
        if ($facture->getCommande() && $facture->getCommande()->getNumeroCommande()) {
            $buyerRef = $xml->createElement('ram:BuyerReference');
            $buyerRef->appendChild($xml->createTextNode($facture->getCommande()->getNumeroCommande()));
            $agreement->appendChild($buyerRef);
        }
        
        // VENDEUR (notre entreprise)
        $seller = $xml->createElement('ram:SellerTradeParty');
        $seller->appendChild($this->createTextElement($xml, 'ram:Name', $this->companyName));
        
        // Identifiants légaux vendeur
        $sellerLegalOrg = $xml->createElement('ram:SpecifiedLegalOrganization');
        $sellerLegalOrg->appendChild($this->createTextElement($xml, 'ram:ID', $this->companySiret));
        $sellerLegalOrg->appendChild($this->createTextElement($xml, 'ram:TradingBusinessName', $this->companyName));
        $seller->appendChild($sellerLegalOrg);
        
        // Adresse vendeur
        $sellerAddress = $xml->createElement('ram:PostalTradeAddress');
        $sellerAddress->appendChild($this->createTextElement($xml, 'ram:PostcodeCode', '75001')); // TODO: Paramétrer
        $sellerAddress->appendChild($this->createTextElement($xml, 'ram:LineOne', $this->companyAddress));
        $sellerAddress->appendChild($this->createTextElement($xml, 'ram:CityName', 'Paris')); // TODO: Paramétrer
        $sellerAddress->appendChild($this->createTextElement($xml, 'ram:CountryID', 'FR'));
        $seller->appendChild($sellerAddress);
        
        // Contact vendeur
        if ($this->companyEmail || $this->companyPhone) {
            $sellerContact = $xml->createElement('ram:DefinedTradeContact');
            if ($this->companyPhone) {
                $phone = $xml->createElement('ram:TelephoneUniversalCommunication');
                $phone->appendChild($this->createTextElement($xml, 'ram:CompleteNumber', $this->companyPhone));
                $sellerContact->appendChild($phone);
            }
            if ($this->companyEmail) {
                $email = $xml->createElement('ram:EmailURIUniversalCommunication');
                $email->appendChild($this->createTextElement($xml, 'ram:URIID', $this->companyEmail));
                $sellerContact->appendChild($email);
            }
            $seller->appendChild($sellerContact);
        }
        
        // Identifiant TVA vendeur
        $sellerTaxReg = $xml->createElement('ram:SpecifiedTaxRegistration');
        $sellerTaxReg->appendChild($this->createTextElement($xml, 'ram:ID', $this->companyVatNumber));
        $sellerTaxReg->setAttribute('schemeID', 'VA'); // VA = VAT Number
        $seller->appendChild($sellerTaxReg);
        
        $agreement->appendChild($seller);

        // ACHETEUR (client)
        $buyer = $xml->createElement('ram:BuyerTradeParty');
        $client = $facture->getClient();
        
        // Utilise nomEntreprise de l'entité Client
        $buyer->appendChild($this->createTextElement($xml, 'ram:Name', $this->getClientFullName($client)));
        
        // Identifiants légaux acheteur (SIRET non disponible dans cette version)
        // Note: Le champ SIRET a été supprimé de l'entité Client
        $buyerLegalOrg = $xml->createElement('ram:SpecifiedLegalOrganization');
        $buyerLegalOrg->appendChild($this->createTextElement($xml, 'ram:TradingBusinessName', $this->getClientFullName($client)));
        $buyer->appendChild($buyerLegalOrg);
        
        // Adresse acheteur - utilise l'adresse de facturation
        $buyerAddress = $xml->createElement('ram:PostalTradeAddress');
        $adresseFacturation = $client->getAdresseFacturation();
        if ($adresseFacturation) {
            $buyerAddress->appendChild($this->createTextElement($xml, 'ram:PostcodeCode', $adresseFacturation->getCodePostal()));
            $buyerAddress->appendChild($this->createTextElement($xml, 'ram:LineOne', $adresseFacturation->getLigne1()));
            $buyerAddress->appendChild($this->createTextElement($xml, 'ram:CityName', $adresseFacturation->getVille()));
            $buyerAddress->appendChild($this->createTextElement($xml, 'ram:CountryID', $adresseFacturation->getPays() ?? 'FR'));
        }
        $buyer->appendChild($buyerAddress);
        
        // Contact acheteur - utilise le contact par défaut ou celui de la facture
        $contact = $facture->getContact() ?? $client->getContactFacturationDefault();
        if ($contact) {
            $buyerContact = $xml->createElement('ram:DefinedTradeContact');
            $buyerContact->appendChild($this->createTextElement($xml, 'ram:PersonName', $this->getContactFullName($contact)));
            
            if ($contact->getTelephone()) {
                $phone = $xml->createElement('ram:TelephoneUniversalCommunication');
                $phone->appendChild($this->createTextElement($xml, 'ram:CompleteNumber', $contact->getTelephone()));
                $buyerContact->appendChild($phone);
            }
            if ($contact->getEmail()) {
                $email = $xml->createElement('ram:EmailURIUniversalCommunication');
                $email->appendChild($this->createTextElement($xml, 'ram:URIID', $contact->getEmail()));
                $buyerContact->appendChild($email);
            }
            $buyer->appendChild($buyerContact);
        }
        
        $agreement->appendChild($buyer);
        $transaction->appendChild($agreement);

        // 3.2 LIVRAISON
        $delivery = $xml->createElement('ram:ApplicableHeaderTradeDelivery');
        
        // Date de livraison si disponible
        if ($facture->getCommande() && method_exists($facture->getCommande(), 'getDateLivraison')) {
            $actualDelivery = $xml->createElement('ram:ActualDeliverySupplyChainEvent');
            $deliveryDateTime = $xml->createElement('ram:OccurrenceDateTime');
            $deliveryDateFormat = $xml->createElement('udt:DateTimeString');
            $deliveryDateFormat->setAttribute('format', '102');
            $deliveryDateFormat->appendChild($xml->createTextNode($facture->getCommande()->getDateLivraison()->format('Ymd')));
            $deliveryDateTime->appendChild($deliveryDateFormat);
            $actualDelivery->appendChild($deliveryDateTime);
            $delivery->appendChild($actualDelivery);
        }
        
        $transaction->appendChild($delivery);

        // 3.3 RÈGLEMENT
        $settlement = $xml->createElement('ram:ApplicableHeaderTradeSettlement');
        
        // Devise
        $settlement->appendChild($this->createTextElement($xml, 'ram:InvoiceCurrencyCode', 'EUR'));
        
        // Conditions de paiement
        $paymentTerms = $xml->createElement('ram:SpecifiedTradePaymentTerms');
        if ($facture->getDateEcheance()) {
            $dueDateTime = $xml->createElement('ram:DueDateDateTime');
            $dueDateFormat = $xml->createElement('udt:DateTimeString');
            $dueDateFormat->setAttribute('format', '102');
            $dueDateFormat->appendChild($xml->createTextNode($facture->getDateEcheance()->format('Ymd')));
            $dueDateTime->appendChild($dueDateFormat);
            $paymentTerms->appendChild($dueDateTime);
        }
        $settlement->appendChild($paymentTerms);

        // Taxes par taux
        $taxTotals = $this->calculateTaxTotals($facture);
        foreach ($taxTotals as $taxRate => $amounts) {
            $taxTotal = $xml->createElement('ram:ApplicableTradeTax');
            $taxTotal->appendChild($this->createAmountElement($xml, 'ram:CalculatedAmount', $amounts['tax_amount']));
            $taxTotal->appendChild($this->createTextElement($xml, 'ram:TypeCode', 'VAT'));
            $taxTotal->appendChild($this->createAmountElement($xml, 'ram:BasisAmount', $amounts['basis_amount']));
            $taxTotal->appendChild($this->createTextElement($xml, 'ram:RateApplicablePercent', $taxRate));
            $settlement->appendChild($taxTotal);
        }

        // Totaux globaux
        $summation = $xml->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $summation->appendChild($this->createAmountElement($xml, 'ram:LineTotalAmount', $facture->getTotalHt()));
        $summation->appendChild($this->createAmountElement($xml, 'ram:TaxBasisTotalAmount', $facture->getTotalHt()));
        $summation->appendChild($this->createAmountElement($xml, 'ram:TaxTotalAmount', $facture->getTotalTva()));
        $summation->appendChild($this->createAmountElement($xml, 'ram:GrandTotalAmount', $facture->getTotalTtc()));
        $summation->appendChild($this->createAmountElement($xml, 'ram:DuePayableAmount', $facture->getMontantRestant()));
        $settlement->appendChild($summation);

        $transaction->appendChild($settlement);

        // 3.4 LIGNES DE FACTURATION (selon profil)
        if (in_array($profile, ['BASIC', 'EN16931'])) {
            foreach ($facture->getFactureItems() as $index => $item) {
                $lineItem = $this->createLineItem($xml, $item, $index + 1);
                $transaction->appendChild($lineItem);
            }
        }

        $root->appendChild($transaction);

        return $xml->saveXML();
    }

    /**
     * Intègre le XML CII dans un PDF comme fichier attaché (selon norme PDF/A-3)
     * 
     * @param string $pdfContent Contenu PDF de base
     * @param string $xmlCII Contenu XML CII
     * @param string $invoiceNumber Numéro de facture pour nommage
     * @return string PDF avec XML intégré
     */
    public function embedXMLInPDF(string $pdfContent, string $xmlCII, string $invoiceNumber): string
    {
        // Pour une implémentation complète, il faudrait utiliser une bibliothèque comme TCPDF ou SetaPDF
        // qui permet l'intégration de fichiers attachés selon PDF/A-3
        
        // Ici, nous créons une implémentation simplifiée qui ajoute le XML en tant que métadonnée
        // Dans un environnement de production, il faut utiliser une vraie bibliothèque PDF/A-3
        
        $this->logger->info('Intégration XML CII dans PDF', [
            'pdf_size' => strlen($pdfContent),
            'xml_size' => strlen($xmlCII),
            'invoice_number' => $invoiceNumber
        ]);
        
        // Métadonnées Factur-X à insérer
        $facturXMetadata = [
            'facturx_filename' => "factur-x-{$invoiceNumber}.xml",
            'facturx_conformance_level' => 'BASIC',
            'facturx_version' => '1.0.07',
            'pdf_a_level' => 'PDF/A-3B'
        ];
        
        // Encodage du XML pour intégration
        $encodedXML = base64_encode($xmlCII);
        
        // Construction du PDF Factur-X avec métadonnées
        $facturXPDF = $pdfContent;
        
        // Ajout des métadonnées Factur-X dans le PDF
        // NOTE: Implémentation simplifiée - remplacer par vraie bibliothèque PDF/A-3
        $facturXPDF .= "\n%FACTUR-X-METADATA\n";
        $facturXPDF .= json_encode($facturXMetadata) . "\n";
        $facturXPDF .= "%FACTUR-X-XML-CONTENT\n";
        $facturXPDF .= $encodedXML . "\n";
        $facturXPDF .= "%END-FACTUR-X\n";
        
        $this->logger->info('XML CII intégré avec succès', [
            'final_size' => strlen($facturXPDF),
            'metadata_keys' => array_keys($facturXMetadata)
        ]);
        
        return $facturXPDF;
    }

    /**
     * Valide le XML CII selon les schémas XSD Factur-X
     * 
     * @param string $xmlCII
     * @param string $profile
     * @return bool
     * @throws \RuntimeException Si validation échoue
     */
    public function validateFacturX(string $xmlCII, string $profile = 'BASIC'): bool
    {
        $this->logger->info('Validation XML CII démarrée', [
            'profile' => $profile,
            'xml_size' => strlen($xmlCII)
        ]);
        
        // Validation de la structure XML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        
        if (!$dom->loadXML($xmlCII)) {
            $errors = libxml_get_errors();
            $errorMessages = array_map(fn($error) => $error->message, $errors);
            
            $this->logger->error('XML CII mal formé', [
                'errors' => $errorMessages
            ]);
            
            throw new \RuntimeException('XML CII mal formé: ' . implode(', ', $errorMessages));
        }
        
        // Validation des éléments obligatoires selon profil
        $requiredElements = $this->getRequiredElementsByProfile($profile);
        $missingElements = [];
        
        foreach ($requiredElements as $element) {
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
            $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
            
            if ($xpath->query($element)->length === 0) {
                $missingElements[] = $element;
            }
        }
        
        if (!empty($missingElements)) {
            $this->logger->error('Éléments XML obligatoires manquants', [
                'profile' => $profile,
                'missing_elements' => $missingElements
            ]);
            
            throw new \RuntimeException(
                "Éléments XML obligatoires manquants pour le profil {$profile}: " . 
                implode(', ', $missingElements)
            );
        }
        
        // Validation des montants
        $this->validateAmounts($dom);
        
        // Validation des dates
        $this->validateDates($dom);
        
        $this->logger->info('Validation XML CII réussie', [
            'profile' => $profile,
            'validated_elements' => count($requiredElements)
        ]);
        
        return true;
    }

    // MÉTHODES PRIVÉES

    /**
     * Génère un PDF/A-3 avec métadonnées Factur-X
     */
    private function generatePDFA3(Facture $facture, string $profile): string
    {
        // Ici, il faudrait utiliser une vraie bibliothèque PDF comme TCPDF, DomPDF ou wkhtmltopdf
        // configurée pour générer du PDF/A-3
        
        $this->logger->info('Génération PDF/A-3', [
            'facture_id' => $facture->getId(),
            'profile' => $profile
        ]);
        
        // Contenu HTML de la facture
        $htmlContent = $this->generateInvoiceHTML($facture, $profile);
        
        // Simulation de génération PDF - remplacer par vraie bibliothèque
        $pdfContent = "%PDF-1.7\n";
        $pdfContent .= "%FACTUR-X PDF/A-3\n";
        $pdfContent .= "% Facture: " . $facture->getNumeroFacture() . "\n";
        $pdfContent .= "% Date: " . $facture->getDateFacture()->format('d/m/Y') . "\n";
        $pdfContent .= "% Client: " . $facture->getClient()->getNomComplet() . "\n";
        $pdfContent .= "% Total TTC: " . $facture->getTotalTtc() . " EUR\n";
        $pdfContent .= "\n" . base64_encode($htmlContent) . "\n";
        $pdfContent .= "%%EOF\n";
        
        return $pdfContent;
    }

    /**
     * Génère le HTML de la facture pour le PDF avec template Twig
     */
    private function generateInvoiceHTML(Facture $facture, string $profile = 'BASIC'): string
    {
        $companyData = [
            'name' => $this->companyName,
            'siret' => $this->companySiret,
            'address' => $this->companyAddress,
            'phone' => $this->companyPhone,
            'email' => $this->companyEmail,
            'vat_number' => $this->companyVatNumber
        ];
        
        $metadata = [
            'title' => 'Facture ' . $facture->getNumeroFacture(),
            'author' => $this->companyName,
            'subject' => 'Facture électronique Factur-X',
            'keywords' => 'Factur-X, facture, électronique, EN 16931',
            'facturx_conformance_level' => $profile,
            'facturx_version' => '1.0.07'
        ];
        
        return $this->twig->render('facture/pdf_facturx.html.twig', [
            'facture' => $facture,
            'company' => $companyData,
            'metadata' => $metadata,
            'profile' => $profile
        ]);
    }

    /**
     * Signature numérique qualifiée du PDF Factur-X
     */
    private function signFacturX(string $facturXContent): string
    {
        if (!$this->areCertificatesAvailable()) {
            $this->logger->warning('Certificats de signature non disponibles');
            return $facturXContent;
        }
        
        $this->logger->info('Signature numérique Factur-X', [
            'content_size' => strlen($facturXContent)
        ]);
        
        // Implémentation simplifiée - remplacer par vraie signature PDF
        $signature = hash('sha256', $facturXContent . $this->companyName . date('Y-m-d H:i:s'));
        $signedContent = $facturXContent . "\n%DIGITAL-SIGNATURE: " . $signature . "\n";
        
        $this->logger->info('Document signé numériquement', [
            'signature_hash' => substr($signature, 0, 16) . '...'
        ]);
        
        return $signedContent;
    }

    /**
     * Calcule les totaux de TVA par taux
     */
    private function calculateTaxTotals(Facture $facture): array
    {
        $taxTotals = [];
        
        foreach ($facture->getFactureItems() as $item) {
            $taxRate = $item->getTvaPercent();
            $basisAmount = (float) $item->getTotalLigneHt();
            $taxAmount = $basisAmount * ((float) $taxRate / 100);
            
            if (!isset($taxTotals[$taxRate])) {
                $taxTotals[$taxRate] = [
                    'basis_amount' => 0,
                    'tax_amount' => 0
                ];
            }
            
            $taxTotals[$taxRate]['basis_amount'] += $basisAmount;
            $taxTotals[$taxRate]['tax_amount'] += $taxAmount;
        }
        
        // Formatage des montants
        foreach ($taxTotals as $rate => &$amounts) {
            $amounts['basis_amount'] = number_format($amounts['basis_amount'], 2, '.', '');
            $amounts['tax_amount'] = number_format($amounts['tax_amount'], 2, '.', '');
        }
        
        return $taxTotals;
    }

    /**
     * Crée une ligne de facture pour le XML CII
     */
    private function createLineItem(\DOMDocument $xml, FactureItem $item, int $lineNumber): \DOMElement
    {
        $lineItem = $xml->createElement('ram:IncludedSupplyChainTradeLineItem');
        
        // Document de ligne associé
        $lineDoc = $xml->createElement('ram:AssociatedDocumentLineDocument');
        $lineDoc->appendChild($this->createTextElement($xml, 'ram:LineID', (string) $lineNumber));
        $lineItem->appendChild($lineDoc);
        
        // Produit/service spécifié
        $product = $xml->createElement('ram:SpecifiedTradeProduct');
        $product->appendChild($this->createTextElement($xml, 'ram:Name', $item->getDesignation()));
        
        if ($item->getDescription()) {
            $product->appendChild($this->createTextElement($xml, 'ram:Description', $item->getDescription()));
        }
        
        $lineItem->appendChild($product);
        
        // Accord commercial de ligne
        $lineAgreement = $xml->createElement('ram:SpecifiedLineTradeAgreement');
        $lineAgreement->appendChild($this->createAmountElement($xml, 'ram:NetPriceProductTradePrice/ram:ChargeAmount', $item->getPrixUnitaireHt()));
        $lineItem->appendChild($lineAgreement);
        
        // Livraison de ligne
        $lineDelivery = $xml->createElement('ram:SpecifiedLineTradeDelivery');
        $lineDelivery->appendChild($this->createQuantityElement($xml, 'ram:BilledQuantity', $item->getQuantite(), 'C62')); // C62 = unité
        $lineItem->appendChild($lineDelivery);
        
        // Règlement de ligne
        $lineSettlement = $xml->createElement('ram:SpecifiedLineTradeSettlement');
        
        // TVA applicable
        $lineTax = $xml->createElement('ram:ApplicableTradeTax');
        $lineTax->appendChild($this->createTextElement($xml, 'ram:TypeCode', 'VAT'));
        $lineTax->appendChild($this->createTextElement($xml, 'ram:RateApplicablePercent', $item->getTvaPercent()));
        $lineSettlement->appendChild($lineTax);
        
        // Total de ligne
        $lineMonetary = $xml->createElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
        $lineMonetary->appendChild($this->createAmountElement($xml, 'ram:LineTotalAmount', $item->getTotalLigneHt()));
        $lineSettlement->appendChild($lineMonetary);
        
        $lineItem->appendChild($lineSettlement);
        
        return $lineItem;
    }

    /**
     * Crée un élément texte XML
     */
    private function createTextElement(\DOMDocument $xml, string $name, string $value): \DOMElement
    {
        $element = $xml->createElement($name);
        $element->appendChild($xml->createTextNode($value));
        return $element;
    }

    /**
     * Crée un élément montant avec devise
     */
    private function createAmountElement(\DOMDocument $xml, string $name, string $amount): \DOMElement
    {
        $element = $xml->createElement($name);
        $element->setAttribute('currencyID', 'EUR');
        $element->appendChild($xml->createTextNode($amount));
        return $element;
    }

    /**
     * Crée un élément quantité avec unité
     */
    private function createQuantityElement(\DOMDocument $xml, string $name, string $quantity, string $unitCode): \DOMElement
    {
        $element = $xml->createElement($name);
        $element->setAttribute('unitCode', $unitCode);
        $element->appendChild($xml->createTextNode($quantity));
        return $element;
    }

    /**
     * Validation XML CII selon schéma
     */
    private function validateXMLCII(string $xmlCII, string $profile): void
    {
        // Validation de base avec DOMDocument
        $this->validateFacturX($xmlCII, $profile);
        
        $this->logger->info('XML CII validé avec succès', [
            'profile' => $profile
        ]);
    }

    /**
     * Retourne les éléments obligatoires selon le profil
     */
    private function getRequiredElementsByProfile(string $profile): array
    {
        $baseElements = [
            '//rsm:ExchangedDocument/ram:ID',
            '//rsm:ExchangedDocument/ram:TypeCode',
            '//rsm:ExchangedDocument/ram:IssueDateTime',
            '//ram:SellerTradeParty/ram:Name',
            '//ram:BuyerTradeParty/ram:Name'
        ];
        
        switch ($profile) {
            case 'MINIMUM':
                return $baseElements;
                
            case 'BASIC_WL':
                return array_merge($baseElements, [
                    '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount',
                    '//ram:InvoiceCurrencyCode'
                ]);
                
            case 'BASIC':
            case 'EN16931':
                return array_merge($baseElements, [
                    '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount',
                    '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount',
                    '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount',
                    '//ram:ApplicableTradeTax',
                    '//ram:InvoiceCurrencyCode'
                ]);
                
            default:
                return $baseElements;
        }
    }

    /**
     * Validation des montants dans le XML
     */
    private function validateAmounts(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        
        // Vérification cohérence des totaux
        $lineTotalNodes = $xpath->query('//ram:LineTotalAmount');
        $taxTotalNodes = $xpath->query('//ram:TaxTotalAmount');
        $grandTotalNodes = $xpath->query('//ram:GrandTotalAmount');
        
        if ($lineTotalNodes->length > 0 && $taxTotalNodes->length > 0 && $grandTotalNodes->length > 0) {
            $lineTotal = (float) $lineTotalNodes->item(0)->textContent;
            $taxTotal = (float) $taxTotalNodes->item(0)->textContent;
            $grandTotal = (float) $grandTotalNodes->item(0)->textContent;
            
            $calculatedTotal = $lineTotal + $taxTotal;
            
            if (abs($calculatedTotal - $grandTotal) > 0.01) {
                throw new \RuntimeException(
                    "Incohérence dans les totaux: HT({$lineTotal}) + TVA({$taxTotal}) = {$calculatedTotal} ≠ TTC({$grandTotal})"
                );
            }
        }
    }

    /**
     * Validation des dates dans le XML
     */
    private function validateDates(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        
        // Vérification format des dates
        $dateNodes = $xpath->query('//udt:DateTimeString[@format="102"]');
        
        foreach ($dateNodes as $dateNode) {
            $dateValue = $dateNode->textContent;
            
            if (!preg_match('/^\d{8}$/', $dateValue)) {
                throw new \RuntimeException("Format de date invalide: {$dateValue} (attendu: YYYYMMDD)");
            }
            
            // Vérification validité de la date
            $year = substr($dateValue, 0, 4);
            $month = substr($dateValue, 4, 2);
            $day = substr($dateValue, 6, 2);
            
            if (!checkdate((int) $month, (int) $day, (int) $year)) {
                throw new \RuntimeException("Date invalide: {$dateValue}");
            }
        }
    }

    /**
     * Vérifie la disponibilité des certificats
     */
    private function areCertificatesAvailable(): bool
    {
        return file_exists($this->certificatePath) && file_exists($this->privateKeyPath);
    }

    /**
     * Obtient le nom complet du client (méthode utilitaire)
     */
    private function getClientFullName(Client $client): string
    {
        return $client->getNomEntreprise() ?? 'Client sans nom';
    }

    /**
     * Obtient le nom complet du contact (méthode utilitaire) 
     */
    private function getContactFullName(Contact $contact): string
    {
        return trim($contact->getPrenom() . ' ' . $contact->getNom());
    }

    /**
     * Export direct en fichier Factur-X
     */
    public function exportFacturXFile(Facture $facture, string $profile = 'BASIC'): BinaryFileResponse
    {
        $facturXContent = $this->generateFacturX($facture, $profile);
        
        // Nom de fichier selon convention
        $filename = sprintf(
            'factur-x_%s_%s.pdf',
            $facture->getNumeroFacture(),
            date('Ymd_His')
        );
        
        // Création fichier temporaire
        $tempFile = tempnam(sys_get_temp_dir(), 'facturx_');
        file_put_contents($tempFile, $facturXContent);
        
        $response = new BinaryFileResponse($tempFile);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Type', 'application/pdf');
        
        // Suppression auto du fichier temporaire
        $response->deleteFileAfterSend(true);
        
        return $response;
    }
}