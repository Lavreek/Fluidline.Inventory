<?php
namespace App\Twig\Extension;

use App\Twig\Runtime\UserRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class UserExtension extends AbstractExtension
{

    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('filter_name', [
                UserRuntime::class,
                'doSomething'
            ])
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('isAdmin', [
                UserRuntime::class,
                'isAdmin'
            ]),
            new TwigFunction('isUser', [
                UserRuntime::class,
                'isUser'
            ]),
            new TwigFunction('getUsername', [
                UserRuntime::class,
                'getUsername'
            ])
        ];
    }
}
