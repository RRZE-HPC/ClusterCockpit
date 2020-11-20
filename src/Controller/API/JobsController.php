<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2018 Jan Eitzinger
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

namespace App\Controller\API;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\View;
use App\Entity\Job;
use App\Entity\Cluster;
use App\Entity\User;
use App\Entity\Node;
use App\Entity\Project;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;

class JobsController extends AbstractFOSRestController
{
    public function postJobsAction(Request $request)
    {
        $jobId = $request->request->get('job_id');
        $userId = $request->request->get('user_id');
        $clusterId = $request->request->get('cluster_id');
        $nodes = $request->request->get('nodes');
        $startTime = $request->request->get('start_time');
        $jobScript = $request->request->get('job_script');

        $job_rep = $this->getDoctrine()->getRepository(Job::class);
        $user_rep = $this->getDoctrine()->getRepository(User::class);
        $node_rep = $this->getDoctrine()->getRepository(Node::class);
        $cluster_rep = $this->getDoctrine()->getRepository(Cluster::class);

        $job = $job_rep->findOneByJobId($jobId);
        if ($job) {
            throw new HttpException(400, "Job already exists ".$jobId);
        }

        $job =  new Job;
        $job->setJobId($jobId);
        $job->setStartTime($startTime);

        $user = $user_rep->findOneByUsername($userId);
        if (empty($user)) {
            throw new HttpException(400, "No such user ID: ".$userId);
        }
        $job->setUser($user);

        $cluster = $cluster_rep->findOneByName($clusterId);
        if (empty($cluster)) {
            throw new HttpException(400, "No such cluster ".$clusterId);
        }
        $job->setCluster($cluster);

        foreach ( $nodes as $nodeId  ){
            $node = $node_rep->findOneByNodeId($nodeId);

            if (empty($node)) {
                throw new HttpException(400, "No such node ".$nodeId);
            }
            $job->addNode($node);
        }

        if (! empty($jobScript) ) {
            $job->setJobScript($jobScript);
        }

        $job->setNumNodes(count($nodes));

        $job->isRunning = true;
        $job->isCached = false;
        $job->duration = 0;
        $job->severity = 0;
        $job->memBwAvg = 0;
        $job->memUsedAvg = 0;
        $job->flopsAnyAvg = 0;
        $job->trafficTotalLustreAvg = 0;
        $job->trafficTotalIbAvg = 0;

        $em = $this->getDoctrine()->getManager();
        $em->persist($job);
        $em->flush();

        $view = new View();
        $view->setStatusCode(200);
        $view->setData($job->getId());
        return $this->handleView($view);
    } // "post_jobs"           [POST] api/jobs

    /**
     * @QueryParam(name="stop_time", requirements="\d+")
     */
    public function patchJobsAction(Job $id, ParamFetcher $paramFetcher)
    {
        $stop_time = $paramFetcher->get('stop_time');

        if (empty($id)) {
            throw new HttpException(400, "No such running job: $id");
        }

        $id->duration = $stop_time - $id->startTime;
        $id->isRunning = false;

        $em = $this->getDoctrine()->getManager();
        $em->persist($id);
        $em->flush();

        $view = new View();
        $view->setStatusCode(200);
        $view->setData("SUCCESS");
        return $this->handleView($view);
    } // "patch_jobs"           [PATCH] api/jobs/$id?stop_time=xxx
}
