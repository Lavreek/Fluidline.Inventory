<?php
namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class AuthRuntime implements RuntimeExtensionInterface
{

    public function __construct()
    {
        // Inject dependencies if needed
    }

    public function flipFormRow($form_row): string
    {
        preg_match('#(.*)(\<label.*<\/label\>)(\<input.*\/>)(.*)#u', $form_row, $match);

        if (isset($match[2], $match[3])) {
            return $match[1] . $match[3] . $match[2] . $match[4];
        }

        return $form_row;
    }
}
