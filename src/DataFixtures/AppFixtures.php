<?php
namespace App\DataFixtures;

use App\Entity\Auth\User;
use App\Kernel;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{

    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->loadAdminUser($manager);

        $kernel = new Kernel("fixtures", false);
        $kernel->boot();
        $loggingPath = $kernel->getContainer()->getParameter('logging');

        $loggingPath .= "fixtures.log";
        file_put_contents($loggingPath, date('H:i:s d-m-Y') . "\n\t" . "Username: " . $admin['username'] . " Password: " . $admin['password'] . "\n");
    }

    private function loadAdminUser(ObjectManager $manager): array
    {
        $admin = new User();

        $username = 'inventory_admin';
        $password = uniqid();

        $admin->setUsername($username);
        $admin->setRoles([
            'ROLE_ADMIN',
            'ROLE_USER'
        ]);

        $hashPassword = $this->hasher->hashPassword($admin, $password);
        $admin->setPassword($hashPassword);

        $manager->persist($admin);
        $manager->flush();

        return [
            'username' => $username,
            'password' => $password
        ];
    }
}
