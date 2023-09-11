<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Entity\InventoryAttachmenthouse;
use App\Form\GenerateType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryGeneratorController extends AbstractController
{
    #[Route('/generator/image', name: 'app_inventory_generator')]
    public function generateImageCSV(Request $request, ManagerRegistry $registry): Response
    {
        $cronLogPath = $this->getParameter('inventory_cron_execute');
        $memoryUsage = memory_get_usage();

        $generatorPath = $this->getParameter('inventory_generator_directory');
        $imageFile = "images.csv";
        $image = false;

        if (file_exists($generatorPath . $imageFile)) {
            $image = true;
        }

        $form = $this->createForm(GenerateType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() and $form->isValid()) {
            $attachmentsRepository = $registry->getRepository(InventoryAttachmenthouse::class);
            $inventoryRepository = $registry->getRepository(Inventory::class);

            $imageRows = "code;attach_id;image\n";

            if (!is_dir($generatorPath)) {
                mkdir($generatorPath, recursive: true);
            }

            $attachments = $attachmentsRepository->findAll();

            while ($attachments) {
                $attach = array_shift($attachments);
                $inventory = $inventoryRepository->findOneBy(['id' => $attach->getCode()]);

                $imageRows .= $inventory->getCode() .";". $attach->getId() .";". $attach->getImage() ."\n";

                if (strlen($imageRows) > (1024 * 1024)) {
                    $writed = false;

                    while (!$writed) {
                        $writed = file_put_contents($generatorPath . $imageFile, $imageRows);
                    }

                    $imageRows = "";
                }
            }

            $writed = false;

            while (!$writed) {
                $writed = file_put_contents($generatorPath . $imageFile, $imageRows);
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

        return $this->render('inventory_generator/index.html.twig', [
            'generate_form' => $form->createView(),
            'file' => $image,
            'controller_name' => 'InventoryGeneratorController',
        ]);
    }
}
