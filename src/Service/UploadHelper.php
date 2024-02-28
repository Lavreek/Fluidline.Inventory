<?php

namespace App\Service;

use Doctrine\Persistence\ManagerRegistry;

class UploadHelper
{
    private \SplFileObject $file;
    private ManagerRegistry $em;

    public function __construct(\SplFileObject $file)
    {
        $this->file = $file;
    }

    public function setManager(ManagerRegistry $em) : void
    {
        $this->em = $em;
    }



}