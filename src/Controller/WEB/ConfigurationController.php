<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2021 Jan Eitzinger
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace App\Controller\WEB;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\View;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use App\Entity\Configuration;

class ConfigurationController extends AbstractFOSRestController
{
    /**
     * @Get("/configurations/{slug}", name="get_configuration")
     */
    public function getConfigurationAction($slug)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\Configuration::class);
        $config = $repository->findOneByName($slug);

        if (empty($config)) {
            throw new HttpException(400, "No such config key ".$slug);
        }

        $view = $this->view($config);
        return $this->handleView($view);
    }

    /**
     * @Post("/configurations/", name="post_configuration")
     */
    public function postConfigurationAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\Configuration::class);
        $id = $request->request->get('id');
        $name = $request->request->get('name');
        $value = $request->request->get('value');
        $scope = $request->request->get('scope');

        if ( $scope === 'default' ){
            $this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Unable to create default configuration!');
        }

        $config = $repository->find($id);
        if (empty($config)) {
            throw new HttpException(400, "No such configuration key: $id");
        }

        $newConfig =  clone $config;
        $newConfig->setValue($value);
        $newConfig->setScope($this->getUser()->getUsername());

        $em = $this->getDoctrine()->getManager();
        $em->persist($newConfig);
        $em->flush();

        $view = new View();
        $view->setStatusCode(200);
        $view->setData("SUCCESS");
        return $this->handleView($view);
    }

    /**
     * @Patch("/configurations/{id}", name="patch_configuration")
     */
    public function patchConfigurationAction(Configuration $id, Request $request)
    {
        $value = $request->request->get('value');

        if (empty($id)) {
            throw new HttpException(400, "No such configuration key: $id");
        }
        if ( $id->getScope() === 'default' ){
            $this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Not allowed to change default configuration!');
        }

        $id->setValue($value);
        $em = $this->getDoctrine()->getManager();
        $em->persist($id);
        $em->flush();

        $view = new View();
        $view->setStatusCode(200);
        $view->setData("SUCCESS");
        return $this->handleView($view);
    }

    /**
     * @Delete("/configurations/{id}", name="delete_configuration")
     */
    public function deleteConfigurationAction(Configuration $id)
    {
        if (empty($id)) {
            throw new HttpException(400, "No such configuration key: $id");
        }
        if ( $id->getScope() === 'default' ){
            $this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Not allowed to change default configuration!');
        }

        $em = $this->getDoctrine()->getManager();
        $em->remove($id);
        $em->flush();

        $view = new View();
        $view->setStatusCode(200);
        $view->setData("SUCCESS");
        return $this->handleView($view);
    }
}
