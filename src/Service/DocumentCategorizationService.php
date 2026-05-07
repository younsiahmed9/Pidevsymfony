<?php

namespace App\Service;

use App\Entity\Categorie;
use App\Entity\Dossier;
use App\Entity\User;
use App\Repository\CategorieRepository;
use App\Repository\DossierRepository;
use Psr\Log\LoggerInterface;

/**
 * Service pour catégoriser automatiquement les documents et extraire des méta-données (IA OCR)
 */
class DocumentCategorizationService
{
    private array $keywordMapping = [
        'Identite' => ['carte', 'identite', 'cin', 'passeport', 'permis', 'conduire', 'photo', 'signature', 'nationalite', 'naissance', 'identite', 'passport', 'id card'],
        'Banque' => ['facture', 'paiement', 'reçu', 'virement', 'banque', 'releve', 'debit', 'credit', 'montant', 'tva', 'euro', 'dt', 'salaire', 'bulletin', 'rib', 'bank'],
        'Finance' => ['facture', 'paiement', 'reçu', 'virement', 'banque', 'releve', 'debit', 'credit', 'montant', 'tva', 'euro', 'dt', 'salaire', 'bulletin', 'finance'],
        'Medical' => ['ordonnance', 'medical', 'docteur', 'hopital', 'clinique', 'analyse', 'pharmacien', 'soins', 'vaccin', 'sante', 'cnam', 'assurance', 'health', 'doctor'],
        'Sante' => ['ordonnance', 'medical', 'docteur', 'hopital', 'clinique', 'analyse', 'pharmacien', 'soins', 'vaccin', 'sante', 'cnam', 'assurance'],
        'Assurance' => ['contrat', 'assurance', 'sinistre', 'police', 'attestation', 'tierce', 'bonus', 'malus', 'responsabilite', 'assurance', 'insurance'],
        'Fiscal' => ['impot', 'taxe', 'declaration', 'revenu', 'fisc', 'fiscal', 'tva', 'contribution', 'avis d\'imposition', 'tax'],
        'Immobilier' => ['bail', 'location', 'vente', 'notaire', 'cadastre', 'terrain', 'maison', 'appartement', 'loyer', 'copropriete', 'immobilier', 'estate', 'rent'],
        'Education' => ['diplome', 'certificat', 'attestation', 'scolaire', 'universite', 'ecole', 'notes', 'examen', 'releve', 'cours', 'etudiant', 'education', 'school'],
        'Juridique' => ['contrat', 'accord', 'bail', 'huissier', 'tribunal', 'jugement', 'avocat', 'notaire', 'article', 'clause', 'loi', 'reglement', 'law', 'legal'],
        'Transport' => ['billet', 'train', 'avion', 'vol', 'reservation', 'ticket', 'peage', 'carburant', 'essence', 'carte grise', 'assurance auto', 'travel'],
    ];

    public function __construct(
        private CategorieRepository $categorieRepository,
        private DossierRepository $dossierRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Extrait des méta-données (titre, description, tags, dates) à partir d'un texte brut OCR
     */
    public function extractMetadata(string $text): array
    {
        if (empty(trim($text))) {
            return ['title' => '', 'description' => '', 'tags' => '', 'date_document' => '', 'date_echeance' => ''];
        }

        // Nettoyage pour l'extraction
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
        
        // 1. Essayer de trouver un titre (première ligne parlante)
        $title = '';
        foreach ($lines as $line) {
            if (strlen($line) > 5 && !preg_match('/^\d+$/', $line)) {
                $title = $this->cleanMetadataValue(mb_convert_case($line, MB_CASE_TITLE, "UTF-8"), 120);
                break;
            }
        }

        // 2. Créer une description (2-3 premières lignes)
        $description = $this->cleanMetadataValue(implode(" ", array_slice($lines, 0, 3)), 200);

        // 3. Extraire des tags basés sur les mots-clés
        $foundTags = [];
        $simpleText = $this->simplifyText($text);
        foreach ($this->keywordMapping as $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($simpleText, $this->simplifyText($kw)) && !in_array($kw, $foundTags)) {
                    $foundTags[] = ucfirst($kw);
                    if (count($foundTags) >= 5) break 2;
                }
            }
        }

        // 4. Extraction des dates
        $dates = $this->extractDates($text);
        $dateDocument = !empty($dates) ? $dates[0] : '';
        $dateEcheance = count($dates) > 1 ? $dates[1] : '';

        // Si on a une date de document mais pas d'échéance, et que c'est une facture
        if ($dateDocument && !$dateEcheance && (stripos($text, 'facture') !== false || stripos($text, 'invoice') !== false)) {
            // Suggérer date + 30 jours pour une facture
            $d = new \DateTime($dateDocument);
            $d->modify('+30 days');
            $dateEcheance = $d->format('Y-m-d');
        }

