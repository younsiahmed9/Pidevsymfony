<?php

namespace App\Controller\FrontOffice;

use CloudConvert\CloudConvert;
use CloudConvert\Models\Job;
use CloudConvert\Models\Task;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class CloudConvertController extends AbstractController
{
    #[Route('/cloudconvert/convert', name: 'cloudconvert_convert', methods: ['POST'])]
    public function convert(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Aucun fichier fourni.'], 400);
        }

        $apiKey = $this->getParameter('cloudconvert_api_key');
        if (!$apiKey || $apiKey === 'YOUR_API_KEY' || empty($apiKey)) {
            return $this->json([
                'success' => false,
                'error' => 'L\'API CloudConvert n\'est pas configurée. Veuillez ajouter votre CLOUDCONVERT_API_KEY dans le fichier .env. Vous pouvez en obtenir une gratuitement sur cloudconvert.com.'
            ], 500);
        }

        try {
            $cloudconvert = new CloudConvert(['api_key' => $apiKey]);

            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $extension = $file->getClientOriginalExtension();

            $job = (new Job())
                ->addTask(
                    (new Task('import/upload', 'upload-task'))
                )
                ->addTask(
                    (new Task('convert', 'convert-task'))
                        ->set('input', 'upload-task')
                        ->set('output_format', 'pdf')
                )
                ->addTask(
                    (new Task('export/url', 'export-task'))
                        ->set('input', 'convert-task')
                );

            $cloudconvert->jobs()->create($job);

            $uploadTask = $job->getTasks()->whereName('upload-task')[0];
            $cloudconvert->tasks()->upload($uploadTask, fopen($file->getPathname(), 'r'), $file->getClientOriginalName());

            $cloudconvert->jobs()->wait($job);

            $exportTask = $job->getTasks()->whereName('export-task')[0];
            
            if ($exportTask->getStatus() === 'error') {
                throw new \Exception("Erreur CloudConvert : " . ($exportTask->getMessage() ?? 'Inconnue'));
            }

            $result = $exportTask->getResult();
            if (!$result || !isset($result->files) || empty($result->files)) {
                throw new \Exception("La conversion est terminée mais aucun fichier n'a été généré par CloudConvert.");
            }

            $outputFile = $result->files[0];

            $content = file_get_contents($outputFile->url);
            $newFilename = $safeFilename . '-' . uniqid() . '.pdf';
            
            $targetPath = $this->getParameter('documents_directory') . '/' . $newFilename;
            file_put_contents($targetPath, $content);

            return $this->json([
                'success' => true,
                'filename' => $newFilename,
                'original_name' => $originalFilename . '.pdf',
                'url' => $this->generateUrl('document_index') // Just for feedback
            ]);

        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erreur de conversion : ' . $e->getMessage()], 500);
        }
    }
}
