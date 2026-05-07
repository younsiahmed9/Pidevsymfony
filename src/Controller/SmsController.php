<?php

namespace App\Controller;

use App\Service\Sms\BrevoSmsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SmsController extends AbstractController
{
    public function __construct(
        private BrevoSmsService $smsService
    ) {
    }

    #[Route('/api/sms/send', name: 'api_sms_send', methods: ['POST'])]
    public function sendSms(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['phone']) || !isset($data['message'])) {
            return new JsonResponse(['error' => 'Phone number and message are required'], 400);
        }

        $success = $this->smsService->sendSms($data['phone'], $data['message']);
        
        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'SMS sent successfully']);
        }
        
        return new JsonResponse(['error' => 'Failed to send SMS'], 500);
    }

    #[Route('/api/sms/test', name: 'api_sms_test', methods: ['GET'])]
    public function testSms(): JsonResponse
    {
        // Pour tester, vous pouvez utiliser votre propre numéro de téléphone
        $testPhone = '+33612345678'; // Remplacez par votre numéro réel
        $testMessage = 'Test SMS depuis FinTrack - Service SMS fonctionnel!';
        
        $success = $this->smsService->sendSms($testPhone, $testMessage);
        
        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Test SMS sent successfully']);
        }
        
        return new JsonResponse(['error' => 'Failed to send test SMS'], 500);
    }

    #[Route('/api/sms/verification', name: 'api_sms_verification', methods: ['POST'])]
    public function sendVerificationCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['phone'])) {
            return new JsonResponse(['error' => 'Phone number is required'], 400);
        }

        $code = sprintf('%06d', random_int(100000, 999999));
        $success = $this->smsService->sendVerificationCode($data['phone'], $code);
        
        if ($success) {
            // En production, vous devriez stocker ce code en session ou en base de données
            return new JsonResponse([
                'success' => true, 
                'message' => 'Verification code sent',
                'code' => $code // En développement uniquement, retirez en production!
            ]);
        }
        
        return new JsonResponse(['error' => 'Failed to send verification code'], 500);
    }
}
