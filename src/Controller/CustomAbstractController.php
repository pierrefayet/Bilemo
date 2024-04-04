<?php

namespace App\Controller;

use App\Entity\Customer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CustomAbstractController extends AbstractController
{
    protected function getCustomer(): Customer
    {
        $user = parent::getUser();

        if (!$user instanceof Customer) {
            throw new \Exception('is not instance of customer type');
        }

        return $user;
    }
}
