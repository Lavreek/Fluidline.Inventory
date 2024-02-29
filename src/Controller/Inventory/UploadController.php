<?php

namespace App\Controller\Inventory;

use App\Entity\Inventory\Inventory;
use App\Form\Upload\PriceType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    #[Route('/admin/upload/price', name: 'admin_upload_price')]
    public function uploadPrice(Request $request, ManagerRegistry $em): Response
    {
        $form = $this->createForm(PriceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            $task = $form->getData();
            /** @var \SplFileObject $file */
            $file = $task['file']->openFile();

            $rowPosition = 0;

            $inventoryRepo = $em->getRepository(Inventory::class);
            $manager = $em->getManager();
            $delimiter = $this->getFileDelimiter($file);

            while ($row = $file->fgetcsv($delimiter)) {
                if (count($row) == 4 && is_numeric($row[2]) && !empty($row[3])) {
                    if ($rowPosition > 0) {
                        $inventory = $inventoryRepo->findOneBy(['code' => $row[0]]);
                        if (!is_null($inventory)) {
                            $pricehouse = $inventory->getPrice();

                            $pricehouse->setValue($row[1]);
                            $pricehouse->setWarehouse((int) $row[2]);
                            $pricehouse->setCurrency($row[3]);

                            $manager->persist($pricehouse);
                        }
                    }
                }

                $rowPosition++;
            }

            $manager->flush();

            return $this->redirectToRoute('auth_account');
        }

        return $this->render('inventory/upload/price.html.twig', [
            'upload_form' => $form
        ]);
    }

    private function getFileDelimiter(\SplFileObject $file) : bool|string
    {
        $line = $file->fgets();
        preg_match_all('#([,|;])#', $line, $matches);
        $file->rewind();

        if (isset($matches[1][0])) {
            return $matches[1][0];
        }

        return false;
    }
}