        return [
            'title' => $title,
            'description' => $description,
            'tags' => implode(", ", $foundTags),
            'date_document' => $dateDocument,
            'date_echeance' => $dateEcheance
        ];
    }

    private function cleanMetadataValue(string $value, int $maxLength): string
    {
        $cleaned = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        if (mb_strlen($cleaned) <= $maxLength) {
            return $cleaned;
        }

        return rtrim(mb_substr($cleaned, 0, max(0, $maxLength - 3))) . '...';
    }

    /**
     * Extrait les dates d'un texte via Regex
     * Retourne un tableau de dates au format YYYY-MM-DD
     */
    private function extractDates(string $text): array
    {
        $foundDates = [];
        
        // Patterns de dates courants (JJ/MM/AAAA, JJ-MM-AAAA, AAAA/MM/JJ, etc.)
        $patterns = [
            '/\b(\d{1,2})[\/\-. ](\d{1,2})[\/\-. ](\d{4})\b/', // 18/04/2026
            '/\b(\d{4})[\/\-. ](\d{1,2})[\/\-. ](\d{1,2})\b/', // 2026/04/18
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    try {
                        $d = null;
                        if (strlen($match[3]) === 4) { // Format JJ/MM/AAAA
                            $d = \DateTime::createFromFormat('d-m-Y', "{$match[1]}-{$match[2]}-{$match[3]}");
                        } else { // Format AAAA/MM/JJ
                            $d = \DateTime::createFromFormat('Y-m-d', "{$match[1]}-{$match[2]}-{$match[3]}");
                        }
                        
                        if ($d && $d->format('Y') > 1900 && $d->format('Y') < 2100) {
                            $isoDate = $d->format('Y-m-d');
                            if (!in_array($isoDate, $foundDates)) {
                                $foundDates[] = $isoDate;
                            }
                        }
                    } catch (\Exception $e) {}
                }
            }
        }

        // Trier les dates par ordre chronologique
        sort($foundDates);
        
        return $foundDates;
    }

    /**
     * Suggère la meilleure catégorie pour un document
     */
    public function suggestCategory(string $text, string $title = ''): ?Categorie
    {
        if (empty(trim($text)) && empty(trim($title))) {
            return null;
        }

        $text = $this->simplifyText($text);
        $title = $this->simplifyText($title);

        $scores = [];
        $categories = $this->categorieRepository->findAll();
        
        foreach ($categories as $categorie) {
            $nomOriginal = $categorie->getNomCategorie();
            $nomSimplement = $this->simplifyText($nomOriginal);
            $score = 0;

            if (!empty($title) && (str_contains($title, $nomSimplement) || stripos($nomSimplement, $title) !== false) && strlen($nomSimplement) >= 3) {
                $score += 40;
            }

            if (!empty($title) && strlen($title) > 3 && strlen($nomSimplement) > 3) {
                $lev = levenshtein($title, $nomSimplement);
                if ($lev <= 2) {
                    $score += 35;
                }
            }

            if (str_contains($text, $nomSimplement) && strlen($nomSimplement) >= 3) {
                $score += 15;
            }

            $keywords = $this->getKeywordsForCategory($nomOriginal);
            foreach ($keywords as $keyword) {
                $keywordSimple = $this->simplifyText($keyword);
                if (!empty($title) && (str_contains($title, $keywordSimple) || str_contains($keywordSimple, $title))) {
                    $score += 15;
                }
                if (str_contains($text, $keywordSimple)) {
                    $score += 5;
                }
            }

            if ($score > 0) {
                $scores[$categorie->getId()] = ['score' => $score, 'categorie' => $categorie];
            }
        }

        if (empty($scores)) {
            foreach ($this->keywordMapping as $catKey => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($title, $kw) || str_contains($text, $kw)) {
                        foreach ($categories as $c) {
                            if (stripos($c->getNomCategorie(), $catKey) !== false || stripos($catKey, $c->getNomCategorie()) !== false) {
                                return $c;
                            }
                        }
                    }
                }
            }
            return null;
        }

        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
        $bestMatch = reset($scores);
        return $bestMatch['categorie'];
    }

    /**
     * Suggère le meilleur dossier pour un document
     */
    public function suggestFolder(string $text, string $title = '', ?User $user = null): ?Dossier
    {
        if (empty(trim($text)) && empty(trim($title))) {
            return null;
        }

        $text = $this->simplifyText($text);
        $title = $this->simplifyText($title);
        
        $criteria = $user ? ['utilisateur' => $user] : [];
        $dossiers = $this->dossierRepository->findBy($criteria);
        $scores = [];

        foreach ($dossiers as $dossier) {
            $nom = $this->simplifyText($dossier->getNomDossier());
            $score = 0;

            if (!empty($title) && (str_contains($title, $nom) || str_contains($nom, $title)) && strlen($nom) >= 3) {
                $score += 30;
            }

            if (str_contains($text, $nom) && strlen($nom) >= 3) {
                $score += 10;
            }

            if ($score > 0) {
                $scores[$dossier->getId()] = ['score' => $score, 'dossier' => $dossier];
            }
        }

        if (empty($scores)) return null;
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
        return reset($scores)['dossier'];
    }

    private function simplifyText(string $text): string
    {
        $text = mb_strtolower($text);
        $search = ['à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ'];
        $replace = ['a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y'];
        $text = str_replace($search, $replace, $text);
        return preg_replace('/[^\w\s]/', ' ', $text);
    }

    private function getKeywordsForCategory(string $categoryName): array
    {
        $categoryNameSimple = $this->simplifyText($categoryName);
        foreach ($this->keywordMapping as $name => $keywords) {
            $nameSimple = $this->simplifyText($name);
            if (str_contains($categoryNameSimple, $nameSimple) || str_contains($nameSimple, $categoryNameSimple)) {
                return $keywords;
            }
        }
        return [];
    }
}
