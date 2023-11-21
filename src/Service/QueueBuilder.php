<?php
namespace App\Service;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mime\Part\File;

class QueueBuilder extends AbstractController
{

    private string $serial;

    private string $queueDirectory;

    public function setQueueDirectory($queueDirectory): void
    {
        $this->queueDirectory = $queueDirectory;
    }

    public function setSerial($serial): void
    {
        $this->serial = $serial;
    }

    public function getSerial(): string
    {
        return $this->serial;
    }

    public function createQueueChunks($products)
    {
        if (empty($this->serial)) {
            throw new \Exception("Серия не может быть пуста");
        }

        $chunks = array_chunk($products, 100);

        $directory = $this->getQueueDirectory();
        $serial = $this->getSerial();

        $serialDirectory = $directory . "/" . $serial . "/";

        if (! is_dir($serialDirectory)) {
            mkdir($serialDirectory, recursive: true);
        }

        foreach ($chunks as $chunk) {
            $filename = $serialDirectory . uniqid() . ".json";
            file_put_contents($filename, json_encode($chunk));
        }
    }

    public function getQueueDirectory(): string
    {
        return $this->queueDirectory;
    }
}
