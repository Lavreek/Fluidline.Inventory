<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Entity\InventoryAttachmenthouse;
use App\Form\InventoryUpdateType;
use App\Repository\InventoryAttachmenthouseRepository;
use App\Repository\InventoryRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryGeneratorController extends AbstractController
{
    #[Route('/generate/image', name: 'app_inventory_generator')]
    public function generateImageCSV(Request $request, ManagerRegistry $registry): Response
    {
        $remoteAddress = $request->server->get('REMOTE_ADDR');

        //ToDo: Make auth.
        if (!in_array($remoteAddress, ['77.50.146.14', '185.180.124.14', '127.0.0.1'])) {
            return new Response();
        }

        $cronLogPath = $this->getParameter('inventory_memory_usage');
        $memoryUsage = memory_get_usage();

        $generateImagePath = $this->getParameter('inventory_generator_images_directory');

        $this->checkDirectory($generateImagePath);

        $generatedFiles = array_diff(scandir($generateImagePath), ['.', '..']);

        $form = $this->createForm(InventoryUpdateType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() and $form->isValid()) {

            /** @var InventoryAttachmenthouseRepository $attachmentsRepository */
            $attachmentsRepository = $registry->getRepository(InventoryAttachmenthouse::class);

            $attachmentsSize = $attachmentsRepository->getAttachmentsSize();
            $size = array_shift($attachmentsSize);

            /** @var InventoryRepository $inventoryRepository */
            $inventoryRepository = $registry->getRepository(Inventory::class);

            $limit = 50000;
            $offset = 0;
            $chunkHeader = "code;attach_id;image\n";
            $iterations = (int)ceil($size / $limit);

            for ($i = 0; $i < $iterations; $i++) {
                $items = $chunkHeader;

                $attachments = $attachmentsRepository->loadAttachments(offset: $offset, limit: $limit);
                $filepath = $generateImagePath . "part-". $i + 1 ."-images.csv";

                while ($attachments) {
                    $attach = array_shift($attachments);
                    $inventory = $inventoryRepository->findOneBy(['id' => $attach->getCode()]);

                    $items .= $inventory->getCode() .";". $attach->getId() .";". $attach->getImage() ."\n";

                    $writed = false;

                    if (strlen($items) > (1024 * 1024)) {
                        while (!$writed) {
                            $writed = file_put_contents($filepath, $items);
                        }

                        $items = "";
                    }

                    while (!$writed) {
                        $writed = file_put_contents($filepath, $items);
                    }
                }

                $offset++;
            }

            file_put_contents(
                $cronLogPath,
                "\n ". date('d-m-Y H:i:s') .
                "\n Generate Images:".
                "\n\tStart with : $memoryUsage. Rise in: ". memory_get_usage() - $memoryUsage .
                ". Memory peak: ". memory_get_peak_usage() .".\n",
                FILE_APPEND
            );
        }

        return $this->render('inventory/generator/index.html.twig', [
            'generate_form' => $form->createView(),
            'files' => $generatedFiles,
        ]);
    }

    private function checkDirectory($path)
    {
        if (!is_dir($path)) {
            mkdir($path, recursive: true);
        }
    }
}
