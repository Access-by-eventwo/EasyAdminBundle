<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Fixtures\AppTestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OverridingEasyAdminController extends AbstractController
{
    /**
     * @return Response
     */
    #[Route('/override_layout', name: 'override_layout')]
    public function overrideLayout()
    {
        return $this->render('override_controller/layout.html.twig');
    }
}
