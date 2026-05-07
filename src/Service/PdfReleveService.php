<?php

namespace App\Service;

use App\Entity\Compte;
use App\Entity\Releve;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use TCPDF;

class PdfReleveService
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Génère un PDF de relevé de compte avec QR code
     * @return string Chemin du fichier généré
     */
    public function genererReleve(Compte $compte, string $urlCompte): string
    {
        // Créer le dossier de stockage s'il n'existe pas
        $releveDir = $this->projectDir . '/var/releves';
        if (!is_dir($releveDir)) {
            mkdir($releveDir, 0755, true);
        }

        // Générer le QR code
        $qrCodePath = $this->genererQrCode($compte, $urlCompte);

        // Créer le PDF
        $pdf = new TCPDF();
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 10);
        $pdf->AddPage();

        // Définir la police et les couleurs
        $pdf->SetFont('helvetica', '', 11);

        // ========== HEADER ==========
        $this->addHeader($pdf, $compte);

        // ========== INFORMATIONS COMPTE ==========
        $this->addCompteInfo($pdf, $compte);

        // ========== CRÉDITS LIÉS ==========
        if ($compte->getTypeCompte() === 'courant' && $compte->getCredits()->count() > 0) {
            $this->addCreditsSection($pdf, $compte);
        }

        // ========== QR CODE ==========
        $this->addQrCode($pdf, $qrCodePath);

        // ========== FOOTER ==========
        $this->addFooter($pdf);

        // Sauvegarder le PDF
        $filename = 'releve_' . $compte->getId() . '_' . date('Y_m_d_H_i_s') . '.pdf';
        $filepath = $releveDir . '/' . $filename;
        $pdf->Output($filepath, 'F');

        // Nettoyer le fichier QR temporaire
        if (file_exists($qrCodePath)) {
            unlink($qrCodePath);
        }

        return $filepath;
    }

    /**
     * Génère un QR code avec les données du compte
     */
    private function genererQrCode(Compte $compte, string $urlCompte): string
    {
        $tempDir = $this->projectDir . '/var/qr_temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Créer les données du QR code (JSON + URL)
        $qrData = json_encode([
            'type' => 'compte',
            'numero' => $compte->getNumeroCompte(),
            'solde' => $compte->getSolde(),
            'typeCompte' => $compte->getTypeCompte(),
            'etat' => $compte->getEtat(),
            'dateGeneration' => (new \DateTime())->format('Y-m-d H:i:s'),
            'url' => $urlCompte
        ], JSON_UNESCAPED_UNICODE);

        // Générer le QR code (API v5 : paramètres via constructeur)
        $qrCode = new QrCode(
            data: $qrData,
            encoding: new Encoding('UTF-8'),
            size: 300
        );

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        $qrPath = $tempDir . '/qr_' . uniqid() . '.png';
        file_put_contents($qrPath, $result->getString());

        return $qrPath;
    }

    /**
     * Ajoute le header du PDF
     */
    private function addHeader(TCPDF $pdf, Compte $compte): void
    {
        // Titre
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(24, 45, 136);
        $pdf->Cell(0, 10, 'RELEVÉ DE COMPTE', 0, 1, 'C');

        // Ligne
        $pdf->SetDrawColor(24, 45, 136);
        $pdf->Line(10, $pdf->GetY() + 2, 200, $pdf->GetY() + 2);

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // Date de génération
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'Généré le ' . (new \DateTime())->format('d/m/Y à H:i'), 0, 1);
        $pdf->Ln(3);
    }

    /**
     * Ajoute les informations du compte
     */
    private function addCompteInfo(TCPDF $pdf, Compte $compte): void
    {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(24, 45, 136);
        $pdf->Cell(0, 8, 'INFORMATIONS DU COMPTE', 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);

        // Tableau d'informations
        $pdf->SetFillColor(242, 242, 242);
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(200, 200, 200);

        // En-têtes
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(24, 45, 136);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(50, 7, 'Champ', 1, 0, 'L', true);
        $pdf->Cell(0, 7, 'Valeur', 1, 1, 'L', true);

        // Données
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);

        $data = [
            'Numéro de compte' => $compte->getNumeroCompte(),
            'Type de compte' => ucfirst($compte->getTypeCompte()),
            'Solde' => number_format((float)$compte->getSolde(), 2, ',', ' ') . ' DT',
            'État' => ucfirst($compte->getEtat()),
            'Ouvert le' => $compte->getDateCreation()->format('d/m/Y'),
        ];

        if ($compte->getTypeCompte() === 'epargne' && $compte->getTauxInteret()) {
            $data['Taux d\'intérêt'] = $compte->getTauxInteret() . '%';
        }

        if ($compte->getTypeCompte() === 'courant' && $compte->getPlafondDecouvert()) {
            $data['Plafond découvert'] = number_format((float)$compte->getPlafondDecouvert(), 2, ',', ' ') . ' DT';
        }

        foreach ($data as $label => $value) {
            $pdf->Cell(50, 6, $label, 1, 0, 'L', false);
            $pdf->MultiCell(0, 6, $value, 1, 'L');
        }

        $pdf->Ln(3);
    }

    /**
     * Ajoute la section des crédits liés
     */
    private function addCreditsSection(TCPDF $pdf, Compte $compte): void
    {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(247, 143, 52);
        $pdf->Cell(0, 8, 'CRÉDITS ASSOCIÉS', 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFillColor(24, 45, 136);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);

        // En-têtes du tableau
        $pdf->Cell(30, 6, 'ID', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Montant', 1, 0, 'R', true);
        $pdf->Cell(20, 6, 'Durée', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Taux', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Statut', 1, 0, 'C', true);
        $pdf->Cell(0, 6, 'Mensualité', 1, 1, 'R', true);

        // Données des crédits
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);

        foreach ($compte->getCredits() as $credit) {
            $statusColor = match($credit->getStatus()) {
                'approuve' => [45, 185, 94],
                'refuse' => [220, 53, 69],
                'en_attente' => [255, 193, 7],
                default => [108, 117, 125]
            };

            $pdf->Cell(30, 6, '#' . $credit->getId(), 1, 0, 'C');
            $pdf->Cell(30, 6, number_format((float)$credit->getMontant(), 2, ',', ' ') . ' DT', 1, 0, 'R');
            $pdf->Cell(20, 6, $credit->getDureeMois() . 'm', 1, 0, 'C');
            $pdf->Cell(20, 6, $credit->getTauxInteret() . '%', 1, 0, 'C');
            
            $pdf->SetFillColor(...$statusColor);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(30, 6, ucfirst(str_replace('_', ' ', $credit->getStatus())), 1, 0, 'C', true);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0, 0, 0);
            
            $pdf->Cell(0, 6, number_format((float)$credit->getMensualite(), 2, ',', ' ') . ' DT', 1, 1, 'R');
        }

        $pdf->Ln(3);
    }

    /**
     * Ajoute le QR code au PDF
     */
    private function addQrCode(TCPDF $pdf, string $qrCodePath): void
    {
        if (!file_exists($qrCodePath)) {
            return;
        }

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(24, 45, 136);
        $pdf->Cell(0, 8, 'CODE DE VÉRIFICATION', 0, 1, 'C');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'Scannez le QR code pour afficher les informations du compte', 0, 1, 'C');
        $pdf->Ln(2);

        // Ajouter l'image du QR code
        $x = (210 - 50) / 2; // Centrer le QR code (largeur A4 = 210mm)
        $pdf->Image($qrCodePath, $x, $pdf->GetY(), 50, 50, 'PNG');
    }

    /**
     * Ajoute le footer du PDF
     */
    private function addFooter(TCPDF $pdf): void
    {
        $pdf->SetY(-15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(128, 128, 128);

        $pdf->Cell(0, 10, 'FinTrack - Relevé de compte | Page ' . $pdf->PageNo(), 0, 0, 'C');
    }
}