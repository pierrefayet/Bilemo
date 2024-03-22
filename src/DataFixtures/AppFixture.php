<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Phone;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixture extends Fixture
{
    private Generator $faker;
    private UserPasswordHasherInterface $passwordHashed;

    public function __construct(UserPasswordHasherInterface $passwordHashed)
    {
        $this->faker = Factory::create('fr_FR');
        $this->passwordHashed = $passwordHashed;
    }

    public function load(ObjectManager $manager): void
    {
        $users = [];
        for ($i = 0; $i < 5; ++$i) {
            $user = new User();
            $user->setEmail($this->faker->unique()->email);
            $password = $this->passwordHashed->hashPassword($user, 'password');
            $user->setPassword($password);
            $user->setLastName($this->faker->unique()->lastName);
            $user->setFirstName($this->faker->unique()->firstName);
            $user->setRoles(['ROLE_USER']);
            $manager->persist($user);
            $users[] = $user;
        }

        $admin = new User();
        $admin->setEmail('admin@bilemo.com');
        $password = $this->passwordHashed->hashPassword($admin, 'password');
        $admin->setPassword($password);
        $admin->setLastName($this->faker->unique()->lastName);
        $admin->setFirstName($this->faker->unique()->firstName);
        $admin->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin);

        $customers = [];
        for ($i = 0; $i < 5; ++$i) {
            $customer = new Customer();
            $customer->setEmail($this->faker->unique()->email);
            $customer->setLastName($this->faker->unique()->lastName);
            $customer->setFirstName($this->faker->unique()->firstName);
            $customer->setCreatedAt(new \DateTimeImmutable());
            shuffle($users);
            for ($j = 0; $j < 5; ++$j) {
                $user = $users[$j];
                $user->addCustomer($customer);
                $customer->addUser($user);
                $manager->persist($user);
            }
            $manager->persist($customer);
            $customers[] = $customer;
        }

        for ($i = 0; $i < 5; ++$i) {
            $phone = new Phone();
            $phone->setBatteryLife($this->faker->numberBetween(10, 100).' heures');
            $phone->setCameraDetails($this->faker->numberBetween(12, 108).'MP');
            $phone->setManufacturer($this->faker->randomElement(['Samsung', 'Apple', 'Huawei', 'Xiaomi', 'OnePlus']).'');
            $phone->setModel('Model '.$this->faker->lexify('????'));
            $phone->setRam($this->faker->randomElement([4, 8, 16]).' GB');
            $phone->setPrice((string) $this->faker->numberBetween(100, 1000));
            $phone->setScreenSize($this->faker->randomFloat(2, 5.0, 6.5).' pouces');
            $phone->setProcessor($this->faker->randomElement(['Snapdragon 888', 'A14 Bionic', 'Kirin 9000', 'Exynos 2100']).'');
            $phone->setStockQuantity((string) $this->faker->numberBetween(0, 100));
            $phone->setStorageCapacity($this->faker->numberBetween(0, 900).'GB');
            $phone->setReleaseDate(new \DateTimeImmutable());
            $manager->persist($phone);
        }

        $manager->flush();
    }
}
